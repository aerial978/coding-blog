<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Core\Contract\SessionInterface;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Model\Contract\UserTokenModelInterface;
use App\Security\Contract\TokenGeneratorInterface;
use App\Service\Security\Contract\RememberMeServiceInterface;
use DateTimeImmutable;
use Throwable;

final class RememberMeService implements RememberMeServiceInterface
{
    private const TTL_DAYS = 30;

    public function __construct(
        private UserTokenModelInterface $userTokenModel,
        private TokenGeneratorInterface $tokenGenerator,
        private SessionInterface $session,
    ) {
    }

    public function createRememberMeToken(int $userId): ?string
    {
        if ($userId <= 0) {
            Logger::logCodeAndGetMessage('auth', 'warning', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'reason'  => 'remember_me_invalid_user_id',
                'user_id' => $userId,
            ]);

            return null;
        }

        try {
            $rawToken   = $this->tokenGenerator->generateUrlSafeToken();
            $tokenHash  = $this->tokenGenerator->hashToken($rawToken);
            $expiresAt  = new DateTimeImmutable('+' . self::TTL_DAYS . ' days');
            $persisted  = $this->userTokenModel->createRememberMeToken($userId, $tokenHash, $expiresAt);

            if (!$persisted) {
                Logger::logCodeAndGetMessage('auth', 'warning', ErrorCode::AUTH_TECHNICAL_ERROR, [
                    'reason'  => 'remember_me_token_not_persisted',
                    'user_id' => $userId,
                ]);

                return null;
            }

            Logger::getLogger('auth')->info('remember_me_token_created', [
                'user_id'    => $userId,
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            ]);

            return $rawToken;
        } catch (Throwable $e) {
            Logger::logCodeAndGetMessage('auth', 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'reason'    => 'remember_me_create_throwable',
                'user_id'   => $userId,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function restoreSessionFromToken(string $rawToken): bool
    {
        $rawToken = trim($rawToken);

        if ($rawToken === '') {
            return false;
        }

        try {
            $tokenHash = $this->tokenGenerator->hashToken($rawToken);
            $context   = $this->userTokenModel->findRememberMeContextByHash($tokenHash);

            if (!is_array($context)) {
                Logger::getLogger('auth')->info('remember_me_restore_failed', [
                    'reason' => 'token_not_found',
                ]);

                return false;
            }

            $userId = $this->extractUserId($context);

            if (!$this->isRestorableContext($context, $userId)) {
                return false;
            }

            $this->session->regenerateAndDeleteOld();
            $this->session->set('user', [
                'id'    => $userId,
                'roles' => ['USER'],
            ]);

            Logger::getLogger('auth')->info('remember_me_restore_success', [
                'user_id' => $userId,
            ]);

            return true;
        } catch (Throwable $e) {
            Logger::logCodeAndGetMessage('auth', 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'reason'    => 'remember_me_restore_throwable',
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function invalidateRememberMeForUser(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        try {
            $invalidated = $this->userTokenModel->invalidateRememberMeToken($userId);

            Logger::getLogger('auth')->info('remember_me_token_invalidated', [
                'user_id'     => $userId,
                'invalidated' => $invalidated,
            ]);

            return $invalidated;
        } catch (Throwable $e) {
            Logger::logCodeAndGetMessage('auth', 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'reason'    => 'remember_me_invalidate_throwable',
                'user_id'   => $userId,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function isRestorableContext(array $context, int $userId): bool
    {
        if ($userId <= 0) {
            Logger::logCodeAndGetMessage('auth', 'warning', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'reason' => 'remember_me_missing_user_id',
            ]);

            return false;
        }

        if ($this->isTokenUsed($context)) {
            Logger::getLogger('auth')->info('remember_me_restore_failed', [
                'reason'  => 'token_used',
                'user_id' => $userId,
            ]);

            return false;
        }

        if ($this->isTokenExpired($context)) {
            $this->invalidateRememberMeForUser($userId);

            Logger::getLogger('auth')->info('remember_me_restore_failed', [
                'reason'  => 'token_expired',
                'user_id' => $userId,
            ]);

            return false;
        }

        if (!$this->isUserActive($context)) {
            $this->invalidateRememberMeForUser($userId);

            Logger::getLogger('auth')->info('remember_me_restore_failed', [
                'reason'      => 'user_not_active',
                'user_id'     => $userId,
                'user_status' => $context['user_status'] ?? null,
            ]);

            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function extractUserId(array $context): int
    {
        $userId = $context['user_id'] ?? null;

        return is_numeric($userId) ? (int) $userId : 0;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function isTokenUsed(array $context): bool
    {
        $used = $context['used'] ?? null;

        if (is_int($used)) {
            return $used === 1;
        }

        if (is_string($used)) {
            return $used === '1';
        }

        if (is_bool($used)) {
            return $used;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function isTokenExpired(array $context): bool
    {
        $expired = $context['is_expired'] ?? null;

        if (is_int($expired)) {
            return $expired === 1;
        }

        if (is_string($expired)) {
            return $expired === '1';
        }

        if (is_bool($expired)) {
            return $expired;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function isUserActive(array $context): bool
    {
        $status = $context['user_status'] ?? null;

        return $status === null || $status === 'active';
    }
}
