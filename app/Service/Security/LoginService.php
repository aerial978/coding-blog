<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Core\Contract\SessionInterface;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Model\Contract\UserModelInterface;
use App\Model\Entity\UserEntity;
use App\Service\Security\Contract\LoginServiceInterface;
use App\Service\Security\Contract\RememberMeServiceInterface;
use App\Validation\Contract\FormValidatorInterface;

final class LoginService implements LoginServiceInterface
{
    public function __construct(
        private FormValidatorInterface $validator,
        private UserModelInterface $userModel,
        private SessionInterface $session,
        private RememberMeServiceInterface $rememberMeService,
        // Extension prévue : throttle dédié login
        // private LoginThrottleServiceInterface $throttle,
    ) {
    }

    /**
     * @param array<string,mixed> $form
     * @return array{
     *   ok?: true,
     *   remember_me_token?: string,
     *   errors?: list<string|int>,
     *   old?: array{identifier:string, remember_me?: string}
     * }
     */
    public function login(array $form): array
    {
        $channel = 'auth';

        try {
            return $this->doLoginFlow($form);
        } catch (\Throwable $e) {
            Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'reason'    => 'login_throwable',
                'exception' => $e->getMessage(),
            ]);

            return [
                'errors' => [ErrorCode::AUTH_TECHNICAL_ERROR],
                'old'    => $this->safeOldFromForm($form),
            ];
        }
    }

    /**
     * @param array<string,mixed> $form
     * @return array{
     *   ok?: true,
     *   remember_me_token?: string,
     *   errors?: list<string|int>,
     *   old?: array{identifier:string, remember_me?: string}
     * }
     */
    private function doLoginFlow(array $form): array
    {
        $channel = 'auth';

        [$identifier, $password, $rememberMe, $old] = $this->sanitizeLoginForm($form);

        $errors = $this->validateLoginForm($identifier, $password);
        if ($errors !== []) {
            return ['errors' => $errors, 'old' => $old];
        }

        $client = $this->resolveClientContext();
        $user   = $this->findUserByIdentifier($identifier);

        $failure = $this->failIfUserMissing($user, $client['ip'], $old, $channel);
        if ($failure !== null) {
            return $failure;
        }

        /** @var UserEntity $user */
        $failure = $this->failIfPasswordInvalid($user, $password, $client['ip'], $old, $channel);
        if ($failure !== null) {
            return $failure;
        }

        $failure = $this->failIfUserInactive($user, $client['ip'], $old, $channel);
        if ($failure !== null) {
            return $failure;
        }

        $userId  = $this->extractUserId($user);
        $failure = $this->failIfUserIdInvalid($userId, $client['ip'], $old, $channel);
        if ($failure !== null) {
            return $failure;
        }

        $this->openAuthenticatedSession($userId, $client['ip'], $channel);

        return $this->buildSuccessResult($rememberMe, $userId, $client['ip'], $channel);
    }

    /**
     * @param array{identifier:string, remember_me?: string} $old
     * @return array{errors:list<string|int>, old:array{identifier:string, remember_me?: string}}|null
     */
    private function failIfUserMissing(?UserEntity $user, string $ip, array $old, string $channel): ?array
    {
        if ($user !== null) {
            return null;
        }

        Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_INVALID_CREDENTIALS, [
            'reason' => 'user_not_found',
            'ip'     => $ip,
        ]);

        return ['errors' => [ErrorCode::AUTH_INVALID_CREDENTIALS], 'old' => $old];
    }

    /**
     * @param array{identifier:string, remember_me?: string} $old
     * @return array{errors:list<string|int>, old:array{identifier:string, remember_me?: string}}|null
     */
    private function failIfPasswordInvalid(UserEntity $user, string $password, string $ip, array $old, string $channel): ?array
    {
        $hash = $this->extractPasswordHash($user);

        if ($hash !== '' && password_verify($password, $hash)) {
            return null;
        }

        Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_INVALID_CREDENTIALS, [
            'reason'  => 'bad_password',
            'ip'      => $ip,
            'user_id' => $this->extractUserId($user) ?: null,
        ]);

        return ['errors' => [ErrorCode::AUTH_INVALID_CREDENTIALS], 'old' => $old];
    }

    /**
     * @param array{identifier:string, remember_me?: string} $old
     * @return array{errors:list<string|int>, old:array{identifier:string, remember_me?: string}}|null
     */
    private function failIfUserInactive(UserEntity $user, string $ip, array $old, string $channel): ?array
    {
        $status = $this->extractUserStatus($user);

        if ($status === null || $status === 'active') {
            return null;
        }

        Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_INVALID_CREDENTIALS, [
            'reason'  => 'user_not_active',
            'status'  => $status,
            'ip'      => $ip,
            'user_id' => $this->extractUserId($user) ?: null,
        ]);

        return ['errors' => [ErrorCode::AUTH_INVALID_CREDENTIALS], 'old' => $old];
    }

    /**
     * @param array{identifier:string, remember_me?: string} $old
     * @return array{errors:list<string|int>, old:array{identifier:string, remember_me?: string}}|null
     */
    private function failIfUserIdInvalid(int $userId, string $ip, array $old, string $channel): ?array
    {
        if ($userId > 0) {
            return null;
        }

        Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
            'reason' => 'missing_user_id',
            'ip'     => $ip,
        ]);

        return ['errors' => [ErrorCode::AUTH_TECHNICAL_ERROR], 'old' => $old];
    }

    private function openAuthenticatedSession(int $userId, string $ip, string $channel): void
    {
        Logger::getLogger('app')->info('session_before_regenerate', [
            'session_id' => session_id(),
        ]);

        $this->session->regenerateAndDeleteOld();

        Logger::getLogger('app')->info('session_after_regenerate', [
            'session_id' => session_id(),
        ]);

        $this->session->set('user', [
            'id'    => $userId,
            'roles' => ['USER'],
        ]);

        Logger::logCodeAndGetMessage($channel, 'info', 'login_success', [
            'user_id' => $userId,
            'ip'      => $ip,
        ]);
    }

    /**
     * @param array<string,mixed> $form
     * @return array{identifier:string, remember_me?: string}
     */
    private function safeOldFromForm(array $form): array
    {
        $identifier = is_string($form['identifier'] ?? null) ? trim($form['identifier']) : '';
        $rememberMe = $this->normalizeRememberMeValue($form);

        $old = ['identifier' => $identifier];

        if ($rememberMe) {
            $old['remember_me'] = '1';
        }

        return $old;
    }

    /**
     * @param array<string, mixed> $form
     */
    private function trimmedStringField(array $form, string $key): string
    {
        $value = $form[$key] ?? null;

        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }

    /**
     * @param array<string, mixed> $form
     */
    private function rawStringField(array $form, string $key): string
    {
        $value = $form[$key] ?? null;

        if (!is_string($value)) {
            return '';
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $form
     */
    private function normalizeRememberMeValue(array $form): bool
    {
        $value = $form['remember_me'] ?? null;

        return $value === '1' || $value === 'on';
    }

    /**
     * @param array<string,mixed> $form
     * @return array{0:string,1:string,2:bool,3:array{identifier:string, remember_me?: string}}
     */
    private function sanitizeLoginForm(array $form): array
    {
        $identifier = $this->trimmedStringField($form, 'identifier');
        $password   = $this->rawStringField($form, 'password');
        $rememberMe = $this->normalizeRememberMeValue($form);

        $old = ['identifier' => $identifier];

        if ($rememberMe) {
            $old['remember_me'] = '1';
        }

        return [$identifier, $password, $rememberMe, $old];
    }

    /**
     * @return list<string|int>
     */
    private function validateLoginForm(string $identifier, string $password): array
    {
        /** @var list<string|int> $errors */
        $errors = $this->validator->validateLogin([
            'identifier' => $identifier,
            'password'   => $password,
        ]);

        return $errors;
    }

    /**
     * @return array{ip:string, userAgent:string}
     */
    private function resolveClientContext(): array
    {
        $ip = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        $ua = is_string($_SERVER['HTTP_USER_AGENT'] ?? null) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown';

        return ['ip' => $ip, 'userAgent' => $ua];
    }

    /**
     * @return UserEntity|null
     */
    private function findUserByIdentifier(string $identifier): ?UserEntity
    {
        $isEmail = filter_var($identifier, \FILTER_VALIDATE_EMAIL) !== false;

        if ($isEmail) {
            return $this->userModel->findAuthByEmail($identifier);
        }

        return $this->userModel->findAuthByUsername($identifier);
    }

    private function extractUserId(UserEntity $user): int
    {
        return (int) ($user->getUserId() ?? 0);
    }

    private function extractPasswordHash(UserEntity $user): string
    {
        return (string) ($user->getPassword() ?? '');
    }

    private function extractUserStatus(UserEntity $user): ?string
    {
        return $user->getStatus();
    }

    /**
     * @return array{ok:true, remember_me_token?: string}
     */
    private function buildSuccessResult(bool $rememberMe, int $userId, string $ip, string $channel): array
    {
        if (!$rememberMe) {
            return ['ok' => true];
        }

        $rememberMeToken = $this->rememberMeService->createRememberMeToken($userId);

        if ($rememberMeToken === null) {
            Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'reason'  => 'remember_me_token_creation_failed',
                'user_id' => $userId,
                'ip'      => $ip,
            ]);

            return ['ok' => true];
        }

        return [
            'ok'                => true,
            'remember_me_token' => $rememberMeToken,
        ];
    }
}
