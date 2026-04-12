<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Core\ErrorCode;
use App\Core\Logger;
use App\Model\Contract\UserTokenModelInterface;
use App\Security\Contract\TokenGeneratorInterface;
use App\Service\Security\Contract\ResetPasswordServiceInterface;
use App\Validation\Contract\FormValidatorInterface;

final class ResetPasswordService implements ResetPasswordServiceInterface
{
    public function __construct(
        private FormValidatorInterface $validator,
        private UserTokenModelInterface $userTokenModel,
        private TokenGeneratorInterface $tokenGen,
    ) {
    }

    public function validateResetToken(string $token): array
    {
        $token = trim($token);

        if ($token === '') {
            return $this->invalidResetTokenResult();
        }

        $hashBin = $this->tokenGen->hashToken($token);
        $ctx     = $this->userTokenModel->findPasswordResetContextByHash($hashBin);

        $this->logValidateResetTokenDebug($hashBin, $ctx);

        if ($this->isInvalidResetContext($ctx)) {
            return $this->invalidResetTokenResult();
        }

        return [
            'ok'      => true,
            'context' => $ctx,
            'hash'    => $hashBin,
        ];
    }

    /**
     * @param array<string, mixed>|null $ctx
     */
    private function isInvalidResetContext(?array $ctx): bool
    {
        if ($ctx === null) {
            return true;
        }

        if (!empty($ctx['is_expired'])) {
            return true;
        }

        return !empty($ctx['used']);
    }

    /**
     * @return array{ok:false, error:string}
     */
    private function invalidResetTokenResult(): array
    {
        return [
            'ok'    => false,
            'error' => ErrorCode::AUTH_PASSWORD_RESET_TOKEN_INVALID,
        ];
    }

    /**
     * @param array<string, mixed>|null $ctx
     */
    private function logValidateResetTokenDebug(string $hashBin, ?array $ctx): void
    {
        Logger::getLogger('auth')->info('reset_validate_token_debug', [
            'token_present' => true,
            'hash_len'      => strlen($hashBin),
            'ctx_found'     => $ctx !== null,
            'used'          => $ctx['used'] ?? null,
            'is_expired'    => $ctx['is_expired'] ?? null,
        ]);
    }

    public function resetPassword(string $token, string $password, string $confirm): array
    {
        $channel = 'auth';

        $tokenCheck = $this->validateResetToken($token);
        $failure    = $this->failIfTokenInvalid($tokenCheck, $channel);
        if ($failure !== null) {
            return $failure;
        }

        [$ctx, $hashBin] = $this->extractValidatedTokenData($tokenCheck);

        $validationErrors = $this->validateNewPassword($password, $confirm);
        if ($validationErrors !== []) {
            return ['errors' => $validationErrors];
        }

        $hashPassword = $this->hashNewPassword($password, $channel);
        if ($hashPassword === null) {
            return ['error' => ErrorCode::AUTH_TECHNICAL_ERROR];
        }

        $failure = $this->failIfPasswordUpdateFails($hashBin, $hashPassword, $channel);
        if ($failure !== null) {
            return $failure;
        }

        $this->logPasswordResetSuccess($ctx, $channel);

        return [];
    }

    /**
     * @param array<string, mixed> $tokenCheck
     * @return array{error:string}|null
     */
    private function failIfTokenInvalid(array $tokenCheck, string $channel): ?array
    {
        if (!empty($tokenCheck['ok'])) {
            return null;
        }

        Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_PASSWORD_RESET_TOKEN_INVALID, [
            'reason' => 'invalid_or_used_or_expired',
        ]);

        $error = $tokenCheck['error'] ?? ErrorCode::AUTH_PASSWORD_RESET_TOKEN_INVALID;

        return ['error' => is_string($error) ? $error : ErrorCode::AUTH_PASSWORD_RESET_TOKEN_INVALID];
    }

    /**
     * @param array<string, mixed> $tokenCheck
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function extractValidatedTokenData(array $tokenCheck): array
    {
        /** @var array<string, mixed> $ctx */
        $ctx = is_array($tokenCheck['context'] ?? null) ? $tokenCheck['context'] : [];

        /** @var string $hashBin */
        $hashBin = is_string($tokenCheck['hash'] ?? null) ? $tokenCheck['hash'] : '';

        return [$ctx, $hashBin];
    }

    /**
     * @return array<string, string>
     */
    private function validateNewPassword(string $password, string $confirm): array
    {
        $errors = [];

        $error = $this->validator->validatePasswordField($password);
        if (is_string($error) && $error !== '') {
            $errors['password'] = $error;
        }

        if ($confirm === '' || $confirm !== $password) {
            $errors['password_confirm'] = ErrorCode::AUTH_PASSWORD_RESET_CONFIRM_MISMATCH;
        }

        return $errors;
    }

    private function hashNewPassword(string $password, string $channel): ?string
    {
        $hashPassword = password_hash($password, PASSWORD_BCRYPT);

        /** @phpstan-ignore-next-line */
        if ($hashPassword === false) {
            Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'reason' => 'password_hash_failed',
            ]);
            return null;
        }

        return $hashPassword;
    }

    /**
     * @return array{error:string}|null
     */
    private function failIfPasswordUpdateFails(string $hashBin, string $hashPassword, string $channel): ?array
    {
        $ok = $this->userTokenModel->consumePasswordResetTokenAndUpdatePassword($hashBin, $hashPassword);

        if ($ok) {
            return null;
        }

        Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_PASSWORD_RESET_TOKEN_INVALID, [
            'reason' => 'consume_or_update_failed',
        ]);

        return ['error' => ErrorCode::AUTH_PASSWORD_RESET_TOKEN_INVALID];
    }

    /**
     * @param array<string, mixed> $ctx
     */
    private function logPasswordResetSuccess(array $ctx, string $channel): void
    {
        $userId = $ctx['user_id'] ?? null;
        $userId = is_int($userId) || is_string($userId) ? $userId : null;

        Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_PASSWORD_RESET_SUCCESS, [
            'user_id' => $userId,
        ]);
    }
}
