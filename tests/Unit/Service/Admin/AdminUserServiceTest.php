<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Admin;

use App\Core\ErrorCode;
use App\Core\Logger;
use App\Model\Contract\UserModelInterface;
use App\Security\Contract\AuthCheckerInterface;
use App\Service\Admin\AdminUserService;
use App\Validation\Contract\FormValidatorInterface;
use Monolog\Handler\TestHandler;
use Monolog\Logger as MonoLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AdminUserServiceTest extends TestCase
{
    private UserModelInterface&MockObject $userModel;
    private AuthCheckerInterface&MockObject $authChecker;
    private FormValidatorInterface&MockObject $validator;
    private AdminUserService $service;
    private TestHandler $logHandler;

    protected function setUp(): void
    {
        Logger::reset();

        $logger = Logger::getLogger('admin');

        self::assertInstanceOf(MonoLogger::class, $logger);

        $this->logHandler = new TestHandler();

        $logger->setHandlers([$this->logHandler]);


        $this->userModel   = $this->createMock(UserModelInterface::class);
        $this->validator   = $this->createMock(FormValidatorInterface::class);
        $this->authChecker = $this->createMock(AuthCheckerInterface::class);

        $this->authChecker
            ->method('getUserId')
            ->willReturn(1);

        $this->service = new AdminUserService(
            $this->userModel,
            $this->validator,
            $this->authChecker,
        );
    }

    protected function tearDown(): void
    {
        Logger::reset();

        parent::tearDown();
    }

    public function testUpdateReturnsNotFoundWhenTargetUserDoesNotExist(): void
    {
        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(999)
            ->willReturn(null);

        $this->validator
            ->expects($this->never())
            ->method('validateAdminUserEdit');

        $this->userModel
            ->expects($this->never())
            ->method('updateAdminEditableFields');

        $result = $this->service->update(999, [
            'username' => 'alice_123',
            'role'     => 'MEMBER',
            'status'   => 'active',
        ]);

        $this->assertSame([
            'errors' => [
                ErrorCode::ADMIN_USER_NOT_FOUND,
            ],
        ], $result);
    }

    public function testUpdateReturnsValidationErrorsWhenSubmittedDataIsInvalid(): void
    {
        $user = (new \App\Model\Entity\UserEntity())
            ->setUserId(228)
            ->setUsername('alice_123')
            ->setSlug('alice-123')
            ->setRole('MEMBER')
            ->setStatus('active');

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(228)
            ->willReturn($user);

        $this->validator
            ->expects($this->once())
            ->method('validateAdminUserEdit')
            ->with([
                'role'   => 'SUPER_ADMIN',
                'status' => 'blocked',
            ])
            ->willReturn([
                'role'   => ErrorCode::ADMIN_USER_ROLE_INVALID,
                'status' => ErrorCode::ADMIN_USER_STATUS_INVALID,
            ]);

        $this->userModel
            ->expects($this->never())
            ->method('updateAdminEditableFields');

        $result = $this->service->update(228, [
            'username' => 'alice_123',
            'role'     => 'SUPER_ADMIN',
            'status'   => 'blocked',
        ]);

        $this->assertSame([
            'errors' => [
                'role'   => ErrorCode::ADMIN_USER_ROLE_INVALID,
                'status' => ErrorCode::ADMIN_USER_STATUS_INVALID,
            ],
            'old' => [
                'role'   => 'SUPER_ADMIN',
                'status' => 'blocked',
            ],
        ], $result);

        $records = $this->logHandler->getRecords();

        $this->assertCount(2, $records);

        $this->assertSame(
            'admin_user_update_rejected',
            $records[0]->message
        );

        $this->assertSame(
            \Monolog\Level::Warning,
            $records[0]->level
        );

        $this->assertSame([
            'actor_id'  => 1,
            'target_id' => 228,
            'reason'    => ErrorCode::ADMIN_USER_ROLE_INVALID,
        ], $records[0]->context);

        $this->assertSame(
            'admin_user_update_rejected',
            $records[1]->message
        );

        $this->assertSame(
            \Monolog\Level::Warning,
            $records[1]->level
        );

        $this->assertSame([
            'actor_id'  => 1,
            'target_id' => 228,
            'reason'    => ErrorCode::ADMIN_USER_STATUS_INVALID,
        ], $records[1]->context);
    }

    public function testUpdateRejectsUsernameModification(): void
    {
        $user = (new \App\Model\Entity\UserEntity())
            ->setUserId(228)
            ->setUsername('alice_123')
            ->setSlug('alice-123')
            ->setRole('MEMBER')
            ->setStatus('active');

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(228)
            ->willReturn($user);

        $this->validator
            ->expects($this->never())
            ->method('validateAdminUserEdit');

        $this->userModel
            ->expects($this->never())
            ->method('updateAdminEditableFields');

        $result = $this->service->update(228, [
            'username' => 'bob_456',
            'role'     => 'MEMBER',
            'status'   => 'active',
        ]);

        $this->assertSame([
            'errors' => [
                'username' => ErrorCode::ADMIN_USER_USERNAME_FORBIDDEN,
            ],
        ], $result);

        $records = $this->logHandler->getRecords();

        $this->assertCount(1, $records);

        $this->assertSame(
            'admin_user_update_rejected',
            $records[0]->message
        );

        $this->assertSame(
            \Monolog\Level::Warning,
            $records[0]->level
        );

        $this->assertSame([
            'actor_id'  => 1,
            'target_id' => 228,
            'reason'    => ErrorCode::ADMIN_USER_USERNAME_FORBIDDEN,
        ], $records[0]->context);
    }

    public function testUpdateRejectsNonStringUsername(): void
    {
        $user = (new \App\Model\Entity\UserEntity())
            ->setUserId(228)
            ->setUsername('alice_123')
            ->setSlug('alice-123')
            ->setRole('MEMBER')
            ->setStatus('active');

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(228)
            ->willReturn($user);

        $this->validator
            ->expects($this->never())
            ->method('validateAdminUserEdit');

        $this->userModel
            ->expects($this->never())
            ->method('updateAdminEditableFields');

        $result = $this->service->update(228, [
            'username' => ['alice_123'],
            'role'     => 'MEMBER',
            'status'   => 'active',
        ]);

        $this->assertSame([
            'errors' => [
                'username' => ErrorCode::ADMIN_USER_USERNAME_FORBIDDEN,
            ],
        ], $result);

        $records = $this->logHandler->getRecords();

        $this->assertCount(1, $records);

        $this->assertSame(
            'admin_user_update_rejected',
            $records[0]->message
        );

        $this->assertSame(
            \Monolog\Level::Warning,
            $records[0]->level
        );

        $this->assertSame([
            'actor_id'  => 1,
            'target_id' => 228,
            'reason'    => ErrorCode::ADMIN_USER_USERNAME_FORBIDDEN,
        ], $records[0]->context);
    }

    public function testUpdateAllowsUnchangedUsernameAndUpdatesMemberStatus(): void
    {
        $user = (new \App\Model\Entity\UserEntity())
            ->setUserId(228)
            ->setUsername('alice_123')
            ->setSlug('alice-123')
            ->setRole('MEMBER')
            ->setStatus('active');

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(228)
            ->willReturn($user);

        $this->validator
            ->expects($this->once())
            ->method('validateAdminUserEdit')
            ->with([
                'role'   => 'MEMBER',
                'status' => 'disabled',
            ])
            ->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('updateAdminEditableFields')
            ->with(
                228,
                'MEMBER',
                'disabled'
            )
            ->willReturn(true);

        $result = $this->service->update(228, [
            'username' => 'alice_123',
            'role'     => 'MEMBER',
            'status'   => 'disabled',
        ]);

        $this->assertSame([
            'ok' => true,
        ], $result);
    }

    public function testUpdateAllowsMissingUsernameField(): void
    {
        $user = (new \App\Model\Entity\UserEntity())
            ->setUserId(228)
            ->setUsername('alice_123')
            ->setSlug('alice-123')
            ->setRole('MEMBER')
            ->setStatus('active');

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(228)
            ->willReturn($user);

        $this->validator
            ->expects($this->once())
            ->method('validateAdminUserEdit')
            ->with([
                'role'   => 'MEMBER',
                'status' => 'disabled',
            ])
            ->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('updateAdminEditableFields')
            ->with(
                228,
                'MEMBER',
                'disabled'
            )
            ->willReturn(true);

        $result = $this->service->update(228, [
            'role'   => 'MEMBER',
            'status' => 'disabled',
        ]);

        $this->assertSame([
            'ok' => true,
        ], $result);
    }

    public function testUpdateRejectsAdminRoleAssignmentToMember(): void
    {
        $user = (new \App\Model\Entity\UserEntity())
            ->setUserId(228)
            ->setUsername('alice_123')
            ->setSlug('alice-123')
            ->setRole('MEMBER')
            ->setStatus('active');

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(228)
            ->willReturn($user);

        $this->validator
            ->expects($this->once())
            ->method('validateAdminUserEdit')
            ->with([
                'role'   => 'ADMIN',
                'status' => 'active',
            ])
            ->willReturn([
                'role' => ErrorCode::ADMIN_USER_ROLE_INVALID,
            ]);

        $this->userModel
            ->expects($this->never())
            ->method('updateAdminEditableFields');

        $result = $this->service->update(228, [
            'username' => 'alice_123',
            'role'     => 'ADMIN',
            'status'   => 'active',
        ]);

        $this->assertSame([
            'errors' => [
                'role' => ErrorCode::ADMIN_USER_ROLE_INVALID,
            ],
            'old' => [
                'role'   => 'ADMIN',
                'status' => 'active',
            ],
        ], $result);

        $records = $this->logHandler->getRecords();

        $this->assertCount(1, $records);

        $this->assertSame(
            'admin_user_update_rejected',
            $records[0]->message
        );

        $this->assertSame(
            \Monolog\Level::Warning,
            $records[0]->level
        );

        $this->assertSame([
            'actor_id'  => 1,
            'target_id' => 228,
            'reason'    => ErrorCode::ADMIN_USER_ROLE_INVALID,
        ], $records[0]->context);
    }

    public function testUpdateReturnsUpdateFailedWhenPersistenceFails(): void
    {
        $user = (new \App\Model\Entity\UserEntity())
            ->setUserId(228)
            ->setUsername('alice_123')
            ->setSlug('alice-123')
            ->setRole('MEMBER')
            ->setStatus('active');

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(228)
            ->willReturn($user);

        $this->validator
            ->expects($this->once())
            ->method('validateAdminUserEdit')
            ->with([
                'role'   => 'MEMBER',
                'status' => 'disabled',
            ])
            ->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('updateAdminEditableFields')
            ->with(
                228,
                'MEMBER',
                'disabled'
            )
            ->willReturn(false);

        $result = $this->service->update(228, [
            'username' => 'alice_123',
            'role'     => 'MEMBER',
            'status'   => 'disabled',
        ]);

        $this->assertSame([
            'errors' => [
                ErrorCode::ADMIN_USER_UPDATE_FAILED,
            ],
            'old' => [
                'role'   => 'MEMBER',
                'status' => 'disabled',
            ],
        ], $result);
    }

    public function testUpdateRejectsModificationOfAdminAccount(): void
    {
        $user = (new \App\Model\Entity\UserEntity())
            ->setUserId(228)
            ->setUsername('alice_123')
            ->setSlug('alice-123')
            ->setRole('ADMIN')
            ->setStatus('active');

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(228)
            ->willReturn($user);

        $this->validator
            ->expects($this->never())
            ->method('validateAdminUserEdit');

        $this->userModel
            ->expects($this->never())
            ->method('updateAdminEditableFields');

        $result = $this->service->update(228, [
            'username' => 'alice_updated',
            'role'     => 'MEMBER',
            'status'   => 'disabled',
        ]);

        $this->assertSame([
            'errors' => [
                ErrorCode::ADMIN_USER_UPDATE_FORBIDDEN,
            ],
        ], $result);

        $records = $this->logHandler->getRecords();

        $this->assertCount(1, $records);

        $this->assertSame(
            'admin_user_update_rejected',
            $records[0]->message
        );

        $this->assertSame(
            \Monolog\Level::Warning,
            $records[0]->level
        );

        $this->assertSame([
            'actor_id'  => 1,
            'target_id' => 228,
            'reason'    => ErrorCode::ADMIN_USER_UPDATE_FORBIDDEN,
        ], $records[0]->context);
    }

    public function testUpdateRejectsModificationOfDisabledAdminAccount(): void
    {
        $user = (new \App\Model\Entity\UserEntity())
            ->setUserId(230)
            ->setUsername('admin_disabled')
            ->setSlug('admin-disabled')
            ->setRole('ADMIN')
            ->setStatus('disabled');

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(230)
            ->willReturn($user);

        $this->validator
            ->expects($this->never())
            ->method('validateAdminUserEdit');

        $this->userModel
            ->expects($this->never())
            ->method('updateAdminEditableFields');

        $result = $this->service->update(230, [
            'role'   => 'MEMBER',
            'status' => 'active',
        ]);

        $this->assertSame([
            'errors' => [
                ErrorCode::ADMIN_USER_UPDATE_FORBIDDEN,
            ],
        ], $result);

        $records = $this->logHandler->getRecords();

        $this->assertCount(1, $records);

        $this->assertSame(
            'admin_user_update_rejected',
            $records[0]->message
        );

        $this->assertSame(
            \Monolog\Level::Warning,
            $records[0]->level
        );

        $this->assertSame([
            'actor_id'  => 1,
            'target_id' => 230,
            'reason'    => ErrorCode::ADMIN_USER_UPDATE_FORBIDDEN,
        ], $records[0]->context);
    }

    public function testUpdateRejectsModificationOfInactiveAdminAccount(): void
    {
        $user = (new \App\Model\Entity\UserEntity())
            ->setUserId(231)
            ->setUsername('admin_inactive')
            ->setSlug('admin-inactive')
            ->setRole('ADMIN')
            ->setStatus('inactive');

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(231)
            ->willReturn($user);

        $this->validator
            ->expects($this->never())
            ->method('validateAdminUserEdit');

        $this->userModel
            ->expects($this->never())
            ->method('updateAdminEditableFields');

        $result = $this->service->update(231, [
            'role'   => 'MEMBER',
            'status' => 'active',
        ]);

        $this->assertSame([
            'errors' => [
                ErrorCode::ADMIN_USER_UPDATE_FORBIDDEN,
            ],
        ], $result);

        $records = $this->logHandler->getRecords();

        $this->assertCount(1, $records);

        $this->assertSame(
            'admin_user_update_rejected',
            $records[0]->message
        );

        $this->assertSame(
            \Monolog\Level::Warning,
            $records[0]->level
        );

        $this->assertSame([
            'actor_id'  => 1,
            'target_id' => 231,
            'reason'    => ErrorCode::ADMIN_USER_UPDATE_FORBIDDEN,
        ], $records[0]->context);
    }

    public function testUpdatePreservesInactiveStatusWhenStatusIsNotSubmitted(): void
    {
        $user = (new \App\Model\Entity\UserEntity())
            ->setUserId(229)
            ->setUsername('alice_123')
            ->setSlug('alice-123')
            ->setRole('MEMBER')
            ->setStatus('inactive');

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(229)
            ->willReturn($user);

        $this->validator
            ->expects($this->once())
            ->method('validateAdminUserEdit')
            ->with([
                'role' => 'MEMBER',
            ])
            ->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('updateAdminEditableFields')
            ->with(
                229,
                'MEMBER',
                'inactive'
            )
            ->willReturn(true);

        $result = $this->service->update(229, [
            'role' => 'MEMBER',
        ]);

        $this->assertSame([
            'ok' => true,
        ], $result);
    }

    public function testUpdateRejectsMissingStatusWhenTargetStatusIsEditable(): void
    {
        $user = (new \App\Model\Entity\UserEntity())
            ->setUserId(229)
            ->setUsername('alice_123')
            ->setSlug('alice-123')
            ->setRole('MEMBER')
            ->setStatus('active');

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(229)
            ->willReturn($user);

        $this->validator
            ->expects($this->once())
            ->method('validateAdminUserEdit')
            ->with([
                'role'   => 'MEMBER',
                'status' => '',
            ])
            ->willReturn([
                'status' => ErrorCode::ADMIN_USER_STATUS_INVALID,
            ]);

        $this->userModel
            ->expects($this->never())
            ->method('updateAdminEditableFields');

        $result = $this->service->update(229, [
            'role' => 'MEMBER',
        ]);

        $this->assertSame([
            'errors' => [
                'status' => ErrorCode::ADMIN_USER_STATUS_INVALID,
            ],
            'old' => [
                'role'   => 'MEMBER',
                'status' => '',
            ],
        ], $result);

        $records = $this->logHandler->getRecords();

        $this->assertCount(1, $records);

        $this->assertSame(
            'admin_user_update_rejected',
            $records[0]->message
        );

        $this->assertSame(
            \Monolog\Level::Warning,
            $records[0]->level
        );

        $this->assertSame([
            'actor_id'  => 1,
            'target_id' => 229,
            'reason'    => ErrorCode::ADMIN_USER_STATUS_INVALID,
        ], $records[0]->context);
    }
}
