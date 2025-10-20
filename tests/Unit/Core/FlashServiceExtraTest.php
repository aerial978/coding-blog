<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\FlashService;
use App\Core\SessionManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FlashServiceExtraTest extends TestCase
{
    private FlashService $flash;
    private SessionManager $session;
    protected function setUp(): void
    {
        // Start an isolated session for each test
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $this->session = new SessionManager();
        // Reset the "flash" bag
        $this->session->set('flash', []);
        $this->flash = new FlashService($this->session);
    }

    protected function tearDown(): void
    {
        // Full cleanup to avoid state leaks between tests
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
    }

    #[Test]
    public function has_goes_true_after_add_then_false_after_get(): void
    {
        self::assertFalse($this->flash->has('info'));
        $this->flash->add('info', 'hello');
        self::assertTrue($this->flash->has('info'));
        // get() consumes and clears that type
        $msgs = $this->flash->get('info');
        self::assertSame(['hello'], $msgs);
        self::assertFalse($this->flash->has('info'));
    }

    #[Test]
    public function getAll_returns_all_and_consumes_bag(): void
    {
        $this->flash->add('success', 'ok');
        $this->flash->add('error', 'ko');
        $all = $this->flash->getAll();
        self::assertArrayHasKey('success', $all);
        self::assertArrayHasKey('error', $all);
        self::assertSame(['ok'], $all['success']);
        self::assertSame(['ko'], $all['error']);
        // Bag is now empty
        self::assertFalse($this->flash->has('success'));
        self::assertFalse($this->flash->has('error'));
    }

    #[Test]
    public function put_and_take_round_trip_and_default_on_missing(): void
    {
        $payload = ['username' => 'alice', 'email' => 'a@ex.tld'];
        $this->flash->put('old', $payload);
        $out1 = $this->flash->take('old');
        self::assertSame($payload, $out1);
        // Consumed → second read returns default
        $out2 = $this->flash->take('old', ['fallback' => true]);
        self::assertSame(['fallback' => true], $out2);
    }

    #[Test]
    public function put_replaces_value_without_stacking(): void
    {
        $this->flash->put('old', ['username' => 'v1']);
        $this->flash->put('old', ['username' => 'v2']);
        // replace

        $out = $this->flash->take('old');
        self::assertSame(['username' => 'v2'], $out);
    }

    #[Test]
    public function take_on_unknown_key_returns_default_and_does_not_create_key(): void
    {
        $val = $this->flash->take('unknown', 'DEF');
        self::assertSame('DEF', $val);
        // Still absent → again returns default
        $val2 = $this->flash->take('unknown', 'DEF2');
        self::assertSame('DEF2', $val2);
    }
}
