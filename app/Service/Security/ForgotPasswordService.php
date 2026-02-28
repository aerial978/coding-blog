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
use App\Validation\Contract\FormValidatorInterface;
use App\Service\Security\Contract\ForgotPasswordServiceInterface;
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

        // Validation "douce" :
        // - si c'est un email -> valider
        // - sinon username : ne pas trop rejeter (anti-énumération)
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

        // 1) Lookup user (email ou username)
        $user = $this->findUserByIdentifier($identifier);

        Logger::getLogger('auth')->info('forgot_lookup', [
            'identifier' => $identifier,
            'user_found' => $user !== null,
        ]);

        if (!$user) {
            Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_PASSWORD_RESET_REQUESTED, [
                'reason'      => 'user_not_found',
                'identifier'  => $identifier,
            ]);
            return [];
        }

        $userId  = $user->getUserId();
        $toEmail = trim((string) $user->getEmail());
        $toName  = (string) $user->getUsername();

        if (!is_int($userId) || $userId <= 0 || $toEmail === '') {
            Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'reason'      => 'invalid_user_basics',
                'identifier'  => $identifier,
                'user_id'     => $userId,
                'email_empty' => ($toEmail === ''),
            ]);
            return [];
        }

        // 2) Blocage strict : si un token reset actif existe => aucun nouvel email
        if ($this->userTokenModel->hasActiveUnusedPasswordResetToken($userId)) {
            Logger::getLogger('auth')->info('forgot_password_skip_active_token', [
                'user_id' => $userId,
                'email'   => $toEmail,
                'reason'  => 'active_reset_token_exists',
            ]);
            return []; // réponse neutre (anti-énumération)
        }

        // 3) Quota métier (sur l'email réel du compte)
        $quota = $this->quotaService->checkQuota(EmailQuotaService::TYPE_PASSWORD_RESET, $toEmail);
        if (!$quota['allowed']) {
            Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_PASSWORD_RESET_QUOTA_EXCEEDED, [
                'reason' => 'quota_exceeded_' . ($quota['reason'] ?? ''),
                'email'  => $toEmail,
                'user_id'=> $userId,
            ]);
            return []; // succès silencieux
        }

        // 4) Token artifacts
        [$token, $hashBin, $expiresAt] = $this->makeNewTokenArtifacts($toEmail);
        if ($hashBin === null) {
            return [];
        }

        // 5) Persist (user_token purpose=password_reset)
        $ok = $this->userTokenModel->createPasswordResetToken($userId, $hashBin, $expiresAt);
        if (!$ok) {
            Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'reason'   => 'persist_failed',
                'user_id'  => $userId,
                'email'    => $toEmail,
                'purpose'  => 'password_reset',
            ]);
            return [];
        }

        // 6) Send email
        $sent = $this->sendResetEmail($toEmail, $toName, $token);
        if (!$sent) {
           // Important : on invalide le token créé, sinon on bloque l'utilisateur alors qu'il n'a rien reçu.
            try {
                $this->userTokenModel->invalidatePasswordResetToken($userId);
            } catch (\Throwable $e) {
                Logger::getLogger('auth')->error('forgot_password_invalidate_failed', [
                    'user_id'    => $userId,
                    'email'      => $toEmail,
                    'exception'  => $e->getMessage(),
                ]);
            } 

            Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_PASSWORD_RESET_EMAIL_SEND_FAILED, [
                'email'   => $toEmail,
                'user_id' => $userId,
            ]);
            return [];
        }

        // 7) Record quota event (après envoi OK)
        $this->recordPasswordResetEvent($toEmail, $userId);

        // 8) Success log (neutre)
        Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_PASSWORD_RESET_REQUESTED, [
            'user_id' => $userId,
            'email'   => $toEmail,
        ]);

        return [];
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

        // username
        return $this->userModel->findAuthByUsername($identifier);
    }

    private function looksLikeEmail(string $value): bool
    {
        return strpos($value, '@') !== false;
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
