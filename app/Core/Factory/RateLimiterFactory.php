<?php

declare(strict_types=1);

namespace App\Core\Factory;

use App\Core\Contract\RateLimiterFactoryInterface;
use App\Core\Contract\RateLimiterInterface;
use App\Core\Contract\SessionInterface;
use App\Security\RateLimiterService;

/**
 * Factory class responsible for creating rate limiter instances.
 *
 * This implementation uses the session storage to keep track of attempts
 * per action key. It provides an easy way to create independent
 * rate limiters with customizable limits and time windows.
 */
final class RateLimiterFactory implements RateLimiterFactoryInterface
{
    /**
     * Constructor.
     *
     * Initializes the factory with a session manager instance used
     * to persist rate limiter state between requests.
     *
     * @param SessionInterface $session
     *     The session manager used for storing rate limiter data.
     */
    public function __construct(private SessionInterface $session)
    {
    }

    /**
     * Creates a new rate limiter service instance.
     *
     * @param string $actionKey
     *     Unique identifier for the limited action (e.g. "login", "register", "email_resend").
     * @param int $limit
     *     Maximum number of allowed attempts within the given time window.
     *     Defaults to 5 attempts.
     * @param int $window
     *     Duration of the time window in seconds during which attempts are tracked.
     *     Defaults to 300 seconds (5 minutes).
     *
     * @return RateLimiterInterface
     *     A new instance of RateLimiterService configured for the given action key.
     */
    public function create(string $actionKey, int $limit = 5, int $window = 300): RateLimiterInterface
    {
        return new RateLimiterService($actionKey, $this->session, $limit, $window);
    }
}
