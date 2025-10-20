<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\SqlHelper;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SqlHelperExtraTest extends TestCase
{
    private PDO $pdo;
    private SqlHelper $sql;
    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $this->sql = new SqlHelper($this->pdo);
    }

    #[Test]
    public function lastInsertId_returns_int_after_insert(): void
    {
        $this->sql->request('INSERT INTO items (name) VALUES (:n)', ['n' => 'foo']);
        self::assertSame(1, $this->sql->lastInsertId());
    }

    #[Test]
    public function beginTransaction_and_commit_persist_changes(): void
    {
        $this->sql->beginTransaction();
        $this->sql->request('INSERT INTO items (name) VALUES (:name)', ['name' => 'bar']);
        $this->sql->commit();

        $stmt = $this->pdo->query('SELECT COUNT(*) FROM items');
        self::assertInstanceOf(\PDOStatement::class, $stmt);
        $count = (int) $stmt->fetchColumn();

        self::assertSame(1, $count);
    }

    #[Test]
    public function rollBack_aborts_when_in_transaction(): void
    {
        $this->sql->beginTransaction();
        $this->sql->request('INSERT INTO items (name) VALUES (:name)', ['name' => 'baz']);
        $this->sql->rollBack();

        $stmt = $this->pdo->query('SELECT COUNT(*) FROM items');
        self::assertInstanceOf(\PDOStatement::class, $stmt);
        $count = (int) $stmt->fetchColumn();

        self::assertSame(0, $count, 'Aucun enregistrement ne doit persister après rollback.');
    }

    #[Test]
    public function rollBack_is_noop_when_not_in_transaction(): void
    {
        $this->sql->rollBack();

        $stmt = $this->pdo->query('SELECT COUNT(*) FROM items');
        self::assertInstanceOf(\PDOStatement::class, $stmt);
        $count = (int) $stmt->fetchColumn();

        self::assertSame(0, $count);
    }
}
