<?php

declare(strict_types=1);

namespace App\Core\Contract;

/**
 * Defines the contract for a flash message storage system.
 *
 * Flash messages are temporary key/value data stored in session
 * and meant to persist only for a single request/response cycle.
 * They are typically used for user feedback messages such as
 * success, error, warning, or info after form submissions.
 */
interface FlashInterface
{
    /**
     * Adds a flash message of a given type (e.g. success, error, warning, info).
     *
     * @param string $type
     *     The message category (success, error, warning, info, etc.).
     * @param string $message
     *     The message content to store.
     */
    public function add(string $type, string $message): void;

    /**
     * Stores a temporary value in the flash session under a given key.
     *
     * @param string $key
     *     The key name under which the value is stored.
     * @param mixed $value
     *     The value to be stored.
     */
    public function put(string $key, mixed $value): void;

    /**
     * Retrieves and removes a flash value by key.
     *
     * If the key does not exist, the provided default value is returned.
     *
     * @param string $key
     *     The flash data key.
     * @param mixed|null $default
     *     Default value to return if the key is not found.
     * @return mixed
     *     The stored flash value or the default if not present.
     */
    public function take(string $key, mixed $default = null): mixed;

    /**
     * Retrieves and removes multiple flash messages grouped by level.
     *
     * @param list<'success'|'error'|'warning'|'info'> $levels
     *     The message levels to consume. Defaults to all standard levels.
     *
     * @return array{
     *     success: list<string>,
     *     error:   list<string>,
     *     warning: list<string>,
     *     info:    list<string>
     * }
     *     An associative array of message lists grouped by level.
     */
    public function consumeMany(
        array $levels = ['success', 'error', 'warning', 'info']
    ): array;
}
