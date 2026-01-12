<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Security;

use App\Core\ErrorCode;
use App\Core\Mail\MailerInterface;
use App\Model\Contract\UserModelInterface;
use App\Model\Contract\UserTokenModelInterface;
use App\Security\Contract\EmailQuotaServiceInterface;
use App\Security\Contract\TokenGeneratorInterface;
use App\Security\EmailQuotaService;
use App\Service\Security\ConfirmationResendService;
use App\Validation\Contract\FormValidatorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ConfirmationResendServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REMOTE_ADDR']     = '1.2.3.4';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
    }

    public function testResendReturnsErrorWhenEmailInvalid(): void
    {
        $validator = $this->createMock(FormValidatorInterface::class);
        $validator->method('validateEmailField')->willReturn('email_invalid');

        $userModel = $this->createMock(UserModelInterface::class);
        $userModel->expects(self::never())->method('findOneByEmail');

        $service = $this->makeService(validator: $validator, userModel: $userModel);

        $res = $service->resend('not-an-email');

        self::assertSame(['error' => 'email_invalid'], $res);
    }

    public function testResendIsSilentWhenUnknownUser(): void
    {
        $validator = $this->createMock(FormValidatorInterface::class);
        $validator->method('validateEmailField')->willReturn(null);

        $userModel = $this->createMock(UserModelInterface::class);
        $userModel->method('findOneByEmail')->willReturn(null);

        $quota = $this->createMock(EmailQuotaServiceInterface::class);
        $quota->expects(self::never())->method('checkQuota');
        $quota->expects(self::never())->method('recordEvent');

        $service = $this->makeService(validator: $validator, userModel: $userModel, quota: $quota);

        $res = $service->resend('bob@example.com');

        self::assertSame([], $res);
    }

    public function testResendIsSilentWhenQuotaExceeded(): void
    {
        $validator = $this->createMock(FormValidatorInterface::class);
        $validator->method('validateEmailField')->willReturn(null);

        $user = new FakeUser(userId: 10, username: 'bob', status: 'pending');

        $userModel = $this->createMock(UserModelInterface::class);
        $userModel->method('findOneByEmail')->willReturn($user);

        $quota = $this->createMock(EmailQuotaServiceInterface::class);
        $quota->method('checkQuota')->willReturn(['allowed' => false, 'reason' => 'hour_quota_exceeded']);
        $quota->expects(self::never())->method('recordEvent');

        $service = $this->makeService(validator: $validator, userModel: $userModel, quota: $quota);

        $res = $service->resend('bob@example.com');

        // anti-énumération: succès silencieux
        self::assertSame([], $res);
    }

    public function testResendReturnsAlreadyConfirmedWhenUserIsActive(): void
    {
        $validator = $this->createMock(FormValidatorInterface::class);
        $validator->method('validateEmailField')->willReturn(null);

        $user = new FakeUser(userId: 10, username: 'bob', status: 'active');

        $userModel = $this->createMock(UserModelInterface::class);
        $userModel->method('findOneByEmail')->willReturn($user);

        $quota = $this->createMock(EmailQuotaServiceInterface::class);
        $quota->method('checkQuota')->willReturn(['allowed' => true, 'reason' => null]);
        $quota->expects(self::never())->method('recordEvent');

        $service = $this->makeService(validator: $validator, userModel: $userModel, quota: $quota);

        $res = $service->resend('bob@example.com');

        self::assertSame(['error' => ErrorCode::AUTH_ALREADY_CONFIRMED], $res);
    }

    public function testResendReturnsTechnicalErrorWhenTokenHashInvalidLength(): void
    {
        $validator = $this->createMock(FormValidatorInterface::class);
        $validator->method('validateEmailField')->willReturn(null);

        $user = new FakeUser(userId: 10, username: 'bob', status: 'pending');

        $userModel = $this->createMock(UserModelInterface::class);
        $userModel->method('findOneByEmail')->willReturn($user);

        $quota = $this->createMock(EmailQuotaServiceInterface::class);
        $quota->method('checkQuota')->willReturn(['allowed' => true, 'reason' => null]);

        $tokenGen = $this->createMock(TokenGeneratorInterface::class);
        $tokenGen->method('generateUrlSafeToken')->willReturn('clear-token');
        $tokenGen->method('hashToken')->willReturn('too-short'); // strlen != 32

        $tokenModel = $this->createMock(UserTokenModelInterface::class);
        $tokenModel->expects(self::never())->method('createConfirmationToken');

        $service = $this->makeService(
            validator: $validator,
            userModel: $userModel,
            quota: $quota,
            tokenGen: $tokenGen,
            tokenModel: $tokenModel
        );

        $res = $service->resend('bob@example.com');

        self::assertSame(['error' => ErrorCode::AUTH_TECHNICAL_ERROR], $res);
    }

    public function testResendReturnsTechnicalErrorWhenPersistFails(): void
    {
        $validator = $this->createMock(FormValidatorInterface::class);
        $validator->method('validateEmailField')->willReturn(null);

        $user = new FakeUser(userId: 10, username: 'bob', status: 'pending');

        $userModel = $this->createMock(UserModelInterface::class);
        $userModel->method('findOneByEmail')->willReturn($user);

        $quota = $this->createMock(EmailQuotaServiceInterface::class);
        $quota->method('checkQuota')->willReturn(['allowed' => true, 'reason' => null]);

        $tokenGen = $this->createMock(TokenGeneratorInterface::class);
        $tokenGen->method('generateUrlSafeToken')->willReturn('clear-token');
        $tokenGen->method('hashToken')->willReturn(str_repeat('a', 32));

        $tokenModel = $this->createMock(UserTokenModelInterface::class);
        $tokenModel->method('createConfirmationToken')->willReturn(false);

        $service = $this->makeService(
            validator: $validator,
            userModel: $userModel,
            quota: $quota,
            tokenGen: $tokenGen,
            tokenModel: $tokenModel
        );

        $res = $service->resend('bob@example.com');

        self::assertSame(['error' => ErrorCode::AUTH_TECHNICAL_ERROR], $res);
    }

    public function testResendReturnsSendFailedWhenMailerThrows(): void
    {
        $validator = $this->createMock(FormValidatorInterface::class);
        $validator->method('validateEmailField')->willReturn(null);

        $user = new FakeUser(userId: 10, username: 'bob', status: 'pending');

        $userModel = $this->createMock(UserModelInterface::class);
        $userModel->method('findOneByEmail')->willReturn($user);

        $quota = $this->createMock(EmailQuotaServiceInterface::class);
        $quota->method('checkQuota')->willReturn(['allowed' => true, 'reason' => null]);

        $tokenGen = $this->createMock(TokenGeneratorInterface::class);
        $tokenGen->method('generateUrlSafeToken')->willReturn('clear-token');
        $tokenGen->method('hashToken')->willReturn(str_repeat('a', 32));

        $tokenModel = $this->createMock(UserTokenModelInterface::class);
        $tokenModel->method('createConfirmationToken')->willReturn(true);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willThrowException(new \RuntimeException('mail down'));

        $service = $this->makeService(
            validator: $validator,
            userModel: $userModel,
            quota: $quota,
            tokenGen: $tokenGen,
            tokenModel: $tokenModel,
            mailer: $mailer
        );

        $res = $service->resend('bob@example.com');

        self::assertSame(['error' => ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED], $res);
    }

    public function testResendReturnsSendFailedWhenMailerReturnsFalse(): void
    {
        $validator = $this->createMock(FormValidatorInterface::class);
        $validator->method('validateEmailField')->willReturn(null);

        $user = new FakeUser(userId: 10, username: 'bob', status: 'pending');

        $userModel = $this->createMock(UserModelInterface::class);
        $userModel->method('findOneByEmail')->willReturn($user);

        $quota = $this->createMock(EmailQuotaServiceInterface::class);
        $quota->method('checkQuota')->willReturn(['allowed' => true, 'reason' => null]);

        $tokenGen = $this->createMock(TokenGeneratorInterface::class);
        $tokenGen->method('generateUrlSafeToken')->willReturn('clear-token');
        $tokenGen->method('hashToken')->willReturn(str_repeat('a', 32));

        $tokenModel = $this->createMock(UserTokenModelInterface::class);
        $tokenModel->method('createConfirmationToken')->willReturn(true);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willReturn(false);

        $service = $this->makeService(
            validator: $validator,
            userModel: $userModel,
            quota: $quota,
            tokenGen: $tokenGen,
            tokenModel: $tokenModel,
            mailer: $mailer
        );

        $res = $service->resend('bob@example.com');

        self::assertSame(['error' => ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED], $res);
    }

    public function testHappyPathReturnsEmptyArrayAndRecordsQuotaEvent(): void
    {
        $validator = $this->createMock(FormValidatorInterface::class);
        $validator->method('validateEmailField')->willReturn(null);

        $user = new FakeUser(userId: 10, username: 'bob', status: 'pending');

        $userModel = $this->createMock(UserModelInterface::class);
        $userModel->method('findOneByEmail')->willReturn($user);

        $quota = $this->createMock(EmailQuotaServiceInterface::class);
        $quota->method('checkQuota')->willReturn(['allowed' => true, 'reason' => null]);
        $quota->expects(self::once())
            ->method('recordEvent')
            ->with(
                'bob@example.com',
                EmailQuotaService::TYPE_CONFIRM_RESEND,
                10,
                '1.2.3.4',
                'PHPUnit'
            )
            ->willReturn(true);

        $tokenGen = $this->createMock(TokenGeneratorInterface::class);
        $tokenGen->method('generateUrlSafeToken')->willReturn('clear-token');
        $tokenGen->method('hashToken')->willReturn(str_repeat('a', 32));

        $tokenModel = $this->createMock(UserTokenModelInterface::class);
        $tokenModel->expects(self::once())
            ->method('createConfirmationToken')
            ->willReturn(true);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->willReturn(true);

        $service = $this->makeService(
            validator: $validator,
            userModel: $userModel,
            quota: $quota,
            tokenGen: $tokenGen,
            tokenModel: $tokenModel,
            mailer: $mailer
        );

        $res = $service->resend('  bob@example.com  '); // test trim

        self::assertSame([], $res);
    }

    public function testResendCatchesThrowableAndReturnsTechnicalError(): void
    {
        $validator = $this->createMock(FormValidatorInterface::class);
        $validator->method('validateEmailField')->willReturn(null);

        $userModel = $this->createMock(UserModelInterface::class);
        $userModel->method('findOneByEmail')->willThrowException(new \RuntimeException('db down'));

        $service = $this->makeService(validator: $validator, userModel: $userModel);

        $res = $service->resend('bob@example.com');

        self::assertSame(['error' => ErrorCode::AUTH_TECHNICAL_ERROR], $res);
    }

    private function makeService(
        ?FormValidatorInterface $validator = null,
        ?UserModelInterface $userModel = null,
        ?UserTokenModelInterface $tokenModel = null,
        ?TokenGeneratorInterface $tokenGen = null,
        ?MailerInterface $mailer = null,
        ?EmailQuotaServiceInterface $quota = null,
    ): ConfirmationResendService {
        $validator  ??= $this->mockValidatorOk();
        $userModel  ??= $this->createMock(UserModelInterface::class);
        $tokenModel ??= $this->createMock(UserTokenModelInterface::class);
        $tokenGen   ??= $this->mockTokenGenOk();
        $mailer     ??= $this->mockMailerOk();
        $quota      ??= $this->mockQuotaOk();

        return new ConfirmationResendService(
            $validator,
            $userModel,
            $tokenModel,
            $tokenGen,
            $mailer,
            $quota
        );
    }

    /**
     * @return FormValidatorInterface&MockObject
     */
    private function mockValidatorOk(): FormValidatorInterface
    {
        /** @var FormValidatorInterface&MockObject $v */
        $v = $this->createMock(FormValidatorInterface::class);
        $v->method('validateEmailField')->willReturn(null);
        return $v;
    }

    /**
     * @return TokenGeneratorInterface&MockObject
     */
    private function mockTokenGenOk(): TokenGeneratorInterface
    {
        /** @var TokenGeneratorInterface&MockObject $t */
        $t = $this->createMock(TokenGeneratorInterface::class);
        $t->method('generateUrlSafeToken')->willReturn('clear-token');
        $t->method('hashToken')->willReturn(str_repeat('a', 32));
        return $t;
    }

    /**
     * @return MailerInterface&MockObject
     */
    private function mockMailerOk(): MailerInterface
    {
        /** @var MailerInterface&MockObject $m */
        $m = $this->createMock(MailerInterface::class);
        $m->method('send')->willReturn(true);
        return $m;
    }

    /**
     * @return EmailQuotaService&MockObject
     */
    private function mockQuotaOk(): EmailQuotaServiceInterface
    {
        /** @var EmailQuotaService&MockObject $q */
        $q = $this->createMock(EmailQuotaServiceInterface::class);
        $q->method('checkQuota')->willReturn(['allowed' => true, 'reason' => null]);
        $q->method('recordEvent')->willReturn(true);
        return $q;
    }
}

/**
 * Petit double d’entité utilisateur (évite de dépendre d’une vraie entity).
 */
final class FakeUser
{
    public function __construct(
        private int $userId,
        private ?string $username,
        private ?string $status
    ) {
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }
}
