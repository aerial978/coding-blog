<?php

namespace App\Service\Security;

use App\Core\AppConfig;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Core\Mail\MailerInterface;
use App\Core\SqlHelper;
use App\Model\Entity\UserEntity;
use App\Model\UserModel;
use App\Model\UserTokenModel;
use App\Security\Contract\TokenGeneratorInterface;
use App\Validation\FormValidator;
use Cocur\Slugify\Slugify;
use DateTimeImmutable;

final class RegistrationService
{
    public function __construct(
        private FormValidator $validator,
        private UserModel $userModel,
        private UserTokenModel $userTokenModel,
        private Slugify $slugify,
        private MailerInterface $mailer,
        private TokenGeneratorInterface $tokenGen,
        private SqlHelper $sqlHelper
    ) {
    }

    /**
     * @param array<string,mixed> $form
     * @return array{
     *   ok?: true,
     *   errors?: list<string|int>,
     *   old?: array{username:string, email:string}
     * }
     */
    public function register(array $form): array
    {
        $channel = 'auth';

        try {
            // 1) Lecture et normalisation des entrées
            [$username, $email, $password, $confirm, $old] = $this->sanitizeRegistrationForm($form);

            // 2) Validation “formelle” et détection de collisions username/email
            $errors = $this->validateRegistrationOrConflicts($username, $email, $password, $confirm);
            if (!empty($errors)) {
                return ['errors' => $errors, 'old' => $old];
            }

            // 3) Préparer les artefacts d’inscription
            // (hash du mot de passe, slug, jeton clair + hash binaire, expiration)
            $art = $this->prepareRegistrationArtifacts($username, $password);
            if ($art === null) {
                // Longueur de hash binaire invalide
                return ['errors' => [ErrorCode::AUTH_TECHNICAL_ERROR], 'old' => $old];
            }

            // 4) Transaction : création utilisateur + insertion du jeton
            $user   = $this->makeUserEntity($username, $email, $art['hashedPassword'], $art['slug']);
            $userId = $this->persistUserAndToken($user, $art['confirmTokenHash'], $art['confirmExpiresAt']);
            if ($userId <= 0) {
                // Les logs sont gérés dans persistUserAndToken()
                return ['errors' => [ErrorCode::AUTH_TECHNICAL_ERROR], 'old' => $old];
            }

            // 5) Envoi de l’email de confirmation
            if (!$this->trySendConfirmationEmail($email, $username, $art['token'], $userId)) {
                return ['errors' => [ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED], 'old' => $old];
            }

            return ['ok' => true];

        } catch (\PDOException $e) {
            $safeOld = [
                'username' => $username ?? '',
                'email'    => $email    ?? '',
            ];

            return $this->handlePdoRegistrationException(
                $e,
                $channel,
                $email    ?? '',
                $username ?? '',
                $safeOld
            );
        } catch (\Throwable $e) {
            // Filet de sécurité global
            Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'reason'     => 'throwable',
                'exception'  => $e->getMessage(),
            ]);

            $safeOld = [
                'username' => $username ?? '',
                'email'    => $email    ?? '',
            ];

            return ['errors' => [ErrorCode::AUTH_TECHNICAL_ERROR], 'old' => $safeOld];
        }
    }

    /**
     * @param array<string,mixed> $form
     * @return array{0:string,1:string,2:string,3:string,4:array{username:string,email:string}}
     */
    private function sanitizeRegistrationForm(array $form): array
    {
        $username = is_string($form['username'] ?? null) ? trim($form['username']) : '';
        $email    = is_string($form['email'] ?? null) ? trim($form['email']) : '';
        $password = is_string($form['password'] ?? null) ? $form['password'] : '';
        $confirm  = is_string($form['confirm_password'] ?? null) ? $form['confirm_password'] : '';
        $old      = ['username' => $username, 'email' => $email];
        return [$username, $email, $password, $confirm, $old];
    }

    /** @return list<string> */
    private function validateRegistrationOrConflicts(string $username, string $email, string $password, string $confirm): array
    {
        $channel = 'auth';
        $errors  = $this->validator->validateRegistration([
            'username'         => $username,
            'email'            => $email,
            'password'         => $password,
            'confirm_password' => $confirm,
        ]);

        // Collisions métier (on garde votre logique et vos logs)
        if ($this->userModel->findOneByUsername($username)) {
            Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_USERNAME_EXISTS, ['username' => $username]);
            $errors = [ErrorCode::AUTH_USERNAME_EXISTS, ErrorCode::AUTH_PASSWORD_REENTER];
        }
        if ($this->userModel->findOneByEmail($email)) {
            Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_EMAIL_EXISTS, ['email' => $email]);
            $errors = [ErrorCode::AUTH_EMAIL_EXISTS, ErrorCode::AUTH_PASSWORD_REENTER];
        }

        /** @var list<string> $out */
        $out = array_values($errors);
        return $out;
    }

    /** @return array{hashedPassword:string,slug:string,token:string,confirmTokenHash:string,confirmExpiresAt:\DateTimeImmutable}|null */
    private function prepareRegistrationArtifacts(string $username, string $password): ?array
    {
        $channel           = 'auth';
        $hashedPassword    = password_hash($password, PASSWORD_ARGON2I);
        $slug              = $this->slugify->slugify($username);
        $token             = $this->tokenGen->generateUrlSafeToken(32);
        $confirmTokenHash  = $this->tokenGen->hashToken($token);

        Logger::logCodeAndGetMessage($channel, 'auth', 'token clair', ['token' => $token]);

        if (strlen($confirmTokenHash) !== 32) {
            Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'reason' => 'invalid_binary_hash_length',
            ]);
            return null;
        }

        return [
            'hashedPassword'   => $hashedPassword,
            'slug'             => $slug,
            'token'            => $token,
            'confirmTokenHash' => $confirmTokenHash,
            'confirmExpiresAt' => new DateTimeImmutable('+24 hours'),
        ];
    }

    private function makeUserEntity(string $username, string $email, string $hashedPassword, string $slug): UserEntity
    {
        return (new UserEntity())
            ->setUsername($username)
            ->setSlug($slug)
            ->setEmail($email)
            ->setPassword($hashedPassword);
    }

    /** Retourne l’ID (>0) ou 0/negatif en cas d’échec. Loggue les raisons. */
    private function persistUserAndToken(UserEntity $user, string $confirmTokenHash, \DateTimeImmutable $expiresAt): int
    {
        $channel = 'auth';
        $this->sqlHelper->beginTransaction();
        try {
            $userId = $this->userModel->createUser($user);
            if ($userId <= 0) {
                Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_REGISTRATION_FAILED, [
                    'email' => $user->getEmail(), 'username' => $user->getUsername(),
                ]);
                $this->sqlHelper->rollBack();
                return 0;
            }

            $ok = $this->userTokenModel->createConfirmationToken($userId, $confirmTokenHash, $expiresAt);
            if (!$ok) {
                $this->sqlHelper->rollBack();
                Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                    'reason' => 'create_confirmation_token_failed', 'user_id' => $userId,
                ]);
                return 0;
            }

            $this->sqlHelper->commit();
            return (int)$userId;
        } catch (\Throwable $txe) {
            $this->sqlHelper->rollBack();
            Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'reason' => 'transaction_exception', 'exception' => $txe->getMessage(),
            ]);
            return 0;
        }
    }

    /** Envoie l’email, gère les exceptions et logs ; retourne true si envoyé. */
    private function trySendConfirmationEmail(string $email, string $username, string $token, int $userId): bool
    {
        $channel = 'auth';
        $baseUrl = AppConfig::getAppUrl();
        $link    = $baseUrl . '/confirm-account?token=' . urlencode($token);

        try {
            $sent = $this->mailer->send(
                $email,
                $username,
                'Confirmation de votre compte',
                'confirmation.html',
                ['username' => $username, 'link' => $link]
            );
        } catch (\Throwable $mailEx) {
            Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED, [
                'email' => $email, 'user_id' => $userId, 'exception' => $mailEx->getMessage(),
            ]);
            return false;
        }

        if (!$sent) {
            Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED, [
                'email' => $email, 'user_id' => $userId, 'reason' => 'mailer_returned_false',
            ]);
            return false;
        }
        return true;
    }

    /**
     * @param array{username:string,email:string} $old
     * @return array{errors:list<string|int>, old:array{username:string,email:string}}
     */
    private function handlePdoRegistrationException(\PDOException $pdoException, string $channel, string $email, string $username, array $old): array
    {
        $sqlState   = $pdoException->getCode();
        $driverCode = (is_array($pdoException->errorInfo ?? null) && isset($pdoException->errorInfo[1]) && is_int($pdoException->errorInfo[1])) ? $pdoException->errorInfo[1] : null;
        $msg        = (is_array($pdoException->errorInfo ?? null) && isset($pdoException->errorInfo[2]) && is_string($pdoException->errorInfo[2])) ? $pdoException->errorInfo[2] : $pdoException->getMessage();

        if ($sqlState === '23000' && $driverCode === 1062) {
            $index = strtolower($msg);
            if (str_contains($index, 'email') || str_contains($index, 'uniq_email') || str_contains($index, 'unique_email')) {
                Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_EMAIL_EXISTS, ['email' => $email, 'dup' => true]);
                return ['errors' => [ErrorCode::AUTH_EMAIL_EXISTS, ErrorCode::AUTH_PASSWORD_REENTER], 'old' => $old];
            }
            if (str_contains($index, 'username') || str_contains($index, 'uniq_username') || str_contains($index, 'unique_username')) {
                Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_USERNAME_EXISTS, ['username' => $username, 'dup' => true]);
                return ['errors' => [ErrorCode::AUTH_USERNAME_EXISTS, ErrorCode::AUTH_PASSWORD_REENTER], 'old' => $old];
            }
            Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_REGISTRATION_FAILED, [
                'reason' => 'duplicate_key', 'sqlstate' => $sqlState, 'driver' => $driverCode,
            ]);
            return ['errors' => [ErrorCode::AUTH_REGISTRATION_FAILED], 'old' => $old];
        }

        Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
            'reason' => 'pdo_exception', 'sqlstate' => $sqlState, 'driver' => $driverCode, 'exception' => $pdoException->getMessage(),
        ]);
        return ['errors' => [ErrorCode::AUTH_TECHNICAL_ERROR], 'old' => $old];
    }
}
