<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Security;

use App\Core\ErrorCode;
use App\Model\Contract\UserTokenModelInterface;
use App\Security\Contract\TokenGeneratorInterface;
use App\Service\Security\AccountConfirmationService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AccountConfirmationServiceTest extends TestCase
{
    private UserTokenModelInterface&MockObject $userTokenModel;
    private TokenGeneratorInterface&MockObject $tokenGen;

    private AccountConfirmationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userTokenModel = $this->createMock(UserTokenModelInterface::class);
        $this->tokenGen       = $this->createMock(TokenGeneratorInterface::class);

        $this->service = new AccountConfirmationService(
            $this->userTokenModel,
            $this->tokenGen,
        );
    }

    public function testConfirmReturnsTechnicalErrorWhenHashLengthIsInvalid(): void
    {
        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->with('clear-token')
            ->willReturn('short-hash');

        $this->userTokenModel
            ->expects($this->never())
            ->method('findConfirmationContextByHash');

        $result = $this->service->confirm('clear-token');

        $this->assertSame(
            ['error' => ErrorCode::AUTH_TECHNICAL_ERROR],
            $result
        );
    }

    public function testConfirmReturnsInvalidTokenWhenContextIsNotFound(): void
    {
        $hash = str_repeat('a', 32);

        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->with('clear-token')
            ->willReturn($hash);

        $this->userTokenModel
            ->expects($this->once())
            ->method('findConfirmationContextByHash')
            ->with($hash)
            ->willReturn(null);

        $result = $this->service->confirm('clear-token');

        $this->assertSame(
            [
                'error'  => ErrorCode::AUTH_INVALID_CONFIRM_TOKEN,
                'reason' => 'not_found',
            ],
            $result
        );
    }

    public function testConfirmReturnsAlreadyConfirmedWhenUserIsActive(): void
    {
        $hash = str_repeat('b', 32);

        $row = [
            'user_id'    => 42,
            'status'     => 'active',
            'is_expired' => 0,
            'used'       => 0,
        ];

        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->with('clear-token')
            ->willReturn($hash);

        $this->userTokenModel
            ->expects($this->once())
            ->method('findConfirmationContextByHash')
            ->with($hash)
            ->willReturn($row);

        $this->userTokenModel
            ->expects($this->never())
            ->method('activateByHash');

        $result = $this->service->confirm('clear-token');

        $this->assertSame(
            ['error' => ErrorCode::AUTH_ALREADY_CONFIRMED],
            $result
        );
    }

    public function testConfirmReturnsInvalidExpiredWhenTokenIsExpiredAndUnused(): void
    {
        $hash = str_repeat('c', 32);

        $row = [
            'user_id'    => 42,
            'status'     => 'pending',
            'is_expired' => 1,
            'used'       => 0,
        ];

        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->with('clear-token')
            ->willReturn($hash);

        $this->userTokenModel
            ->expects($this->once())
            ->method('findConfirmationContextByHash')
            ->with($hash)
            ->willReturn($row);

        $this->userTokenModel
            ->expects($this->never())
            ->method('activateByHash');

        $result = $this->service->confirm('clear-token');

        $this->assertSame(
            [
                'error'  => ErrorCode::AUTH_INVALID_CONFIRM_TOKEN,
                'reason' => 'expired',
            ],
            $result
        );
    }

    public function testConfirmReturnsInvalidExpiredWhenTokenIsExpiredAndUsed(): void
    {
        $hash = str_repeat('d', 32);

        $row = [
            'user_id'    => 42,
            'status'     => 'pending',
            'is_expired' => 1,
            'used'       => 1,
        ];

        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->with('clear-token')
            ->willReturn($hash);

        $this->userTokenModel
            ->expects($this->once())
            ->method('findConfirmationContextByHash')
            ->with($hash)
            ->willReturn($row);

        $this->userTokenModel
            ->expects($this->never())
            ->method('activateByHash');

        $result = $this->service->confirm('clear-token');

        $this->assertSame(
            [
                'error'  => ErrorCode::AUTH_INVALID_CONFIRM_TOKEN,
                'reason' => 'expired',
            ],
            $result
        );
    }

    public function testConfirmReturnsTokenUsedWhenTokenWasUsedAndUserStillInactive(): void
    {
        $hash = str_repeat('e', 32);

        $row = [
            'user_id'    => 42,
            'status'     => 'pending',
            'is_expired' => 0,
            'used'       => 1,
        ];

        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->with('clear-token')
            ->willReturn($hash);

        $this->userTokenModel
            ->expects($this->once())
            ->method('findConfirmationContextByHash')
            ->with($hash)
            ->willReturn($row);

        $this->userTokenModel
            ->expects($this->never())
            ->method('activateByHash');

        $result = $this->service->confirm('clear-token');

        $this->assertSame(
            [
                'error'  => ErrorCode::AUTH_CONFIRM_TOKEN_USED,
                'reason' => 'used',
            ],
            $result
        );
    }

    public function testConfirmReturnsTechnicalErrorWhenActivationFails(): void
    {
        $hash = str_repeat('f', 32);

        $row = [
            'user_id'    => 42,
            'status'     => 'pending',
            'is_expired' => 0,
            'used'       => 0,
        ];

        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->with('clear-token')
            ->willReturn($hash);

        $this->userTokenModel
            ->expects($this->once())
            ->method('findConfirmationContextByHash')
            ->with($hash)
            ->willReturn($row);

        $this->userTokenModel
            ->expects($this->once())
            ->method('activateByHash')
            ->with($hash)
            ->willReturn(false);

        $result = $this->service->confirm('clear-token');

        $this->assertSame(
            [
                'error'  => ErrorCode::AUTH_TECHNICAL_ERROR,
                'reason' => 'activate_failed',
            ],
            $result
        );
    }

    public function testConfirmReturnsSuccessWhenActivationSucceeds(): void
    {
        $hash = str_repeat('g', 32);

        $row = [
            'user_id'    => 42,
            'status'     => 'pending',
            'is_expired' => 0,
            'used'       => 0,
        ];

        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->with('clear-token')
            ->willReturn($hash);

        $this->userTokenModel
            ->expects($this->once())
            ->method('findConfirmationContextByHash')
            ->with($hash)
            ->willReturn($row);

        $this->userTokenModel
            ->expects($this->once())
            ->method('activateByHash')
            ->with($hash)
            ->willReturn(true);

        $result = $this->service->confirm('clear-token');

        $this->assertSame([], $result);
    }

    public function testConfirmReturnsTechnicalErrorWhenUnexpectedThrowableOccurs(): void
    {
        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->with('clear-token')
            ->willThrowException(new \RuntimeException('unexpected'));

        $result = $this->service->confirm('clear-token');

        $this->assertSame(
            ['error' => ErrorCode::AUTH_TECHNICAL_ERROR],
            $result
        );
    }
}
