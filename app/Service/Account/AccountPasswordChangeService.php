<?php

declare(strict_types=1);

namespace App\Service\Account;

use App\Core\Contract\SqlHelperInterface;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Model\Contract\UserModelInterface;
use App\Model\Contract\UserTokenModelInterface;
use App\Security\Contract\AuthCheckerInterface;
use App\Security\PasswordBlacklist;
use App\Service\Account\Contract\AccountPasswordChangeServiceInterface;
use App\Validation\Contract\FormValidatorInterface;
use RuntimeException;
use Throwable;

final class AccountPasswordChangeService implements AccountPasswordChangeServiceInterface
{
    public function __construct(
        private AuthCheckerInterface $authChecker,
        private UserModelInterface $userModel,
        private UserTokenModelInterface $userTokenModel,
        private FormValidatorInterface $validator,
        private PasswordBlacklist $passwordBlacklist,
        private SqlHelperInterface $sqlHelper
    ) {
    }

    /**
     * @param array<string, mixed> $formData
     *
     * @return array{ok: true}|array{errors: array<string, string>}
     */
    public function changePassword(array $formData): array
    {
        $userId = $this->authChecker->getUserId();

        if ($userId === null) {
            return $this->failure(
                'current_password',
                ErrorCode::ACCOUNT_USER_NOT_FOUND
            );
        }

        $currentPassword = $this->stringField($formData, 'current_password');
        $newPassword     = $this->stringField($formData, 'new_password');
        $confirmation    = $this->stringField($formData, 'password_confirmation');

        $errors = $this->validatePasswords(
            $currentPassword,
            $newPassword,
            $confirmation
        );

        if ($errors !== []) {
            return ['errors' => $errors];
        }

        return $this->executePasswordChange(
            $userId,
            $currentPassword,
            $newPassword
        );
    }

    /**
     * @param array<string, mixed> $formData
     */
    private function stringField(array $formData, string $field): string
    {
        return is_string($formData[$field] ?? null)
            ? $formData[$field]
            : '';
    }

    /**
     * @return array<string, string>
     */
    private function validatePasswords(
        string $currentPassword,
        string $newPassword,
        string $confirmation
    ): array {
        $errors = $this->validateRequiredFields(
            $currentPassword,
            $newPassword,
            $confirmation
        );

        if ($errors !== []) {
            return $errors;
        }

        if ($confirmation !== $newPassword) {
            $errors['password_confirmation'] =
                ErrorCode::ACCOUNT_PASSWORD_CONFIRMATION_MISMATCH;
        }

        $passwordError = $this->validator->validatePasswordChangeField(
            $newPassword
        );

        if ($passwordError !== null) {
            $errors['new_password'] = $passwordError;
        }

        if ($this->passwordBlacklist->isBlacklisted($newPassword)) {
            $errors['new_password'] = ErrorCode::AUTH_PASSWORD_TOO_COMMON;
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function validateRequiredFields(
        string $currentPassword,
        string $newPassword,
        string $confirmation
    ): array {
        $errors = [];

        if ($currentPassword === '') {
            $errors['current_password'] = ErrorCode::AUTH_FIELD_REQUIRED;
        }

        if ($newPassword === '') {
            $errors['new_password'] = ErrorCode::AUTH_FIELD_REQUIRED;
        }

        if ($confirmation === '') {
            $errors['password_confirmation'] = ErrorCode::AUTH_FIELD_REQUIRED;
        }

        return $errors;
    }

    /**
     * @return array{ok: true}|array{errors: array<string, string>}
     */
    private function executePasswordChange(
        int $userId,
        string $currentPassword,
        string $newPassword
    ): array {
        $transactionStarted = false;

        try {
            $this->sqlHelper->beginTransaction();
            $transactionStarted = true;

            $result = $this->updatePasswordInTransaction(
                $userId,
                $currentPassword,
                $newPassword
            );

            if (isset($result['errors'])) {
                $this->sqlHelper->rollBack();

                return $result;
            }

            $this->sqlHelper->commit();

            return ['ok' => true];
        } catch (Throwable $e) {
            return $this->handleTransactionFailure(
                $userId,
                $e,
                $transactionStarted
            );
        }
    }

    /**
     * @return array{ok: true}|array{errors: array<string, string>}
     */
    private function updatePasswordInTransaction(
        int $userId,
        string $currentPassword,
        string $newPassword
    ): array {
        $user = $this->userModel->findOneByIdForUpdate($userId);

        if ($user === null) {
            return $this->failure(
                'current_password',
                ErrorCode::ACCOUNT_USER_NOT_FOUND
            );
        }

        $storedHash = $user->getPassword();

        if (!is_string($storedHash) || !password_verify($currentPassword, $storedHash)) {
            return $this->failure(
                'current_password',
                ErrorCode::ACCOUNT_CURRENT_PASSWORD_INVALID
            );
        }

        if (password_verify($newPassword, $storedHash)) {
            return $this->failure(
                'new_password',
                ErrorCode::ACCOUNT_PASSWORD_UNCHANGED
            );
        }

        $newHash = password_hash($newPassword, PASSWORD_ARGON2ID);

        if (!$this->userModel->updatePassword($userId, $newHash)) {
            throw new RuntimeException('Password update failed.');
        }

        $this->userTokenModel->revokeAllActiveRememberMeTokens($userId);

        return ['ok' => true];
    }

    /**
     * @return array{errors: array<string, string>}
     */
    private function handleTransactionFailure(
        int $userId,
        Throwable $exception,
        bool $transactionStarted
    ): array {
        if ($transactionStarted) {
            try {
                $this->sqlHelper->rollBack();
            } catch (Throwable $rollbackException) {
                Logger::logCodeAndGetMessage(
                    'account',
                    'error',
                    ErrorCode::ACCOUNT_PASSWORD_ROLLBACK_FAILED,
                    ['user_id' => $userId]
                );
            }
        }

        Logger::logCodeAndGetMessage(
            'account',
            'error',
            ErrorCode::ACCOUNT_PASSWORD_UPDATE_FAILED,
            [
                'user_id'        => $userId,
                'exception_type' => get_class($exception),
            ]
        );

        return $this->failure(
            'current_password',
            ErrorCode::ACCOUNT_PASSWORD_UPDATE_FAILED
        );
    }

    /**
     * @return array{errors: array<string, string>}
     */
    private function failure(string $field, string $code): array
    {
        return ['errors' => [$field => $code]];
    }
}
