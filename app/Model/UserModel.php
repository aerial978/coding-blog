<?php

namespace App\Model;

use App\Core\SqlHelper;
use App\Model\Entity\UserEntity;

/**
 * Handles database operations related to the User entity.
 *
 * This class acts as the data access layer for user records.
 * It uses SqlHelper for database queries and LoggerInterface for logging.
 */
class UserModel
{
    /**
     * @param SqlHelper $sqlHelper Helper for executing SQL queries.
     */
    public function __construct(
        private SqlHelper $sqlHelper
    ) {
    }

    /**
     * Retrieves all users from the database.
     *
     * @return UserEntity[] An array of UserEntity objects representing all users in the database.
     */
    public function findAll(): array
    {
        // Execute the query to retrieve user data
        $stmt = $this->sqlHelper->request('SELECT id AS user_id, username, email, created_at FROM user');

        // Fetch all results as associative arrays
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $users = [];

        /** @var array<string, mixed> $row */
        foreach ($results as $row) {
            // Hydrate each UserEntity with database data
            $users[] = (new UserEntity())->hydrate($row);
        }

        /* Log the retrieval event
        $this->logger->info('Retrieved users', [
            'total'     => count($users),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);*/

        return $users;
    }
}
