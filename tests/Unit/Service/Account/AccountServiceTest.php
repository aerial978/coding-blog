<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Account;

use App\Model\Contract\UserModelInterface;
use App\Model\Entity\UserEntity;
use App\Security\Contract\AuthCheckerInterface;
use App\Service\Account\AccountService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AccountServiceTest extends TestCase
{
    private AuthCheckerInterface&MockObject $authChecker;

    private UserModelInterface&MockObject $userModel;

    private AccountService $service;

    protected function setUp(): void
    {
        $this->authChecker = $this->createMock(AuthCheckerInterface::class);
        $this->userModel   = $this->createMock(UserModelInterface::class);

        $this->service = new AccountService(
            $this->authChecker,
            $this->userModel,
        );
    }

    public function testGetCurrentUserReturnsAuthenticatedUser(): void
    {
        $user = $this->createMock(UserEntity::class);

        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(42);

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(42)
            ->willReturn($user);

        $result = $this->service->getCurrentUser();

        $this->assertSame($user, $result);
    }

    public function testGetCurrentUserReturnsNullWhenUserIdIsMissing(): void
    {
        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(null);

        $this->userModel
            ->expects($this->never())
            ->method('findOneById');

        $result = $this->service->getCurrentUser();

        $this->assertNull($result);
    }

    public function testGetCurrentUserReturnsNullWhenUserIsNotFound(): void
    {
        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(42);

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(42)
            ->willReturn(null);

        $result = $this->service->getCurrentUser();

        $this->assertNull($result);
    }
}