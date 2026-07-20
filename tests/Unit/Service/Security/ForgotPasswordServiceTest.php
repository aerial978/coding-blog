<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Security;

use App\Core\ErrorCode;
use App\Core\Mail\MailerInterface;
use App\Model\Contract\UserModelInterface;
use App\Model\Contract\UserTokenModelInterface;
use App\Model\Entity\UserEntity;
use App\Security\Contract\EmailQuotaServiceInterface;
use App\Security\Contract\TokenGeneratorInterface;
use App\Security\EmailQuotaService;
use App\Service\Security\ForgotPasswordService;
use App\Validation\Contract\FormValidatorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ForgotPasswordServiceTest extends TestCase
{
    private FormValidatorInterface&MockObject $validator;
    private UserModelInterface&MockObject $userModel;
    private UserTokenModelInterface&MockObject $userTokenModel;
    private TokenGeneratorInterface&MockObject $tokenGen;
    private MailerInterface&MockObject $mailer;
    private EmailQuotaServiceInterface&MockObject $quotaService;

    private ForgotPasswordService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REMOTE_ADDR']     = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $this->validator      = $this->createMock(FormValidatorInterface::class);
        $this->userModel      = $this->createMock(UserModelInterface::class);
        $this->userTokenModel = $this->createMock(UserTokenModelInterface::class);
        $this->tokenGen       = $this->createMock(TokenGeneratorInterface::class);
        $this->mailer         = $this->createMock(MailerInterface::class);
        $this->quotaService   = $this->createMock(EmailQuotaServiceInterface::class);

        $this->service = new ForgotPasswordService(
            $this->validator,
            $this->userModel,
            $this->userTokenModel,
            $this->tokenGen,
            $this->mailer,
            $this->quotaService,
        );
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
        parent::tearDown();
    }

    private function makeUser(
        int $id = 42,
        string $email = 'john@example.com',
        string $username = 'john'
    ): UserEntity {
        $user = new UserEntity();
        $user->setUserId($id);
        $user->setEmail($email);
        $user->setUsername($username);

        return $user;
    }

    public function testRequestResetReturnsNeutralWhenIdentifierIsEmpty(): void
    {
        $this->validator
            ->expects($this->never())
            ->method('validateEmailField');

        $this->userModel
            ->expects($this->never())
            ->method('findAuthByEmail');

        $result = $this->service->requestReset('   ');

        $this->assertSame([], $result);
    }

    public function testRequestResetReturnsNeutralWhenEmailFormatIsInvalid(): void
    {
        $this->validator
            ->expects($this->once())
            ->method('validateEmailField')
            ->with('john@example.com')
            ->willReturn(ErrorCode::AUTH_EMAIL_INVALID);

        $this->userModel
            ->expects($this->never())
            ->method('findAuthByEmail');

        $result = $this->service->requestReset('john@example.com');

        $this->assertSame([], $result);
    }

    public function testRequestResetReturnsNeutralWhenUserIsNotFoundByEmail(): void
    {
        $this->validator
            ->expects($this->once())
            ->method('validateEmailField')
            ->with('john@example.com')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findAuthByEmail')
            ->with('john@example.com')
            ->willReturn(null);

        $result = $this->service->requestReset('john@example.com');

        $this->assertSame([], $result);
    }

    public function testRequestResetReturnsNeutralWhenUserIsNotFoundByUsername(): void
    {
        $this->validator
            ->expects($this->never())
            ->method('validateEmailField');

        $this->userModel
            ->expects($this->once())
            ->method('findAuthByUsername')
            ->with('john')
            ->willReturn(null);

        $result = $this->service->requestReset('john');

        $this->assertSame([], $result);
    }

    public function testRequestResetReturnsNeutralWhenUserBasicsAreInvalid(): void
    {
        $user = $this->makeUser(id: 0, email: '', username: 'john');

        $this->validator
            ->expects($this->once())
            ->method('validateEmailField')
            ->with('john@example.com')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findAuthByEmail')
            ->with('john@example.com')
            ->willReturn($user);

        $this->userTokenModel
            ->expects($this->never())
            ->method('hasActiveUnusedPasswordResetToken');

        $result = $this->service->requestReset('john@example.com');

        $this->assertSame([], $result);
    }

    public function testRequestResetReturnsNeutralWhenActiveResetTokenAlreadyExists(): void
    {
        $user = $this->makeUser();

        $this->validator
            ->expects($this->once())
            ->method('validateEmailField')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findAuthByEmail')
            ->willReturn($user);

        $this->userTokenModel
            ->expects($this->once())
            ->method('hasActiveUnusedPasswordResetToken')
            ->with(42)
            ->willReturn(true);

        $this->quotaService
            ->expects($this->never())
            ->method('checkQuota');

        $result = $this->service->requestReset('john@example.com');

        $this->assertSame([], $result);
    }

    public function testRequestResetReturnsNeutralWhenQuotaIsExceeded(): void
    {
        $user = $this->makeUser();

        $this->validator
            ->expects($this->once())
            ->method('validateEmailField')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findAuthByEmail')
            ->willReturn($user);

        $this->userTokenModel
            ->expects($this->once())
            ->method('hasActiveUnusedPasswordResetToken')
            ->with(42)
            ->willReturn(false);

        $this->quotaService
            ->expects($this->once())
            ->method('checkQuota')
            ->with(EmailQuotaService::TYPE_PASSWORD_RESET, 'john@example.com')
            ->willReturn([
                'allowed' => false,
                'reason'  => 'daily_limit',
            ]);

        $this->tokenGen
            ->expects($this->never())
            ->method('generateUrlSafeToken');

        $result = $this->service->requestReset('john@example.com');

        $this->assertSame([], $result);
    }

    public function testRequestResetReturnsNeutralWhenTokenHashLengthIsInvalid(): void
    {
        $user = $this->makeUser();

        $this->validator
            ->expects($this->once())
            ->method('validateEmailField')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findAuthByEmail')
            ->willReturn($user);

        $this->userTokenModel
            ->expects($this->once())
            ->method('hasActiveUnusedPasswordResetToken')
            ->willReturn(false);

        $this->quotaService
            ->expects($this->once())
            ->method('checkQuota')
            ->willReturn(['allowed' => true]);

        $this->tokenGen
            ->expects($this->once())
            ->method('generateUrlSafeToken')
            ->with(32)
            ->willReturn('plain-reset-token');

        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->with('plain-reset-token')
            ->willReturn('short-hash');

        $this->userTokenModel
            ->expects($this->never())
            ->method('createPasswordResetToken');

        $result = $this->service->requestReset('john@example.com');

        $this->assertSame([], $result);
    }

    public function testRequestResetReturnsNeutralWhenPersistFails(): void
    {
        $user = $this->makeUser();

        $this->validator
            ->expects($this->once())
            ->method('validateEmailField')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findAuthByEmail')
            ->willReturn($user);

        $this->userTokenModel
            ->expects($this->once())
            ->method('hasActiveUnusedPasswordResetToken')
            ->willReturn(false);

        $this->quotaService
            ->expects($this->once())
            ->method('checkQuota')
            ->willReturn(['allowed' => true]);

        $this->tokenGen
            ->expects($this->once())
            ->method('generateUrlSafeToken')
            ->willReturn('plain-reset-token');

        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->willReturn(str_repeat('a', 32));

        $this->userTokenModel
            ->expects($this->once())
            ->method('createPasswordResetToken')
            ->with(
                42,
                str_repeat('a', 32),
                $this->isInstanceOf(\DateTimeImmutable::class)
            )
            ->willReturn(false);

        $this->mailer
            ->expects($this->never())
            ->method('send');

        $result = $this->service->requestReset('john@example.com');

        $this->assertSame([], $result);
    }

    public function testRequestResetInvalidatesTokenWhenMailerReturnsFalse(): void
    {
        $user = $this->makeUser();

        $this->validator
            ->expects($this->once())
            ->method('validateEmailField')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findAuthByEmail')
            ->willReturn($user);

        $this->userTokenModel
            ->expects($this->once())
            ->method('hasActiveUnusedPasswordResetToken')
            ->willReturn(false);

        $this->quotaService
            ->expects($this->once())
            ->method('checkQuota')
            ->willReturn(['allowed' => true]);

        $this->tokenGen
            ->expects($this->once())
            ->method('generateUrlSafeToken')
            ->willReturn('plain-reset-token');

        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->willReturn(str_repeat('b', 32));

        $this->userTokenModel
            ->expects($this->once())
            ->method('createPasswordResetToken')
            ->willReturn(true);

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->willReturn(false);

        $this->userTokenModel
            ->expects($this->once())
            ->method('invalidatePasswordResetToken')
            ->with(42);

        $this->quotaService
            ->expects($this->never())
            ->method('recordEvent');

        $result = $this->service->requestReset('john@example.com');

        $this->assertSame([], $result);
    }

    public function testRequestResetRecordsQuotaEventWhenFlowSucceeds(): void
    {
        $user = $this->makeUser();

        $this->validator
            ->expects($this->once())
            ->method('validateEmailField')
            ->with('john@example.com')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findAuthByEmail')
            ->with('john@example.com')
            ->willReturn($user);

        $this->userTokenModel
            ->expects($this->once())
            ->method('hasActiveUnusedPasswordResetToken')
            ->with(42)
            ->willReturn(false);

        $this->quotaService
            ->expects($this->once())
            ->method('checkQuota')
            ->with(EmailQuotaService::TYPE_PASSWORD_RESET, 'john@example.com')
            ->willReturn(['allowed' => true]);

        $this->tokenGen
            ->expects($this->once())
            ->method('generateUrlSafeToken')
            ->with(32)
            ->willReturn('plain-reset-token');

        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->with('plain-reset-token')
            ->willReturn(str_repeat('c', 32));

        $this->userTokenModel
            ->expects($this->once())
            ->method('createPasswordResetToken')
            ->with(
                42,
                str_repeat('c', 32),
                $this->isInstanceOf(\DateTimeImmutable::class)
            )
            ->willReturn(true);

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with(
                'john@example.com',
                'john',
                'Réinitialisation de votre mot de passe',
                'password-reset-request.html',
                $this->callback(function (array $vars): bool {
                    return $vars['username'] === 'john'
                        && is_string($vars['link'])
                        && str_contains($vars['link'], '/reset-password?token=plain-reset-token');
                })
            )
            ->willReturn(true);

        $this->quotaService
            ->expects($this->once())
            ->method('recordEvent')
            ->with(
                'john@example.com',
                EmailQuotaService::TYPE_PASSWORD_RESET,
                42,
                '127.0.0.1',
                'PHPUnit'
            );

        $result = $this->service->requestReset('john@example.com');

        $this->assertSame([], $result);
    }

    public function testRequestResetReturnsNeutralWhenUnexpectedThrowableOccurs(): void
    {
        $this->validator
            ->expects($this->once())
            ->method('validateEmailField')
            ->with('john@example.com')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findauthByEmail')
            ->with('john@example.com')
            ->willThrowException(new \RuntimeException('unexpected'));

        $result = $this->service->requestReset('john@example.com');

        $this->assertSame([], $result);
    }
}
