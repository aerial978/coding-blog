<?php

declare(strict_types=1);

namespace App\Core\Contract;

/**
 * Defines the contract for a rate limiter mechanism.
 *
 * A rate limiter restricts how frequently a certain action can be performed
 * within a defined time window, helping to prevent abuse such as brute-force
 * attacks, spam submissions, or excessive API requests.
 */
interface RateLimiterInterface
{
    /**
     * Determines whether the current action is allowed
     * based on the defined rate limit rules.
     *
     * @return bool
     *     True if the action is permitted, false if the rate limit has been exceeded.
     */
    public function isAllowed(): bool;
    /**
         * Returns the number of seconds remaining before the next allowed attempt.
         *
         * This is typically used to inform the user how long they must wait
         * before retrying the restricted action.
         *
         * @return int
         *     Time remaining in seconds until the rate limiter resets.
         */
    public function getRetryAfter(): int;
    /**
         * Records a new attempt for the current rate limiter key.
         *
         * This should be called each time an action (such as a login attempt)
         * is performed, so the limiter can track usage within the time window.
         */
    public function recordAttempt(): void;
}
