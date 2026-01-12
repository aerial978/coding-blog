<?php

namespace App\Service\Security;

use App\Core\AppConfig;
use App\Core\Contract\SqlHelperInterface;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Core\Mail\MailerInterface;
use App\Model\Contract\UserModelInterface;
use App\Model\Contract\UserTokenModelInterface;
use App\Model\Entity\UserEntity;
use App\Security\Contract\RegistrationThrottleServiceInterface;
use App\Security\Contract\TokenGeneratorInterface;
use App\Security\DisposableChecker;
use App\Security\PasswordBlacklist;
use App\Service\Security\Contract\RegistrationServiceInterface;
use App\Validation\Contract\FormValidatorInterface;
use Cocur\Slugify\Slugify;
use DateTimeImmutable;

final class RegistrationService implements RegistrationServiceInterface
{
    public function __construct(
        private FormValidatorInterface $validator,
        private UserModelInterface $userModel,
        private UserTokenModelInterface $userTokenModel,
        private Slugify $slugify,
        private MailerInterface $mailer,
        private TokenGeneratorInterface $tokenGen,
        private SqlHelperInterface $sqlHelper,
        private RegistrationThrottleServiceInterface $throttle,
        private PasswordBlacklist $passwordBlacklist,
        private DisposableChecker $disposableChecker,
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
            // Le flux principal est isolé ici pour abaisser la complexité de cette méthode.
            return $this->doRegisterFlow($form);
        } catch (\PDOException $e) {
            // Reconstruit un old “sûr” directement depuis $form (indépendant des variables locales)
            $safeOld = $this->safeOldFromForm($form);

            return $this->handlePdoRegistrationException(
                $e,
                $channel,
                $safeOld['email'],
                $safeOld['username'],
                $safeOld
            );
        } catch (\Throwable $e) {
            // Filet de sécurité global (inchangé)
            Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'reason'    => 'throwable',
                'exception' => $e->getMessage(),
            ]);

            $safeOld = $this->safeOldFromForm($form);
            return ['errors' => [ErrorCode::AUTH_TECHNICAL_ERROR], 'old' => $safeOld];
        }
    }

    /**
     * Flux “heureux” + gardes d’erreurs, linéarisé pour réduire la complexité cyclomatique.
     *
     * @param array<string,mixed> $form
     * @return array{
     *   ok?: true,
     *   errors?: list<string|int>,
     *   old?: array{username:string, email:string}
     * }
     */
    private function doRegisterFlow(array $form): array
    {
        $channel = 'auth';

        // 1) Lecture et normalisation des entrées
        [$username, $email, $password, $confirm, $old] = $this->sanitizeRegistrationForm($form);

        // 2) Validation “formelle” et collisions
        $errors = $this->validateRegistrationOrConflicts($username, $email, $password, $confirm);
        $err    = $this->outIfValidationErrors($errors, $old);
        if ($err !== null) {
            return $err;
        }

        // 3) Throttling métier d'inscription (IP + User-Agent + email)
        $ip        = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        $userAgent = is_string($_SERVER['HTTP_USER_AGENT'] ?? null) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown';

        $throttleResult = $this->throttle->checkQuota($ip);

        if (!$throttleResult['allowed']) {
            Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_REGISTRATION_QUOTA_EXCEEDED, [
                'email'  => $email,
                'ip'     => $ip,
                'reason' => (string) ($throttleResult['reason'] ?? ''),
            ]);

            return [
                'errors' => [ErrorCode::AUTH_REGISTRATION_QUOTA_EXCEEDED],
                'old'    => $old,
            ];
        }

        // 4) Préparer les artefacts d’inscription
        $art = $this->prepareRegistrationArtifacts($username, $password);
        $err = $this->outIfArtifactsNull($art, $old);
        if ($err !== null) {
            return $err;
        }

        /** @var array{hashedPassword:string,slug:string,token:string,confirmTokenHash:string,confirmExpiresAt:\DateTimeImmutable} $art */

        // 5) Transaction : création utilisateur + insertion du jeton
        $user   = $this->makeUserEntity($username, $email, $art['hashedPassword'], $art['slug']);
        $userId = $this->persistUserAndToken($user, $art['confirmTokenHash'], $art['confirmExpiresAt']);
        $err    = $this->outIfUserIdInvalid($userId, $old);
        if ($err !== null) {
            return $err;
        }

        // 6) Envoi de l’email de confirmation
        $sent = $this->trySendConfirmationEmail($email, $username, $art['token'], $userId);
        $err  = $this->outIfEmailNotSent($sent, $old);
        if ($err !== null) {
            return $err;
        }

        $this->throttle->recordSuccess(
            email: $email,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent,
        );

        return ['ok' => true];
    }

    /**
     * @param array<string,mixed> $form
     * @return array{username:string,email:string}
     */
    private function safeOldFromForm(array $form): array
    {
        $username = is_string($form['username'] ?? null) ? trim($form['username']) : '';
        $email    = is_string($form['email'] ?? null) ? trim($form['email']) : '';
        return ['username' => $username, 'email' => $email];
    }

    /**
     * @param list<string> $errors
     * @param array{username:string,email:string} $old
     * @return array{errors:list<string|int>,old:array{username:string,email:string}}|null
     */
    private function outIfValidationErrors(array $errors, array $old): ?array
    {
        if ($errors === []) {
            return null;
        }
        return ['errors' => $errors, 'old' => $old];
    }

    /**
     * @param array{hashedPassword:string,slug:string,token:string,confirmTokenHash:string,confirmExpiresAt:\DateTimeImmutable}|null $art
     * @param array{username:string,email:string} $old
     * @return array{errors:list<string|int>,old:array{username:string,email:string}}|null
     */
    private function outIfArtifactsNull(?array $art, array $old): ?array
    {
        if ($art !== null) {
            return null;
        }
        // Longueur de hash binaire invalide (comportement inchangé)
        return ['errors' => [ErrorCode::AUTH_TECHNICAL_ERROR], 'old' => $old];
    }

    /**
     * @param array{username:string,email:string} $old
     * @return array{errors:list<string|int>,old:array{username:string,email:string}}|null
     */
    private function outIfUserIdInvalid(int $userId, array $old): ?array
    {
        if ($userId > 0) {
            return null;
        }
        // Les logs sont déjà produits dans persistUserAndToken()
        return ['errors' => [ErrorCode::AUTH_TECHNICAL_ERROR], 'old' => $old];
    }

    /**
     * @param array{username:string,email:string} $old
     * @return array{errors:list<string|int>,old:array{username:string,email:string}}|null
     */
    private function outIfEmailNotSent(bool $sent, array $old): ?array
    {
        if ($sent) {
            return null;
        }
        return ['errors' => [ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED], 'old' => $old];
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

        if ($this->passwordBlacklist->isBlacklisted($password)) {
            // on log
            Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_PASSWORD_TOO_COMMON, [
                'email'    => $email,
                'username' => $username,
            ]);

            // on ajoute l’erreur métier
            $errors[] = ErrorCode::AUTH_PASSWORD_TOO_COMMON;
        }

        if ($this->disposableChecker->isDisposable($email)) {
            Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_REGISTRATION_EMAIL_DISPOSABLE, [
                'email'    => $email,
                'username' => $username,
            ]);
            $errors = [ErrorCode::AUTH_REGISTRATION_EMAIL_DISPOSABLE];
        }

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
        $out = $errors; // déjà une list<string>
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
     * Handle a PDOException raised during registration and map it to domain errors.
     *
     * @param array{username:string,email:string} $old
     * @return array{errors:list<string|int>, old:array{username:string,email:string}}
     */
    private function handlePdoRegistrationException(\PDOException $pdoException, string $channel, string $email, string $username, array $old): array
    {
        $details    = $this->extractPdoDetails($pdoException);
        $sqlState   = $details['sqlstate'];
        $driverCode = $details['driver'];
        $msg        = $details['message'];

        // Duplicate key (MySQL / MariaDB): SQLSTATE 23000 & driver error 1062
        if ($sqlState === '23000' && $driverCode === 1062) {
            $dupKind = $this->classifyDuplicateIndex($msg);

            if ($dupKind === 'email') {
                Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_EMAIL_EXISTS, [
                    'email' => $email, 'dup' => true,
                ]);
                return ['errors' => [ErrorCode::AUTH_EMAIL_EXISTS, ErrorCode::AUTH_PASSWORD_REENTER], 'old' => $old];
            }

            if ($dupKind === 'username') {
                Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_USERNAME_EXISTS, [
                    'username' => $username, 'dup' => true,
                ]);
                return ['errors' => [ErrorCode::AUTH_USERNAME_EXISTS, ErrorCode::AUTH_PASSWORD_REENTER], 'old' => $old];
            }

            // Duplicate key, but index not recognized (keep your original fallback)
            Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_REGISTRATION_FAILED, [
                'reason' => 'duplicate_key', 'sqlstate' => $sqlState, 'driver' => $driverCode,
            ]);
            return ['errors' => [ErrorCode::AUTH_REGISTRATION_FAILED], 'old' => $old];
        }

        // Any other PDO error → technical error
        Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
            'reason' => 'pdo_exception', 'sqlstate' => $sqlState, 'driver' => $driverCode, 'exception' => $pdoException->getMessage(),
        ]);
        return ['errors' => [ErrorCode::AUTH_TECHNICAL_ERROR], 'old' => $old];
    }

    /**
     * @return array{sqlstate:string|null, driver:int|null, message:string}
     */
    private function extractPdoDetails(\PDOException $pdoException): array
    {
        $code     = $pdoException->getCode(); // peut être string|int|null selon l’implémentation
        $sqlState = is_string($code) ? ($code !== '' ? $code : null) : null;

        $driver = (is_array($pdoException->errorInfo ?? null) && isset($pdoException->errorInfo[1]) && is_int($pdoException->errorInfo[1]))
            ? $pdoException->errorInfo[1]
            : null;

        $message = (is_array($pdoException->errorInfo ?? null) && isset($pdoException->errorInfo[2]) && is_string($pdoException->errorInfo[2]))
            ? $pdoException->errorInfo[2]
            : $pdoException->getMessage();

        return [
            'sqlstate' => $sqlState,
            'driver'   => $driver,
            'message'  => $message,
        ];
    }

    /**
     * Classify duplicate-key error index as 'email' | 'username' | null from a driver message.
     *
     * @return 'email'|'username'|null
     */
    private function classifyDuplicateIndex(string $message): ?string
    {
        $idx = strtolower($message);

        // Email-related unique index hints
        if (str_contains($idx, 'email') || str_contains($idx, 'uniq_email') || str_contains($idx, 'unique_email')) {
            return 'email';
        }

        // Username-related unique index hints
        if (str_contains($idx, 'username') || str_contains($idx, 'uniq_username') || str_contains($idx, 'unique_username')) {
            return 'username';
        }

        return null;
    }
}
