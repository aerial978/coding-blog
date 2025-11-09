<?php

// App/Service/Security/ConfirmationResendService.php

declare(strict_types=1);

namespace App\Service\Security;

use App\Core\AppConfig;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Core\Mail\MailerInterface;
use App\Model\UserModel;
use App\Model\UserTokenModel;
use App\Security\Contract\TokenGeneratorInterface;
use App\Validation\FormValidator;
use DateTimeImmutable;

final class ConfirmationResendService
{
    public function __construct(private FormValidator $validator, private UserModel $userModel, private UserTokenModel $userTokenModel, private TokenGeneratorInterface $tokenGen, private MailerInterface $mailer)
    {
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
        $err     = $this->validator->validateEmailField($email);
        if ($err !== null) {
            return ['error' => $err];
        }

        try {
            $user = $this->userModel->findOneByEmail($email);
            if ($user === null) {
                // Succès générique (anti-énumération)
                Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_RESEND_EMAIL_SENT, [
                    'email'  => $email,
                    'reason' => 'user_not_found',
                ]);
                return [];
            }

            if ($this->isActive($user->getStatus())) {
                Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_ALREADY_CONFIRMED, [
                    'user_id' => $user->getUserId(),
                    'email'   => $email,
                ]);
                return ['error' => ErrorCode::AUTH_ALREADY_CONFIRMED];
            }

            // Génère artefacts
            [$token, $hashBin, $expiresAt] = $this->makeNewTokenArtifacts($email);
            if ($hashBin === null) {
                return ['error' => ErrorCode::AUTH_TECHNICAL_ERROR];
            }

            // Persiste jeton
            if (!$this->persistNewToken((int) $user->getUserId(), $hashBin, $expiresAt)) {
                return ['error' => ErrorCode::AUTH_TECHNICAL_ERROR];
            }

            // Envoi email
            if (
                !$this->sendConfirmationEmail($email, $user->getUsername() ?? '', $token)
            ) {
                return ['error' => ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED];
            }

            Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_RESEND_EMAIL_SENT, [
                'user_id' => $user->getUserId(),
                'email'   => $email,
            ]);
            return [];
        } catch (\Throwable $e) {
            Logger::logCodeAndGetMessage('auth', 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'exception' => $e->getMessage(),
                'email'     => $email,
            ]);
            return ['error' => ErrorCode::AUTH_TECHNICAL_ERROR];
        }
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
