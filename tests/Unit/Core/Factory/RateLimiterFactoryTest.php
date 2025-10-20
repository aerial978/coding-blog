<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Factory;

use App\Core\Contract\RateLimiterInterface;
use App\Core\Factory\RateLimiterFactory;
use App\Core\SessionManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RateLimiterFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        // On isole l'état de session pour ne pas polluer d'autres tests.
        $_SESSION = [];
    }

    #[Test]
    public function create_returns_rate_limiter_and_shares_session_between_instances(): void
    {
        $session = new SessionManager();
        $factory = new RateLimiterFactory($session);
        // Limiteur A (même clé) : limite à 1 tentative pour rendre l’effet visible.
        $limiterA = $factory->create('registration', 1, 600);
        $this->assertInstanceOf(RateLimiterInterface::class, $limiterA);
        // D’abord autorisé…
        $this->assertTrue($limiterA->isAllowed());
        // …on enregistre une tentative.
        $limiterA->recordAttempt();
        // Nouveau limiteur B créé via la factory MAIS même session + même clé.
        $limiterB = $factory->create('registration', 1, 600);
        // Il doit “voir” la tentative précédente et donc refuser.
        $this->assertFalse($limiterB->isAllowed());
        $this->assertGreaterThan(0, $limiterB->getRetryAfter());
        // Une autre clé doit être indépendante.
        $other = $factory->create('resend_confirmation', 1, 600);
        $this->assertTrue($other->isAllowed());
    }

    #[Test]
    public function create_uses_default_limit_and_window(): void
    {
        $session = new SessionManager();
        $factory = new RateLimiterFactory($session);
        // Par défaut: limit = 5, window = 300.
        $limiter = $factory->create('default_key');

        // 4 tentatives: encore autorisé.
        for ($i = 0; $i < 4; $i++) {
            $limiter->recordAttempt();
        }
        $this->assertTrue($limiter->isAllowed());
        // 5e tentative → dépassement.
        $limiter->recordAttempt();
        $this->assertFalse($limiter->isAllowed());
        $this->assertGreaterThanOrEqual(0, $limiter->getRetryAfter());
    }
}
