<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Core\AppConfig;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Core\Mail\MailerInterface;
use App\Model\Contract\UserModelInterface;
use App\Model\Contract\UserTokenModelInterface;
use App\Security\Contract\EmailQuotaServiceInterface;
use App\Security\Contract\TokenGeneratorInterface;
use App\Security\EmailQuotaService;
use App\Service\Security\Contract\ForgotPasswordServiceInterface;
use App\Validation\Contract\FormValidatorInterface;
use DateTimeImmutable;

final class ForgotPasswordService implements ForgotPasswordServiceInterface
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
     * Politique anti-énumération :
     * - Retourne toujours [] (neutre), même si email/username inconnu
     * - Log les raisons côté serveur
     *
     * @return array<string,mixed>
     */
    public function requestReset(string $identifier): array
    {
        $channel    = 'auth';
        $identifier = trim($identifier);

        if ($identifier === '') {
            Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_PASSWORD_RESET_REQUESTED, [
                'reason' => 'empty_identifier',
            ]);

            return [];
        }

        if ($this->looksLikeEmail($identifier)) {
            $err = $this->validator->validateEmailField($identifier);
            if ($err !== null) {
                Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_PASSWORD_RESET_REQUESTED, [
                    'reason' => 'invalid_email_format',
                ]);

                return [];
            }
        }

        try {
            return $this->doRequestResetFlow($identifier);
        } catch (\Throwable $e) {
            Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'exception'  => $e->getMessage(),
                'identifier' => $identifier,
            ]);

            return [];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function doRequestResetFlow(string $identifier): array
    {
        $channel = 'auth';

        $user = $this->findUserByIdentifier($identifier);

        Logger::getLogger('auth')->info('forgot_lookup', [
            'identifier' => $identifier,
            'user_found' => $user !== null,
        ]);

        $res = $this->outIfUnknownUser($user, $identifier, $channel);
        if ($res !== null) {
            return $res;
        }

        /** @var \App\Model\Entity\UserEntity $user */
        [$userId, $toEmail, $toName] = $this->extractUserBasics($user);

        $res = $this->outIfInvalidUserBasics($userId, $toEmail, $identifier, $channel);
        if ($res !== null) {
            return $res;
        }

        $res = $this->outIfActiveResetTokenExists($userId, $toEmail);
        if ($res !== null) {
            return $res;
        }

        $res = $this->outIfQuotaExceeded($toEmail, $userId, $channel);
        if ($res !== null) {
            return $res;
        }

        return $this->processResetDelivery($toEmail, $toName, $userId, $channel);
    }

    /**
     * @return array<string,mixed>
     */
    private function processResetDelivery(string $toEmail, string $toName, int $userId, string $channel): array
    {
        [$token, $hashBin, $expiresAt] = $this->makeNewTokenArtifacts($toEmail);

        $hashNullResult = $this->outIfHashNull($hashBin);
        if ($hashNullResult !== null) {
            return $hashNullResult;
        }

        /** @var string $hashBin */
        $persistResult = $this->persistResetTokenOrOut($userId, $hashBin, $expiresAt, $toEmail, $channel);
        if ($persistResult !== null) {
            return $persistResult;
        }

        $sendResult = $this->sendResetEmailOrRollback($toEmail, $toName, $token, $userId, $channel);
        if ($sendResult !== null) {
            return $sendResult;
        }

        $this->recordPasswordResetEvent($toEmail, $userId);

        Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_PASSWORD_RESET_REQUESTED, [
            'user_id' => $userId,
            'email'   => $toEmail,
        ]);

        return [];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function outIfUnknownUser(?\App\Model\Entity\UserEntity $user, string $identifier, string $channel): ?array
    {
        if ($user !== null) {
            return null;
        }

        Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_PASSWORD_RESET_REQUESTED, [
            'reason'     => 'user_not_found',
            'identifier' => $identifier,
        ]);

        return [];
    }

    /**
     * @return array{0:int,1:string,2:string}
     */
    private function extractUserBasics(\App\Model\Entity\UserEntity $user): array
    {
        $rawUserId = $user->getUserId();
        $userId    = is_int($rawUserId) && $rawUserId > 0 ? $rawUserId : 0;

        $toEmail = trim((string) $user->getEmail());
        $toName  = (string) $user->getUsername();

        return [$userId, $toEmail, $toName];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function outIfInvalidUserBasics(int $userId, string $toEmail, string $identifier, string $channel): ?array
    {
        if ($userId > 0 && $toEmail !== '') {
            return null;
        }

        Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
            'reason'      => 'invalid_user_basics',
            'identifier'  => $identifier,
            'user_id'     => $userId > 0 ? $userId : null,
            'email_empty' => ($toEmail === ''),
        ]);

        return [];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function outIfActiveResetTokenExists(int $userId, string $toEmail): ?array
    {
        if (!$this->userTokenModel->hasActiveUnusedPasswordResetToken($userId)) {
            return null;
        }

        Logger::getLogger('auth')->info('forgot_password_skip_active_token', [
            'user_id' => $userId,
            'email'   => $toEmail,
            'reason'  => 'active_reset_token_exists',
        ]);

        return [];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function outIfQuotaExceeded(string $toEmail, int $userId, string $channel): ?array
    {
        $quota = $this->quotaService->checkQuota(EmailQuotaService::TYPE_PASSWORD_RESET, $toEmail);

        if ($quota['allowed']) {
            return null;
        }

        Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_PASSWORD_RESET_QUOTA_EXCEEDED, [
            'reason'  => 'quota_exceeded_' . ($quota['reason'] ?? ''),
            'email'   => $toEmail,
            'user_id' => $userId,
        ]);

        return [];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function outIfHashNull(?string $hashBin): ?array
    {
        return $hashBin === null ? [] : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function persistResetTokenOrOut(
        int $userId,
        string $hashBin,
        DateTimeImmutable $expiresAt,
        string $toEmail,
        string $channel
    ): ?array {
        $ok = $this->userTokenModel->createPasswordResetToken($userId, $hashBin, $expiresAt);

        if ($ok) {
            return null;
        }

        Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
            'reason'  => 'persist_failed',
            'user_id' => $userId,
            'email'   => $toEmail,
            'purpose' => 'password_reset',
        ]);

        return [];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function sendResetEmailOrRollback(
        string $toEmail,
        string $toName,
        string $token,
        int $userId,
        string $channel
    ): ?array {
        $sent = $this->sendResetEmail($toEmail, $toName, $token);

        if ($sent) {
            return null;
        }

        $this->tryInvalidatePasswordResetToken($userId, $toEmail);

        Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_PASSWORD_RESET_EMAIL_SEND_FAILED, [
            'email'   => $toEmail,
            'user_id' => $userId,
        ]);

        return [];
    }

    private function tryInvalidatePasswordResetToken(int $userId, string $toEmail): void
    {
        try {
            $this->userTokenModel->invalidatePasswordResetToken($userId);
        } catch (\Throwable $e) {
            Logger::getLogger('auth')->error('forgot_password_invalidate_failed', [
                'user_id'   => $userId,
                'email'     => $toEmail,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function recordPasswordResetEvent(string $email, int $userId): void
    {
        $ip = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        $ua = is_string($_SERVER['HTTP_USER_AGENT'] ?? null) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown';

        $this->quotaService->recordEvent(
            $email,
            EmailQuotaService::TYPE_PASSWORD_RESET,
            $userId,
            $ip,
            $ua
        );
    }

    private function findUserByIdentifier(string $identifier): ?\App\Model\Entity\UserEntity
    {
        if ($this->looksLikeEmail($identifier)) {
            return $this->userModel->findAuthByEmail($identifier);
        }

        return $this->userModel->findAuthByUsername($identifier);
    }

    private function looksLikeEmail(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
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

            return [$token, null, new DateTimeImmutable('+1 hour')];
        }

        return [$token, $hashBin, new DateTimeImmutable('+1 hour')];
    }

    private function sendResetEmail(string $email, string $username, string $token): bool
    {
        $baseUrl = AppConfig::getAppUrl();
        $link    = $baseUrl . '/reset-password?token=' . urlencode($token);

        try {
            return $this->mailer->send(
                $email,
                $username,
                'Réinitialisation de votre mot de passe',
                'password-reset-request.html',
                [
                    'username' => $username,
                    'link'     => $link,
                ]
            );
        } catch (\Throwable $e) {
            Logger::logCodeAndGetMessage('auth', 'error', ErrorCode::AUTH_PASSWORD_RESET_EMAIL_SEND_FAILED, [
                'email'     => $email,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
