<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Core\Contract\SessionInterface;
use App\Model\Contract\UserModelInterface;
use App\Model\Entity\UserEntity;
use App\Security\SessionAuthChecker;
use PHPUnit\Framework\TestCase;

final class SessionAuthCheckerTest extends TestCase
{
    public function testIsAuthenticatedReturnsFalseWhenNoUserInSession(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $model   = $this->createMock(UserModelInterface::class);

        $session->method('get')->with('user')->willReturn(null);
        $model->expects(self::never())->method('findOneById');

        $checker = new SessionAuthChecker($session, $model);

        self::assertFalse($checker->isAuthenticated());
    }

    public function testIsAuthenticatedReturnsFalseWhenSessionUserIsInvalid(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $model   = $this->createMock(UserModelInterface::class);

        $session->method('get')->with('user')->willReturn('invalid');
        $model->expects(self::never())->method('findOneById');

        $checker = new SessionAuthChecker($session, $model);

        self::assertFalse($checker->isAuthenticated());
    }

    public function testIsAuthenticatedReturnsFalseWhenUserIdIsInvalid(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $model   = $this->createMock(UserModelInterface::class);

        $session->method('get')->with('user')->willReturn(['id' => '123']);
        $model->expects(self::never())->method('findOneById');

        $checker = new SessionAuthChecker($session, $model);

        self::assertFalse($checker->isAuthenticated());
        self::assertNull($checker->getUserId());
    }

    public function testIsAuthenticatedReturnsTrueForActiveUser(): void
    {
        $user = $this->makeUser(123, 'MEMBER', 'active');

        $session = $this->createMock(SessionInterface::class);
        $model   = $this->createMock(UserModelInterface::class);

        $session->method('get')->with('user')->willReturn(['id' => 123]);
        $model->expects(self::once())
            ->method('findOneById')
            ->with(123)
            ->willReturn($user);

        $checker = new SessionAuthChecker($session, $model);

        self::assertTrue($checker->isAuthenticated());
        self::assertSame(123, $checker->getUserId());
        self::assertSame(['MEMBER'], $checker->getRoles());
    }

    public function testDeletedUserIsRejectedAndRemovedFromSession(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $model   = $this->createMock(UserModelInterface::class);

        $session->method('get')->with('user')->willReturn(['id' => 123]);

        $model->method('findOneById')->with(123)->willReturn(null);

        $session->expects(self::once())
            ->method('remove')
            ->with('user');

        $checker = new SessionAuthChecker($session, $model);

        self::assertFalse($checker->isAuthenticated());
        self::assertNull($checker->getUserId());
        self::assertSame([], $checker->getRoles());
    }

    public function testDisabledUserIsRejectedAndRemovedFromSession(): void
    {
        $user = $this->makeUser(123, 'MEMBER', 'disabled');

        $session = $this->createMock(SessionInterface::class);
        $model   = $this->createMock(UserModelInterface::class);

        $session->method('get')->with('user')->willReturn(['id' => 123]);
        $model->method('findOneById')->with(123)->willReturn($user);

        $session->expects(self::once())
            ->method('remove')
            ->with('user');

        $checker = new SessionAuthChecker($session, $model);

        self::assertFalse($checker->isAuthenticated());
    }

    public function testInactiveUserIsRejected(): void
    {
        $user = $this->makeUser(123, 'MEMBER', 'inactive');

        $session = $this->createMock(SessionInterface::class);
        $model   = $this->createMock(UserModelInterface::class);

        $session->method('get')->with('user')->willReturn(['id' => 123]);
        $model->method('findOneById')->with(123)->willReturn($user);

        $checker = new SessionAuthChecker($session, $model);

        self::assertFalse($checker->isAuthenticated());
    }

    public function testRolesComeFromDatabaseInsteadOfSession(): void
    {
        $user = $this->makeUser(123, 'MEMBER', 'active');

        $session = $this->createMock(SessionInterface::class);
        $model   = $this->createMock(UserModelInterface::class);

        $session->method('get')->with('user')->willReturn([
            'id'    => 123,
            'roles' => ['ADMIN'],
        ]);

        $model->method('findOneById')->with(123)->willReturn($user);

        $checker = new SessionAuthChecker($session, $model);

        self::assertSame(['MEMBER'], $checker->getRoles());
    }

    public function testUserIsLoadedOnlyOncePerCheckerInstance(): void
    {
        $user = $this->makeUser(123, 'ADMIN', 'active');

        $session = $this->createMock(SessionInterface::class);
        $model   = $this->createMock(UserModelInterface::class);

        $session->method('get')->with('user')->willReturn(['id' => 123]);

        $model->expects(self::once())
            ->method('findOneById')
            ->with(123)
            ->willReturn($user);

        $checker = new SessionAuthChecker($session, $model);

        self::assertTrue($checker->isAuthenticated());
        self::assertSame(['ADMIN'], $checker->getRoles());
        self::assertSame(123, $checker->getUserId());
        self::assertTrue($checker->isAuthenticated());
    }

    public function testDatabaseFailureDeniesAccessWithoutRemovingSession(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $model   = $this->createMock(UserModelInterface::class);

        $session->method('get')->with('user')->willReturn(['id' => 123]);

        $model->method('findOneById')
            ->willThrowException(new \RuntimeException('Database unavailable'));

        $session->expects(self::never())->method('remove');

        $checker = new SessionAuthChecker($session, $model);

        self::assertFalse($checker->isAuthenticated());
        self::assertSame([], $checker->getRoles());
        self::assertNull($checker->getUserId());
    }

    private function makeUser(
        int $id,
        string $role,
        string $status
    ): UserEntity {
        return (new UserEntity())
            ->setUserId($id)
            ->setRole($role)
            ->setStatus($status);
    }
}
