<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Security;

use App\Core\Contract\SessionInterface;
use App\Model\Contract\UserTokenModelInterface;
use App\Security\Contract\TokenGeneratorInterface;
use App\Service\Security\RememberMeService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RememberMeServiceTest extends TestCase
{
    private UserTokenModelInterface&MockObject $userTokenModel;
    private TokenGeneratorInterface&MockObject $tokenGenerator;
    private SessionInterface&MockObject $session;

    private RememberMeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userTokenModel = $this->createMock(UserTokenModelInterface::class);
        $this->tokenGenerator = $this->createMock(TokenGeneratorInterface::class);
        $this->session        = $this->createMock(SessionInterface::class);

        $this->service = new RememberMeService(
            $this->userTokenModel,
            $this->tokenGenerator,
            $this->session,
        );
    }

    public function testCreateRememberMeTokenReturnsNullWhenUserIdIsInvalid(): void
    {
        $this->tokenGenerator
            ->expects($this->never())
            ->method('generateUrlSafeToken');

        $this->userTokenModel
            ->expects($this->never())
            ->method('createRememberMeToken');

        $this->assertNull($this->service->createRememberMeToken(0));
    }

    public function testCreateRememberMeTokenReturnsRawTokenWhenPersisted(): void
    {
        $this->tokenGenerator
            ->expects($this->once())
            ->method('generateUrlSafeToken')
            ->willReturn('raw-token');

        $this->tokenGenerator
            ->expects($this->once())
            ->method('hashToken')
            ->with('raw-token')
            ->willReturn(str_repeat('a', 32));

        $this->userTokenModel
            ->expects($this->once())
            ->method('createRememberMeToken')
            ->with(
                42,
                str_repeat('a', 32),
                $this->isInstanceOf(\DateTimeImmutable::class)
            )
            ->willReturn(true);

        $this->assertSame('raw-token', $this->service->createRememberMeToken(42));
    }

    public function testCreateRememberMeTokenReturnsNullWhenPersistenceFails(): void
    {
        $this->tokenGenerator
            ->method('generateUrlSafeToken')
            ->willReturn('raw-token');

        $this->tokenGenerator
            ->method('hashToken')
            ->willReturn(str_repeat('b', 32));

        $this->userTokenModel
            ->expects($this->once())
            ->method('createRememberMeToken')
            ->willReturn(false);

        $this->assertNull($this->service->createRememberMeToken(42));
    }

    public function testCreateRememberMeTokenReturnsNullOnThrowable(): void
    {
        $this->tokenGenerator
            ->expects($this->once())
            ->method('generateUrlSafeToken')
            ->willThrowException(new \RuntimeException('token failure'));

        $this->assertNull($this->service->createRememberMeToken(42));
    }

    public function testRestoreSessionFromTokenReturnsFalseWhenTokenIsEmpty(): void
    {
        $this->tokenGenerator
            ->expects($this->never())
            ->method('hashToken');

        $this->assertFalse($this->service->restoreSessionFromToken('   '));
    }

    public function testRestoreSessionFromTokenReturnsFalseWhenTokenNotFound(): void
    {
        $this->tokenGenerator
            ->expects($this->once())
            ->method('hashToken')
            ->with('raw-token')
            ->willReturn(str_repeat('c', 32));

        $this->userTokenModel
            ->expects($this->once())
            ->method('findRememberMeContextByHash')
            ->with(str_repeat('c', 32))
            ->willReturn(null);

        $this->session
            ->expects($this->never())
            ->method('set');

        $this->assertFalse($this->service->restoreSessionFromToken('raw-token'));
    }

    public function testRestoreSessionFromTokenReturnsFalseWhenUserIdIsMissing(): void
    {
        $this->tokenGenerator
            ->method('hashToken')
            ->willReturn(str_repeat('d', 32));

        $this->userTokenModel
            ->method('findRememberMeContextByHash')
            ->willReturn([
                'user_id'     => null,
                'user_status' => 'active',
                'used'        => 0,
                'is_expired'  => 0,
            ]);

        $this->session
            ->expects($this->never())
            ->method('set');

        $this->assertFalse($this->service->restoreSessionFromToken('raw-token'));
    }

    public function testRestoreSessionFromTokenReturnsFalseWhenTokenIsUsed(): void
    {
        $this->tokenGenerator
            ->method('hashToken')
            ->willReturn(str_repeat('e', 32));

        $this->userTokenModel
            ->method('findRememberMeContextByHash')
            ->willReturn([
                'user_id'     => 42,
                'user_status' => 'active',
                'used'        => 1,
                'is_expired'  => 0,
            ]);

        $this->session
            ->expects($this->never())
            ->method('set');

        $this->assertFalse($this->service->restoreSessionFromToken('raw-token'));
    }

    public function testRestoreSessionFromTokenInvalidatesAndReturnsFalseWhenTokenIsExpired(): void
    {
        $this->tokenGenerator
            ->method('hashToken')
            ->willReturn(str_repeat('f', 32));

        $this->userTokenModel
            ->method('findRememberMeContextByHash')
            ->willReturn([
                'user_id'     => 42,
                'user_status' => 'active',
                'used'        => 0,
                'is_expired'  => 1,
            ]);

        $this->userTokenModel
            ->expects($this->once())
            ->method('invalidateRememberMeToken')
            ->with(42)
            ->willReturn(true);

        $this->session
            ->expects($this->never())
            ->method('set');

        $this->assertFalse($this->service->restoreSessionFromToken('raw-token'));
    }

    public function testRestoreSessionFromTokenInvalidatesAndReturnsFalseWhenUserIsNotActive(): void
    {
        $this->tokenGenerator
            ->method('hashToken')
            ->willReturn(str_repeat('g', 32));

        $this->userTokenModel
            ->method('findRememberMeContextByHash')
            ->willReturn([
                'user_id'     => 42,
                'user_status' => 'inactive',
                'used'        => 0,
                'is_expired'  => 0,
            ]);

        $this->userTokenModel
            ->expects($this->once())
            ->method('invalidateRememberMeToken')
            ->with(42)
            ->willReturn(true);

        $this->session
            ->expects($this->never())
            ->method('set');

        $this->assertFalse($this->service->restoreSessionFromToken('raw-token'));
    }

    public function testRestoreSessionFromTokenCreatesSessionAndReturnsTrueWhenTokenIsValid(): void
    {
        $this->tokenGenerator
            ->expects($this->once())
            ->method('hashToken')
            ->with('raw-token')
            ->willReturn(str_repeat('h', 32));

        $this->userTokenModel
            ->expects($this->once())
            ->method('findRememberMeContextByHash')
            ->with(str_repeat('h', 32))
            ->willReturn([
                'user_id'     => 42,
                'user_status' => 'active',
                'used'        => 0,
                'is_expired'  => 0,
            ]);

        $this->session
            ->expects($this->once())
            ->method('regenerateAndDeleteOld');

        $this->session
            ->expects($this->once())
            ->method('set')
            ->with('user', [
                'id'    => 42,
                'roles' => ['USER'],
            ]);

        $this->assertTrue($this->service->restoreSessionFromToken('raw-token'));
    }

    public function testRestoreSessionFromTokenReturnsFalseOnThrowable(): void
    {
        $this->tokenGenerator
            ->expects($this->once())
            ->method('hashToken')
            ->willThrowException(new \RuntimeException('hash failure'));

        $this->session
            ->expects($this->never())
            ->method('set');

        $this->assertFalse($this->service->restoreSessionFromToken('raw-token'));
    }

    public function testInvalidateRememberMeForUserReturnsFalseWhenUserIdIsInvalid(): void
    {
        $this->userTokenModel
            ->expects($this->never())
            ->method('invalidateRememberMeToken');

        $this->assertFalse($this->service->invalidateRememberMeForUser(0));
    }

    public function testInvalidateRememberMeForUserReturnsModelResult(): void
    {
        $this->userTokenModel
            ->expects($this->once())
            ->method('invalidateRememberMeToken')
            ->with(42)
            ->willReturn(true);

        $this->assertTrue($this->service->invalidateRememberMeForUser(42));
    }

    public function testInvalidateRememberMeForUserReturnsFalseOnThrowable(): void
    {
        $this->userTokenModel
            ->expects($this->once())
            ->method('invalidateRememberMeToken')
            ->with(42)
            ->willThrowException(new \RuntimeException('db failure'));

        $this->assertFalse($this->service->invalidateRememberMeForUser(42));
    }
}
