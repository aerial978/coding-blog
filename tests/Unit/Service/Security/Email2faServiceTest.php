<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Security;

use App\Core\Mail\MailerInterface;
use App\Model\Contract\Email2faChallengeModelInterface;
use App\Model\Entity\Email2faChallengeEntity;
use App\Model\Entity\UserEntity;
use App\Service\Security\Email2faService;
use PHPUnit\Framework\TestCase;

final class Email2faServiceTest extends TestCase
{
    public function testGenerateAndSendCodeReturnsFalseWhenUserIsIncomplete(): void
    {
        $challengeModel = $this->createMock(Email2faChallengeModelInterface::class);
        $mailer         = $this->createMock(MailerInterface::class);

        $service = new Email2faService($challengeModel, $mailer);

        $user = new UserEntity();

        self::assertFalse($service->generateAndSendCode($user));
    }

    public function testGenerateAndSendCodeReturnsFalseWhenChallengeCreationFails(): void
    {
        $challengeModel = $this->createMock(Email2faChallengeModelInterface::class);
        $mailer         = $this->createMock(MailerInterface::class);

        $user = (new UserEntity())
            ->setUserId(42)
            ->setUsername('Michael')
            ->setEmail('michael@example.com');

        $challengeModel
            ->expects(self::once())
            ->method('invalidateActiveChallengesForUser')
            ->with(42);

        $challengeModel
            ->expects(self::once())
            ->method('createChallenge')
            ->with(
                42,
                self::isType('string'),
                self::isType('string')
            )
            ->willReturn(0);

        $mailer
            ->expects(self::never())
            ->method('sendEmail2faCode');

        $service = new Email2faService($challengeModel, $mailer);

        self::assertFalse($service->generateAndSendCode($user));
    }

    public function testGenerateAndSendCodeCreatesChallengeAndSendsEmail(): void
    {
        $challengeModel = $this->createMock(Email2faChallengeModelInterface::class);
        $mailer         = $this->createMock(MailerInterface::class);

        $user = (new UserEntity())
            ->setUserId(42)
            ->setUsername('Michael')
            ->setEmail('michael@example.com');

        $challengeModel
            ->expects(self::once())
            ->method('invalidateActiveChallengesForUser')
            ->with(42);

        $challengeModel
            ->expects(self::once())
            ->method('createChallenge')
            ->with(
                42,
                self::callback(static fn (string $hash): bool => strlen($hash) === 32),
                self::callback(static fn (string $expiresAt): bool => $expiresAt !== '')
            )
            ->willReturn(123);

        $mailer
            ->expects(self::once())
            ->method('sendEmail2faCode')
            ->with(
                'michael@example.com',
                'Michael',
                self::callback(static fn (string $code): bool => preg_match('/^\d{6}$/', $code) === 1),
                10
            )
            ->willReturn(true);

        $service = new Email2faService($challengeModel, $mailer);

        self::assertTrue($service->generateAndSendCode($user));
    }

    public function testResendCodeDelegatesToGenerateAndSendCode(): void
    {
        $challengeModel = $this->createMock(Email2faChallengeModelInterface::class);
        $mailer         = $this->createMock(MailerInterface::class);

        $user = (new UserEntity())
            ->setUserId(42)
            ->setUsername('Michael')
            ->setEmail('michael@example.com');

        $challengeModel
            ->expects(self::once())
            ->method('invalidateActiveChallengesForUser')
            ->with(42);

        $challengeModel
            ->expects(self::once())
            ->method('createChallenge')
            ->willReturn(123);

        $mailer
            ->expects(self::once())
            ->method('sendEmail2faCode')
            ->willReturn(true);

        $service = new Email2faService($challengeModel, $mailer);

        self::assertTrue($service->resendCode($user));
    }

    public function testVerifyCodeReturnsInvalidForInvalidUserId(): void
    {
        $challengeModel = $this->createMock(Email2faChallengeModelInterface::class);
        $mailer         = $this->createMock(MailerInterface::class);

        $challengeModel
            ->expects(self::never())
            ->method('findActiveChallengeByUserId');

        $service = new Email2faService($challengeModel, $mailer);

        self::assertSame(
            Email2faService::VERIFY_INVALID,
            $service->verifyCode(0, '123456')
        );
    }

    public function testVerifyCodeReturnsInvalidForInvalidCodeFormat(): void
    {
        $challengeModel = $this->createMock(Email2faChallengeModelInterface::class);
        $mailer         = $this->createMock(MailerInterface::class);

        $challengeModel
            ->expects(self::never())
            ->method('findActiveChallengeByUserId');

        $service = new Email2faService($challengeModel, $mailer);

        self::assertSame(
            Email2faService::VERIFY_INVALID,
            $service->verifyCode(42, 'abc')
        );
    }

    public function testVerifyCodeReturnsInvalidWhenNoActiveChallengeExists(): void
    {
        $challengeModel = $this->createMock(Email2faChallengeModelInterface::class);
        $mailer         = $this->createMock(MailerInterface::class);

        $challengeModel
            ->expects(self::once())
            ->method('findActiveChallengeByUserId')
            ->with(42)
            ->willReturn(null);

        $service = new Email2faService($challengeModel, $mailer);

        self::assertSame(
            Email2faService::VERIFY_INVALID,
            $service->verifyCode(42, '123456')
        );
    }

    public function testVerifyCodeReturnsTooManyAttemptsWhenChallengeAlreadyReachedLimit(): void
    {
        $challengeModel = $this->createMock(Email2faChallengeModelInterface::class);
        $mailer         = $this->createMock(MailerInterface::class);

        $challenge = (new Email2faChallengeEntity())
            ->setId(123)
            ->setUserId(42)
            ->setCodeHash(hash('sha256', '123456', true))
            ->setAttempts(5);

        $challengeModel
            ->expects(self::once())
            ->method('findActiveChallengeByUserId')
            ->with(42)
            ->willReturn($challenge);

        $challengeModel
            ->expects(self::once())
            ->method('invalidateActiveChallengesForUser')
            ->with(42);

        $service = new Email2faService($challengeModel, $mailer);

        self::assertSame(
            Email2faService::VERIFY_TOO_MANY_ATTEMPTS,
            $service->verifyCode(42, '123456')
        );
    }

    public function testVerifyCodeReturnsInvalidAndIncrementsAttemptsWhenCodeIsWrong(): void
    {
        $challengeModel = $this->createMock(Email2faChallengeModelInterface::class);
        $mailer         = $this->createMock(MailerInterface::class);

        $challenge = (new Email2faChallengeEntity())
            ->setId(123)
            ->setUserId(42)
            ->setCodeHash(hash('sha256', '123456', true))
            ->setAttempts(1);

        $challengeModel
            ->expects(self::once())
            ->method('findActiveChallengeByUserId')
            ->with(42)
            ->willReturn($challenge);

        $challengeModel
            ->expects(self::once())
            ->method('incrementAttempts')
            ->with(123);

        $challengeModel
            ->expects(self::never())
            ->method('markAsUsed');

        $service = new Email2faService($challengeModel, $mailer);

        self::assertSame(
            Email2faService::VERIFY_INVALID,
            $service->verifyCode(42, '654321')
        );
    }

    public function testVerifyCodeReturnsTooManyAttemptsWhenWrongCodeReachesLimit(): void
    {
        $challengeModel = $this->createMock(Email2faChallengeModelInterface::class);
        $mailer         = $this->createMock(MailerInterface::class);

        $challenge = (new Email2faChallengeEntity())
            ->setId(123)
            ->setUserId(42)
            ->setCodeHash(hash('sha256', '123456', true))
            ->setAttempts(4);

        $challengeModel
            ->expects(self::once())
            ->method('findActiveChallengeByUserId')
            ->with(42)
            ->willReturn($challenge);

        $challengeModel
            ->expects(self::once())
            ->method('incrementAttempts')
            ->with(123);

        $challengeModel
            ->expects(self::once())
            ->method('invalidateActiveChallengesForUser')
            ->with(42);

        $service = new Email2faService($challengeModel, $mailer);

        self::assertSame(
            Email2faService::VERIFY_TOO_MANY_ATTEMPTS,
            $service->verifyCode(42, '654321')
        );
    }

    public function testVerifyCodeReturnsSuccessAndMarksChallengeAsUsedWhenCodeIsCorrect(): void
    {
        $challengeModel = $this->createMock(Email2faChallengeModelInterface::class);
        $mailer         = $this->createMock(MailerInterface::class);

        $challenge = (new Email2faChallengeEntity())
            ->setId(123)
            ->setUserId(42)
            ->setCodeHash(hash('sha256', '123456', true))
            ->setAttempts(0);

        $challengeModel
            ->expects(self::once())
            ->method('findActiveChallengeByUserId')
            ->with(42)
            ->willReturn($challenge);

        $challengeModel
            ->expects(self::once())
            ->method('markAsUsed')
            ->with(123);

        $challengeModel
            ->expects(self::never())
            ->method('incrementAttempts');

        $service = new Email2faService($challengeModel, $mailer);

        self::assertSame(
            Email2faService::VERIFY_SUCCESS,
            $service->verifyCode(42, '123456')
        );
    }
}
