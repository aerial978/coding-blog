<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Admin;

use App\Core\Contract\SqlHelperInterface;
use App\Core\ErrorCode;
use App\Model\Contract\EmailEventModelInterface;
use App\Model\Contract\RegistrationEventModelInterface;
use App\Model\Contract\UserModelInterface;
use App\Model\Contract\UserTokenModelInterface;
use App\Model\Entity\UserEntity;
use App\Security\Contract\AuthCheckerInterface;
use App\Service\Admin\AdminUserDeletionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AdminUserDeletionServiceTest extends TestCase
{
    private UserModelInterface&MockObject $userModel;
    private UserTokenModelInterface&MockObject $userTokenModel;
    private EmailEventModelInterface&MockObject $emailEventModel;
    private RegistrationEventModelInterface&MockObject $registrationEventModel;
    private SqlHelperInterface&MockObject $sqlHelper;
    private AuthCheckerInterface&MockObject $authChecker;
    private AdminUserDeletionService $service;

    protected function setUp(): void
    {
        $this->userModel              = $this->createMock(UserModelInterface::class);
        $this->userTokenModel         = $this->createMock(UserTokenModelInterface::class);
        $this->emailEventModel        = $this->createMock(EmailEventModelInterface::class);
        $this->registrationEventModel = $this->createMock(RegistrationEventModelInterface::class);
        $this->sqlHelper              = $this->createMock(SqlHelperInterface::class);
        $this->authChecker            = $this->createMock(AuthCheckerInterface::class);

        $this->service = new AdminUserDeletionService(
            $this->userModel,
            $this->userTokenModel,
            $this->emailEventModel,
            $this->registrationEventModel,
            $this->sqlHelper,
            $this->authChecker,
        );
    }

    public function testDeleteRejectsNonAdminUser(): void
    {
        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(10);

        $this->authChecker
            ->expects($this->once())
            ->method('getRoles')
            ->willReturn(['MEMBER']);

        $this->sqlHelper
            ->expects($this->never())
            ->method('beginTransaction');

        $this->userModel
            ->expects($this->never())
            ->method('deleteById');

        $result = $this->service->delete(20);

        $this->assertSame([
            'errors' => [
                ErrorCode::ADMIN_USER_DELETE_FAILED,
            ],
        ], $result);
    }

    public function testDeleteRejectsOwnAccount(): void
    {
        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(10);

        $this->authChecker
            ->expects($this->once())
            ->method('getRoles')
            ->willReturn(['ADMIN']);

        $this->sqlHelper
            ->expects($this->never())
            ->method('beginTransaction');

        $this->userModel
            ->expects($this->never())
            ->method('deleteById');

        $result = $this->service->delete(10);

        $this->assertSame([
            'errors' => [
                ErrorCode::ADMIN_USER_DELETE_SELF,
            ],
        ], $result);
    }

    public function testDeleteReturnsNotFoundWhenTargetDoesNotExist(): void
    {
        $admin = (new UserEntity())
            ->setUserId(10)
            ->setRole('ADMIN')
            ->setStatus('active');

        $this->authChecker
            ->method('getUserId')
            ->willReturn(10);

        $this->authChecker
            ->method('getRoles')
            ->willReturn(['ADMIN']);

        $this->sqlHelper
            ->expects($this->once())
            ->method('beginTransaction');

        $this->userModel
            ->expects($this->once())
            ->method('lockAdminRole');

        $this->userModel
            ->expects($this->exactly(2))
            ->method('findOneByIdForUpdate')
            ->willReturnCallback(
                static fn (int $id): ?UserEntity => match ($id) {
                    10      => $admin,
                    999     => null,
                    default => throw new \LogicException('Unexpected user ID'),
                }
            );

        $this->sqlHelper
            ->expects($this->once())
            ->method('rollBack');

        $this->sqlHelper
            ->expects($this->never())
            ->method('commit');

        $this->userModel
            ->expects($this->never())
            ->method('deleteById');

        $result = $this->service->delete(999);

        $this->assertSame([
            'errors' => [
                ErrorCode::ADMIN_USER_NOT_FOUND,
            ],
        ], $result);
    }

    public function testDeleteRejectsActiveAdminAccount(): void
    {
        $admin = (new UserEntity())
            ->setUserId(10)
            ->setRole('ADMIN')
            ->setStatus('active');

        $target = (new UserEntity())
            ->setUserId(20)
            ->setRole('ADMIN')
            ->setStatus('active');

        $this->authChecker
            ->method('getUserId')
            ->willReturn(10);

        $this->authChecker
            ->method('getRoles')
            ->willReturn(['ADMIN']);

        $this->sqlHelper
            ->expects($this->once())
            ->method('beginTransaction');

        $this->userModel
            ->expects($this->once())
            ->method('lockAdminRole');

        $this->userModel
            ->expects($this->exactly(2))
            ->method('findOneByIdForUpdate')
            ->willReturnCallback(
                static fn (int $id): ?UserEntity => match ($id) {
                    10      => $admin,
                    20      => $target,
                    default => throw new \LogicException('Unexpected user ID'),
                }
            );

        $this->userModel
            ->expects($this->never())
            ->method('countActiveAdminsForUpdate');

        $this->userModel
            ->expects($this->never())
            ->method('deleteById');

        $this->sqlHelper
            ->expects($this->once())
            ->method('rollBack');

        $this->sqlHelper
            ->expects($this->never())
            ->method('commit');

        $result = $this->service->delete(20);

        $this->assertSame([
            'errors' => [
                ErrorCode::ADMIN_USER_DELETE_FORBIDDEN,
            ],
        ], $result);
    }

    public function testDeleteRejectsDisabledAdmin(): void
    {
        $admin = (new UserEntity())
            ->setUserId(10)
            ->setRole('ADMIN')
            ->setStatus('disabled');

        $this->authChecker
            ->method('getUserId')
            ->willReturn(10);

        $this->authChecker
            ->method('getRoles')
            ->willReturn(['ADMIN']);

        $this->sqlHelper
            ->expects($this->once())
            ->method('beginTransaction');

        $this->userModel
            ->expects($this->once())
            ->method('lockAdminRole');

        $this->userModel
            ->expects($this->once())
            ->method('findOneByIdForUpdate')
            ->with(10)
            ->willReturn($admin);

        $this->sqlHelper
            ->expects($this->once())
            ->method('rollBack');

        $this->sqlHelper
            ->expects($this->never())
            ->method('commit');

        $this->userModel
            ->expects($this->never())
            ->method('deleteById');

        $result = $this->service->delete(20);

        $this->assertSame([
            'errors' => [
                ErrorCode::ADMIN_USER_DELETE_FAILED,
            ],
        ], $result);
    }

    public function testDeleteSuccessfullyRemovesUserAndCommitsTransaction(): void
    {
        $admin = (new UserEntity())
            ->setUserId(10)
            ->setRole('ADMIN')
            ->setStatus('active');

        $target = (new UserEntity())
            ->setUserId(20)
            ->setRole('MEMBER')
            ->setStatus('active');

        $this->authChecker
            ->method('getUserId')
            ->willReturn(10);

        $this->authChecker
            ->method('getRoles')
            ->willReturn(['ADMIN']);

        $this->sqlHelper
            ->expects($this->once())
            ->method('beginTransaction');

        $this->userModel
            ->expects($this->once())
            ->method('lockAdminRole');

        $this->userModel
            ->expects($this->exactly(2))
            ->method('findOneByIdForUpdate')
            ->willReturnCallback(
                static fn (int $id): ?UserEntity => match ($id) {
                    10      => $admin,
                    20      => $target,
                    default => throw new \LogicException('Unexpected user ID'),
                }
            );

        $this->userModel
            ->expects($this->never())
            ->method('countActiveAdmins');

        $this->emailEventModel
            ->expects($this->once())
            ->method('detachUser')
            ->with(20);

        $this->registrationEventModel
            ->expects($this->once())
            ->method('detachUser')
            ->with(20);

        $this->userTokenModel
            ->expects($this->once())
            ->method('deleteAllByUserId')
            ->with(20);

        $this->userModel
            ->expects($this->once())
            ->method('deleteById')
            ->with(20)
            ->willReturn(true);

        $this->sqlHelper
            ->expects($this->once())
            ->method('commit');

        $this->sqlHelper
            ->expects($this->never())
            ->method('rollBack');

        $this->assertSame([], $this->service->delete(20));
    }

    public function testDeleteRollsBackWhenUserDeletionFails(): void
    {
        $admin = (new UserEntity())
            ->setUserId(10)
            ->setRole('ADMIN')
            ->setStatus('active');

        $target = (new UserEntity())
            ->setUserId(20)
            ->setRole('MEMBER')
            ->setStatus('active');

        $this->authChecker
            ->method('getUserId')
            ->willReturn(10);

        $this->authChecker
            ->method('getRoles')
            ->willReturn(['ADMIN']);

        $this->sqlHelper
            ->expects($this->once())
            ->method('beginTransaction');

        $this->userModel
            ->expects($this->once())
            ->method('lockAdminRole');

        $this->userModel
            ->expects($this->exactly(2))
            ->method('findOneByIdForUpdate')
            ->willReturnCallback(
                static fn (int $id): ?UserEntity => match ($id) {
                    10      => $admin,
                    20      => $target,
                    default => throw new \LogicException('Unexpected user ID'),
                }
            );

        $this->emailEventModel
            ->expects($this->once())
            ->method('detachUser')
            ->with(20);

        $this->registrationEventModel
            ->expects($this->once())
            ->method('detachUser')
            ->with(20);

        $this->userTokenModel
            ->expects($this->once())
            ->method('deleteAllByUserId')
            ->with(20);

        $this->userModel
            ->expects($this->once())
            ->method('deleteById')
            ->with(20)
            ->willReturn(false);

        $this->sqlHelper
            ->expects($this->once())
            ->method('rollBack');

        $this->sqlHelper
            ->expects($this->never())
            ->method('commit');

        $this->assertSame([
            'errors' => [
                ErrorCode::ADMIN_USER_DELETE_FAILED,
            ],
        ], $this->service->delete(20));
    }

    public function testDeleteRollsBackWhenTokenDeletionThrowsException(): void
    {
        $admin = (new UserEntity())
            ->setUserId(10)
            ->setRole('ADMIN')
            ->setStatus('active');

        $target = (new UserEntity())
            ->setUserId(20)
            ->setRole('MEMBER')
            ->setStatus('active');

        $this->authChecker
            ->method('getUserId')
            ->willReturn(10);

        $this->authChecker
            ->method('getRoles')
            ->willReturn(['ADMIN']);

        $this->sqlHelper
            ->expects($this->once())
            ->method('beginTransaction');

        $this->userModel
            ->expects($this->once())
            ->method('lockAdminRole');

        $this->userModel
            ->expects($this->exactly(2))
            ->method('findOneByIdForUpdate')
            ->willReturnCallback(
                static fn (int $id): ?UserEntity => match ($id) {
                    10      => $admin,
                    20      => $target,
                    default => throw new \LogicException('Unexpected user ID'),
                }
            );

        $this->emailEventModel
            ->expects($this->once())
            ->method('detachUser')
            ->with(20);

        $this->registrationEventModel
            ->expects($this->once())
            ->method('detachUser')
            ->with(20);

        $this->userTokenModel
            ->expects($this->once())
            ->method('deleteAllByUserId')
            ->with(20)
            ->willThrowException(new \RuntimeException('Database error'));

        $this->userModel
            ->expects($this->never())
            ->method('deleteById');

        $this->sqlHelper
            ->expects($this->once())
            ->method('rollBack');

        $this->sqlHelper
            ->expects($this->never())
            ->method('commit');

        $this->assertSame([
            'errors' => [
                ErrorCode::ADMIN_USER_DELETE_FAILED,
            ],
        ], $this->service->delete(20));
    }

    public function testDeleteRejectsAdminEvenWhenAnotherActiveAdminRemains(): void
    {
        $admin = (new UserEntity())
            ->setUserId(10)
            ->setRole('ADMIN')
            ->setStatus('active');

        $target = (new UserEntity())
            ->setUserId(20)
            ->setRole('ADMIN')
            ->setStatus('active');

        $this->authChecker
            ->method('getUserId')
            ->willReturn(10);

        $this->authChecker
            ->method('getRoles')
            ->willReturn(['ADMIN']);

        $this->sqlHelper
            ->expects($this->once())
            ->method('beginTransaction');

        $this->userModel
            ->expects($this->once())
            ->method('lockAdminRole');

        $this->userModel
            ->expects($this->exactly(2))
            ->method('findOneByIdForUpdate')
            ->willReturnCallback(
                static fn (int $id): ?UserEntity => match ($id) {
                    10      => $admin,
                    20      => $target,
                    default => throw new \LogicException('Unexpected user ID'),
                }
            );

        $this->userModel
            ->expects($this->never())
            ->method('countActiveAdminsForUpdate');

        $this->emailEventModel
            ->expects($this->never())
            ->method('detachUser');

        $this->registrationEventModel
            ->expects($this->never())
            ->method('detachUser');

        $this->userTokenModel
            ->expects($this->never())
            ->method('deleteAllByUserId');

        $this->userModel
            ->expects($this->never())
            ->method('deleteById');

        $this->sqlHelper
            ->expects($this->once())
            ->method('rollBack');

        $this->sqlHelper
            ->expects($this->never())
            ->method('commit');

        $this->assertSame([
            'errors' => [
                ErrorCode::ADMIN_USER_DELETE_FORBIDDEN,
            ],
        ], $this->service->delete(20));
    }

    public function testDeleteRejectsUnauthenticatedUser(): void
    {
        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(null);

        $this->authChecker
            ->expects($this->never())
            ->method('getRoles');

        $this->sqlHelper
            ->expects($this->never())
            ->method('beginTransaction');

        $this->userModel
            ->expects($this->never())
            ->method('deleteById');

        $result = $this->service->delete(20);

        $this->assertSame([
            'errors' => [
                ErrorCode::ADMIN_USER_DELETE_FAILED,
            ],
        ], $result);
    }

    public function testDeleteHandlesTransactionStartFailure(): void
    {
        $this->authChecker
            ->method('getUserId')
            ->willReturn(10);

        $this->authChecker
            ->method('getRoles')
            ->willReturn(['ADMIN']);

        $this->sqlHelper
            ->expects($this->once())
            ->method('beginTransaction')
            ->willThrowException(
                new \RuntimeException('Unable to start transaction')
            );

        $this->userModel
            ->expects($this->never())
            ->method('lockAdminRole');

        $this->userModel
            ->expects($this->never())
            ->method('deleteById');

        $this->sqlHelper
            ->expects($this->once())
            ->method('rollBack');

        $this->sqlHelper
            ->expects($this->never())
            ->method('commit');

        $this->assertSame([
            'errors' => [
                ErrorCode::ADMIN_USER_DELETE_FAILED,
            ],
        ], $this->service->delete(20));
    }

    public function testDeleteRollsBackWhenCommitFails(): void
    {
        $admin = (new UserEntity())
            ->setUserId(10)
            ->setRole('ADMIN')
            ->setStatus('active');

        $target = (new UserEntity())
            ->setUserId(20)
            ->setRole('MEMBER')
            ->setStatus('active');

        $this->authChecker
            ->method('getUserId')
            ->willReturn(10);

        $this->authChecker
            ->method('getRoles')
            ->willReturn(['ADMIN']);

        $this->sqlHelper
            ->expects($this->once())
            ->method('beginTransaction');

        $this->userModel
            ->expects($this->once())
            ->method('lockAdminRole');

        $this->userModel
            ->expects($this->exactly(2))
            ->method('findOneByIdForUpdate')
            ->willReturnCallback(
                static fn (int $id): ?UserEntity => match ($id) {
                    10      => $admin,
                    20      => $target,
                    default => throw new \LogicException('Unexpected user ID'),
                }
            );

        $this->emailEventModel
            ->expects($this->once())
            ->method('detachUser')
            ->with(20);

        $this->registrationEventModel
            ->expects($this->once())
            ->method('detachUser')
            ->with(20);

        $this->userTokenModel
            ->expects($this->once())
            ->method('deleteAllByUserId')
            ->with(20);

        $this->userModel
            ->expects($this->once())
            ->method('deleteById')
            ->with(20)
            ->willReturn(true);

        $this->sqlHelper
            ->expects($this->once())
            ->method('commit')
            ->willThrowException(
                new \RuntimeException('Transaction commit failed')
            );

        $this->sqlHelper
            ->expects($this->once())
            ->method('rollBack');

        $this->assertSame([
            'errors' => [
                ErrorCode::ADMIN_USER_DELETE_FAILED,
            ],
        ], $this->service->delete(20));
    }

    public function testDeleteHandlesRollbackFailure(): void
    {
        $this->authChecker
            ->method('getUserId')
            ->willReturn(10);

        $this->authChecker
            ->method('getRoles')
            ->willReturn(['ADMIN']);

        $this->sqlHelper
            ->expects($this->once())
            ->method('beginTransaction')
            ->willThrowException(
                new \RuntimeException('Transaction start failed')
            );

        $this->sqlHelper
            ->expects($this->once())
            ->method('rollBack')
            ->willThrowException(
                new \RuntimeException('Rollback failed')
            );

        $this->userModel
            ->expects($this->never())
            ->method('deleteById');

        $this->sqlHelper
            ->expects($this->never())
            ->method('commit');

        $this->assertSame([
            'errors' => [
                ErrorCode::ADMIN_USER_DELETE_FAILED,
            ],
        ], $this->service->delete(20));
    }

    public function testDeleteRejectsAdminWhoseRoleWasRevoked(): void
    {
        $admin = (new UserEntity())
            ->setUserId(10)
            ->setRole('MEMBER')
            ->setStatus('active');

        $this->authChecker
            ->method('getUserId')
            ->willReturn(10);

        $this->authChecker
            ->method('getRoles')
            ->willReturn(['ADMIN']);

        $this->sqlHelper
            ->expects($this->once())
            ->method('beginTransaction');

        $this->userModel
            ->expects($this->once())
            ->method('lockAdminRole');

        $this->userModel
            ->expects($this->once())
            ->method('findOneByIdForUpdate')
            ->with(10)
            ->willReturn($admin);

        $this->sqlHelper
            ->expects($this->once())
            ->method('rollBack');

        $this->sqlHelper
            ->expects($this->never())
            ->method('commit');

        $this->userModel
            ->expects($this->never())
            ->method('deleteById');

        $this->assertSame([
            'errors' => [
                ErrorCode::ADMIN_USER_DELETE_FAILED,
            ],
        ], $this->service->delete(20));
    }

    public function testDeleteRejectsDisabledAdminAccount(): void
    {
        $admin = (new UserEntity())
            ->setUserId(10)
            ->setRole('ADMIN')
            ->setStatus('active');

        $target = (new UserEntity())
            ->setUserId(20)
            ->setRole('ADMIN')
            ->setStatus('disabled');

        $this->authChecker
            ->method('getUserId')
            ->willReturn(10);

        $this->authChecker
            ->method('getRoles')
            ->willReturn(['ADMIN']);

        $this->sqlHelper
            ->expects($this->once())
            ->method('beginTransaction');

        $this->userModel
            ->expects($this->once())
            ->method('lockAdminRole');

        $this->userModel
            ->expects($this->exactly(2))
            ->method('findOneByIdForUpdate')
            ->willReturnCallback(
                static fn (int $id): ?UserEntity => match ($id) {
                    10      => $admin,
                    20      => $target,
                    default => throw new \LogicException('Unexpected user ID'),
                }
            );

        $this->userModel
            ->expects($this->never())
            ->method('countActiveAdminsForUpdate');

        $this->userModel
            ->expects($this->never())
            ->method('deleteById');

        $this->sqlHelper
            ->expects($this->once())
            ->method('rollBack');

        $this->sqlHelper
            ->expects($this->never())
            ->method('commit');

        $this->assertSame([
            'errors' => [
                ErrorCode::ADMIN_USER_DELETE_FORBIDDEN,
            ],
        ], $this->service->delete(20));
    }

    public function testDeleteRejectsInactiveAdminAccount(): void
    {
        $admin = (new UserEntity())
            ->setUserId(10)
            ->setRole('ADMIN')
            ->setStatus('active');

        $target = (new UserEntity())
            ->setUserId(20)
            ->setRole('ADMIN')
            ->setStatus('inactive');

        $this->authChecker
            ->method('getUserId')
            ->willReturn(10);

        $this->authChecker
            ->method('getRoles')
            ->willReturn(['ADMIN']);

        $this->sqlHelper
            ->expects($this->once())
            ->method('beginTransaction');

        $this->userModel
            ->expects($this->once())
            ->method('lockAdminRole');

        $this->userModel
            ->expects($this->exactly(2))
            ->method('findOneByIdForUpdate')
            ->willReturnCallback(
                static fn (int $id): ?UserEntity => match ($id) {
                    10      => $admin,
                    20      => $target,
                    default => throw new \LogicException('Unexpected user ID'),
                }
            );

        $this->userModel
            ->expects($this->never())
            ->method('countActiveAdminsForUpdate');

        $this->userModel
            ->expects($this->never())
            ->method('deleteById');

        $this->sqlHelper
            ->expects($this->once())
            ->method('rollBack');

        $this->sqlHelper
            ->expects($this->never())
            ->method('commit');

        $this->assertSame([
            'errors' => [
                ErrorCode::ADMIN_USER_DELETE_FORBIDDEN,
            ],
        ], $this->service->delete(20));
    }
}
