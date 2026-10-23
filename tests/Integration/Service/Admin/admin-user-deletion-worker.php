<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\EnvLoader;
use App\Core\SqlHelper;
use App\Model\EmailEventModel;
use App\Model\RegistrationEventModel;
use App\Model\UserModel;
use App\Model\UserTokenModel;
use App\Security\Contract\AuthCheckerInterface;
use App\Service\Admin\AdminUserDeletionService;

require dirname(__DIR__, 4) . '/vendor/autoload.php';

$_ENV['APP_ENV']      = 'test';
$_ENV['DB_HOST']      = 'localhost';
$_ENV['DB_USER']      = 'root';
$_ENV['DB_PASSWORD']  = '';
$_ENV['DB_NAME_TEST'] = 'coding-blog_test';
$_ENV['DB_CHARSET']   = 'utf8mb4';

EnvLoader::load(dirname(__DIR__, 4) . '/');

$pdo = (new Database())->getConnection();

if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'coding-blog_test') {
    throw new RuntimeException(
        'Refusing to run deletion worker outside the test database.'
    );
}

$pdo->exec('SET SESSION innodb_lock_wait_timeout = 10');

$adminId = filter_var(
    $argv[1] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($adminId === false) {
    throw new RuntimeException(
        'A valid administrator ID is required.'
    );
}

$targetId = filter_var(
    $argv[2] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($targetId === false) {
    throw new RuntimeException(
        'A valid target user ID is required.'
    );
}

$authChecker = new class ($adminId) implements AuthCheckerInterface {
    public function __construct(
        private int $adminId
    ) {
    }

    public function isAuthenticated(): bool
    {
        return true;
    }

    public function getRoles(): array
    {
        return ['ADMIN'];
    }

    public function getUserId(): ?int
    {
        return $this->adminId;
    }
};

$sqlHelper = new SqlHelper($pdo);

$deletionService = new AdminUserDeletionService(
    new UserModel($sqlHelper),
    new UserTokenModel($sqlHelper),
    new EmailEventModel($sqlHelper),
    new RegistrationEventModel($sqlHelper),
    $sqlHelper,
    $authChecker
);

$startSignal = fgets(STDIN);

if ($startSignal !== "START\n") {
    throw new RuntimeException(
        'Deletion worker did not receive the expected start signal.'
    );
}

$result = $deletionService->delete($targetId);

echo json_encode(
    $result,
    JSON_THROW_ON_ERROR
) . PHP_EOL;
