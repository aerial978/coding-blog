<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Security;

use App\Core\ErrorCode;
use App\Model\Contract\UserTokenModelInterface;
use App\Security\Contract\TokenGeneratorInterface;
use App\Service\Security\ResetPasswordService;
use App\Validation\Contract\FormValidatorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ResetPasswordServiceTest extends TestCase
{
    private FormValidatorInterface&MockObject $validator;
    private UserTokenModelInterface&MockObject $userTokenModel;
    private TokenGeneratorInterface&MockObject $tokenGen;

    private ResetPasswordService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator      = $this->createMock(FormValidatorInterface::class);
        $this->userTokenModel = $this->createMock(UserTokenModelInterface::class);
        $this->tokenGen       = $this->createMock(TokenGeneratorInterface::class);

        $this->service = new ResetPasswordService(
            $this->validator,
            $this->userTokenModel,
            $this->tokenGen,
        );
    }

    public function testValidateResetTokenReturnsInvalidWhenTokenIsEmpty(): void
    {
        $this->tokenGen
            ->expects($this->never())
            ->method('hashToken');

        $result = $this->service->validateResetToken('   ');

        $this->assertSame([
            'ok'    => false,
            'error' => ErrorCode::AUTH_PASSWORD_RESET_TOKEN_INVALID,
        ], $result);
    }

    public function testValidateResetTokenReturnsInvalidWhenContextIsNotFound(): void
    {
        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->with('valid-token')
            ->willReturn(str_repeat('a', 32));

        $this->userTokenModel
            ->expects($this->once())
            ->method('findPasswordResetContextByHash')
            ->with(str_repeat('a', 32))
            ->willReturn(null);

        $result = $this->service->validateResetToken('valid-token');

        $this->assertSame([
            'ok'    => false,
            'error' => ErrorCode::AUTH_PASSWORD_RESET_TOKEN_INVALID,
        ], $result);
    }

    public function testValidateResetTokenReturnsInvalidWhenTokenIsExpired(): void
    {
        $ctx = [
            'user_id'    => 42,
            'used'       => 0,
            'is_expired' => true,
        ];

        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->with('valid-token')
            ->willReturn(str_repeat('b', 32));

        $this->userTokenModel
            ->expects($this->once())
            ->method('findPasswordResetContextByHash')
            ->with(str_repeat('b', 32))
            ->willReturn($ctx);

        $result = $this->service->validateResetToken('valid-token');

        $this->assertSame([
            'ok'    => false,
            'error' => ErrorCode::AUTH_PASSWORD_RESET_TOKEN_INVALID,
        ], $result);
    }

    public function testValidateResetTokenReturnsInvalidWhenTokenIsUsed(): void
    {
        $ctx = [
            'user_id'    => 42,
            'used'       => 1,
            'is_expired' => false,
        ];

        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->with('valid-token')
            ->willReturn(str_repeat('c', 32));

        $this->userTokenModel
            ->expects($this->once())
            ->method('findPasswordResetContextByHash')
            ->with(str_repeat('c', 32))
            ->willReturn($ctx);

        $result = $this->service->validateResetToken('valid-token');

        $this->assertSame([
            'ok'    => false,
            'error' => ErrorCode::AUTH_PASSWORD_RESET_TOKEN_INVALID,
        ], $result);
    }

    public function testValidateResetTokenReturnsContextWhenTokenIsValid(): void
    {
        $ctx = [
            'user_id'    => 42,
            'used'       => 0,
            'is_expired' => false,
        ];

        $hash = str_repeat('d', 32);

        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->with('valid-token')
            ->willReturn($hash);

        $this->userTokenModel
            ->expects($this->once())
            ->method('findPasswordResetContextByHash')
            ->with($hash)
            ->willReturn($ctx);

        $result = $this->service->validateResetToken('valid-token');

        $this->assertTrue($result['ok']);
        $this->assertSame($ctx, $result['context']);
        $this->assertSame($hash, $result['hash']);
    }

    public function testResetPasswordReturnsErrorWhenTokenIsInvalid(): void
    {
        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->with('invalid-token')
            ->willReturn(str_repeat('e', 32));

        $this->userTokenModel
            ->expects($this->once())
            ->method('findPasswordResetContextByHash')
            ->with(str_repeat('e', 32))
            ->willReturn(null);

        $this->validator
            ->expects($this->never())
            ->method('validatePasswordField');

        $result = $this->service->resetPassword(
            'invalid-token',
            'Password123!',
            'Password123!'
        );

        $this->assertSame([
            'error' => ErrorCode::AUTH_PASSWORD_RESET_TOKEN_INVALID,
        ], $result);
    }

    public function testResetPasswordReturnsErrorsWhenPasswordValidationFails(): void
    {
        $ctx = [
            'user_id'    => 42,
            'used'       => 0,
            'is_expired' => false,
        ];

        $hash = str_repeat('f', 32);

        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->with('valid-token')
            ->willReturn($hash);

        $this->userTokenModel
            ->expects($this->once())
            ->method('findPasswordResetContextByHash')
            ->with($hash)
            ->willReturn($ctx);

        $this->validator
            ->expects($this->once())
            ->method('validatePasswordField')
            ->with('weak')
            ->willReturn(ErrorCode::AUTH_PASSWORD_RESET_PASSWORD_INVALID);

        $this->userTokenModel
            ->expects($this->never())
            ->method('consumePasswordResetTokenAndUpdatePassword');

        $result = $this->service->resetPassword(
            'valid-token',
            'weak',
            'weak'
        );

        $this->assertSame([
            'errors' => [
                'password' => ErrorCode::AUTH_PASSWORD_RESET_PASSWORD_INVALID,
            ],
        ], $result);
    }

    public function testResetPasswordReturnsErrorsWhenConfirmationDoesNotMatch(): void
    {
        $ctx = [
            'user_id'    => 42,
            'used'       => 0,
            'is_expired' => false,
        ];

        $hash = str_repeat('g', 32);

        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->with('valid-token')
            ->willReturn($hash);

        $this->userTokenModel
            ->expects($this->once())
            ->method('findPasswordResetContextByHash')
            ->with($hash)
            ->willReturn($ctx);

        $this->validator
            ->expects($this->once())
            ->method('validatePasswordField')
            ->with('Password123!')
            ->willReturn(null);

        $this->userTokenModel
            ->expects($this->never())
            ->method('consumePasswordResetTokenAndUpdatePassword');

        $result = $this->service->resetPassword(
            'valid-token',
            'Password123!',
            'Different123!'
        );

        $this->assertSame([
            'errors' => [
                'password_confirm' => ErrorCode::AUTH_PASSWORD_RESET_CONFIRM_MISMATCH,
            ],
        ], $result);
    }

    public function testResetPasswordReturnsInvalidTokenWhenConsumeFails(): void
    {
        $ctx = [
            'user_id'    => 42,
            'used'       => 0,
            'is_expired' => false,
        ];

        $hash = str_repeat('h', 32);

        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->with('valid-token')
            ->willReturn($hash);

        $this->userTokenModel
            ->expects($this->once())
            ->method('findPasswordResetContextByHash')
            ->with($hash)
            ->willReturn($ctx);

        $this->validator
            ->expects($this->once())
            ->method('validatePasswordField')
            ->with('Password123!')
            ->willReturn(null);

        $this->userTokenModel
            ->expects($this->once())
            ->method('consumePasswordResetTokenAndUpdatePassword')
            ->with(
                $hash,
                $this->callback(function (mixed $hashPassword): bool {
                    return is_string($hashPassword)
                        && $hashPassword !== ''
                        && password_verify('Password123!', $hashPassword);
                })
            )
            ->willReturn(false);

        $result = $this->service->resetPassword(
            'valid-token',
            'Password123!',
            'Password123!'
        );

        $this->assertSame([
            'error' => ErrorCode::AUTH_PASSWORD_RESET_TOKEN_INVALID,
        ], $result);
    }

    public function testResetPasswordReturnsSuccessWhenEverythingSucceeds(): void
    {
        $ctx = [
            'user_id'    => 42,
            'used'       => 0,
            'is_expired' => false,
        ];

        $hash = str_repeat('i', 32);

        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->with('valid-token')
            ->willReturn($hash);

        $this->userTokenModel
            ->expects($this->once())
            ->method('findPasswordResetContextByHash')
            ->with($hash)
            ->willReturn($ctx);

        $this->validator
            ->expects($this->once())
            ->method('validatePasswordField')
            ->with('Password123!')
            ->willReturn(null);

        $this->userTokenModel
            ->expects($this->once())
            ->method('consumePasswordResetTokenAndUpdatePassword')
            ->with(
                $hash,
                $this->callback(function (mixed $hashPassword): bool {
                    return is_string($hashPassword)
                        && $hashPassword !== ''
                        && password_verify('Password123!', $hashPassword);
                })
            )
            ->willReturn(true);

        $result = $this->service->resetPassword(
            'valid-token',
            'Password123!',
            'Password123!'
        );

        $this->assertSame([], $result);
    }
}
