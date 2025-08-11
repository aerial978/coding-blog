<?php

namespace App\Core\Container;

use App\Core\AppConfig;
use App\Core\Database;
use App\Core\Logger;
use App\Core\SqlHelper;
use PDO;
use Psr\Log\LoggerInterface;

/**
 * Provides access to system-level services such as configuration,
 * database connections, SQL helpers, and logging.
 */
class SystemServices
{
    private ?PDO $pdo = null;

    /**
     * Returns the application configuration.
     *
     * @return AppConfig
     */
    public function getAppConfig(): AppConfig
    {
        return new AppConfig();
    }

    /**
     * Returns a PDO database connection.
     * Creates a new connection if none exists yet.
     *
     * @return PDO
     */
    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = (new Database())->getConnection();
        }

        return $this->pdo;
    }

    /**
     * Returns an instance of SqlHelper using the current PDO connection.
     *
     * @return SqlHelper
     */
    public function getSqlHelper(): SqlHelper
    {
        return new SqlHelper($this->getPdo());
    }

    /**
     * Provides a PSR-3 compliant logger for the given channel.
     *
     * @param string $channel The logging channel name.
     * @return LoggerInterface
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public function getLogger(string $channel = 'app'): LoggerInterface
    {
        return Logger::getLogger($channel);
    }
}
