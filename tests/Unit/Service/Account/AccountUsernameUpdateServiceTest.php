<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Account;

use App\Core\ErrorCode;
use App\Core\Logger;
use App\Model\Contract\UserModelInterface;
use App\Model\Entity\UserEntity;
use App\Security\Contract\AuthCheckerInterface;
use App\Service\Account\AccountUsernameUpdateService;
use App\Validation\Contract\FormValidatorInterface;
use Cocur\Slugify\Slugify;
use Monolog\Handler\TestHandler;
use Monolog\Logger as MonoLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AccountUsernameUpdateServiceTest extends TestCase
{
    private AuthCheckerInterface&MockObject $authChecker;
    private UserModelInterface&MockObject $userModel;
    private FormValidatorInterface&MockObject $validator;
    private AccountUsernameUpdateService $service;

    protected function setUp(): void
    {
        $this->authChecker = $this->createMock(AuthCheckerInterface::class);
        $this->userModel   = $this->createMock(UserModelInterface::class);
        $this->validator   = $this->createMock(FormValidatorInterface::class);

        $this->service = new AccountUsernameUpdateService(
            $this->authChecker,
            $this->userModel,
            $this->validator,
            new Slugify()
        );
    }

    public function testUpdateSuccessfullyChangesUsernameAndSlug(): void
    {
        $user = (new UserEntity())
            ->setUserId(228)
            ->setUsername('ancienNom')
            ->setSlug('anciennom');

        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(228);

        $this->validator
            ->expects($this->once())
            ->method('validateUsernameUpdate')
            ->with(['username' => 'nouveauNom'])
            ->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(228)
            ->willReturn($user);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByUsername')
            ->with('nouveauNom')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findOneBySlug')
            ->with('nouveaunom')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('updateUsername')
            ->with(228, 'nouveauNom', 'nouveaunom')
            ->willReturn(true);

        $result = $this->service->update([
            'username' => 'nouveauNom',
        ]);

        $this->assertSame(['ok' => true], $result);
    }

    public function testUpdateRejectsUnchangedUsername(): void
    {
        $user = (new UserEntity())
            ->setUserId(228)
            ->setUsername('ancienNom')
            ->setSlug('anciennom');

        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(228);

        $this->validator
            ->expects($this->once())
            ->method('validateUsernameUpdate')
            ->with(['username' => 'ancienNom'])
            ->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(228)
            ->willReturn($user);

        $this->userModel
            ->expects($this->never())
            ->method('findOneByUsername');

        $this->userModel
            ->expects($this->never())
            ->method('findOneBySlug');

        $this->userModel
            ->expects($this->never())
            ->method('updateUsername');

        $result = $this->service->update([
            'username' => 'ancienNom',
        ]);

        $this->assertSame([
            'errors' => [
                'username' => ErrorCode::ACCOUNT_USERNAME_UNCHANGED,
            ],
        ], $result);
    }

    public function testUpdateRejectsUsernameAlreadyUsedByAnotherUser(): void
    {
        $currentUser = (new UserEntity())
            ->setUserId(228)
            ->setUsername('ancienNom')
            ->setSlug('anciennom');

        $existingUser = (new UserEntity())
            ->setUserId(229)
            ->setUsername('nomExistant')
            ->setSlug('nomexistant');

        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(228);

        $this->validator
            ->expects($this->once())
            ->method('validateUsernameUpdate')
            ->with(['username' => 'nomExistant'])
            ->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(228)
            ->willReturn($currentUser);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByUsername')
            ->with('nomExistant')
            ->willReturn($existingUser);

        $this->userModel
            ->expects($this->never())
            ->method('findOneBySlug');

        $this->userModel
            ->expects($this->never())
            ->method('updateUsername');

        $result = $this->service->update([
            'username' => 'nomExistant',
        ]);

        $this->assertSame([
            'errors' => [
                'username' => ErrorCode::ACCOUNT_USERNAME_EXISTS,
            ],
        ], $result);
    }

    public function testUpdateRejectsInvalidUsername(): void
    {
        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(228);

        $this->validator
            ->expects($this->once())
            ->method('validateUsernameUpdate')
            ->with(['username' => 'nom!invalide'])
            ->willReturn([
                'username' => ErrorCode::AUTH_USERNAME_INVALID,
            ]);

        $this->userModel
            ->expects($this->never())
            ->method('findOneById');

        $this->userModel
            ->expects($this->never())
            ->method('findOneByUsername');

        $this->userModel
            ->expects($this->never())
            ->method('findOneBySlug');

        $this->userModel
            ->expects($this->never())
            ->method('updateUsername');

        $result = $this->service->update([
            'username' => 'nom!invalide',
        ]);

        $this->assertSame([
            'errors' => [
                'username' => ErrorCode::AUTH_USERNAME_INVALID,
            ],
        ], $result);
    }

    public function testUpdateAllowsCaseOnlyUsernameChange(): void
    {
        $user = (new UserEntity())
            ->setUserId(228)
            ->setUsername('ancienNom')
            ->setSlug('anciennom');

        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(228);

        $this->validator
            ->expects($this->once())
            ->method('validateUsernameUpdate')
            ->with(['username' => 'AncienNom'])
            ->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(228)
            ->willReturn($user);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByUsername')
            ->with('AncienNom')
            ->willReturn($user);

        $this->userModel
            ->expects($this->once())
            ->method('findOneBySlug')
            ->with('anciennom')
            ->willReturn($user);

        $this->userModel
            ->expects($this->once())
            ->method('updateUsername')
            ->with(228, 'AncienNom', 'anciennom')
            ->willReturn(true);

        $result = $this->service->update([
            'username' => 'AncienNom',
        ]);

        $this->assertSame(['ok' => true], $result);
    }

    public function testUpdateGeneratesAlternativeSlugWhenSlugAlreadyExists(): void
    {
        $currentUser = (new UserEntity())
            ->setUserId(228)
            ->setUsername('ancienNom')
            ->setSlug('anciennom');

        $existingUser = (new UserEntity())
            ->setUserId(229)
            ->setUsername('autreUtilisateur')
            ->setSlug('nouveaunom');

        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(228);

        $this->validator
            ->expects($this->once())
            ->method('validateUsernameUpdate')
            ->with(['username' => 'nouveauNom'])
            ->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(228)
            ->willReturn($currentUser);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByUsername')
            ->with('nouveauNom')
            ->willReturn(null);

        $this->userModel
            ->expects($this->exactly(2))
            ->method('findOneBySlug')
            ->willReturnCallback(
                static function (string $slug) use ($existingUser): ?UserEntity {
                    return match ($slug) {
                        'nouveaunom'   => $existingUser,
                        'nouveaunom-2' => null,
                        default        => throw new \LogicException(
                            'Unexpected slug: ' . $slug
                        ),
                    };
                }
            );

        $this->userModel
            ->expects($this->once())
            ->method('updateUsername')
            ->with(228, 'nouveauNom', 'nouveaunom-2')
            ->willReturn(true);

        $result = $this->service->update([
            'username' => 'nouveauNom',
        ]);

        $this->assertSame(['ok' => true], $result);
    }

    public function testUpdateRejectsUnauthenticatedUser(): void
    {
        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(null);

        $this->validator
            ->expects($this->never())
            ->method('validateUsernameUpdate');

        $this->userModel
            ->expects($this->never())
            ->method('findOneById');

        $this->userModel
            ->expects($this->never())
            ->method('findOneByUsername');

        $this->userModel
            ->expects($this->never())
            ->method('findOneBySlug');

        $this->userModel
            ->expects($this->never())
            ->method('updateUsername');

        $result = $this->service->update([
            'username' => 'nouveauNom',
        ]);

        $this->assertSame([
            'errors' => [
                'username' => ErrorCode::ACCOUNT_USER_NOT_FOUND,
            ],
        ], $result);
    }

    public function testUpdateRejectsUserNotFoundInDatabase(): void
    {
        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(228);

        $this->validator
            ->expects($this->once())
            ->method('validateUsernameUpdate')
            ->with(['username' => 'nouveauNom'])
            ->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(228)
            ->willReturn(null);

        $this->userModel
            ->expects($this->never())
            ->method('findOneByUsername');

        $this->userModel
            ->expects($this->never())
            ->method('findOneBySlug');

        $this->userModel
            ->expects($this->never())
            ->method('updateUsername');

        $result = $this->service->update([
            'username' => 'nouveauNom',
        ]);

        $this->assertSame([
            'errors' => [
                'username' => ErrorCode::ACCOUNT_USER_NOT_FOUND,
            ],
        ], $result);
    }

    public function testUpdateReturnsErrorWhenPersistenceFails(): void
    {
        $user = (new UserEntity())
            ->setUserId(228)
            ->setUsername('ancienNom')
            ->setSlug('anciennom');

        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(228);

        $this->validator
            ->expects($this->once())
            ->method('validateUsernameUpdate')
            ->with(['username' => 'nouveauNom'])
            ->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(228)
            ->willReturn($user);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByUsername')
            ->with('nouveauNom')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findOneBySlug')
            ->with('nouveaunom')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('updateUsername')
            ->with(228, 'nouveauNom', 'nouveaunom')
            ->willReturn(false);

        $result = $this->service->update([
            'username' => 'nouveauNom',
        ]);

        $this->assertSame([
            'errors' => [
                'username' => ErrorCode::ACCOUNT_USERNAME_UPDATE_FAILED,
            ],
        ], $result);
    }

    public function testUpdateRejectsConcurrentUsernameDuplicate(): void
    {
        $user = (new UserEntity())
            ->setUserId(228)
            ->setUsername('ancienNom')
            ->setSlug('anciennom');

        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(228);

        $this->validator
            ->expects($this->once())
            ->method('validateUsernameUpdate')
            ->with(['username' => 'nouveauNom'])
            ->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(228)
            ->willReturn($user);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByUsername')
            ->with('nouveauNom')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findOneBySlug')
            ->with('nouveaunom')
            ->willReturn(null);

        $exception = new \PDOException(
            "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'nouveauNom' for key 'username'",
            23000
        );

        $exception->errorInfo = [
            '23000',
            1062,
            "Duplicate entry 'nouveauNom' for key 'username'",
        ];

        $this->userModel
            ->expects($this->once())
            ->method('updateUsername')
            ->with(228, 'nouveauNom', 'nouveaunom')
            ->willThrowException($exception);

        $result = $this->service->update([
            'username' => 'nouveauNom',
        ]);

        $this->assertSame([
            'errors' => [
                'username' => ErrorCode::ACCOUNT_USERNAME_EXISTS,
            ],
        ], $result);
    }

    public function testUpdateRetriesWhenConcurrentSlugDuplicateOccurs(): void
    {
        $user = (new UserEntity())
            ->setUserId(228)
            ->setUsername('ancienNom')
            ->setSlug('anciennom');

        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(228);

        $this->validator
            ->expects($this->once())
            ->method('validateUsernameUpdate')
            ->with(['username' => 'nouveauNom'])
            ->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(228)
            ->willReturn($user);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByUsername')
            ->with('nouveauNom')
            ->willReturn(null);

        $this->userModel
            ->expects($this->exactly(2))
            ->method('findOneBySlug')
            ->willReturnCallback(
                static function (string $slug): ?UserEntity {
                    if (!in_array($slug, ['nouveaunom', 'nouveaunom-2'], true)) {
                        throw new \LogicException('Unexpected slug: ' . $slug);
                    }

                    return null;
                }
            );

        $exception = new \PDOException(
            "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'nouveaunom' for key 'slug'",
            23000
        );

        $exception->errorInfo = [
            '23000',
            1062,
            "Duplicate entry 'nouveaunom' for key 'slug'",
        ];

        $attempt = 0;

        $this->userModel
            ->expects($this->exactly(2))
            ->method('updateUsername')
            ->willReturnCallback(
                static function (
                    int $userId,
                    string $username,
                    string $slug
                ) use (
                    &$attempt,
                    $exception
                ): bool {
                    $attempt++;

                    if ($userId !== 228 || $username !== 'nouveauNom') {
                        throw new \LogicException('Unexpected update arguments.');
                    }

                    if ($attempt === 1) {
                        if ($slug !== 'nouveaunom') {
                            throw new \LogicException('Unexpected first slug.');
                        }

                        throw $exception;
                    }

                    if ($slug !== 'nouveaunom-2') {
                        throw new \LogicException('Unexpected second slug.');
                    }

                    return true;
                }
            );

        $result = $this->service->update([
            'username' => 'nouveauNom',
        ]);

        $this->assertSame(['ok' => true], $result);
        $this->assertSame(2, $attempt);
    }

    public function testUpdateFailsWhenAllSlugCandidatesAreTaken(): void
    {
        $currentUser = (new UserEntity())
            ->setUserId(228)
            ->setUsername('ancienNom')
            ->setSlug('anciennom');

        $existingUser = (new UserEntity())
            ->setUserId(229)
            ->setUsername('autreUtilisateur')
            ->setSlug('slugexistant');

        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(228);

        $this->validator
            ->expects($this->once())
            ->method('validateUsernameUpdate')
            ->with(['username' => 'nouveauNom'])
            ->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(228)
            ->willReturn($currentUser);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByUsername')
            ->with('nouveauNom')
            ->willReturn(null);

        $checkedSlugs = [];

        $this->userModel
            ->expects($this->exactly(20))
            ->method('findOneBySlug')
            ->willReturnCallback(
                static function (string $slug) use (
                    $existingUser,
                    &$checkedSlugs
                ): UserEntity {
                    $checkedSlugs[] = $slug;

                    return $existingUser;
                }
            );

        $this->userModel
            ->expects($this->never())
            ->method('updateUsername');

        $result = $this->service->update([
            'username' => 'nouveauNom',
        ]);

        $expectedSlugs = ['nouveaunom'];

        for ($i = 2; $i <= 20; $i++) {
            $expectedSlugs[] = 'nouveaunom-' . $i;
        }

        $this->assertSame($expectedSlugs, $checkedSlugs);

        $this->assertSame([
            'errors' => [
                'username' => ErrorCode::ACCOUNT_USERNAME_UPDATE_FAILED,
            ],
        ], $result);
    }

    public function testUpdateLogsUnexpectedDatabaseException(): void
    {
        Logger::reset();

        try {
            $logger = Logger::getLogger('account');

            $this->assertInstanceOf(MonoLogger::class, $logger);

            $logHandler = new TestHandler();
            $logger->setHandlers([$logHandler]);

            $user = (new UserEntity())
                ->setUserId(228)
                ->setUsername('ancienNom')
                ->setSlug('anciennom');

            $this->authChecker
                ->expects($this->once())
                ->method('getUserId')
                ->willReturn(228);

            $this->validator
                ->expects($this->once())
                ->method('validateUsernameUpdate')
                ->with(['username' => 'nouveauNom'])
                ->willReturn([]);

            $this->userModel
                ->expects($this->once())
                ->method('findOneById')
                ->with(228)
                ->willReturn($user);

            $this->userModel
                ->expects($this->once())
                ->method('findOneByUsername')
                ->with('nouveauNom')
                ->willReturn(null);

            $this->userModel
                ->expects($this->once())
                ->method('findOneBySlug')
                ->with('nouveaunom')
                ->willReturn(null);

            $exception = new \PDOException(
                'SQLSTATE[HY000]: General database error',
                0
            );

            $exception->errorInfo = [
                'HY000',
                2006,
                'MySQL server has gone away',
            ];

            $this->userModel
                ->expects($this->once())
                ->method('updateUsername')
                ->with(228, 'nouveauNom', 'nouveaunom')
                ->willThrowException($exception);

            $result = $this->service->update([
                'username' => 'nouveauNom',
            ]);

            $this->assertSame([
                'errors' => [
                    'username' => ErrorCode::ACCOUNT_USERNAME_UPDATE_FAILED,
                ],
            ], $result);

            $records = $logHandler->getRecords();

            $this->assertCount(1, $records);
            $this->assertSame('ERROR', $records[0]->level->getName());

            $this->assertSame(
                228,
                $records[0]->context['user_id']
            );

            $this->assertSame(
                'SQLSTATE[HY000]: General database error',
                $records[0]->context['exception']
            );
        } finally {
            Logger::reset();
        }
    }

    public function testUpdateLogsDuplicateKeyExceptionWithUnknownIndex(): void
    {
        Logger::reset();

        try {
            $logger = Logger::getLogger('account');

            $this->assertInstanceOf(MonoLogger::class, $logger);

            $logHandler = new TestHandler();
            $logger->setHandlers([$logHandler]);

            $user = (new UserEntity())
                ->setUserId(228)
                ->setUsername('ancienNom')
                ->setSlug('anciennom');

            $this->authChecker
                ->expects($this->once())
                ->method('getUserId')
                ->willReturn(228);

            $this->validator
                ->expects($this->once())
                ->method('validateUsernameUpdate')
                ->with(['username' => 'nouveauNom'])
                ->willReturn([]);

            $this->userModel
                ->expects($this->once())
                ->method('findOneById')
                ->with(228)
                ->willReturn($user);

            $this->userModel
                ->expects($this->once())
                ->method('findOneByUsername')
                ->with('nouveauNom')
                ->willReturn(null);

            $this->userModel
                ->expects($this->once())
                ->method('findOneBySlug')
                ->with('nouveaunom')
                ->willReturn(null);

            $exception = new \PDOException(
                'SQLSTATE[23000]: Duplicate entry for an unknown index',
                23000
            );

            $exception->errorInfo = [
                '23000',
                1062,
            ];

            $this->userModel
                ->expects($this->once())
                ->method('updateUsername')
                ->with(228, 'nouveauNom', 'nouveaunom')
                ->willThrowException($exception);

            $result = $this->service->update([
                'username' => 'nouveauNom',
            ]);

            $this->assertSame([
                'errors' => [
                    'username' => ErrorCode::ACCOUNT_USERNAME_UPDATE_FAILED,
                ],
            ], $result);

            $records = $logHandler->getRecords();

            $this->assertCount(1, $records);
            $this->assertSame('ERROR', $records[0]->level->getName());
            $this->assertSame(228, $records[0]->context['user_id']);
            $this->assertSame(
                'SQLSTATE[23000]: Duplicate entry for an unknown index',
                $records[0]->context['exception']
            );
        } finally {
            Logger::reset();
        }
    }

    public function testUpdateRejectsMissingUsername(): void
    {
        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(228);

        $this->validator
            ->expects($this->once())
            ->method('validateUsernameUpdate')
            ->with(['username' => ''])
            ->willReturn([
                'username' => ErrorCode::AUTH_USERNAME_INVALID,
            ]);

        $this->userModel
            ->expects($this->never())
            ->method('findOneById');

        $this->userModel
            ->expects($this->never())
            ->method('findOneByUsername');

        $this->userModel
            ->expects($this->never())
            ->method('findOneBySlug');

        $this->userModel
            ->expects($this->never())
            ->method('updateUsername');

        $result = $this->service->update([]);

        $this->assertSame([
            'errors' => [
                'username' => ErrorCode::AUTH_USERNAME_INVALID,
            ],
        ], $result);
    }
}
