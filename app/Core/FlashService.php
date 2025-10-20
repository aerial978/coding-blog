<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Contract\FlashInterface;

/**
 * Provides a session-based flash message management service.
 *
 * This service allows storing short-lived messages (e.g., success, error, warning, info)
 * in the user session. Flash messages persist only for one request/response cycle
 * and are automatically cleared after being retrieved.
 *
 * It also supports storing arbitrary temporary values through simple key/value storage.
 */
final class FlashService implements FlashInterface
{
    private const BAG = 'flash';

    /**
     * Constructor.
     *
     * Initializes the flash service with a session manager
     * used to persist flash data between requests.
     *
     * @param SessionManager $session
     *     The session manager instance for accessing and updating session data.
     */
    public function __construct(private SessionManager $session)
    {
    }

    /**
     * Adds a message to a specific flash category (type).
     *
     * Common types include "success", "error", "warning", or "info".
     *
     * @param string $type
     *     The message category.
     * @param string $message
     *     The message content.
     */
    public function add(string $type, string $message): void
    {
        /** @var mixed $raw */
        $raw = $this->session->get(self::BAG, []);
        $bag = (array) $raw;                // ← cast sûr, plus d’is_array()

        if (!isset($bag[$type]) || !is_array($bag[$type])) {
            $bag[$type] = [];
        }

        $bag[$type][] = $message;
        $this->session->set(self::BAG, $bag);
    }

    /** @return list<string> */
    public function get(string $type): array
    {
        /** @var mixed $raw */
        $raw = $this->session->get(self::BAG, []);
        $bag = (array) $raw;

        $messages = [];
        if (isset($bag[$type]) && is_array($bag[$type])) {
            foreach ($bag[$type] as $v) {
                if (is_string($v)) {
                    $messages[] = $v;
                }
            }
        }

        unset($bag[$type]);
        $this->session->set(self::BAG, $bag);
        return $messages;
    }

    /**
     * Checks if there are any messages of a given type.
     *
     * @param string $type
     *     The message category to check.
     * @return bool
     *     True if at least one message of this type exists, false otherwise.
     */
    public function has(string $type): bool
    {
        $bag = $this->session->get(self::BAG, []);
        return is_array($bag) && !empty($bag[$type]);
    }

    /** @return array<string, list<string>> */
    public function getAll(): array
    {
        $raw = $this->session->get(self::BAG, []);
        $this->session->set(self::BAG, []);

        if (!is_array($raw)) {
            return [];
        }

        // Normalize to array<string, list<string>>
        $bag = [];
        foreach ($raw as $k => $v) {
            $key  = is_string($k) ? $k : (string) $k;
            $list = [];
            if (is_array($v)) {
                foreach ($v as $msg) {
                    if (is_string($msg)) {
                        $list[] = $msg;
                    }
                }
            }
            $bag[$key] = $list;
        }

        return $bag;
    }

    /**
     * Stores a temporary value in the flash storage under a custom key.
     *
     * Unlike add(), this method overwrites existing data for the same key.
     *
     * @param string $key
     *     The custom key under which to store the value.
     * @param mixed $value
     *     The value to be stored temporarily.
     */
    public function put(string $key, mixed $value): void
    {
        /** @var mixed $raw */
        $raw       = $this->session->get(self::BAG, []);
        $bag       = (array) $raw;
        $bag[$key] = $value;
        $this->session->set(self::BAG, $bag);
    }

    /**
     * Retrieves and removes a temporary value from the flash storage.
     *
     * @param string $key
     *     The key to retrieve.
     * @param mixed|null $default
     *     Default value returned if the key does not exist.
     * @return mixed
     *     The stored value or the default value if not found.
     */
    public function take(string $key, mixed $default = null): mixed
    {
        /** @var mixed $raw */
        $raw = $this->session->get(self::BAG, []);
        $bag = (array) $raw;

        $val = array_key_exists($key, $bag) ? $bag[$key] : $default;
        unset($bag[$key]);
        $this->session->set(self::BAG, $bag);
        return $val;
    }

    /**
     * @param list<'success'|'error'|'warning'|'info'> $levels
     * @return array{
     *   success: list<string>,
     *   error:   list<string>,
     *   warning: list<string>,
     *   info:    list<string>
     * }
     */
    public function consumeMany(array $levels = ['success','error','warning','info']): array
    {
        $out = [
            'success' => [],
            'error'   => [],
            'warning' => [],
            'info'    => [],
        ];

        foreach ($levels as $lvl) {
            $out[$lvl] = $this->get($lvl); // no array_values() needed
        }

        return $out;
    }
}
