<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Core\Mail\MailerInterface;
use App\Model\Contract\Email2faChallengeModelInterface;
use App\Model\Entity\Email2faChallengeEntity;
use App\Model\Entity\UserEntity;
use App\Service\Security\Contract\Email2faServiceInterface;
use DateTimeImmutable;

final class Email2faService implements Email2faServiceInterface
{
    public const VERIFY_SUCCESS           = 'success';
    public const VERIFY_INVALID           = 'invalid';
    public const VERIFY_TOO_MANY_ATTEMPTS = 'too_many_attempts';

    private const CODE_MIN         = 100000;
    private const CODE_MAX         = 999999;
    private const CODE_TTL_MINUTES = 10;
    private const MAX_ATTEMPTS     = 5;

    public function __construct(
        private readonly Email2faChallengeModelInterface $challengeModel,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function generateAndSendCode(UserEntity $user): bool
    {
        $userId   = $user->getUserId();
        $email    = $user->getEmail();
        $username = $user->getUsername();

        if ($userId === null || $email === null || $username === null) {
            return false;
        }

        $code      = $this->generateCode();
        $codeHash  = $this->hashCode($code);
        $expiresAt = $this->buildExpirationDate();

        $this->challengeModel->invalidateActiveChallengesForUser($userId);

        $challengeId = $this->challengeModel->createChallenge(
            $userId,
            $codeHash,
            $expiresAt,
        );

        if ($challengeId <= 0) {
            return false;
        }

        return $this->mailer->sendEmail2faCode(
            $email,
            $username,
            $code,
            self::CODE_TTL_MINUTES,
        );
    }

    public function resendCode(UserEntity $user): bool
    {
        return $this->generateAndSendCode($user);
    }

    public function verifyCode(int $userId, string $submittedCode): string
    {
        if ($userId <= 0 || !$this->isValidCodeFormat($submittedCode)) {
            return self::VERIFY_INVALID;
        }

        $challenge = $this->resolveValidChallenge($userId);

        if (!$challenge instanceof Email2faChallengeEntity) {
            return self::VERIFY_INVALID;
        }

        $challengeId = $challenge->getId();

        if ($challengeId === null) {
            return self::VERIFY_INVALID;
        }

        if ($this->isMaxAttemptsReached($challenge)) {
            $this->challengeModel->invalidateActiveChallengesForUser($userId);

            return self::VERIFY_TOO_MANY_ATTEMPTS;
        }

        if (!$this->isSubmittedCodeValid($challenge, $submittedCode)) {
            return $this->handleInvalidSubmittedCode($challenge, $userId);
        }

        $this->challengeModel->markAsUsed($challengeId);

        return self::VERIFY_SUCCESS;
    }

    private function resolveValidChallenge(int $userId): ?Email2faChallengeEntity
    {
        $challenge = $this->challengeModel->findActiveChallengeByUserId($userId);

        if (!$challenge instanceof Email2faChallengeEntity) {
            return null;
        }

        if ($challenge->getId() === null || $challenge->getCodeHash() === null) {
            return null;
        }

        return $challenge;
    }

    private function isMaxAttemptsReached(Email2faChallengeEntity $challenge): bool
    {
        return $challenge->getAttempts() >= self::MAX_ATTEMPTS;
    }

    private function isSubmittedCodeValid(Email2faChallengeEntity $challenge, string $submittedCode): bool
    {
        $storedHash = $challenge->getCodeHash();

        if ($storedHash === null) {
            return false;
        }

        return hash_equals($storedHash, $this->hashCode($submittedCode));
    }

    private function handleInvalidSubmittedCode(Email2faChallengeEntity $challenge, int $userId): string
    {
        $challengeId = $challenge->getId();

        if ($challengeId === null) {
            return self::VERIFY_INVALID;
        }

        $this->challengeModel->incrementAttempts($challengeId);

        if (($challenge->getAttempts() + 1) >= self::MAX_ATTEMPTS) {
            $this->challengeModel->invalidateActiveChallengesForUser($userId);

            return self::VERIFY_TOO_MANY_ATTEMPTS;
        }

        return self::VERIFY_INVALID;
    }

    private function generateCode(): string
    {
        return (string) random_int(self::CODE_MIN, self::CODE_MAX);
    }

    private function hashCode(string $code): string
    {
        return hash('sha256', $code, true);
    }

    private function buildExpirationDate(): string
    {
        return (new DateTimeImmutable())
            ->modify('+' . self::CODE_TTL_MINUTES . ' minutes')
            ->format('Y-m-d H:i:s');
    }

    private function isValidCodeFormat(string $code): bool
    {
        return preg_match('/^\d{6}$/', $code) === 1;
    }
}
