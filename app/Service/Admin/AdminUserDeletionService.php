<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Core\Contract\SqlHelperInterface;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Model\Contract\EmailEventModelInterface;
use App\Model\Contract\RegistrationEventModelInterface;
use App\Model\Contract\UserModelInterface;
use App\Model\Contract\UserTokenModelInterface;
use App\Security\Contract\AuthCheckerInterface;
use App\Service\Admin\Contract\AdminUserDeletionServiceInterface;
use RuntimeException;

final class AdminUserDeletionService implements AdminUserDeletionServiceInterface
{
    public function __construct(
        private UserModelInterface $userModel,
        private UserTokenModelInterface $userTokenModel,
        private EmailEventModelInterface $emailEventModel,
        private RegistrationEventModelInterface $registrationEvents,
        private SqlHelperInterface $sqlHelper,
        private AuthCheckerInterface $authChecker,
    ) {
    }

    /** @return array{errors?: list<string>} */
    public function delete(int $userId): array
    {
        $adminId = $this->authChecker->getUserId();

        if (!$this->isAuthorizedAdmin($adminId)) {
            return $this->failure(ErrorCode::ADMIN_USER_DELETE_FAILED, $adminId);
        }

        if ($adminId === $userId) {
            return $this->failure(ErrorCode::ADMIN_USER_DELETE_SELF, $adminId);
        }

        try {
            $this->sqlHelper->beginTransaction();

            // Sérialise les suppressions concurrentes d'administrateurs.
            $this->userModel->lockAdminRole();

            // Revérifie les droits de l'administrateur dans la transaction.
            $admin = $this->userModel->findOneByIdForUpdate($adminId);

            if (!$this->isActiveAdmin($admin)) {
                $this->sqlHelper->rollBack();

                return $this->failure(ErrorCode::ADMIN_USER_DELETE_FAILED, $adminId);
            }

            $target = $this->userModel->findOneByIdForUpdate($userId);

            if ($target === null) {
                $this->sqlHelper->rollBack();

                return $this->failure(ErrorCode::ADMIN_USER_NOT_FOUND, $adminId);
            }

            if ($this->isLastActiveAdmin($target)) {
                $this->sqlHelper->rollBack();

                return $this->failure(ErrorCode::ADMIN_USER_DELETE_LAST_ADMIN, $adminId);
            }

            // Préserve les événements historiques sans référence utilisateur.
            $this->emailEventModel->detachUser($userId);
            $this->registrationEvents->detachUser($userId);

            // Les jetons ne possèdent pas de clé étrangère avec suppression en cascade.
            $this->userTokenModel->deleteAllByUserId($userId);

            $this->deleteUserOrFail($userId);

            $this->sqlHelper->commit();
        } catch (\Throwable $exception) {
            $this->rollbackSilently();

            return $this->failure(ErrorCode::ADMIN_USER_DELETE_FAILED, $adminId);
        }

        $this->logDeletion($adminId, $userId);

        return [];
    }

    private function isActiveAdmin(?\App\Model\Entity\UserEntity $user): bool
    {
        return $user !== null
            && $user->getStatus() === 'active'
            && $user->getRole()   === 'ADMIN';
    }

    private function isLastActiveAdmin(\App\Model\Entity\UserEntity $user): bool
    {
        return $user->getRole()   === 'ADMIN'
            && $user->getStatus() === 'active'
            && $this->userModel->countActiveAdminsForUpdate() <= 1;
    }

    /**
     * @phpstan-assert-if-true int $adminId
     */
    private function isAuthorizedAdmin(?int $adminId): bool
    {
        return $adminId !== null
            && in_array('ADMIN', $this->authChecker->getRoles(), true);
    }

    private function deleteUserOrFail(int $userId): void
    {
        if (!$this->userModel->deleteById($userId)) {
            throw new RuntimeException('User deletion did not affect one row.');
        }
    }

    /** @return array{errors: list<string>} */
    private function failure(string $errorCode, ?int $adminId): array
    {
        try {
            Logger::getLogger('admin')->warning('admin_user_deletion_rejected', [
                'actor_id' => $adminId,
                'reason'   => $errorCode,
            ]);
        } catch (\Throwable) {
            // La journalisation ne doit pas modifier le résultat du refus.
        }

        return ['errors' => [$errorCode]];
    }

    private function rollbackSilently(): void
    {
        try {
            $this->sqlHelper->rollBack();
        } catch (\Throwable) {
            // Ne pas masquer l'échec initial.
        }
    }

    private function logDeletion(int $adminId, int $userId): void
    {
        // La journalisation ne doit pas compromettre une suppression validée.
        try {
            Logger::getLogger('admin')->info('admin_user_deleted', [
                'actor_id'  => $adminId,
                'target_id' => $userId,
            ]);
        } catch (\Throwable) {
            // L'opération SQL a déjà été validée.
        }
    }
}
