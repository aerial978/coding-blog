<?php

declare(strict_types=1);

namespace Tests\Unit\Service\OAuth;

use App\Model\Contract\UserModelInterface;
use App\Model\Entity\UserEntity;
use App\Service\OAuth\OAuthUserProvisioningService;
use Cocur\Slugify\Slugify;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OAuthUserProvisioningServiceTest extends TestCase
{
    private UserModelInterface&MockObject $userModel;

    private OAuthUserProvisioningService $service;

    protected function setUp(): void
    {
        $this->userModel = $this->createMock(UserModelInterface::class);

        $this->service = new OAuthUserProvisioningService(
            $this->userModel,
            new Slugify()
        );
    }

    public function testProvisionCreatesOAuthUserSuccessfully(): void
    {
        $profile = [
            'id'             => 'google_123',
            'email'          => 'michael@example.com',
            'email_verified' => true,
            'name'           => 'Michael Doe',
            'avatar'         => null,
        ];

        $this->userModel
            ->expects($this->once())
            ->method('findOneByUsername')
            ->with('michael_doe')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findOneBySlug')
            ->with('michael-doe')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('createOAuthUser')
            ->willReturn(123);

        $user = $this->service->provisionFromGoogleProfile($profile);

        $this->assertInstanceOf(UserEntity::class, $user);
        $this->assertSame(123, $user->getUserId());
        $this->assertSame('michael_doe', $user->getUsername());
        $this->assertSame('michael-doe', $user->getSlug());
        $this->assertSame('michael@example.com', $user->getEmail());
        $this->assertSame('active', $user->getStatus());
        $this->assertFalse($user->isEmail2faEnabled());

        $password = $user->getPassword();

        $this->assertIsString($password);
        $this->assertNotSame('', $password);
        $this->assertTrue(password_get_info($password)['algo'] !== 0);
    }

    public function testProvisionReturnsNullWhenEmailIsNotVerified(): void
    {
        $profile = [
            'id'             => 'google_123',
            'email'          => 'michael@example.com',
            'email_verified' => false,
            'name'           => 'Michael Doe',
            'avatar'         => null,
        ];

        $this->userModel
            ->expects($this->never())
            ->method('createOAuthUser');

        $result = $this->service->provisionFromGoogleProfile($profile);

        $this->assertNull($result);
    }

    public function testProvisionReturnsNullWhenEmailIsEmpty(): void
    {
        $profile = [
            'id'             => 'google_123',
            'email'          => '',
            'email_verified' => true,
            'name'           => 'Michael Doe',
            'avatar'         => null,
        ];

        $this->userModel
            ->expects($this->never())
            ->method('createOAuthUser');

        $result = $this->service->provisionFromGoogleProfile($profile);

        $this->assertNull($result);
    }

    public function testProvisionGeneratesUniqueUsername(): void
    {
        $profile = [
            'id'             => 'google_123',
            'email'          => 'michael@example.com',
            'email_verified' => true,
            'name'           => 'Michael Doe',
            'avatar'         => null,
        ];

        $existingUser = (new UserEntity())
            ->setUserId(1)
            ->setUsername('michael_doe');

        $this->userModel
            ->expects($this->exactly(2))
            ->method('findOneByUsername')
            ->willReturnOnConsecutiveCalls(
                $existingUser,
                null
            );

        $this->userModel
            ->expects($this->once())
            ->method('findOneBySlug')
            ->with('michael-doe-2')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('createOAuthUser')
            ->willReturn(456);

        $user = $this->service->provisionFromGoogleProfile($profile);

        $this->assertInstanceOf(UserEntity::class, $user);
        $this->assertSame('michael_doe_2', $user->getUsername());
        $this->assertSame('michael-doe-2', $user->getSlug());
    }

    public function testProvisionReturnsNullWhenCreateOAuthUserFails(): void
    {
        $profile = [
            'id'             => 'google_123',
            'email'          => 'michael@example.com',
            'email_verified' => true,
            'name'           => 'Michael Doe',
            'avatar'         => null,
        ];

        $this->userModel
            ->expects($this->once())
            ->method('findOneByUsername')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findOneBySlug')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('createOAuthUser')
            ->willReturn(0);

        $result = $this->service->provisionFromGoogleProfile($profile);

        $this->assertNull($result);
    }

    public function testProvisionUsesEmailLocalPartWhenNameIsEmpty(): void
    {
        $profile = [
            'id'             => 'google_123',
            'email'          => 'john.doe@example.com',
            'email_verified' => true,
            'name'           => '',
            'avatar'         => null,
        ];

        $this->userModel
            ->expects($this->once())
            ->method('findOneByUsername')
            ->with('john_doe')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findOneBySlug')
            ->with('john-doe')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('createOAuthUser')
            ->willReturn(789);

        $user = $this->service->provisionFromGoogleProfile($profile);

        $this->assertInstanceOf(UserEntity::class, $user);
        $this->assertSame('john_doe', $user->getUsername());
        $this->assertSame('john-doe', $user->getSlug());
    }

    public function testProvisionFallsBackToUserWhenNameIsNotUsable(): void
    {
        $profile = [
            'id'             => 'google_123',
            'email'          => 'valid@example.com',
            'email_verified' => true,
            'name'           => '!!!',
            'avatar'         => null,
        ];

        $this->userModel
            ->expects($this->once())
            ->method('findOneByUsername')
            ->with('user')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findOneBySlug')
            ->with('user')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('createOAuthUser')
            ->willReturn(790);

        $user = $this->service->provisionFromGoogleProfile($profile);

        $this->assertInstanceOf(UserEntity::class, $user);
        $this->assertSame('user', $user->getUsername());
        $this->assertSame('user', $user->getSlug());
    }

    public function testProvisionGeneratesUniqueSlug(): void
    {
        $profile = [
            'id'             => 'google_123',
            'email'          => 'michael@example.com',
            'email_verified' => true,
            'name'           => 'Michael Doe',
            'avatar'         => null,
        ];

        $existingUser = (new UserEntity())->setUserId(1);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByUsername')
            ->with('michael_doe')
            ->willReturn(null);

        $this->userModel
            ->expects($this->exactly(2))
            ->method('findOneBySlug')
            ->willReturnOnConsecutiveCalls(
                $existingUser,
                null
            );

        $this->userModel
            ->expects($this->once())
            ->method('createOAuthUser')
            ->willReturn(791);

        $user = $this->service->provisionFromGoogleProfile($profile);

        $this->assertInstanceOf(UserEntity::class, $user);
        $this->assertSame('michael_doe', $user->getUsername());
        $this->assertSame('michael-doe-2', $user->getSlug());
    }

    public function testProvisionThrowsExceptionWhenUniqueUsernameCannotBeGenerated(): void
    {
        $profile = [
            'id'             => 'google_123',
            'email'          => 'michael@example.com',
            'email_verified' => true,
            'name'           => 'Michael Doe',
            'avatar'         => null,
        ];

        $existingUser = (new UserEntity())->setUserId(1);

        $this->userModel
            ->expects($this->exactly(20))
            ->method('findOneByUsername')
            ->willReturn($existingUser);

        $this->userModel
            ->expects($this->never())
            ->method('createOAuthUser');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to generate unique OAuth username.');

        $this->service->provisionFromGoogleProfile($profile);
    }

    public function testProvisionThrowsExceptionWhenUniqueSlugCannotBeGenerated(): void
    {
        $profile = [
            'id'             => 'google_123',
            'email'          => 'michael@example.com',
            'email_verified' => true,
            'name'           => 'Michael Doe',
            'avatar'         => null,
        ];

        $existingUser = (new UserEntity())->setUserId(1);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByUsername')
            ->willReturn(null);

        $this->userModel
            ->expects($this->exactly(20))
            ->method('findOneBySlug')
            ->willReturn($existingUser);

        $this->userModel
            ->expects($this->never())
            ->method('createOAuthUser');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to generate unique OAuth slug.');

        $this->service->provisionFromGoogleProfile($profile);
    }

    public function testProvisionPadsUsernameWhenTooShort(): void
    {
        $profile = [
            'id'             => 'google_123',
            'email'          => 'ab@example.com',
            'email_verified' => true,
            'name'           => 'ab',
            'avatar'         => null,
        ];

        $this->userModel
            ->expects($this->once())
            ->method('findOneByUsername')
            ->with('ab_')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findOneBySlug')
            ->with('ab')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('createOAuthUser')
            ->willReturn(792);

        $user = $this->service->provisionFromGoogleProfile($profile);

        $this->assertInstanceOf(UserEntity::class, $user);
        $this->assertSame('ab_', $user->getUsername());
        $this->assertSame('ab', $user->getSlug());
    }
}
