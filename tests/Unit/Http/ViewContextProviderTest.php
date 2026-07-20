<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Core\Contract\FlashInterface;
use App\Core\Contract\SessionInterface;
use App\Http\ViewContextProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ViewContextProviderTest extends TestCase
{
    private FlashInterface&MockObject $flash;
    private SessionInterface&MockObject $session;

    private ViewContextProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->flash   = $this->createMock(FlashInterface::class);
        $this->session = $this->createMock(SessionInterface::class);

        $this->provider = new ViewContextProvider(
            $this->flash,
            $this->session,
        );
    }

    public function testGetContextReturnsAuthenticatedContext(): void
    {
        $_ENV['TURNSTILE_SITE_KEY'] = 'test-site-key';

        $user = [
            'id'    => 42,
            'roles' => ['USER'],
        ];

        $flashes = [
            'success' => ['Connexion réussie.'],
            'error'   => [],
            'warning' => [],
            'info'    => [],
        ];

        $this->session
            ->expects($this->once())
            ->method('get')
            ->with('user')
            ->willReturn($user);

        $this->session
            ->expects($this->once())
            ->method('has')
            ->with('auth_2fa_pending')
            ->willReturn(true);

        $this->flash
            ->expects($this->once())
            ->method('consumeMany')
            ->with(['success', 'error', 'warning', 'info'])
            ->willReturn($flashes);

        $result = $this->provider->getContext();

        $this->assertSame($flashes, $result['flashes']);
        $this->assertSame($user, $result['auth_user']);
        $this->assertTrue($result['is_authenticated']);
        $this->assertTrue($result['email_2fa_pending']);
        $this->assertTrue($result['show_header']);
        $this->assertSame('test-site-key', $result['turnstile_site_key']);
    }

    public function testGetContextReturnsGuestContextWhenUserIsMissing(): void
    {
        unset($_ENV['TURNSTILE_SITE_KEY']);

        $flashes = [
            'success' => [],
            'error'   => [],
            'warning' => [],
            'info'    => [],
        ];

        $this->session
            ->expects($this->once())
            ->method('get')
            ->with('user')
            ->willReturn(null);

        $this->session
            ->expects($this->once())
            ->method('has')
            ->with('auth_2fa_pending')
            ->willReturn(false);

        $this->flash
            ->expects($this->once())
            ->method('consumeMany')
            ->with(['success', 'error', 'warning', 'info'])
            ->willReturn($flashes);

        $result = $this->provider->getContext();

        $this->assertSame($flashes, $result['flashes']);
        $this->assertNull($result['auth_user']);
        $this->assertFalse($result['is_authenticated']);
        $this->assertFalse($result['email_2fa_pending']);
        $this->assertFalse($result['show_header']);
        $this->assertSame('', $result['turnstile_site_key']);
    }

    public function testGetContextReturnsGuestContextWhenSessionUserIsNotArray(): void
    {
        $_ENV['TURNSTILE_SITE_KEY'] = 'test-site-key';

        $flashes = [
            'success' => [],
            'error'   => [],
            'warning' => [],
            'info'    => [],
        ];

        $this->session
            ->expects($this->once())
            ->method('get')
            ->with('user')
            ->willReturn('invalid-user');

        $this->session
            ->expects($this->once())
            ->method('has')
            ->with('auth_2fa_pending')
            ->willReturn(false);

        $this->flash
            ->expects($this->once())
            ->method('consumeMany')
            ->willReturn($flashes);

        $result = $this->provider->getContext();

        $this->assertNull($result['auth_user']);
        $this->assertFalse($result['is_authenticated']);
        $this->assertFalse($result['show_header']);
    }
}
