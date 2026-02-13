<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Contract\RateLimiterInterface;
use App\Core\Logger;
use App\Core\Contract\SessionInterface;

/**
 * Session-based rate limiter service.
 *
 * This service implements a simple in-session rate limiting mechanism
 * to protect against spam or brute-force attacks. It tracks the timestamps
 * of recent attempts for a given action key (e.g. "registration") and enforces
 * a fixed window cooldown based on the most recent attempt.
 *
 * Once the defined limit is exceeded within the current time window,
 * further actions are temporarily blocked until the cooldown expires.
 */
final class RateLimiterService implements RateLimiterInterface
{
    /** @var string Session key under which rate-limit data is stored */
    private const BAG = 'rate_limit';
    /** @var int Number of seconds remaining before next allowed attempt */
    private int $retryAfter = 0;

    /**
     * Constructor.
     *
     * Initializes the rate limiter with the session handler and configuration
     * for the allowed number of attempts and cooldown duration.
     *
     * @param string $actionKey
     *     Identifier for the rate-limited action (e.g. "register", "login").
     * @param SessionManager $session
     *     Session handler used for persisting the rate-limiting state.
     * @param int $limit
     *     Maximum number of allowed attempts within the time window.
     * @param int $window
     *     Time window duration in seconds (default: 300 = 5 minutes).
     */
    public function __construct(
        private string $actionKey, // ex: 'registration'
        private SessionInterface $session,
        private int $limit = 5, // ex: 5 tentatives
        private int $window = 300,        // 300s = 5min
    ) {
    }

    /**
     * Determines whether the action is allowed within the current window.
     *
     * Cleans up old attempts outside the window and calculates whether
     * the number of remaining attempts has been exceeded. If so,
     * a retry cooldown is computed based on the last attempt timestamp.
     *
     * @return bool
     *     True if the action is permitted; false if the rate limit is exceeded.
     */
    public function isAllowed(): bool
    {
        $now   = time();
        /** @var array<string, mixed> $state */
        $state = (array) $this->session->get(self::BAG, []);

        // Normaliser -> list<int>
        $raw  = $state[$this->actionKey] ?? [];
        $list = array_values(is_array($raw) ? $raw : []);
        // garder uniquement des int ou des chaînes d'entiers
        $list = array_values(array_filter($list, fn ($value): bool =>
            is_int($value) || (is_string($value) && ctype_digit($value))));
        // puis convertir en int
        $list = array_map(fn ($value): int => (int) $value, $list);

        // Filtrer la fenêtre (closure NON static pour accéder à $this)
        $list = array_values(array_filter($list, fn (int $ts): bool =>
            ($now - $ts) < $this->window));

        $state[$this->actionKey] = $list;
        $this->session->set(self::BAG, $state);

        if (count($list) >= $this->limit) {
            $last             = $list !== [] ? max($list) : $now; // garde-fou
            $this->retryAfter = max(0, ($last + $this->window) - $now);

            Logger::getLogger('auth')->warning('Rate limit exceeded', [
                'action'      => $this->actionKey,
                'limit'       => $this->limit,
                'window_sec'  => $this->window,
                'retry_after' => $this->retryAfter,
            ]);
            return false;
        }

        $this->retryAfter = 0;
        return true;
    }

    /**
     * Records a new attempt timestamp.
     *
     * Should be called after each action attempt (e.g., after each form submission)
     * to update the rate limiter’s state.
     *
     * @return void
     */
    public function recordAttempt(): void
    {
        $state                   = (array) $this->session->get(self::BAG, []);
        $list                    = array_values((array)($state[$this->actionKey] ?? []));
        $list[]                  = time();
        $state[$this->actionKey] = $list;
        $this->session->set(self::BAG, $state);
    }

    /**
     * Returns the number of seconds before the next allowed attempt.
     *
     * @return int
     *     Remaining cooldown time in seconds.
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    /**
     * Returns the number of remaining attempts within the current window.
     *
     * This method can be used to display feedback to the user (e.g.,
     * "You have 2 attempts left before a cooldown is applied").
     *
     * @return int
     *     The number of remaining allowed attempts.
     */
    public function getRemaining(): int
    {
        $now   = time();
        /** @var array<string, mixed> $state */
        $state = (array) $this->session->get(self::BAG, []);

        $raw  = $state[$this->actionKey] ?? [];
        $list = array_values(is_array($raw) ? $raw : []);
        $list = array_values(array_filter($list, fn ($value): bool =>
            is_int($value) || (is_string($value) && ctype_digit($value))));
        $list = array_map(fn ($value): int => (int) $value, $list);
        $list = array_values(array_filter($list, fn (int $ts): bool =>
            ($now - $ts) < $this->window));

        return max(0, $this->limit - count($list));
    }
}
