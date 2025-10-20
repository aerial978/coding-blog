<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\AppConfig;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Core\Mail\MailerInterface;
use App\Core\SqlHelper;
use App\Model\Entity\UserEntity;
use App\Model\UserModel;
use App\Model\UserTokenModel;
use App\Security\Contract\TokenGeneratorInterface;
use App\Service\Contract\SecurityServiceInterface;
use App\Validation\FormValidator;
use Cocur\Slugify\Slugify;
use DateTimeImmutable;

/**
 * Implements user registration, account confirmation, and confirmation-resend flows.
 *
 * This service coordinates validation, persistence, token generation/storage, and
 * email delivery around user onboarding. It is transaction-aware for operations
 * that must be committed atomically (e.g., user creation + token insertion).
 */
final class SecurityService implements SecurityServiceInterface
{
    private FormValidator $validator;
    private UserModel $userModel;
    private UserTokenModel $userTokenModel;
    private Slugify $slugify;
    private MailerInterface $mailer;
    private TokenGeneratorInterface $tokenGen;
    /**
         * Constructor.
         *
         * Wires all collaborators required for registration and confirmation workflows.
         *
         * @param FormValidator $validator Input validation service for registration fields.
         * @param UserModel $userModel Data access layer for user records.
         * @param UserTokenModel $userTokenModel Data access layer for user confirmation tokens.
         * @param Slugify $slugify Utility to generate URL-friendly slugs from usernames.
         * @param MailerInterface $mailer Email sender used for confirmation emails.
         * @param TokenGeneratorInterface $tokenGen Secure token generator (random + hashing).
         * @param SqlHelper $sqlHelper SQL helper providing transaction operations and queries.
         */
    public function __construct(FormValidator $validator, UserModel $userModel, UserTokenModel $userTokenModel, Slugify $slugify, MailerInterface $mailer, TokenGeneratorInterface $tokenGen, private SqlHelper $sqlHelper)
    {
        $this->validator      = $validator;
        $this->userModel      = $userModel;
        $this->userTokenModel = $userTokenModel;
        $this->slugify        = $slugify;
        $this->mailer         = $mailer;
        $this->tokenGen       = $tokenGen;
    }

    /**
     * Registers a new user and sends a confirmation email.
     *
     * Steps:
     *  1) Validate input (username, email, password).
     *  2) Check for existing username/email conflicts.
     *  3) Begin transaction: create user and persist confirmation token.
     *  4) Commit and send confirmation email; otherwise roll back on failure.
     *
     * @param array<string, mixed> $form
     *     Raw registration form data (username, email, password, confirm_password).
     *
     * @return array<string, mixed>
     *     On success: ['ok' => true].
     *     On failure: ['errors' => string[], 'old' => array{username:string,email:string}]
     *     providing error codes and previously entered (safe) values.
     */
    public function register(array $form): array
    {
        $channel = 'auth';
        // Pour l’instant, renvoie toujours un tableau succès
        $username = is_string($form['username'] ?? null) ? trim($form['username']) : '';
        $email    = is_string($form['email'] ?? null) ? trim($form['email']) : '';
        $password = is_string($form['password'] ?? null) ? $form['password'] : '';
        $confirm  = is_string(($form['confirm_password'] ?? null)) ? $form['confirm_password'] : '';
        $old      = ['username' => $username, 'email' => $email];
        try {
            // 1) Validation
            $errors = $this->validator->validateRegistration([
                'username'         => $username,
                'email'            => $email,
                'password'         => $password,
                'confirm_password' => $confirm,
            ]);
            // ok
            if ($this->userModel->findOneByUsername($username)) {
                Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_USERNAME_EXISTS, [
                    'username' => $username
                ]);
                $errors = [ErrorCode::AUTH_USERNAME_EXISTS, ErrorCode::AUTH_PASSWORD_REENTER];
            }

            if ($this->userModel->findOneByEmail($email)) {
                Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_EMAIL_EXISTS, [
                    'email'    => $email,
                ]);
                $errors = [ErrorCode::AUTH_EMAIL_EXISTS, ErrorCode::AUTH_PASSWORD_REENTER];
            }

            if (!empty($errors)) {
                return ['errors' => $errors, 'old' => $old];
            }

            // 2) Préparation des données
            $hashedPassword        = password_hash($password, PASSWORD_ARGON2I);
            $slug                  = $this->slugify->slugify($username);
            $token                 = $this->tokenGen->generateUrlSafeToken(32);
            $confirmTokenHash      = $this->tokenGen->hashToken($token);

            Logger::logCodeAndGetMessage($channel, 'auth', 'token clair', [
                        'token' => $token
            ]);

            if (strlen($confirmTokenHash) !== 32) {
                Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                    'reason' => 'invalid_binary_hash_length',
                ]);
                return ['errors' => [ErrorCode::AUTH_TECHNICAL_ERROR], 'old' => $old];
            }

            $confirmExpiresAt = (new DateTimeImmutable('+24 hours'));
            // 3) Transaction : création utilisateur + insertion du jeton
            $this->sqlHelper->beginTransaction();
            try {
                $user = (new UserEntity())
                    ->setUsername($username)
                    ->setSlug($slug)
                    ->setEmail($email)
                    ->setPassword($hashedPassword);
                $userId = $this->userModel->createUser($user);
                // ok
                if ($userId <= 0) {
                    Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_REGISTRATION_FAILED, [
                        'email'    => $email,
                        'username' => $username,
                    ]);
                    return ['errors' => [ErrorCode::AUTH_REGISTRATION_FAILED], 'old' => $old];
                }

                // Insère le jeton en VARBINARY(32)
                $ok = $this->userTokenModel->createConfirmationToken(
                    $userId,
                    $confirmTokenHash, // 32 bytes
                    $confirmExpiresAt
                );
                if (!$ok) {
                    $this->sqlHelper->rollBack();
                    Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                        'reason'  => 'create_confirmation_token_failed',
                        'user_id' => $userId,
                        ]);
                    return ['errors' => [ErrorCode::AUTH_TECHNICAL_ERROR], 'old' => $old];
                }

                $this->sqlHelper->commit();
            } catch (\Throwable $txe) {
                $this->sqlHelper->rollBack();
                Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'reason'    => 'transaction_exception',
                'exception' => $txe->getMessage(),
                ]);
                return ['errors' => [ErrorCode::AUTH_TECHNICAL_ERROR], 'old' => $old];
            }

            // 4) Envoi e-mail de confirmation
            $baseUrl          = AppConfig::getAppUrl();
            $confirmationLink = $baseUrl . '/confirm-account?token=' . urlencode($token);
            $vars             = [
                'username' => $username,
                'link'     => $confirmationLink
            ];
            try {
                $sent = $this->mailer->send($email, $username, 'Confirmation de votre compte', 'confirmation.html', $vars);
            } catch (\Throwable $mailEx) {
                Logger::logCodeAndGetMessage('auth', 'error', ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED, [
                'email'     => $email,
                'user_id'   => $userId,
                'exception' => $mailEx->getMessage(), // <- cause réseau/SMTP, indispensable pour diagnostiquer
                ]);
                return ['errors' => [ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED], 'old' => $old];
            }

            // ok
            if (!$sent) {
                Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED, [
                'email'   => $email,
                'user_id' => $userId,
                'reason'  => 'mailer_returned_false'
                ]);
                return ['errors' => [ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED], 'old' => $old];
            }

            return ['ok' => true];
        } catch (\PDOException $e) {
            // MySQL duplicate key = 1062, SQLSTATE 23000
            $sqlState   = $e->getCode();
            $driverCode = (is_array($e->errorInfo ?? null) && isset($e->errorInfo[1]) && is_int($e->errorInfo[1]))
            ? $e->errorInfo[1]
            : null;
            $msg        = (is_array($e->errorInfo ?? null) && isset($e->errorInfo[2]) && is_string($e->errorInfo[2]))
                ? $e->errorInfo[2]
                : $e->getMessage();
            if ($sqlState === '23000' && $driverCode === 1062) {
                // Essaie d’identifier l’index unique (email vs username) dans le message
                $index = strtolower($msg);
                if (str_contains($index, 'email') || str_contains($index, 'uniq_email') || str_contains($index, 'unique_email')) {
                    Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_EMAIL_EXISTS, ['email' => $email, 'dup' => true]);
                    return ['errors' => [ErrorCode::AUTH_EMAIL_EXISTS, ErrorCode::AUTH_PASSWORD_REENTER], 'old' => $old];
                }
                if (str_contains($index, 'username') || str_contains($index, 'uniq_username') || str_contains($index, 'unique_username')) {
                    Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_USERNAME_EXISTS, ['username' => $username, 'dup' => true]);
                    return ['errors' => [ErrorCode::AUTH_USERNAME_EXISTS, ErrorCode::AUTH_PASSWORD_REENTER],'old' => $old];
                }
                // Indéterminé → message générique
                Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_REGISTRATION_FAILED, [
                    'reason' => 'duplicate_key', 'sqlstate' => $sqlState, 'driver' => $driverCode,
                ]);
                return ['errors' => [ErrorCode::AUTH_REGISTRATION_FAILED], 'old' => $old];
            }

            // Autres erreurs PDO
            Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'reason' => 'pdo_exception', 'sqlstate' => $sqlState, 'driver' => $driverCode, 'exception' => $e->getMessage(),
            ]);
            return ['errors' => [ErrorCode::AUTH_TECHNICAL_ERROR], 'old' => $old];
        } catch (\Throwable $e) {
            // Filets de sécurité
            Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'reason' => 'throwable', 'exception' => $e->getMessage(),
            ]);
            return ['errors' => [ErrorCode::AUTH_TECHNICAL_ERROR], 'old' => $old];
        }
    }

    /**
    * Confirms an account using the provided confirmation token.
    *
    * Steps:
    *  1) Hash the provided token (binary 32 bytes expected).
    *  2) Retrieve token context and guard against invalid/expired/used tokens.
    *  3) Activate the user if the token is valid and not expired.
    *
    * @param string $token
    *     The raw confirmation token received from the user (URL parameter or form).
    *
    * @return array<string, mixed>
    *     Empty array on success.
    *     On failure: ['error' => ErrorCode::<...>, 'reason' => string?]
    *     describing the specific guard that failed.
    */
    public function confirmAccount(string $token): array
    {
        $channel           = 'auth';
        try {
            // 1) Hash binaire 32 octets (VARBINARY(32) en BDD)
            $hash = $this->tokenGen->hashToken($token);
            if (strlen($hash) !== 32) {
                Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                    'reason' => 'invalid_binary_hash_length',
                ]);
                return ['error' => ErrorCode::AUTH_TECHNICAL_ERROR];
            }

            // 2) Lire l'état du token en SQL (fiable), Si aucune ligne ne correspond, on considère le lien invalide/altéré et on s’arrête.
            $row = $this->userTokenModel->findConfirmationContextByHash($hash);
            if (!$row) {
                Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, [
                'reason' => 'not_found',
                ]);
                return ['error' => ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, 'reason' => 'not_found'];
            }

            // Récupération typée
            $userId = isset($row['user_id']) && is_numeric($row['user_id']) ? (int)$row['user_id'] : 0;

            $status = '';
            if (isset($row['status']) && is_string($row['status'])) {
                $status = $row['status'];
            } elseif (isset($row['status_user']) && is_string($row['status_user'])) {
                $status = $row['status_user'];
            } elseif (isset($row['user_status']) && is_string($row['user_status'])) {
                $status = $row['user_status'];
            }

            $used      = isset($row['used'])       && is_numeric($row['used']) ? (int)$row['used'] : 0;
            $isExpired = isset($row['is_expired']) && is_numeric($row['is_expired']) ? (int)$row['is_expired'] : 0;

            $statusNorm = strtolower(trim($status));
            $isActive   = ($statusNorm === 'active' || $statusNorm === '1');

            // 3) Déjà actif -> message convivial (statut texte 'active' OU numérique '1')
            if ($isActive) {
                Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_ALREADY_CONFIRMED, [
                'user_id' => $userId,
                'reason'  => 'status_active',
                ]);
                return ['error' => ErrorCode::AUTH_ALREADY_CONFIRMED];
            }

            if ($isExpired === 1 && $used === 1) {
                Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, [
                'reason'  => 'expired_and_used_inconsistent_state',
                'user_id' => $userId,
                ]);
                return ['error' => ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, 'reason' => 'expired'];
            }

            // 4) Jeton expiré avant activation (premier clic trop tard)
            if ($isExpired === 1 && $used === 0) {
                Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, [
                'reason'  => 'expired_before_activation',
                'user_id' => $userId,
                ]);
                return ['error' => ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, 'reason' => 'expired'];
            }

            // 5) Jeton déjà utilisé mais utilisateur PAS encore actif -> message dédié
            if ($used === 1) {
                /** @var array<string, scalar|\Stringable|array<mixed>|null> $ctx */
                $ctx = [
                    'user_id' => $userId,
                    'reason'  => 'token_used_user_still_inactive',
                ];
                Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_CONFIRM_TOKEN_USED, $ctx);
                return ['error' => ErrorCode::AUTH_CONFIRM_TOKEN_USED, 'reason' => 'used'];
            }

            // 6) Premier clic valide -> tentative d’activation
            $ok = $this->userTokenModel->activateByHash($hash);
            if (!$ok) {
                Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'reason'  => 'activate_failed_sql_guard',
                'user_id' => $userId,
                ]);
                return ['error' => ErrorCode::AUTH_TECHNICAL_ERROR, 'reason' => 'activate_failed'];
            }

            // Succès
            Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_CONFIRMATION_SUCCESS, [
                'user_id' => $userId,
            ]);
            return [];
        } catch (\Throwable $e) {
            Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'exception' => $e->getMessage(),
            ]);
            return ['error' => ErrorCode::AUTH_TECHNICAL_ERROR];
        }
    }

    /**
     * Resends an account confirmation email for an inactive user.
     *
     * The flow is privacy-preserving (anti-enumeration): unknown emails
     * result in a generic success response. For inactive users, a new token
     * is generated, stored, and emailed to the registered address.
     *
     * @param string $email
     *     The email address to look up and send the confirmation link to.
     *
     * @return array<string, mixed>
     *     Empty array on generic/success paths.
     *     On failure: ['error' => ErrorCode::<...>] describing the error.
     */
    public function resendConfirmation(string $email): array
    {
        $channel = 'auth';
        $email   = trim($email);
        try {
            // 1) Recherche utilisateur par email
            $user = $this->userModel->findOneByEmail($email);
            // Anti-enumeration: on renvoie succès "générique" même si l'email est inconnu
            if (!$user) {
                Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_RESEND_EMAIL_SENT, [
                    'email'  => $email,
                    'reason' => 'user_not_found',
                ]);
                return [];
                // message générique côté UI
            }

            // 2) Déjà actif ? -> pas de renvoi
            $status   = trim((string)($user->getStatus() ?? ''));
            $isActive = (strcasecmp($status, 'active') === 0) || ($status === '1');
            if ($isActive) {
                Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_ALREADY_CONFIRMED, [
                    'user_id' => $user->getUserId(),
                    'email'   => $email,
                ]);
                return ['error' => ErrorCode::AUTH_ALREADY_CONFIRMED];
            }

            // 3) Générer un nouveau token + persist dans user_token
            $token   = $this->tokenGen->generateUrlSafeToken(32);
            $hashBin = $this->tokenGen->hashToken($token);
            Logger::logCodeAndGetMessage($channel, 'auth', 'token clair', [
                'token' => $token,
            ]);
            if (strlen($hashBin) !== 32) {
                Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                                'reason' => 'invalid_binary_hash_length',
                                'email'  => $email,
                ]);
                return ['error' => ErrorCode::AUTH_TECHNICAL_ERROR];
            }

            $expiresAt = new DateTimeImmutable('+24 hours');
            $ok        = $this->userTokenModel->createConfirmationToken((int)$user->getUserId(), $hashBin, $expiresAt);
            if (!$ok) {
                Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                    'user_id' => $user->getUserId() ?? null,
                    'reason'  => 'set_confirmation_token_failed',
                ]);
                return ['error' => ErrorCode::AUTH_TECHNICAL_ERROR];
            }

            // 4) Envoi email
            $baseUrl          = AppConfig::getAppUrl();
            $confirmationLink = $baseUrl . '/confirm-account?token=' . urlencode($token);
            try {
                $sent = $this->mailer->send($email, $user->getUsername() ?? '', 'Confirmation de votre compte', 'confirmation.html', [
                    'username' => $user->getUsername() ?? '',
                    'link'     => $confirmationLink,
                ]);
            } catch (\Throwable $mailEx) {
                Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED, [
                'user_id'   => $user->getUserId() ?? null,
                'email'     => $email,
                'exception' => $mailEx->getMessage(),
                ]);
                return ['error' => ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED];
            }

            if (!$sent) {
                Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED, [
                'user_id' => $user->getUserId(),
                'email'   => $email,
                'reason'  => 'mailer_returned_false',
                ]);
                return ['error' => ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED];
            }

            // 5) Succès
            Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_RESEND_EMAIL_SENT, [
                'user_id' => $user->getUserId() ?? null,
                'email'   => $email,
            ]);
            return [];
        } catch (\Throwable $e) {
            Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'exception' => $e->getMessage(),
                'email'     => $email,
            ]);
            return ['error' => ErrorCode::AUTH_TECHNICAL_ERROR];
        }
    }
}
