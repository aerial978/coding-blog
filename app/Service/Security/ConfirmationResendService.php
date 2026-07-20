<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Core\AppConfig;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Core\Mail\MailerInterface;
use App\Model\Contract\UserModelInterface;
use App\Model\Contract\UserTokenModelInterface;
use App\Model\Entity\UserEntity;
use App\Security\Contract\EmailQuotaServiceInterface;
use App\Security\Contract\TokenGeneratorInterface;
use App\Security\EmailQuotaService;
use App\Service\Security\Contract\ConfirmationResendServiceInterface;
use App\Validation\Contract\FormValidatorInterface;
use DateTimeImmutable;

final class ConfirmationResendService implements ConfirmationResendServiceInterface
{
    public function __construct(
        private FormValidatorInterface $validator,
        private UserModelInterface $userModel,
        private UserTokenModelInterface $userTokenModel,
        private TokenGeneratorInterface $tokenGen,
        private MailerInterface $mailer,
        private EmailQuotaServiceInterface $quotaService,
    ) {
    }

    /**
     * Politique anti-énumération : retourne [] même si l’email est inconnu.
     * En cas d’utilisateur déjà actif → ['error' => AUTH_ALREADY_CONFIRMED]
     * En cas d’échec technique → ['error' => AUTH_TECHNICAL_ERROR] (ou AUTH_CONFIRM_EMAIL_SEND_FAILED)
     *
     * @return array<string, mixed>
     */
    public function resend(string $email): array
    {
        $channel = 'auth';
        $email   = trim($email);

        $err = $this->validator->validateEmailField($email);
        if ($err !== null) {
            return ['error' => $err];
        }

        try {
            return $this->doResendFlow($email);
        } catch (\Throwable $e) {
            Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'exception' => $e->getMessage(),
                'email'     => $email,
            ]);

            return ['error' => ErrorCode::AUTH_TECHNICAL_ERROR];
        }
    }

    /**
     * Flux “heureux” + gardes d’erreurs, linéarisé pour réduire la complexité.
     *
     * @return array<string, mixed>
     */
    private function doResendFlow(string $email): array
    {
        $channel = 'auth';

        $userResult = $this->resolveUserForResend($email, $channel);
        if ($userResult !== null) {
            return $userResult;
        }

        $user = $this->userModel->findOneByEmail($email);
        if (!$user instanceof UserEntity) {
            return ['error' => ErrorCode::AUTH_TECHNICAL_ERROR];
        }

        [$status, $userId, $toName] = $this->extractUserBasics($user);

        $guardResult = $this->guardResendEligibility($email, $status, $userId, $channel);
        if ($guardResult !== null) {
            return $guardResult;
        }

        return $this->processResendDelivery($email, $userId, $toName, $channel);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveUserForResend(string $email, string $channel): ?array
    {
        $user   = $this->userModel->findOneByEmail($email);
        $result = $this->guardUnknownUser($user, $email, $channel);

        if ($result !== null) {
            return $result;
        }

        if (!$user instanceof UserEntity) {
            return ['error' => ErrorCode::AUTH_TECHNICAL_ERROR];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function guardResendEligibility(string $email, ?string $status, int $userId, string $channel): ?array
    {
        $quotaResult = $this->guardQuota($email, $channel);
        if ($quotaResult !== null) {
            return $quotaResult;
        }

        return $this->outIfAlreadyActive($status, $userId, $email, $channel);
    }

    /**
     * @return array<string, mixed>
     */
    private function processResendDelivery(string $email, int $userId, string $toName, string $channel): array
    {
        [$token, $hashBin, $expiresAt] = $this->makeNewTokenArtifacts($email);

        $hashResult = $this->outIfHashNull($hashBin);
        if ($hashResult !== null) {
            return $hashResult;
        }

        /** @var string $hashBin */
        $persistResult = $this->persistTokenOrFail($userId, $hashBin, $expiresAt);
        if ($persistResult !== null) {
            return $persistResult;
        }

        $mailResult = $this->sendEmailOrFail($email, $toName, $token);
        if ($mailResult !== null) {
            return $mailResult;
        }

        $this->recordResendEvent($email, $userId);
        $this->logResendSuccess($channel, $userId, $email);

        /** @var array<string, mixed> $result */
        $result = [];

        return $result;
    }

    /** @return array<string, mixed>|null */
    private function guardUnknownUser(?UserEntity $user, string $email, string $channel): ?array
    {
        if ($user !== null) {
            return null;
        }

        Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_RESEND_EMAIL_SENT, [
            'email'  => $email,
            'reason' => 'user_not_found',
        ]);

        /** @var array<string, mixed> $result */
        $result = [];

        return $result;
    }

    /**
     * Normalise les champs indispensables issus de $user.
     *
     * @return array{0: ?string, 1: int, 2: string} [status, userId, toName]
     */
    private function extractUserBasics(UserEntity $user): array
    {
        $tmpStatus = $user->getStatus();
        $status    = is_string($tmpStatus) ? $tmpStatus : null;

        $tmpId  = $user->getUserId();
        $userId = is_int($tmpId) ? $tmpId : 0;

        $tmpName = $user->getUsername();
        $toName  = is_string($tmpName) ? $tmpName : '';

        return [$status, $userId, $toName];
    }

    /**
     * Retourne [] si quota dépassé (succès silencieux), sinon null.
     *
     * @return array<string, mixed>|null
     */
    private function guardQuota(string $email, string $channel): ?array
    {
        $quota = $this->quotaService->checkQuota(EmailQuotaService::TYPE_CONFIRM_RESEND, $email);

        if (!$quota['allowed']) {
            Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_RESEND_QUOTA_EXCEEDED, [
                'email'  => $email,
                'reason' => 'quota_exceeded_' . ($quota['reason'] ?? ''),
            ]);

            /** @var array<string, mixed> $result */
            $result = [];

            return $result;
        }

        return null;
    }

    /** @return array{error:string}|null */
    private function outIfAlreadyActive(?string $status, int $userId, string $email, string $channel): ?array
    {
        if (!$this->isActive($status)) {
            return null;
        }

        Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_ALREADY_CONFIRMED, [
            'user_id' => $userId,
            'email'   => $email,
        ]);

        return ['error' => ErrorCode::AUTH_ALREADY_CONFIRMED];
    }

    /** @return array{error:string}|null */
    private function outIfHashNull(?string $hashBin): ?array
    {
        return ($hashBin === null) ? ['error' => ErrorCode::AUTH_TECHNICAL_ERROR] : null;
    }

    /** @return array{error:string}|null */
    private function outIfPersistFailed(bool $ok): ?array
    {
        return $ok ? null : ['error' => ErrorCode::AUTH_TECHNICAL_ERROR];
    }

    /** @return array{error:string}|null */
    private function outIfEmailNotSent(bool $sent): ?array
    {
        return $sent ? null : ['error' => ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED];
    }

    /** @return array{error:string}|null */
    private function persistTokenOrFail(int $userId, string $hashBin, DateTimeImmutable $expiresAt): ?array
    {
        $ok = $this->persistNewToken($userId, $hashBin, $expiresAt);

        return $this->outIfPersistFailed($ok);
    }

    /** @return array{error:string}|null */
    private function sendEmailOrFail(string $email, string $toName, string $token): ?array
    {
        $sent = $this->sendConfirmationEmail($email, $toName, $token);

        return $this->outIfEmailNotSent($sent);
    }

    private function isActive(?string $status): bool
    {
        $statusNormalized = trim((string) $status);

        return strcasecmp($statusNormalized, 'active') === 0 || $statusNormalized === '1';
    }

    /**
     * @return array{0:string,1:string|null,2:DateTimeImmutable}
     */
    private function makeNewTokenArtifacts(string $email): array
    {
        $channel = 'auth';
        $token   = $this->tokenGen->generateUrlSafeToken(32);
        $hashBin = $this->tokenGen->hashToken($token);

        if (strlen($hashBin) !== 32) {
            Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'reason' => 'invalid_binary_hash_length',
                'email'  => $email,
            ]);

            return [$token, null, new DateTimeImmutable('+24 hours')];
        }

        return [$token, $hashBin, new DateTimeImmutable('+24 hours')];
    }

    private function persistNewToken(int $userId, string $hashBin, DateTimeImmutable $expiresAt): bool
    {
        return $this->userTokenModel->createConfirmationToken($userId, $hashBin, $expiresAt);
    }

    private function sendConfirmationEmail(string $toEmail, string $toName, string $token): bool
    {
        $baseUrl = AppConfig::getAppUrl();
        $link    = $baseUrl . '/confirm-account?token=' . urlencode($token);

        try {
            $sent = $this->mailer->send(
                $toEmail,
                $toName,
                'Confirmation de votre compte',
                'confirmation.html',
                ['username' => $toName, 'link' => $link]
            );
        } catch (\Throwable $mailEx) {
            Logger::logCodeAndGetMessage('auth', 'error', ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED, [
                'email'     => $toEmail,
                'exception' => $mailEx->getMessage(),
            ]);

            return false;
        }

        if (!$sent) {
            Logger::logCodeAndGetMessage('auth', 'error', ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED, [
                'email'  => $toEmail,
                'reason' => 'mailer_returned_false',
            ]);

            return false;
        }

        return true;
    }

    private function recordResendEvent(string $email, int $userId): void
    {
        $ip = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        $ua = is_string($_SERVER['HTTP_USER_AGENT'] ?? null) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown';

        $this->quotaService->recordEvent(
            $email,
            EmailQuotaService::TYPE_CONFIRM_RESEND,
            $userId,
            $ip,
            $ua
        );
    }

    private function logResendSuccess(string $channel, int $userId, string $email): void
    {
        Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_RESEND_EMAIL_SENT, [
            'user_id' => $userId,
            'email'   => $email,
        ]);
    }
}
