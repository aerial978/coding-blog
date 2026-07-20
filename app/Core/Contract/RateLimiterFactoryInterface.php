<?php

declare(strict_types=1);

namespace App\Core\Contract;

/**
 * Defines the contract for a rate limiter factory.
 *
 * This interface is responsible for creating instances of rate limiters
 * that enforce restrictions on how frequently specific actions can be performed.
 * Typical use cases include login attempts, password reset requests,
 * and email confirmation resends.
 */
interface RateLimiterFactoryInterface
{
    /**
     * Creates and returns a new rate limiter instance.
     *
     * @param string $key
     *     Unique identifier for the action or resource to be limited
     *     (e.g. user ID, IP address, or route name).
     * @param int $maxAttempts
     *     Maximum number of allowed attempts within the given time window.
     * @param int $windowSeconds
     *     Duration of the time window in seconds during which attempts are counted.
     *
     * @return RateLimiterInterface
     *     The instantiated rate limiter for the specified configuration.
     */
    public function create(string $key, int $maxAttempts, int $windowSeconds): RateLimiterInterface;
}
