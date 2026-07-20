<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Security;

use App\Core\Contract\SessionInterface;
use App\Service\Security\Contract\RememberMeServiceInterface;
use App\Service\Security\LogoutService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class LogoutServiceTest extends TestCase
{
    private SessionInterface&MockObject $session;
    private LogoutService $service;
    private RememberMeServiceInterface&MockObject $rememberMeService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->session           = $this->createMock(SessionInterface::class);
        $this->rememberMeService = $this->createMock(RememberMeServiceInterface::class);

        $this->service = new LogoutService(
            $this->session,
            $this->rememberMeService,
        );
    }

    public function testLogoutClearsSessionAndRegeneratesIdWhenUserIdIsInt(): void
    {
        $this->session
            ->expects($this->once())
            ->method('get')
            ->with('user')
            ->willReturn([
                'id'    => 223,
                'roles' => ['USER'],
            ]);

        $this->session
            ->expects($this->once())
            ->method('clear');

        $this->session
            ->expects($this->once())
            ->method('regenerateAndDeleteOld');

        $this->rememberMeService
            ->expects($this->once())
            ->method('invalidateRememberMeForUser')
            ->with(223)
            ->willReturn(true);

        $this->service->logout();

        $this->assertTrue(true);
    }

    public function testLogoutDoesNotInvalidateRememberMeWhenUserIdIsString(): void
    {
        $this->session
            ->expects($this->once())
            ->method('get')
            ->with('user')
            ->willReturn([
                'id'    => '223',
                'roles' => ['user'],
            ]);

        $this->session
            ->expects($this->once())
            ->method('clear');

        $this->session
            ->expects($this->once())
            ->method('regenerateAndDeleteOld');

        $this->rememberMeService
            ->expects($this->never())
            ->method('invalidateRememberMeForUser');

        $this->service->logout();

        $this->assertTrue(true);
    }

    public function testLogoutClearsSessionAndRegeneratesIdWhenUserIdIsNull(): void
    {
        $this->session
            ->expects($this->once())
            ->method('get')
            ->with('user')
            ->willReturn(null);

        $this->session
            ->expects($this->once())
            ->method('clear');

        $this->session
            ->expects($this->once())
            ->method('regenerateAndDeleteOld');

        $this->rememberMeService
            ->expects($this->never())
            ->method('invalidateRememberMeForUser');

        $this->service->logout();

        $this->assertTrue(true);
    }

    public function testLogoutClearsSessionAndRegeneratesIdWhenUserIdIsUnexpectedType(): void
    {
        $this->session
            ->expects($this->once())
            ->method('get')
            ->with('user')
            ->willReturn(['unexpected']);

        $this->session
            ->expects($this->once())
            ->method('clear');

        $this->session
            ->expects($this->once())
            ->method('regenerateAndDeleteOld');

        $this->rememberMeService
            ->expects($this->never())
            ->method('invalidateRememberMeForUser');

        $this->service->logout();

        $this->assertTrue(true);
    }
}
