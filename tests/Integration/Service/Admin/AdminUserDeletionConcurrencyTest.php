<?php

declare(strict_types=1);

namespace Tests\Integration\Service\Admin;

use App\Core\Database;
use PDO;
use RuntimeException;
use Tests\BaseTestCase;

final class AdminUserDeletionConcurrencyTest extends BaseTestCase
{
    private PDO $pdo;

    /** @var list<int> */
    private array $createdUserIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = (new Database())->getConnection();

        if ($this->pdo->query('SELECT DATABASE()')->fetchColumn() !== 'coding-blog_test') {
            throw new RuntimeException(
                'Concurrency tests must use the dedicated test database.'
            );
        }
    }

    public function testCreatesTwoActiveAdministrators(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $adminA = $this->createTestAdmin('concurrency_a_' . $suffix);
        $adminB = $this->createTestAdmin('concurrency_b_' . $suffix);

        self::assertNotSame($adminA, $adminB);

        $statement = $this->pdo->prepare(
            'SELECT COUNT(*)
            FROM user u
            INNER JOIN role r ON r.id = u.role_id
            WHERE u.id IN (:admin_a, :admin_b)
            AND r.name = :role
            AND u.status = :status'
        );

        $statement->execute([
            'admin_a' => $adminA,
            'admin_b' => $adminB,
            'role'    => 'ADMIN',
            'status'  => 'active',
        ]);

        self::assertSame(2, (int) $statement->fetchColumn());
    }

    public function testConcurrentDeletionsPreserveOneActiveAdministrator(): void
    {
        self::assertSame(
            0,
            $this->countActiveAdmins(),
            'The concurrency test requires no pre-existing active administrators.'
        );

        $suffix = bin2hex(random_bytes(4));

        $adminA = $this->createTestAdmin('concurrency_delete_a_' . $suffix);
        $adminB = $this->createTestAdmin('concurrency_delete_b_' . $suffix);

        self::assertNotSame($adminA, $adminB);

        self::assertSame(
            2,
            $this->countActiveAdmins(),
            'Exactly two active administrators must exist before concurrent deletion.'
        );

        $workerA = $this->startDeletionWorker($adminA, $adminB);

        try {
            $workerB = $this->startDeletionWorker($adminB, $adminA);
        } catch (\Throwable $exception) {
            fclose($workerA['pipes'][0]);

            try {
                $this->finishDeletionWorker($workerA);
            } catch (\Throwable) {
                // Preserve the original worker startup failure.
            }

            throw $exception;
        }

        $startException = null;

        foreach ([$workerA, $workerB] as $worker) {
            try {
                $bytesWritten = fwrite($worker['pipes'][0], "START\n");

                if ($bytesWritten !== strlen("START\n")) {
                    throw new RuntimeException(
                        'Unable to send the deletion worker start signal.'
                    );
                }
            } catch (\Throwable $exception) {
                $startException ??= $exception;
            } finally {
                fclose($worker['pipes'][0]);
            }
        }

        if ($startException !== null) {
            foreach ([$workerA, $workerB] as $worker) {
                try {
                    $this->finishDeletionWorker($worker);
                } catch (\Throwable) {
                    // Preserve the original synchronization failure.
                }
            }

            throw $startException;
        }

        $resultA = [];
        $workerAException = null;

        try {
            $resultA = $this->finishDeletionWorker($workerA);
        } catch (\Throwable $exception) {
            $workerAException = $exception;
        }

        try {
            $resultB = $this->finishDeletionWorker($workerB);
        } catch (\Throwable $exception) {
            if ($workerAException !== null) {
                throw $workerAException;
            }

            throw $exception;
        }

        if ($workerAException !== null) {
            throw $workerAException;
        }

        $successfulDeletions = 0;

        foreach ([$resultA, $resultB] as $result) {
            if ($result === []) {
                ++$successfulDeletions;
            }
        }

        self::assertSame(
            1,
            $this->countActiveAdmins(),
            'Exactly one active administrator must remain after concurrent deletions.'
        );

        self::assertSame(
            1,
            $successfulDeletions,
            'Exactly one concurrent administrator deletion must succeed.'
        );
    }

    private function createTestAdmin(string $username): int
    {
        if ($this->pdo->query('SELECT DATABASE()')->fetchColumn() !== 'coding-blog_test') {
            throw new RuntimeException(
                'Refusing to create test users outside the test database.'
            );
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO user (role_id, username, slug, email, password, status)
            SELECT id, :username, :slug, :email, :password, :status
            FROM role
            WHERE name = :role'
        );

        $statement->execute([
            'role'     => 'ADMIN',
            'username' => $username,
            'slug'     => $username,
            'email'    => $username . '@example.test',
            'password' => password_hash('TestPassword123!', PASSWORD_DEFAULT),
            'status'   => 'active',
        ]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Unable to create the test administrator.');
        }

        $adminId = (int) $this->pdo->lastInsertId();

        $this->createdUserIds[] = $adminId;

        return $adminId;
    }

    /**
     * Starts an independent PHP worker process.
     *
     * @return array{
     *     process: resource,
     *     pipes: array<int, resource>,
     *     deadline: float
     * }
     */
    private function startDeletionWorker(int $adminId, int $targetId): array
    {
        $workerPath = __DIR__ . '/admin-user-deletion-worker.php';

        $command = [
            PHP_BINARY,
            $workerPath,
            (string) $adminId,
            (string) $targetId,
        ];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            $command,
            $descriptors,
            $pipes,
            dirname(__DIR__, 4)
        );

        if (!is_resource($process)) {
            throw new RuntimeException(
                'Unable to start the deletion worker.'
            );
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        return [
            'process'  => $process,
            'pipes'    => $pipes,
            'deadline' => microtime(true) + 15,
        ];
    }

    /**
     * Waits for a worker to finish and returns its deletion result.
     *
     * @param array{
     *     process: resource,
     *     pipes: array<int, resource>,
     *     deadline: float
     * } $worker
     *
     * @return array<string, mixed>|list<mixed>
     */
    private function finishDeletionWorker(array $worker): array
    {
        $deadline = $worker['deadline'];

        while (true) {
            $status = proc_get_status($worker['process']);

            if (!$status['running']) {
                $exitCode = $status['exitcode'];
                break;
            }

            if (microtime(true) >= $deadline) {
                proc_terminate($worker['process']);

                // Give the terminated process time to exit.
                $terminationDeadline = microtime(true) + 2;

                do {
                    $terminationStatus = proc_get_status($worker['process']);

                    if (!$terminationStatus['running']) {
                        break;
                    }

                    usleep(100_000);
                } while (microtime(true) < $terminationDeadline);

                if ($terminationStatus['running']) {
                    throw new RuntimeException(
                        'Deletion worker is still running after termination request.'
                    );
                }

                fclose($worker['pipes'][1]);
                fclose($worker['pipes'][2]);

                proc_close($worker['process']);

                throw new RuntimeException(
                    'Deletion worker exceeded the 15-second timeout.'
                );
            }

            usleep(100_000);
        }
    
        $stdout = stream_get_contents($worker['pipes'][1]);
        $stderr = stream_get_contents($worker['pipes'][2]);

        fclose($worker['pipes'][1]);
        fclose($worker['pipes'][2]);

        $closeExitCode = proc_close($worker['process']);

        if ($exitCode === -1) {
            $exitCode = $closeExitCode;
        }

        if ($exitCode !== 0) {
            throw new RuntimeException(
                sprintf(
                    'Deletion worker failed with exit code %d: %s',
                    $exitCode,
                    $stderr === false ? '' : trim($stderr)
                )
            );
        }

        if ($stdout === false) {
            throw new RuntimeException(
                'Unable to read deletion worker output.'
            );
        }

        $result = json_decode(
            trim($stdout),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($result)) {
            throw new RuntimeException(
                'Invalid deletion worker result.'
            );
        }

        return $result;
    }

    /**
     * Counts active administrators in the test database.
     */
    private function countActiveAdmins(): int
    {
        $statement = $this->pdo->query(
            "SELECT COUNT(*)
            FROM user u
            INNER JOIN role r ON r.id = u.role_id
            WHERE r.name = 'ADMIN'
            AND u.status = 'active'"
        );

        return (int) $statement->fetchColumn();
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->pdo)) {
                if ($this->pdo->query('SELECT DATABASE()')->fetchColumn() !== 'coding-blog_test') {
                    throw new RuntimeException(
                        'Refusing to clean up outside the test database.'
                    );
                }

                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                $statement = $this->pdo->prepare(
                    'DELETE FROM user WHERE id = :user_id'
                );

                foreach ($this->createdUserIds as $userId) {
                    $statement->execute(['user_id' => $userId]);
                }
            }
        } finally {
            parent::tearDown();
        }
    }
}