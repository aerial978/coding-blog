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
use App\Service\Security\ConfirmationResendService;
use App\Validation\Contract\FormValidatorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ConfirmationResendServiceTest extends TestCase
{
    private FormValidatorInterface&MockObject $validator;
    private UserModelInterface&MockObject $userModel;
    private UserTokenModelInterface&MockObject $userTokenModel;
    private TokenGeneratorInterface&MockObject $tokenGen;
    private MailerInterface&MockObject $mailer;
    private EmailQuotaServiceInterface&MockObject $quotaService;

    private ConfirmationResendService $service;

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

        $this->service = new ConfirmationResendService(
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
        int|string $id = 42,
        string $email = 'john@example.com',
        string $username = 'john',
        ?string $status = 'pending'
    ): UserEntity {
        $user = new UserEntity();
        $user->setUserId($id);
        $user->setEmail($email);
        $user->setUsername($username);
        if ($status !== null) {
            $user->setStatus($status);
        }

        return $user;
    }

    public function testResendReturnsValidatorErrorWhenEmailIsInvalid(): void
    {
        $this->validator
            ->expects($this->once())
            ->method('validateEmailField')
            ->with('john@example.com')
            ->willReturn(ErrorCode::AUTH_EMAIL_INVALID);

        $this->userModel
            ->expects($this->never())
            ->method('findOneByEmail');

        $result = $this->service->resend('john@example.com');

        $this->assertSame(['error' => ErrorCode::AUTH_EMAIL_INVALID], $result);
    }

    public function testResendReturnsNeutralWhenUserIsUnknown(): void
    {
        $this->validator
            ->expects($this->once())
            ->method('validateEmailField')
            ->with('john@example.com')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByEmail')
            ->with('john@example.com')
            ->willReturn(null);

        $this->quotaService
            ->expects($this->never())
            ->method('checkQuota');

        $result = $this->service->resend('john@example.com');

        $this->assertSame([], $result);
    }

    public function testResendReturnsNeutralWhenQuotaIsExceeded(): void
    {
        $user = $this->makeUser();

        $this->validator
            ->expects($this->once())
            ->method('validateEmailField')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByEmail')
            ->willReturn($user);

        $this->quotaService
            ->expects($this->once())
            ->method('checkQuota')
            ->with(EmailQuotaService::TYPE_CONFIRM_RESEND, 'john@example.com')
            ->willReturn([
                'allowed' => false,
                'reason'  => 'daily_limit',
            ]);

        $this->tokenGen
            ->expects($this->never())
            ->method('generateUrlSafeToken');

        $result = $this->service->resend('john@example.com');

        $this->assertSame([], $result);
    }

    public function testResendReturnsAlreadyConfirmedWhenUserIsActive(): void
    {
        $user = $this->makeUser(status: 'active');

        $this->validator
            ->expects($this->once())
            ->method('validateEmailField')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByEmail')
            ->willReturn($user);

        $this->quotaService
            ->expects($this->once())
            ->method('checkQuota')
            ->willReturn(['allowed' => true]);

        $this->tokenGen
            ->expects($this->never())
            ->method('generateUrlSafeToken');

        $result = $this->service->resend('john@example.com');

        $this->assertSame(['error' => ErrorCode::AUTH_ALREADY_CONFIRMED], $result);
    }

    public function testResendReturnsTechnicalErrorWhenTokenHashLengthIsInvalid(): void
    {
        $user = $this->makeUser(status: 'pending');

        $this->validator
            ->expects($this->once())
            ->method('validateEmailField')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByEmail')
            ->willReturn($user);

        $this->quotaService
            ->expects($this->once())
            ->method('checkQuota')
            ->willReturn(['allowed' => true]);

        $this->tokenGen
            ->expects($this->once())
            ->method('generateUrlSafeToken')
            ->with(32)
            ->willReturn('plain-token');

        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->with('plain-token')
            ->willReturn('short-hash');

        $this->userTokenModel
            ->expects($this->never())
            ->method('createConfirmationToken');

        $result = $this->service->resend('john@example.com');

        $this->assertSame(['error' => ErrorCode::AUTH_TECHNICAL_ERROR], $result);
    }

    public function testResendReturnsTechnicalErrorWhenPersistFails(): void
    {
        $user = $this->makeUser(status: 'pending');

        $this->validator
            ->expects($this->once())
            ->method('validateEmailField')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByEmail')
            ->willReturn($user);

        $this->quotaService
            ->expects($this->once())
            ->method('checkQuota')
            ->willReturn(['allowed' => true]);

        $this->tokenGen
            ->expects($this->once())
            ->method('generateUrlSafeToken')
            ->willReturn('plain-token');

        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->willReturn(str_repeat('a', 32));

        $this->userTokenModel
            ->expects($this->once())
            ->method('createConfirmationToken')
            ->with(
                42,
                str_repeat('a', 32),
                $this->isInstanceOf(\DateTimeImmutable::class)
            )
            ->willReturn(false);

        $this->mailer
            ->expects($this->never())
            ->method('send');

        $result = $this->service->resend('john@example.com');

        $this->assertSame(['error' => ErrorCode::AUTH_TECHNICAL_ERROR], $result);
    }

    public function testResendReturnsEmailSendFailedWhenMailerReturnsFalse(): void
    {
        $user = $this->makeUser(status: 'pending');

        $this->validator
            ->expects($this->once())
            ->method('validateEmailField')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByEmail')
            ->willReturn($user);

        $this->quotaService
            ->expects($this->once())
            ->method('checkQuota')
            ->willReturn(['allowed' => true]);

        $this->tokenGen
            ->expects($this->once())
            ->method('generateUrlSafeToken')
            ->willReturn('plain-token');

        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->willReturn(str_repeat('b', 32));

        $this->userTokenModel
            ->expects($this->once())
            ->method('createConfirmationToken')
            ->willReturn(true);

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->willReturn(false);

        $this->quotaService
            ->expects($this->never())
            ->method('recordEvent');

        $result = $this->service->resend('john@example.com');

        $this->assertSame(['error' => ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED], $result);
    }

    public function testResendRecordsEventWhenFlowSucceeds(): void
    {
        $user = $this->makeUser(status: 'pending');

        $this->validator
            ->expects($this->once())
            ->method('validateEmailField')
            ->with('john@example.com')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByEmail')
            ->with('john@example.com')
            ->willReturn($user);

        $this->quotaService
            ->expects($this->once())
            ->method('checkQuota')
            ->with(EmailQuotaService::TYPE_CONFIRM_RESEND, 'john@example.com')
            ->willReturn(['allowed' => true]);

        $this->tokenGen
            ->expects($this->once())
            ->method('generateUrlSafeToken')
            ->with(32)
            ->willReturn('plain-token');

        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->with('plain-token')
            ->willReturn(str_repeat('c', 32));

        $this->userTokenModel
            ->expects($this->once())
            ->method('createConfirmationToken')
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
                'Confirmation de votre compte',
                'confirmation.html',
                $this->callback(function (array $vars): bool {
                    return $vars['username'] === 'john'
                        && is_string($vars['link'])
                        && str_contains($vars['link'], '/confirm-account?token=plain-token');
                })
            )
            ->willReturn(true);

        $this->quotaService
            ->expects($this->once())
            ->method('recordEvent')
            ->with(
                'john@example.com',
                EmailQuotaService::TYPE_CONFIRM_RESEND,
                42,
                '127.0.0.1',
                'PHPUnit'
            );

        $result = $this->service->resend('john@example.com');

        $this->assertSame([], $result);
    }

    public function testResendReturnsTechnicalErrorWhenUnexpectedThrowableOccurs(): void
    {
        $this->validator
            ->expects($this->once())
            ->method('validateEmailField')
            ->with('john@example.com')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByEmail')
            ->with('john@example.com')
            ->willThrowException(new \RuntimeException('unexpected'));

        $result = $this->service->resend('john@example.com');

        $this->assertSame(['error' => ErrorCode::AUTH_TECHNICAL_ERROR], $result);
    }
}
