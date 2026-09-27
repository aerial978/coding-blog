<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Core\Contract\FlashInterface;
use App\Core\Contract\SessionInterface;
use App\Core\FormId;
use App\Http\ViewContextProvider;
use App\Security\Contract\CsrfTokenInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ViewContextProviderTest extends TestCase
{
    private FlashInterface&MockObject $flash;
    private SessionInterface&MockObject $session;

    private ViewContextProvider $provider;

    private CsrfTokenInterface&MockObject $csrf;

    protected function setUp(): void
    {
        parent::setUp();

        $this->flash   = $this->createMock(FlashInterface::class);
        $this->session = $this->createMock(SessionInterface::class);
        $this->csrf    = $this->createMock(CsrfTokenInterface::class);

        $this->provider = new ViewContextProvider(
            $this->flash,
            $this->session,
            $this->csrf,
        );
    }

    public function testGetContextReturnsAuthenticatedContext(): void
    {
        $_ENV['TURNSTILE_SITE_KEY'] = 'test-site-key';

        $user = [
            'id'    => 42,
            'roles' => ['MEMBER'],
        ];

        $flashes = [
            'success' => ['Connexion réussie.'],
            'error'   => [],
            'warning' => [],
            'info'    => [],
        ];

        $this->csrf
            ->expects($this->once())
            ->method('generateToken')
            ->with(FormId::LOGOUT)
            ->willReturn('logout-csrf-token');

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
        $this->assertSame('logout-csrf-token', $result['logout_csrf_token']);
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

        $this->csrf
            ->expects($this->never())
            ->method('generateToken');

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
        $this->assertSame('', $result['logout_csrf_token']);
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

        $this->csrf
            ->expects($this->never())
            ->method('generateToken');

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

        $this->assertSame('', $result['logout_csrf_token']);
        $this->assertNull($result['auth_user']);
        $this->assertFalse($result['is_authenticated']);
        $this->assertFalse($result['show_header']);
    }
}
