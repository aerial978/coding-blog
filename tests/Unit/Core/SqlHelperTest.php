<?php

namespace Tests\Unit\Core;

use App\Core\Database;
use App\Core\SqlHelper;
use Tests\Unit\UnitTestCase;

/**
 * Unit tests for the SqlHelper class.
 *
 * This test suite ensures that:
 * - SQL queries without parameters execute successfully.
 * - SQL queries with bound parameters work correctly.
 * - An exception is thrown when a query fails.
 */
final class SqlHelperTest extends UnitTestCase
{
    /**
     * The SqlHelper instance used in the tests.
     *
     * @var SqlHelper
     */
    private SqlHelper $sqlHelper;

    /**
     * A randomly generated username used for testing database insertions.
     *
     * @var string
     */
    private string $dummyUsername;

    /**
     * Set up the test environment.
     *
     * Creates a PDO connection, instantiates SqlHelper,
     * and generates a unique dummy username for insertion tests.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $pdo             = (new Database())->getConnection();
        $this->sqlHelper = new SqlHelper($pdo);

        $this->dummyUsername = 'test_user_' . uniqid();
    }

    /**
     * Tear down the test environment.
     *
     * Deletes the dummy user created during the tests
     * to ensure no leftover data remains in the database.
     */
    protected function tearDown(): void
    {
        $pdo  = (new Database())->getConnection();
        $stmt = $pdo->prepare('DELETE FROM user WHERE username = :username');
        $stmt->execute(['username' => $this->dummyUsername]);
    }

    /**
     * Test executing a SQL query without parameters.
     *
     * This method inserts a dummy user and verifies that:
     * - The insertion query affected rows.
     * - The inserted user can be retrieved from the database.
     */
    public function testRequestWithoutParameters(): void
    {
        $email = $this->dummyUsername . '@example.com';
        $sql   = "INSERT INTO user (username, email) VALUES ('{$this->dummyUsername}', '$email')";
        $stmt  = $this->sqlHelper->request($sql);

        $inserted = $stmt->rowCount() > 0;
        $this->assertTrue($inserted, 'User insertion failed.');

        $stmt = $this->sqlHelper->request("SELECT username FROM user WHERE username = '$this->dummyUsername'");

        $fetchedUsername = $stmt->fetchColumn();
        $this->assertSame($this->dummyUsername, $fetchedUsername);
    }

    /**
     * Test executing a SQL query with bound parameters.
     *
     * This method:
     * - Inserts a dummy user using a prepared statement with parameters.
     * - Fetches the inserted user using a parameterized SELECT query.
     * - Verifies that the user data matches the expected values.
     */
    public function testRequestWithParameters(): void
    {
        $sql    = 'INSERT INTO user (username, email) VALUES (:username, :email)';
        $params = [
            'username' => $this->dummyUsername,
            'email'    => $this->dummyUsername . '@example.com'
        ];

        $stmt = $this->sqlHelper->request($sql, $params);
        $this->assertTrue($stmt->rowCount() > 0, 'User insertion failed.');

        $stmt = $this->sqlHelper->request(
            'SELECT username FROM user WHERE username = :username',
            ['username' => $this->dummyUsername]
        );

        $fetchedUsername = $stmt->fetchColumn();
        $this->assertSame($this->dummyUsername, $fetchedUsername);
    }

    /**
     * Test that a RuntimeException is thrown when a query fails.
     *
     * This simulates a query failure by mocking PDO::query
     * to return false and verifying that SqlHelper throws
     * the appropriate exception.
     */
    public function testRequestThrowsExceptionOnFailedQuery(): void
    {
        $pdoMock = $this->createMock(\PDO::class);
        $pdoMock->method('query')->willReturn(false);

        $sqlHelper = new SqlHelper($pdoMock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/SQL query failed/');

        $sqlHelper->request('SELECT * FROM nonexistent_table');
    }
}
