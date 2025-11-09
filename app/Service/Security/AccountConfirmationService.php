<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Core\ErrorCode;
use App\Core\Logger;
use App\Model\UserTokenModel;
use App\Security\Contract\TokenGeneratorInterface;

final class AccountConfirmationService
{
    public function __construct(
        private UserTokenModel $userTokenModel,
        private TokenGeneratorInterface $tokenGen
    ) {
    }

    /**
     * Confirme un compte à partir d’un token “clair”.
     *
     * @return array{error?:string, reason?:string}  Vide en cas de succès.
     */
    public function confirm(string $token): array
    {
        $channel = 'auth';

        try {
            // 1) Hash + validation longueur
            $hashOrErr = $this->hashTokenOrError($token, $channel);
            
            if (isset($hashOrErr['error'])) {
                /** @var array{error:string,reason?:string} $out */
                $out = $hashOrErr['error'];
                return $out;
            }

            if (!isset($hashOrErr['hash'])) {
                return ['error' => (string) ErrorCode::AUTH_TECHNICAL_ERROR];
            }
            $hash = $hashOrErr['hash'];

            // 2) Chargement du contexte
            $ctxOrErr = $this->loadContextOrError($hash, $channel);

            if (isset($ctxOrErr['error'])) {
                /** @var array{error:string,reason?:string} $out */
                $out = $ctxOrErr['error'];
                return $out;
            }

            /** @var array{row: array<string,mixed>} $ctxOrErr */
            $row = $ctxOrErr['row'];
            $ctx = $this->normalizeContext($row);

            // 3) Chaîne de garde-fous métiers
            $err = $this->guardAlreadyActive($ctx['isActive'], $ctx['userId'], $channel);
            if ($err !== null) {
                return $err;
            }

            $err = $this->guardExpiredAndUsed($ctx['isExpired'], $ctx['isUsed'], $ctx['userId'], $channel);
            if ($err !== null) {
                return $err;
            }

            $err = $this->guardExpiredBeforeActivation($ctx['isExpired'], $ctx['userId'], $channel);
            if ($err !== null) {
                return $err;
            }

            $err = $this->guardUsedButInactive($ctx['isUsed'], $ctx['userId'], $channel);
            if ($err !== null) {
                return $err;
            }

            // 4) Activation atomique
            $err = $this->tryActivate($hash, $ctx['userId'], $channel);
            if ($err !== null) {
                return $err;
            }

            // 5) Succès
            Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_CONFIRMATION_SUCCESS, [
                'user_id' => $ctx['userId'],
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
     * @return array{
     *   hash?: string,
     *   error?: array{error:string,reason?:string}
     * }
     */
    private function hashTokenOrError(string $token, string $channel): array
    {
        $hash = $this->tokenGen->hashToken($token);
        if (strlen($hash) !== 32) {
            Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'reason' => 'invalid_binary_hash_length',
            ]);
            return ['error' => ['error' => (string) ErrorCode::AUTH_TECHNICAL_ERROR]];
        }
        return ['hash' => $hash];
    }

    /**
     * @return array{
     *   row?: array<string,mixed>,
     *   error?: array{error:string,reason?:string}
     * }
     */
    private function loadContextOrError(string $hash, string $channel): array
    {
        $row = $this->userTokenModel->findConfirmationContextByHash($hash);
        if (!$row) {
            Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, [
                'reason' => 'not_found',
            ]);
            return ['error' => ['error' => (string) ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, 'reason' => 'not_found']];
        }
        return ['row' => $row];
    }

    /** @param array<string,mixed> $row
     *  @return array{userId:int,isActive:bool,isExpired:bool,isUsed:bool}
     */
    private function normalizeContext(array $row): array
    {
        $userId    = isset($row['user_id']) && is_numeric($row['user_id']) ? (int)$row['user_id'] : 0;
        $statusRaw = '';
        if (isset($row['status']) && is_string($row['status'])) {
            $statusRaw = $row['status'];
        } elseif (isset($row['status_user']) && is_string($row['status_user'])) {
            $statusRaw = $row['status_user'];
        } elseif (isset($row['user_status']) && is_string($row['user_status'])) {
            $statusRaw = $row['user_status'];
        }
        $statusNorm = strtolower(trim($statusRaw));
        $isActive   = ($statusNorm === 'active' || $statusNorm === '1');

        $isExpired = isset($row['is_expired']) && is_numeric($row['is_expired']) ? ((int)$row['is_expired'] === 1) : false;
        $isUsed    = isset($row['used'])       && is_numeric($row['used']) ? ((int)$row['used'] === 1) : false;

        return [
            'userId'    => $userId,
            'isActive'  => $isActive,
            'isExpired' => $isExpired,
            'isUsed'    => $isUsed,
        ];
    }

    /** @return array{error:string,reason?:string}|null */
    private function guardAlreadyActive(bool $isActive, int $userId, string $channel): ?array
    {
        if ($isActive) {
            Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_ALREADY_CONFIRMED, [
                'user_id' => $userId,
                'reason'  => 'status_active',
            ]);
            return ['error' => (string) ErrorCode::AUTH_ALREADY_CONFIRMED];
        }
        return null;
    }

    /** @return array{error:string,reason?:string}|null */
    private function guardExpiredAndUsed(bool $isExpired, bool $isUsed, int $userId, string $channel): ?array
    {
        if ($isExpired && $isUsed) {
            Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, [
                'reason'  => 'expired_and_used_inconsistent_state',
                'user_id' => $userId,
            ]);
            return ['error' => (string) ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, 'reason' => 'expired'];
        }
        return null;
    }

    /** @return array{error:string,reason?:string}|null */
    private function guardExpiredBeforeActivation(bool $isExpired, int $userId, string $channel): ?array
    {
        if ($isExpired) {
            Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, [
                'reason'  => 'expired_before_activation',
                'user_id' => $userId,
            ]);
            return ['error' => (string) ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, 'reason' => 'expired'];
        }
        return null;
    }

    /** @return array{error:string,reason?:string}|null */
    private function guardUsedButInactive(bool $isUsed, int $userId, string $channel): ?array
    {
        if ($isUsed) {
            Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_CONFIRM_TOKEN_USED, [
                'user_id' => $userId,
                'reason'  => 'token_used_user_still_inactive',
            ]);
            return ['error' => (string) ErrorCode::AUTH_CONFIRM_TOKEN_USED, 'reason' => 'used'];
        }
        return null;
    }

    /** @return array{error:string,reason?:string}|null */
    private function tryActivate(string $hash, int $userId, string $channel): ?array
    {
        $ok = $this->userTokenModel->activateByHash($hash);
        if (!$ok) {
            Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'reason'  => 'activate_failed_sql_guard',
                'user_id' => $userId,
            ]);
            return ['error' => (string) ErrorCode::AUTH_TECHNICAL_ERROR, 'reason' => 'activate_failed'];
        }
        return null;
    }
}
