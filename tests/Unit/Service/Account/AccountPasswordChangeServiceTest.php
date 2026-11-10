<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Account;

use App\Core\Contract\SqlHelperInterface;
use App\Core\ErrorCode;
use App\Model\Contract\UserModelInterface;
use App\Model\Contract\UserTokenModelInterface;
use App\Security\Contract\AuthCheckerInterface;
use App\Security\PasswordBlacklist;
use App\Service\Account\AccountPasswordChangeService;
use App\Validation\Contract\FormValidatorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AccountPasswordChangeServiceTest extends TestCase
{
    private AuthCheckerInterface&MockObject $authChecker;
    private UserModelInterface&MockObject $userModel;
    private UserTokenModelInterface&MockObject $userTokenModel;
    private FormValidatorInterface&MockObject $validator;
    private SqlHelperInterface&MockObject $sqlHelper;
    private AccountPasswordChangeService $service;

    protected function setUp(): void
    {
        $this->authChecker    = $this->createMock(AuthCheckerInterface::class);
        $this->userModel      = $this->createMock(UserModelInterface::class);
        $this->userTokenModel = $this->createMock(UserTokenModelInterface::class);
        $this->validator      = $this->createMock(FormValidatorInterface::class);
        $this->sqlHelper      = $this->createMock(SqlHelperInterface::class);

        $this->service = new AccountPasswordChangeService(
            $this->authChecker,
            $this->userModel,
            $this->userTokenModel,
            $this->validator,
            new PasswordBlacklist(['Password123!']),
            $this->sqlHelper
        );
    }

    public function testRejectsUnauthenticatedUser(): void
    {
        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(null);

        $this->sqlHelper
            ->expects($this->never())
            ->method('beginTransaction');

        $result = $this->service->changePassword([
            'current_password'      => 'Current123!Password',
            'new_password'          => 'New123!Password',
            'password_confirmation' => 'New123!Password',
        ]);

        $this->assertSame([
            'errors' => [
                'current_password' => ErrorCode::ACCOUNT_USER_NOT_FOUND,
            ],
        ], $result);
    }

    public function testRejectsMissingFields(): void
    {
        $this->authChecker
            ->method('getUserId')
            ->willReturn(228);

        $this->validator
            ->expects($this->never())
            ->method('validatePasswordChangeField');

        $this->sqlHelper
            ->expects($this->never())
            ->method('beginTransaction');

        $result = $this->service->changePassword([]);

        $this->assertSame([
            'errors' => [
                'current_password'      => ErrorCode::AUTH_FIELD_REQUIRED,
                'new_password'          => ErrorCode::AUTH_FIELD_REQUIRED,
                'password_confirmation' => ErrorCode::AUTH_FIELD_REQUIRED,
            ],
        ], $result);
    }

    public function testRejectsPasswordConfirmationMismatch(): void
    {
        $this->authChecker
            ->method('getUserId')
            ->willReturn(228);

        $this->validator
            ->expects($this->once())
            ->method('validatePasswordChangeField')
            ->with('New123!Password')
            ->willReturn(null);

        $this->sqlHelper
            ->expects($this->never())
            ->method('beginTransaction');

        $result = $this->service->changePassword([
            'current_password'      => 'Current123!Password',
            'new_password'          => 'New123!Password',
            'password_confirmation' => 'Different123!Password',
        ]);

        $this->assertSame([
            'errors' => [
                'password_confirmation' => ErrorCode::ACCOUNT_PASSWORD_CONFIRMATION_MISMATCH,
            ],
        ], $result);
    }

    public function testRejectsInvalidNewPassword(): void
    {
        $this->authChecker
            ->method('getUserId')
            ->willReturn(228);

        $this->validator
            ->expects($this->once())
            ->method('validatePasswordChangeField')
            ->with('weakpassword')
            ->willReturn(ErrorCode::AUTH_PASSWORD_INVALID);

        $this->sqlHelper
            ->expects($this->never())
            ->method('beginTransaction');

        $result = $this->service->changePassword([
            'current_password'      => 'Current123!Password',
            'new_password'          => 'weakpassword',
            'password_confirmation' => 'weakpassword',
        ]);

        $this->assertSame([
            'errors' => [
                'new_password' => ErrorCode::AUTH_PASSWORD_INVALID,
            ],
        ], $result);
    }

    public function testRejectsBlacklistedNewPassword(): void
    {
        $this->authChecker
            ->method('getUserId')
            ->willReturn(228);

        $this->validator
            ->expects($this->once())
            ->method('validatePasswordChangeField')
            ->with('Password123!')
            ->willReturn(null);

        $this->sqlHelper
            ->expects($this->never())
            ->method('beginTransaction');

        $result = $this->service->changePassword([
            'current_password'      => 'Current123!Password',
            'new_password'          => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $this->assertSame([
            'errors' => [
                'new_password' => ErrorCode::AUTH_PASSWORD_TOO_COMMON,
            ],
        ], $result);
    }

    public function testRejectsIncorrectCurrentPassword(): void
    {
        $userId = 228;

        $storedHash = password_hash(
            'Actual123!Password',
            PASSWORD_BCRYPT
        );

        $user = (new \App\Model\Entity\UserEntity())
            ->setUserId($userId)
            ->setPassword($storedHash);

        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn($userId);

        $this->validator
            ->expects($this->once())
            ->method('validatePasswordChangeField')
            ->with('New123!Password')
            ->willReturn(null);

        $this->sqlHelper
            ->expects($this->once())
            ->method('beginTransaction');

        $this->userModel
            ->expects($this->once())
            ->method('findOneByIdForUpdate')
            ->with($userId)
            ->willReturn($user);

        $this->sqlHelper
            ->expects($this->once())
            ->method('rollBack');

        $this->sqlHelper
            ->expects($this->never())
            ->method('commit');

        $this->userModel
            ->expects($this->never())
            ->method('updatePassword');

        $this->userTokenModel
            ->expects($this->never())
            ->method('revokeAllActiveRememberMeTokens');

        $result = $this->service->changePassword([
            'current_password'      => 'Incorrect123!Password',
            'new_password'          => 'New123!Password',
            'password_confirmation' => 'New123!Password',
        ]);

        $this->assertSame([
            'errors' => [
                'current_password' => ErrorCode::ACCOUNT_CURRENT_PASSWORD_INVALID,
            ],
        ], $result);
    }

    public function testRejectsUnchangedPassword(): void
    {
        $userId   = 228;
        $password = 'Current123!Password';

        $storedHash = password_hash($password, PASSWORD_BCRYPT);

        $user = (new \App\Model\Entity\UserEntity())
            ->setUserId($userId)
            ->setPassword($storedHash);

        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn($userId);

        $this->validator
            ->expects($this->once())
            ->method('validatePasswordChangeField')
            ->with($password)
            ->willReturn(null);

        $this->sqlHelper
            ->expects($this->once())
            ->method('beginTransaction');

        $this->userModel
            ->expects($this->once())
            ->method('findOneByIdForUpdate')
            ->with($userId)
            ->willReturn($user);

        $this->sqlHelper
            ->expects($this->once())
            ->method('rollBack');

        $this->sqlHelper
            ->expects($this->never())
            ->method('commit');

        $this->userModel
            ->expects($this->never())
            ->method('updatePassword');

        $this->userTokenModel
            ->expects($this->never())
            ->method('revokeAllActiveRememberMeTokens');

        $result = $this->service->changePassword([
            'current_password'      => $password,
            'new_password'          => $password,
            'password_confirmation' => $password,
        ]);

        $this->assertSame([
            'errors' => [
                'new_password' => ErrorCode::ACCOUNT_PASSWORD_UNCHANGED,
            ],
        ], $result);
    }

    public function testSuccessfullyChangesPasswordAndRevokesRememberMeTokens(): void
    {
        $userId          = 228;
        $currentPassword = 'Current123!Password';
        $newPassword     = 'New123!Password';

        $storedHash = password_hash($currentPassword, PASSWORD_BCRYPT);

        $user = (new \App\Model\Entity\UserEntity())
            ->setUserId($userId)
            ->setPassword($storedHash);

        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn($userId);

        $this->validator
            ->expects($this->once())
            ->method('validatePasswordChangeField')
            ->with($newPassword)
            ->willReturn(null);

        $this->sqlHelper
            ->expects($this->once())
            ->method('beginTransaction');

        $this->userModel
            ->expects($this->once())
            ->method('findOneByIdForUpdate')
            ->with($userId)
            ->willReturn($user);

        $this->userModel
            ->expects($this->once())
            ->method('updatePassword')
            ->with(
                $userId,
                $this->callback(
                    static function (string $hash) use ($newPassword): bool {
                        $info = password_get_info($hash);

                        return $info['algo'] === PASSWORD_ARGON2ID
                            && password_verify($newPassword, $hash);
                    }
                )
            )
            ->willReturn(true);

        $this->userTokenModel
            ->expects($this->once())
            ->method('revokeAllActiveRememberMeTokens')
            ->with($userId);

        $this->sqlHelper
            ->expects($this->once())
            ->method('commit');

        $this->sqlHelper
            ->expects($this->never())
            ->method('rollBack');

        $result = $this->service->changePassword([
            'current_password'      => $currentPassword,
            'new_password'          => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $this->assertSame(['ok' => true], $result);
    }

    public function testRollsBackWhenPasswordUpdateFails(): void
    {
        $userId          = 228;
        $currentPassword = 'Current123!Password';
        $newPassword     = 'New123!Password';

        $storedHash = password_hash($currentPassword, PASSWORD_BCRYPT);

        $user = (new \App\Model\Entity\UserEntity())
            ->setUserId($userId)
            ->setPassword($storedHash);

        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn($userId);

        $this->validator
            ->expects($this->once())
            ->method('validatePasswordChangeField')
            ->with($newPassword)
            ->willReturn(null);

        $this->sqlHelper
            ->expects($this->once())
            ->method('beginTransaction');

        $this->userModel
            ->expects($this->once())
            ->method('findOneByIdForUpdate')
            ->with($userId)
            ->willReturn($user);

        $this->userModel
            ->expects($this->once())
            ->method('updatePassword')
            ->with(
                $userId,
                $this->callback(
                    static fn (string $hash): bool =>
                        password_verify($newPassword, $hash)
                )
            )
            ->willReturn(false);

        $this->userTokenModel
            ->expects($this->never())
            ->method('revokeAllActiveRememberMeTokens');

        $this->sqlHelper
            ->expects($this->once())
            ->method('rollBack');

        $this->sqlHelper
            ->expects($this->never())
            ->method('commit');

        $result = $this->service->changePassword([
            'current_password'      => $currentPassword,
            'new_password'          => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $this->assertSame([
            'errors' => [
                'current_password' => ErrorCode::ACCOUNT_PASSWORD_UPDATE_FAILED,
            ],
        ], $result);
    }

    public function testRollsBackWhenRememberMeTokenRevocationFails(): void
    {
        $userId          = 228;
        $currentPassword = 'Current123!Password';
        $newPassword     = 'New123!Password';

        $storedHash = password_hash($currentPassword, PASSWORD_BCRYPT);

        $user = (new \App\Model\Entity\UserEntity())
            ->setUserId($userId)
            ->setPassword($storedHash);

        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn($userId);

        $this->validator
            ->expects($this->once())
            ->method('validatePasswordChangeField')
            ->with($newPassword)
            ->willReturn(null);

        $this->sqlHelper
            ->expects($this->once())
            ->method('beginTransaction');

        $this->userModel
            ->expects($this->once())
            ->method('findOneByIdForUpdate')
            ->with($userId)
            ->willReturn($user);

        $this->userModel
            ->expects($this->once())
            ->method('updatePassword')
            ->with(
                $userId,
                $this->callback(
                    static fn (string $hash): bool =>
                        password_verify($newPassword, $hash)
                )
            )
            ->willReturn(true);

        $this->userTokenModel
            ->expects($this->once())
            ->method('revokeAllActiveRememberMeTokens')
            ->with($userId)
            ->willThrowException(
                new \RuntimeException('Token revocation failed.')
            );

        $this->sqlHelper
            ->expects($this->once())
            ->method('rollBack');

        $this->sqlHelper
            ->expects($this->never())
            ->method('commit');

        $result = $this->service->changePassword([
            'current_password'      => $currentPassword,
            'new_password'          => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $this->assertSame([
            'errors' => [
                'current_password' => ErrorCode::ACCOUNT_PASSWORD_UPDATE_FAILED,
            ],
        ], $result);
    }

    public function testRejectsUserNotFoundAfterTransactionStarts(): void
    {
        $userId      = 228;
        $newPassword = 'New123!Password';

        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn($userId);

        $this->validator
            ->expects($this->once())
            ->method('validatePasswordChangeField')
            ->with($newPassword)
            ->willReturn(null);

        $this->sqlHelper
            ->expects($this->once())
            ->method('beginTransaction');

        $this->userModel
            ->expects($this->once())
            ->method('findOneByIdForUpdate')
            ->with($userId)
            ->willReturn(null);

        $this->sqlHelper
            ->expects($this->once())
            ->method('rollBack');

        $this->sqlHelper
            ->expects($this->never())
            ->method('commit');

        $this->userModel
            ->expects($this->never())
            ->method('updatePassword');

        $this->userTokenModel
            ->expects($this->never())
            ->method('revokeAllActiveRememberMeTokens');

        $result = $this->service->changePassword([
            'current_password'      => 'Current123!Password',
            'new_password'          => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $this->assertSame([
            'errors' => [
                'current_password' => ErrorCode::ACCOUNT_USER_NOT_FOUND,
            ],
        ], $result);
    }

    public function testHandlesTransactionStartFailure(): void
    {
        $userId      = 228;
        $newPassword = 'New123!Password';

        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn($userId);

        $this->validator
            ->expects($this->once())
            ->method('validatePasswordChangeField')
            ->with($newPassword)
            ->willReturn(null);

        $this->sqlHelper
            ->expects($this->once())
            ->method('beginTransaction')
            ->willThrowException(
                new \RuntimeException('Transaction start failed.')
            );

        $this->sqlHelper
            ->expects($this->never())
            ->method('rollBack');

        $this->sqlHelper
            ->expects($this->never())
            ->method('commit');

        $this->userModel
            ->expects($this->never())
            ->method('findOneByIdForUpdate');

        $this->userModel
            ->expects($this->never())
            ->method('updatePassword');

        $this->userTokenModel
            ->expects($this->never())
            ->method('revokeAllActiveRememberMeTokens');

        $result = $this->service->changePassword([
            'current_password'      => 'Current123!Password',
            'new_password'          => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $this->assertSame([
            'errors' => [
                'current_password' => ErrorCode::ACCOUNT_PASSWORD_UPDATE_FAILED,
            ],
        ], $result);
    }

    public function testHandlesRollbackFailure(): void
    {
        $userId          = 228;
        $currentPassword = 'Current123!Password';
        $newPassword     = 'New123!Password';

        $storedHash = password_hash($currentPassword, PASSWORD_BCRYPT);

        $user = (new \App\Model\Entity\UserEntity())
            ->setUserId($userId)
            ->setPassword($storedHash);

        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn($userId);

        $this->validator
            ->expects($this->once())
            ->method('validatePasswordChangeField')
            ->with($newPassword)
            ->willReturn(null);

        $this->sqlHelper
            ->expects($this->once())
            ->method('beginTransaction');

        $this->userModel
            ->expects($this->once())
            ->method('findOneByIdForUpdate')
            ->with($userId)
            ->willReturn($user);

        $this->userModel
            ->expects($this->once())
            ->method('updatePassword')
            ->willReturn(true);

        $this->userTokenModel
            ->expects($this->once())
            ->method('revokeAllActiveRememberMeTokens')
            ->with($userId)
            ->willThrowException(
                new \RuntimeException('Token revocation failed.')
            );

        $this->sqlHelper
            ->expects($this->once())
            ->method('rollBack')
            ->willThrowException(
                new \RuntimeException('Rollback failed.')
            );

        $this->sqlHelper
            ->expects($this->never())
            ->method('commit');

        $result = $this->service->changePassword([
            'current_password'      => $currentPassword,
            'new_password'          => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $this->assertSame([
            'errors' => [
                'current_password' => ErrorCode::ACCOUNT_PASSWORD_UPDATE_FAILED,
            ],
        ], $result);
    }
}
