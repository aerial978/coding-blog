<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Core\ErrorCode;
use App\Model\Contract\UserModelInterface;
use App\Model\Entity\UserEntity;
use App\Security\Contract\AuthCheckerInterface;
use App\Service\Admin\Contract\AdminUserServiceInterface;
use App\Validation\Contract\FormValidatorInterface;
use Cocur\Slugify\Slugify;

final class AdminUserService implements AdminUserServiceInterface
{
    public function __construct(
        private UserModelInterface $userModel,
        private FormValidatorInterface $validator,
        private AuthCheckerInterface $authChecker,
        private Slugify $slugify,
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

        [$editable, $status] = $this->prepareEditableFields(
            $userId,
            $form,
            $user
        );

        $errors = $this->validator->validateAdminUserEdit($editable);

        if ($errors !== []) {
            return [
                'errors' => $errors,
                'old'    => $editable,
            ];
        }

        $currentUsername = $user->getUsername();

        if ($editable['username'] !== $currentUsername) {
            $existingUser = $this->userModel->findOneByUsername($editable['username']);

            if ($existingUser !== null) {
                return [
                    'errors' => [
                        'username' => ErrorCode::ADMIN_USER_USERNAME_EXISTS,
                    ],
                    'old' => $editable,
                ];
            }
        }

        $slug = $user->getSlug() ?? '';

        if ($editable['username'] !== $currentUsername) {
            $slug = $this->slugify->slugify($editable['username']);
        }

        $updated = $this->userModel->updateAdminEditableFields(
            $userId,
            $editable['username'],
            $slug,
            $editable['role'],
            $status
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
     * @return array{0: array<string, string>, 1: string}
     */
    private function prepareEditableFields(
        int $userId,
        array $form,
        UserEntity $user
    ): array {
        $editable = [
            'username' => $this->stringField($form, 'username'),
            'role'     => $this->stringField($form, 'role'),
        ];

        $currentStatus = $user->getStatus() ?? '';

        if ($this->authChecker->getUserId() === $userId) {
            $editable['role'] = $user->getRole() ?? '';

            return [$editable, $currentStatus];
        }

        if ($currentStatus === 'inactive') {
            return [$editable, $currentStatus];
        }

        $editable['status'] = $this->stringField($form, 'status');

        return [$editable, $editable['status']];
    }

    /**
     * @param array<string, mixed> $form
     */
    private function stringField(array $form, string $key): string
    {
        $value = $form[$key] ?? null;

        return is_string($value) ? trim($value) : '';
    }
}
