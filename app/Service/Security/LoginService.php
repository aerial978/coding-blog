<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Core\Contract\SessionInterface;
use App\Model\Entity\UserEntity;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Model\Contract\UserModelInterface;
use App\Service\Security\Contract\LoginServiceInterface;
use App\Validation\Contract\FormValidatorInterface;

final class LoginService implements LoginServiceInterface
{
    public function __construct(
        private FormValidatorInterface $validator,
        private UserModelInterface $userModel,
        private SessionInterface $session,
        // Extension prévue : throttle dédié login
        // private LoginThrottleServiceInterface $throttle,
    ) {
    }

    /**
     * @param array<string,mixed> $form
     * @return array{
     *   ok?: true,
     *   errors?: list<string|int>,
     *   old?: array{identifier:string}
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
     *   errors?: list<string|int>,
     *   old?: array{identifier:string}
     * }
     */
    private function doLoginFlow(array $form): array
    {
        $channel = 'auth';

        // 1) Lecture / normalisation
        [$identifier, $password, $old] = $this->sanitizeLoginForm($form);

        // 2) Validation formelle
        $errors = $this->validateLoginForm($identifier, $password);
        if ($errors !== []) {
            return ['errors' => $errors, 'old' => $old];
        }

        // 3) Contexte client
        $client = $this->resolveClientContext();

        // 4) Throttling (à brancher)
        // if (!$this->throttle->isAllowed($client['ip'], $identifier)) {
        //     Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_LOGIN_QUOTA_EXCEEDED, [
        //         'ip' => $client['ip'],
        //         'identifier' => $identifier !== '' ? hash('sha256', mb_strtolower($identifier)) : null,
        //     ]);
        //     return ['errors' => [ErrorCode::AUTH_LOGIN_QUOTA_EXCEEDED], 'old' => $old];
        // }

        // 5) Résolution utilisateur
        $user = $this->findUserByIdentifier($identifier);

        // 6) Anti-énumération
        if ($user === null) {
            Logger::logCodeAndGetMessage($channel, 'info', ErrorCode:: AUTH_INVALID_CREDENTIALS, [
                'reason' => 'user_not_found',
                'ip'     => $client['ip'],
            ]);

            return ['errors' => [ErrorCode:: AUTH_INVALID_CREDENTIALS], 'old' => $old];
        }

        $hash = $this->extractPasswordHash($user);
        if ($hash === '' || !password_verify($password, $hash)) {
            Logger::logCodeAndGetMessage($channel, 'info', ErrorCode:: AUTH_INVALID_CREDENTIALS, [
                'reason'  => 'bad_password',
                'ip'      => $client['ip'],
                'user_id' => $this->extractUserId($user) ?: null,
            ]);

            return ['errors' => [ErrorCode:: AUTH_INVALID_CREDENTIALS], 'old' => $old];
        }

        // 7) Optionnel : statut
        $status = $this->extractUserStatus($user);
        if ($status !== null && $status !== 'active') {
            Logger::logCodeAndGetMessage($channel, 'info', ErrorCode:: AUTH_INVALID_CREDENTIALS, [
                'reason'  => 'user_not_active',
                'status'  => $status,
                'ip'      => $client['ip'],
                'user_id' => $this->extractUserId($user) ?: null,
            ]);

            return ['errors' => [ErrorCode:: AUTH_INVALID_CREDENTIALS], 'old' => $old];
        }

        // 8) Session (alignée avec SessionAuthChecker)
        $userId = $this->extractUserId($user);
        if ($userId <= 0) {
            Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'reason' => 'missing_user_id',
                'ip'     => $client['ip'],
            ]);

            return ['errors' => [ErrorCode::AUTH_TECHNICAL_ERROR], 'old' => $old];
        }

        Logger::getLogger('app')->info('session_before_regenerate', [
            'session_id' => session_id(),
        ]);

        $this->session->regenerateAndDeleteOld();

        Logger::getLogger('app')->info('session_after_regenerate', [
            'session_id' => session_id(),
        ]);

        // IMPORTANT : adaptez la clé si votre AuthChecker attend autre chose
        $this->session->set('user', [
            'id' => $userId,
            'roles' => ['USER'],
        ]);

        Logger::logCodeAndGetMessage($channel, 'info', 'login_success', [
            'user_id' => $userId,
            'ip'      => $client['ip'],
        ]);

        // 9) Throttle success (si branché)
        // $this->throttle->recordSuccess($client['ip'], $userId, $client['userAgent']);

        return ['ok' => true];
    }

    /**
     * @param array<string,mixed> $form
     * @return array{identifier:string}
     */
    private function safeOldFromForm(array $form): array
    {
        $identifier = is_string($form['identifier'] ?? null) ? trim($form['identifier']) : '';
        return ['identifier' => $identifier];
    }

    private function strOrEmptyField(array $form, string $key, bool $trim = true): string
    {
        $val = $form[$key] ?? null;
        if (!is_string($val)) {
            return '';
        }
        return $trim ? trim($val) : $val;
    }

    /**
     * @param array<string,mixed> $form
     * @return array{0:string,1:string,2:array{identifier:string}}
     */
    private function sanitizeLoginForm(array $form): array
    {
        $identifier = $this->strOrEmptyField($form, 'identifier', true);
        $password   = $this->strOrEmptyField($form, 'password', false);

        return [$identifier, $password, ['identifier' => $identifier]];
    }

    /**
     * @return list<string|int>
     */
    private function validateLoginForm(string $identifier, string $password): array
    {
        if (method_exists($this->validator, 'validateLogin')) {
            /** @var list<string|int> $errors */
            $errors = $this->validator->validateLogin([
                'identifier' => $identifier,
                'password'   => $password,
            ]);
            return $errors;
        }

        // Fallback minimal
        if ($identifier === '' || $password === '') {
            return [ErrorCode:: AUTH_INVALID_CREDENTIALS];
        }

        return [];
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
}
