<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Core\ErrorCode;
use App\Core\Logger;
use App\Model\Contract\UserModelInterface;
use App\Model\Entity\UserEntity;
use App\Security\Contract\AuthCheckerInterface;
use App\Service\Admin\Contract\AdminUserServiceInterface;
use App\Validation\Contract\FormValidatorInterface;

final class AdminUserService implements AdminUserServiceInterface
{
    public function __construct(
        private UserModelInterface $userModel,
        private FormValidatorInterface $validator,
        private AuthCheckerInterface $authChecker,
    ) {
    }

    /**
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    public function update(int $userId, array $form): array
    {
        $user = $this->userModel->findOneById($userId);

        if ($user === null) {
            return [
                'errors' => [
                    ErrorCode::ADMIN_USER_NOT_FOUND,
                ],
            ];
        }

        // Administrator accounts cannot be modified from this interface.
        if ($user->getRole() === 'ADMIN') {
            $this->logRejectedUpdate(
                $userId,
                ErrorCode::ADMIN_USER_UPDATE_FORBIDDEN
            );

            return [
                'errors' => [
                    ErrorCode::ADMIN_USER_UPDATE_FORBIDDEN,
                ],
            ];
        }

        // Usernames can only be changed through account management.
        if ($this->isUsernameModificationAttempt($form, $user)) {
            $this->logRejectedUpdate(
                $userId,
                ErrorCode::ADMIN_USER_USERNAME_FORBIDDEN
            );

            return [
                'errors' => [
                    'username' => ErrorCode::ADMIN_USER_USERNAME_FORBIDDEN,
                ],
            ];
        }

        $editable = $this->prepareEditableFields($form, $user);

        $validationData = $editable;

        if ($editable['status'] === 'inactive') {
            unset($validationData['status']);
        }

        $errors = $this->validator->validateAdminUserEdit($validationData);

        if ($errors !== []) {
            $this->logValidationErrors($userId, $errors);

            return [
                'errors' => $errors,
                'old'    => $editable,
            ];
        }

        $updated = $this->userModel->updateAdminEditableFields(
            $userId,
            $editable['role'],
            $editable['status']
        );

        if (!$updated) {
            return [
                'errors' => [
                    ErrorCode::ADMIN_USER_UPDATE_FAILED,
                ],
                'old' => $editable,
            ];
        }

        return [
            'ok' => true,
        ];
    }

    /**
     * @param array<string, mixed> $form
     * @return array<string, string>
     */
    private function prepareEditableFields(
        array $form,
        UserEntity $user
    ): array {
        $currentStatus = $user->getStatus() ?? '';

        return [
            'role'   => $this->stringField($form, 'role'),
            'status' => $currentStatus === 'inactive'
                ? $currentStatus
                : $this->stringField($form, 'status'),
        ];
    }

    /**
     * @param array<string, mixed> $form
     */
    private function stringField(array $form, string $key): string
    {
        $value = $form[$key] ?? null;

        return is_string($value) ? trim($value) : '';
    }

    /**
     * Detects attempts to modify the username through the admin interface.
     *
     * @param array<string, mixed> $form
     */
    private function isUsernameModificationAttempt(
        array $form,
        UserEntity $user
    ): bool {
        if (!array_key_exists('username', $form)) {
            return false;
        }

        return !is_string($form['username'])
            || $form['username'] !== $user->getUsername();
    }

    /**
     * Logs rejected role and status modifications.
     *
     * @param array<string, string> $errors
     */
    private function logValidationErrors(int $userId, array $errors): void
    {
        foreach ($errors as $errorCode) {
            if (
                $errorCode    === ErrorCode::ADMIN_USER_ROLE_INVALID
                || $errorCode === ErrorCode::ADMIN_USER_STATUS_INVALID
            ) {
                $this->logRejectedUpdate($userId, $errorCode);
            }
        }
    }

    private function logRejectedUpdate(int $userId, string $errorCode): void
    {
        try {
            Logger::getLogger('admin')->warning('admin_user_update_rejected', [
                'actor_id'  => $this->authChecker->getUserId(),
                'target_id' => $userId,
                'reason'    => $errorCode,
            ]);
        } catch (\Throwable) {
            // La journalisation ne doit pas modifier le résultat du refus.
        }
    }
}
