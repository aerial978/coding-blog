<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Security;

use App\Core\Contract\SessionInterface;
use App\Service\Security\LogoutService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class LogoutServiceTest extends TestCase
{
    private SessionInterface&MockObject $session;
    private LogoutService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->session = $this->createMock(SessionInterface::class);

        $this->service = new LogoutService($this->session);
    }

    public function testLogoutClearsSessionAndRegeneratesIdWhenUserIdIsInt(): void
    {
        $this->session
            ->expects($this->once())
            ->method('get')
            ->with('user_id')
            ->willReturn(223);

        $this->session
            ->expects($this->once())
            ->method('clear');

        $this->session
            ->expects($this->once())
            ->method('regenerateAndDeleteOld');

        $this->service->logout();

        $this->assertTrue(true);
    }

    public function testLogoutClearsSessionAndRegeneratesIdWhenUserIdIsString(): void
    {
        $this->session
            ->expects($this->once())
            ->method('get')
            ->with('user_id')
            ->willReturn('223');

        $this->session
            ->expects($this->once())
            ->method('clear');

        $this->session
            ->expects($this->once())
            ->method('regenerateAndDeleteOld');

        $this->service->logout();

        $this->assertTrue(true);
    }

    public function testLogoutClearsSessionAndRegeneratesIdWhenUserIdIsNull(): void
    {
        $this->session
            ->expects($this->once())
            ->method('get')
            ->with('user_id')
            ->willReturn(null);

        $this->session
            ->expects($this->once())
            ->method('clear');

        $this->session
            ->expects($this->once())
            ->method('regenerateAndDeleteOld');

        $this->service->logout();

        $this->assertTrue(true);
    }

    public function testLogoutClearsSessionAndRegeneratesIdWhenUserIdIsUnexpectedType(): void
    {
        $this->session
            ->expects($this->once())
            ->method('get')
            ->with('user_id')
            ->willReturn(['unexpected']);

        $this->session
            ->expects($this->once())
            ->method('clear');

        $this->session
            ->expects($this->once())
            ->method('regenerateAndDeleteOld');

        $this->service->logout();

        $this->assertTrue(true);
    }
}
