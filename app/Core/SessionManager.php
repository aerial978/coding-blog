<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Provides an abstraction layer for native PHP session handling.
 *
 * This class centralizes access to the global `$_SESSION` array and
 * offers helper methods for managing session data. It simplifies
 * interaction with sessions while maintaining strict typing and
 * improving testability.
 *
 * The session must be started externally (e.g., via `session_start()`)
 * before using this class.
 */
final class SessionManager
{
    /**
     * Stores a value in the session under the given key.
     *
     * @param string $key
     *     The key name under which the value will be stored.
     * @param mixed $value
     *     The value to store in the session.
     */
    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Retrieves a value from the session by its key.
     *
     * If the key does not exist, the provided default value is returned.
     *
     * @param string $key
     *     The key name of the session entry.
     * @param mixed|null $default
     *     Default value returned when the key does not exist.
     *
     * @return mixed
     *     The session value or the default value if the key is not found.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Checks whether a session key exists.
     *
     * @param string $key
     *     The key name to check.
     *
     * @return bool
     *     True if the key exists in the session, false otherwise.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    /**
     * Retrieves all session data.
     *
     * @return array<string, mixed>
     *     An associative array containing all session keys and values.
     */
    public function all(): array
    {
        /** @var array<string, mixed> $session */
        $session = (array) $_SESSION;
        return $session;
    }

    /**
     * Removes a specific entry from the session.
     *
     * @param string $key
     *     The key name of the session entry to remove.
     */
    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Clears all session data.
     *
     * This method effectively resets the session to an empty state.
     */
    public function clear(): void
    {
        $_SESSION = [];
    }

    public function regenerateAndDeleteOld(): void
    {
        session_regenerate_id(true);
    }

    public function regenerateKeepOld(): void
    {
        session_regenerate_id(false);
    }
}
