<?php

// App/Service/Security/ConfirmationResendService.php

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
     * @return array<string,mixed>
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
     * @return array<string,mixed>
     */
    private function doResendFlow(string $email): array
    {
        $channel = 'auth';

        // 1) Lookup utilisateur
        $user = $this->userModel->findOneByEmail($email);
        $res  = $this->guardUnknownUser(is_object($user) ? $user : null, $email, $channel);
        if ($res !== null) {
            return $res; // [] (politique anti-énumération)
        }
        /** @var object $user */

        // 2) Normalisations (status, id, name)
        [$status, $userId, $toName] = $this->extractUserBasics($user);

        // 3) Quota métier
        $res = $this->guardQuota($email, $channel);
        if ($res !== null) {
            return $res; // [] succès silencieux si quota dépassé
        }

        // 4) Déjà actif ?
        $res = $this->outIfAlreadyActive($status, $userId, $email, $channel);
        if ($res !== null) {
            return $res; // ['error' => AUTH_ALREADY_CONFIRMED]
        }

        // 5) Artefacts (token, hash, expiry)
        [$token, $hashBin, $expiresAt] = $this->makeNewTokenArtifacts($email);
        $res = $this->outIfHashNull($hashBin);
        if ($res !== null) {
            return $res; // ['error' => TECHNICAL]
        }
        /** @var string $hashBin */

        // 6) Persistance
        $ok  = $this->persistNewToken($userId, $hashBin, $expiresAt);
        $res = $this->outIfPersistFailed($ok);
        if ($res !== null) {
            return $res;
        }

        // 7) Envoi de l’email
        $sent = $this->sendConfirmationEmail($email, $toName, $token);
        $res  = $this->outIfEmailNotSent($sent);
        if ($res !== null) {
            return $res;
        }

        // 8) Enregistrement de l’événement + log succès
        $this->recordResendEvent($email, $userId);
        $this->logResendSuccess($channel, $userId, $email);

        return [];
    }

    /** @return array<string,mixed>|null */
    private function guardUnknownUser(?object $user, string $email, string $channel): ?array
    {
        if ($user !== null) {
            return null;
        }
        Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_RESEND_EMAIL_SENT, [
            'email'  => $email,
            'reason' => 'user_not_found',
        ]);
        return []; // politique anti-énumération
    }

    /**
     * Normalise les champs indispensables issus de $user.
     * @return array{0: ?string, 1: int, 2: string}  [status, userId, toName]
     */
    private function extractUserBasics(object $user): array
    {
        $tmpStatus = method_exists($user, 'getStatus') ? $user->getStatus() : null;
        $status    = is_string($tmpStatus) ? $tmpStatus : null;

        $tmpId  = method_exists($user, 'getUserId') ? $user->getUserId() : null;
        $userId = is_int($tmpId)
            ? $tmpId
            : ((is_string($tmpId) && ctype_digit($tmpId)) ? (int) $tmpId : 0);

        $tmpName = method_exists($user, 'getUsername') ? $user->getUsername() : null;
        $toName  = is_string($tmpName) ? $tmpName : '';

        return [$status, $userId, $toName];
    }

    /** Retourne [] si quota dépassé (succès silencieux), sinon null. */
    private function guardQuota(string $email, string $channel): ?array
    {
        $quota = $this->quotaService->checkQuota(
            EmailQuotaService::TYPE_CONFIRM_RESEND,
            $email
        );

        if ($quota['allowed']) {
            return null;
        }

        Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_RESEND_QUOTA_EXCEEDED, [
            'email'  => $email,
            'reason' => 'quota_exceeded_' . ($quota['reason'] ?? ''),
        ]);
        return []; // succès silencieux
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

    // --------- helpers (petits "guards" et actions) ---------

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
        //Logger::logCodeAndGetMessage($channel, 'auth', 'token clair', ['token' => $token]);

        if (strlen($hashBin) !== 32) {
            Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'reason' => 'invalid_binary_hash_length',
                'email'  => $email,
            ]);
            return [$token, null, new DateTimeImmutable('+24 hours')];
        }

        return [$token, $hashBin, new DateTimeImmutable('+24 hours')];
    }

    private function persistNewToken(int $userId, string $hashBin, \DateTimeImmutable $expiresAt): bool
    {
        return $this->userTokenModel->createConfirmationToken($userId, $hashBin, $expiresAt);
    }

    private function sendConfirmationEmail(string $toEmail, string $toName, string $token): bool
    {
        $baseUrl = AppConfig::getAppUrl();
        $link    = $baseUrl . '/confirm-account?token=' . urlencode($token);
        try {
            $sent = $this->mailer->send($toEmail, $toName, 'Confirmation de votre compte', 'confirmation.html', ['username' => $toName, 'link' => $link]);
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
}
