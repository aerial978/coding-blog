<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Admin;

use App\Core\ErrorCode;
use App\Model\Contract\UserModelInterface;
use App\Security\Contract\AuthCheckerInterface;
use App\Service\Admin\AdminUserService;
use App\Validation\Contract\FormValidatorInterface;
use Cocur\Slugify\Slugify;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AdminUserServiceTest extends TestCase
{
    private UserModelInterface&MockObject $userModel;
    private FormValidatorInterface&MockObject $validator;
    private AuthCheckerInterface&MockObject $authChecker;
    private Slugify&MockObject $slugify;
    private AdminUserService $service;

    protected function setUp(): void
    {
        $this->userModel   = $this->createMock(UserModelInterface::class);
        $this->validator   = $this->createMock(FormValidatorInterface::class);
        $this->authChecker = $this->createMock(AuthCheckerInterface::class);
        $this->slugify     = $this->createMock(Slugify::class);

        $this->service = new AdminUserService(
            $this->userModel,
            $this->validator,
            $this->authChecker,
            $this->slugify,
        );
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
                'username' => '',
                'role'     => 'SUPER_ADMIN',
                'status'   => 'blocked',
            ])
            ->willReturn([
                'username' => ErrorCode::ADMIN_USER_USERNAME_REQUIRED,
                'role'     => ErrorCode::ADMIN_USER_ROLE_INVALID,
                'status'   => ErrorCode::ADMIN_USER_STATUS_INVALID,
            ]);

        $this->userModel
            ->expects($this->never())
            ->method('findOneByUsername');

        $this->userModel
            ->expects($this->never())
            ->method('updateAdminEditableFields');

        $result = $this->service->update(228, [
            'username' => '',
            'role'     => 'SUPER_ADMIN',
            'status'   => 'blocked',
        ]);

        $this->assertSame([
            'errors' => [
                'username' => ErrorCode::ADMIN_USER_USERNAME_REQUIRED,
                'role'     => ErrorCode::ADMIN_USER_ROLE_INVALID,
                'status'   => ErrorCode::ADMIN_USER_STATUS_INVALID,
            ],
            'old' => [
                'username' => '',
                'role'     => 'SUPER_ADMIN',
                'status'   => 'blocked',
            ],
        ], $result);
    }

    public function testUpdateReturnsUsernameExistsWhenChangedUsernameIsAlreadyUsed(): void
    {
        $user = (new \App\Model\Entity\UserEntity())
            ->setUserId(228)
            ->setUsername('alice_123')
            ->setSlug('alice-123')
            ->setRole('MEMBER')
            ->setStatus('active');

        $existingUser = (new \App\Model\Entity\UserEntity())
            ->setUsername('bob_456');

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(228)
            ->willReturn($user);

        $this->validator
            ->expects($this->once())
            ->method('validateAdminUserEdit')
            ->with([
                'username' => 'bob_456',
                'role'     => 'MEMBER',
                'status'   => 'active',
            ])
            ->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByUsername')
            ->with('bob_456')
            ->willReturn($existingUser);

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
                'username' => ErrorCode::ADMIN_USER_USERNAME_EXISTS,
            ],
            'old' => [
                'username' => 'bob_456',
                'role'     => 'MEMBER',
                'status'   => 'active',
            ],
        ], $result);
    }

    public function testUpdateDoesNotCheckUsernameCollisionWhenUsernameIsUnchanged(): void
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
                'username' => 'alice_123',
                'role'     => 'MEMBER',
                'status'   => 'active',
            ])
            ->willReturn([]);

        $this->userModel
            ->expects($this->never())
            ->method('findOneByUsername');

        $this->slugify
            ->expects($this->never())
            ->method('slugify');

        $this->userModel
            ->expects($this->once())
            ->method('updateAdminEditableFields')
            ->with(
                228,
                'alice_123',
                'alice-123',
                'MEMBER',
                'active'
            )
            ->willReturn(true);

        $result = $this->service->update(228, [
            'username' => 'alice_123',
            'role'     => 'MEMBER',
            'status'   => 'active',
        ]);

        $this->assertSame([
            'ok' => true,
        ], $result);
    }

    public function testUpdateGeneratesNewSlugAndPersistsWhenUsernameChanges(): void
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
                'username' => 'alice_updated',
                'role'     => 'ADMIN',
                'status'   => 'disabled',
            ])
            ->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByUsername')
            ->with('alice_updated')
            ->willReturn(null);

        $this->slugify
            ->expects($this->once())
            ->method('slugify')
            ->with('alice_updated')
            ->willReturn('alice-updated');

        $this->userModel
            ->expects($this->once())
            ->method('updateAdminEditableFields')
            ->with(
                228,
                'alice_updated',
                'alice-updated',
                'ADMIN',
                'disabled'
            )
            ->willReturn(true);

        $result = $this->service->update(228, [
            'username' => 'alice_updated',
            'role'     => 'ADMIN',
            'status'   => 'disabled',
        ]);

        $this->assertSame([
            'ok' => true,
        ], $result);
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
                'username' => 'alice_updated',
                'role'     => 'ADMIN',
                'status'   => 'disabled',
            ])
            ->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByUsername')
            ->with('alice_updated')
            ->willReturn(null);

        $this->slugify
            ->expects($this->once())
            ->method('slugify')
            ->with('alice_updated')
            ->willReturn('alice-updated');

        $this->userModel
            ->expects($this->once())
            ->method('updateAdminEditableFields')
            ->with(
                228,
                'alice_updated',
                'alice-updated',
                'ADMIN',
                'disabled'
            )
            ->willReturn(false);

        $result = $this->service->update(228, [
            'username' => 'alice_updated',
            'role'     => 'ADMIN',
            'status'   => 'disabled',
        ]);

        $this->assertSame([
            'errors' => [
                ErrorCode::ADMIN_USER_UPDATE_FAILED,
            ],
            'old' => [
                'username' => 'alice_updated',
                'role'     => 'ADMIN',
                'status'   => 'disabled',
            ],
        ], $result);
    }

    public function testUpdatePreservesRoleAndStatusWhenAdminEditsOwnAccount(): void
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

        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(228);

        $this->validator
            ->expects($this->once())
            ->method('validateAdminUserEdit')
            ->with([
                'username' => 'alice_updated',
                'role'     => 'ADMIN',
            ])
            ->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByUsername')
            ->with('alice_updated')
            ->willReturn(null);

        $this->slugify
            ->expects($this->once())
            ->method('slugify')
            ->with('alice_updated')
            ->willReturn('alice-updated');

        $this->userModel
            ->expects($this->once())
            ->method('updateAdminEditableFields')
            ->with(
                228,
                'alice_updated',
                'alice-updated',
                'ADMIN',
                'active'
            )
            ->willReturn(true);

        $result = $this->service->update(228, [
            'username' => 'alice_updated',
            'role'     => 'MEMBER',
            'status'   => 'disabled',
        ]);

        $this->assertSame([
            'ok' => true,
        ], $result);
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

        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(228);

        $this->validator
            ->expects($this->once())
            ->method('validateAdminUserEdit')
            ->with([
                'username' => 'alice_updated',
                'role'     => 'MEMBER',
            ])
            ->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByUsername')
            ->with('alice_updated')
            ->willReturn(null);

        $this->slugify
            ->expects($this->once())
            ->method('slugify')
            ->with('alice_updated')
            ->willReturn('alice-updated');

        $this->userModel
            ->expects($this->once())
            ->method('updateAdminEditableFields')
            ->with(
                229,
                'alice_updated',
                'alice-updated',
                'MEMBER',
                'inactive'
            )
            ->willReturn(true);

        $result = $this->service->update(229, [
            'username' => 'alice_updated',
            'role'     => 'MEMBER',
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

        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(228);

        $this->validator
            ->expects($this->once())
            ->method('validateAdminUserEdit')
            ->with([
                'username' => 'alice_updated',
                'role'     => 'MEMBER',
                'status'   => '',
            ])
            ->willReturn([
                'status' => ErrorCode::ADMIN_USER_STATUS_INVALID,
            ]);

        $this->userModel
            ->expects($this->never())
            ->method('findOneByUsername');

        $this->slugify
            ->expects($this->never())
            ->method('slugify');

        $this->userModel
            ->expects($this->never())
            ->method('updateAdminEditableFields');

        $result = $this->service->update(229, [
            'username' => 'alice_updated',
            'role'     => 'MEMBER',
        ]);

        $this->assertSame([
            'errors' => [
                'status' => ErrorCode::ADMIN_USER_STATUS_INVALID,
            ],
            'old' => [
                'username' => 'alice_updated',
                'role'     => 'MEMBER',
                'status'   => '',
            ],
        ], $result);
    }
}
