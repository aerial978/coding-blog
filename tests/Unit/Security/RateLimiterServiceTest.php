<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Core\SessionManager;
use App\Security\RateLimiterService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RateLimiterServiceTest extends TestCase
{
    private SessionManager $session;
    protected function setUp(): void
    {
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            @\session_start();
        }
        $this->session = new SessionManager();
        // Reset du sac de rate-limit avant chaque test
        $this->session->set('rate_limit', []);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (\session_status() === PHP_SESSION_ACTIVE) {
            @\session_write_close();
        }
    }

    /**
     * Helper pour fixer manuellement l'historique d'une action.
     * @param string $key
     * @param int[]  $timestamps
     */
    private function seed(string $key, array $timestamps): void
    {
        $state       = (array) $this->session->get('rate_limit', []);
        $state[$key] = \array_values($timestamps);
        $this->session->set('rate_limit', $state);
    }

    #[Test]
    public function isAllowed_true_when_under_limit_and_retryAfter_resets(): void
    {
        $key = 'register';
        $now = \time();
        // 1 tentative récente, limite à 3 -> autorisé
        $this->seed($key, [$now - 2]);
        $rl = new RateLimiterService($key, limit: 3, window: 60, session: $this->session);
        self::assertTrue($rl->isAllowed());
        self::assertSame(0, $rl->getRetryAfter());
    }

    #[Test]
    public function isAllowed_blocks_when_limit_reached_and_sets_retryAfter(): void
    {
        $key    = 'register';
        $window = 10;
        $now    = \time();
        // 3 tentatives récentes (== limite) -> doit bloquer
        // dernier = now - 1  => retryAfter ≈ 9 (avec tolérance de 0..1 sec)
        $this->seed($key, [$now - 3, $now - 2, $now - 1]);
        $rl = new RateLimiterService($key, limit: 3, window: $window, session: $this->session);
        self::assertFalse($rl->isAllowed());
        $ra       = $rl->getRetryAfter();
        $expected = ($now - 1 + $window) - \time();
        // recompute avec petite dérive temporelle
        // Tolérance pour la fraction de seconde entre nos calculs
        self::assertGreaterThanOrEqual($expected - 1, $ra);
        self::assertLessThanOrEqual($expected + 1, $ra);
    }

    #[Test]
    public function isAllowed_filters_out_expired_entries(): void
    {
        $key    = 'any';
        $window = 5;
        $now    = \time();
        // Un très ancien + un récent => le vieux doit être filtré
        $fresh = $now - 3;
        $this->seed($key, [$now - 100, $fresh]);
        $rl = new RateLimiterService($key, limit: 3, window: $window, session: $this->session);
        self::assertTrue($rl->isAllowed());
        // sous la limite après filtrage

        // Le sac doit maintenant ne contenir que l'entrée fraîche
        $state = (array) $this->session->get('rate_limit', []);
        self::assertSame([$fresh], \array_values((array)($state[$key] ?? [])));
    }

    #[Test]
    public function recordAttempt_appends_current_timestamp(): void
    {
        $key = 'post';
        $this->seed($key, []);
        // vide

        $rl = new RateLimiterService($key, limit: 5, window: 60, session: $this->session);
        $rl->recordAttempt();
        $state = (array) $this->session->get('rate_limit', []);
        self::assertCount(1, (array)$state[$key]);
        $rl->recordAttempt();
        $state = (array) $this->session->get('rate_limit', []);
        self::assertCount(2, (array)$state[$key]);
    }

    #[Test]
    public function getRemaining_counts_only_fresh_entries(): void
    {
        $key    = 'remaining';
        $window = 10;
        $limit  = 3;
        $now    = \time();
        // 1 expiré (now-11), 1 frais (now-1) => il reste 2
        $this->seed($key, [$now - 11, $now - 1]);
        $rl = new RateLimiterService($key, limit: $limit, window: $window, session: $this->session);
        self::assertSame(2, $rl->getRemaining());
    }

    #[Test]
    public function retryAfter_resets_to_zero_once_allowed_again(): void
    {
        $key    = 'cooldown';
        $window = 10;
        $now    = \time();
        // D'abord bloqué
        $this->seed($key, [$now - 2, $now - 2, $now - 1]);
        // 3/3
        $rl = new RateLimiterService($key, limit: 3, window: $window, session: $this->session);
        self::assertFalse($rl->isAllowed());
        self::assertGreaterThan(0, $rl->getRetryAfter());
        // On "libère" artificiellement en supprimant une tentative
        $this->seed($key, [$now - 2, $now - 1]);
        // 2/3
        self::assertTrue($rl->isAllowed());
        self::assertSame(0, $rl->getRetryAfter());
    }
}
