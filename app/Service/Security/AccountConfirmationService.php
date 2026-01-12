<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Core\ErrorCode;
use App\Core\Logger;
use App\Model\UserTokenModel;
use App\Security\Contract\TokenGeneratorInterface;
use App\Service\Security\Contract\AccountConfirmationServiceInterface;

final class AccountConfirmationService implements AccountConfirmationServiceInterface
{
    public function __construct(
        private UserTokenModel $userTokenModel,
        private TokenGeneratorInterface $tokenGen
    ) {
    }

    /**
     * Confirm a user account from a “clear” token.
     *
     * @param string $token The raw confirmation token provided by the user.
     * @return array{error?:string, reason?:string} Empty array on success.
     */
    public function confirm(string $token): array
    {
        $channel = 'auth';
        try {
            // 1) Hashing + length validation
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

            // 2) Load token context from database
            $ctxOrErr = $this->loadContextOrError($hash, $channel);
            if (isset($ctxOrErr['error'])) {
                /** @var array{error:string,reason?:string} $out */
                $out = $ctxOrErr['error'];
                return $out;
            }

            /** @var array{row: array<string,mixed>} $ctxOrErr */
            $row = $ctxOrErr['row'];
            $ctx = $this->normalizeContext($row);

            // 3) Run all business guard checks
            $err = $this->firstGuardError($ctx, $channel);
            if ($err !== null) {
                return $err;
            }

            // 4) Atomic activation
            $err = $this->tryActivate($hash, $ctx['userId'], $channel);
            if ($err !== null) {
                return $err;
            }

            // 5) Success
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
     * Aggregate all guard checks into a single method to reduce cyclomatic complexity.
     *
     * @param array{userId:int,isActive:bool,isExpired:bool,isUsed:bool} $ctx
     * @param string $channel
     * @return array{error:string,reason?:string}|null Returns the first error found or null if all checks pass.
     */
    private function firstGuardError(array $ctx, string $channel): ?array
    {
        if ($ctx['isActive']) {
            Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_ALREADY_CONFIRMED, [
                'user_id' => $ctx['userId'],
                'reason'  => 'status_active',
            ]);
            return ['error' => (string) ErrorCode::AUTH_ALREADY_CONFIRMED];
        }

        if ($ctx['isExpired'] && $ctx['isUsed']) {
            Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, [
                'reason'  => 'expired_and_used_inconsistent_state',
                'user_id' => $ctx['userId'],
            ]);
            return ['error' => (string) ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, 'reason' => 'expired'];
        }

        if ($ctx['isExpired']) {
            Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, [
                'reason'  => 'expired_before_activation',
                'user_id' => $ctx['userId'],
            ]);
            return ['error' => (string) ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, 'reason' => 'expired'];
        }

        if ($ctx['isUsed']) {
            Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_CONFIRM_TOKEN_USED, [
                'user_id' => $ctx['userId'],
                'reason'  => 'token_used_user_still_inactive',
            ]);
            return ['error' => (string) ErrorCode::AUTH_CONFIRM_TOKEN_USED, 'reason' => 'used'];
        }

        return null;
    }

    /**
     * Hash and validate the provided token.
     *
     * @param string $token
     * @param string $channel
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
     * Load token context or return an error if not found.
     *
     * @param string $hash
     * @param string $channel
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

    /**
     * Normalize raw database row into a well-defined context array.
     *
     * @param array<string,mixed> $row
     * @return array{userId:int,isActive:bool,isExpired:bool,isUsed:bool}
     */
    private function normalizeContext(array $row): array
    {
        $userId     = $this->intFromNumeric($row, 'user_id', 0);
        $statusRaw  = $this->firstString($row, ['status', 'status_user', 'user_status']) ?? '';
        $statusNorm = strtolower(trim($statusRaw));
        $isActive   = ($statusNorm === 'active' || $statusNorm === '1');

        $isExpired  = $this->boolFromNumeric($row, 'is_expired');
        $isUsed     = $this->boolFromNumeric($row, 'used');

        return [
            'userId'    => $userId,
            'isActive'  => $isActive,
            'isExpired' => $isExpired,
            'isUsed'    => $isUsed,
        ];
    }

    /**
     * Return the first value that is a string among the provided keys, or null.
     *
     * @param array<string,mixed> $row
     * @param list<string> $keys
     */
    private function firstString(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $row[$key] ?? null;
            if (is_string($value)) {
                return $value;
            }
        }
        return null;
    }

    /**
     * Read a numeric-like field and cast to int, or default.
     *
     * @param array<string,mixed> $row
     */
    private function intFromNumeric(array $row, string $key, int $default = 0): int
    {
        $value = $row[$key] ?? null;
        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Read a numeric-like field (0/1) and cast to bool, default false.
     *
     * @param array<string,mixed> $row
     */
    private function boolFromNumeric(array $row, string $key): bool
    {
        $value = $row[$key] ?? null;
        return is_numeric($value) ? ((int) $value === 1) : false;
    }

    /**
     * Try to atomically activate the user account by token hash.
     *
     * @param string $hash
     * @param int $userId
     * @param string $channel
     * @return array{error:string,reason?:string}|null
     */
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
