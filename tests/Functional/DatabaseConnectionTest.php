<?php

namespace Tests\Functional;

use App\Core\Database;
use PDO;

/**
 * Functional test to ensure that the application is connected
 * to the correct test database.
 *
 * This class verifies that the PDO connection returned by
 * Database::getConnection() points specifically to
 * "coding-blog_test" during testing.
 */
class DatabaseConnectionTest extends FunctionalTestCase
{
    /**
     * Test that the database connection is using the test database.
     *
     * This ensures that the PDO connection returned by Database::getConnection()
     * is connected to the correct database (coding-blog_test).
     */
    public function testDatabaseConnectionIsUsingTestDatabase(): void
    {
        $pdo = (new Database())->getConnection();

        // Ensure the returned object is a valid PDO instance
        $this->assertInstanceOf(PDO::class, $pdo);

        // Query the current database name
        $stmt = $pdo->query('SELECT DATABASE()');

        $this->assertInstanceOf(\PDOStatement::class, $stmt);

        // Fetch the database name
        $dbName = $stmt->fetchColumn();

        // Verify the database name matches the expected test database
        $this->assertSame('coding-blog_test', $dbName, 'The database in use is not coding-blog_test.');
    }
}
