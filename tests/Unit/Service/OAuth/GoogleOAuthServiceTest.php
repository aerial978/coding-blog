<?php

declare(strict_types=1);

namespace Tests\Unit\Service\OAuth;

use App\Service\OAuth\Contract\GoogleOAuthProviderInterface;
use App\Service\OAuth\GoogleOAuthService;
use League\OAuth2\Client\Token\AccessTokenInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GoogleOAuthServiceTest extends TestCase
{
    private GoogleOAuthProviderInterface&MockObject $provider;

    private GoogleOAuthService $service;

    protected function setUp(): void
    {
        $this->provider = $this->createMock(
            GoogleOAuthProviderInterface::class
        );

        $this->service = new GoogleOAuthService($this->provider);
    }

    public function testGetAuthorizationUrlReturnsProviderUrl(): void
    {
        $expectedUrl = 'https://accounts.google.com/o/oauth2/auth';

        $this->provider
            ->expects($this->once())
            ->method('getAuthorizationUrl')
            ->willReturn($expectedUrl);

        $this->assertSame(
            $expectedUrl,
            $this->service->getAuthorizationUrl()
        );
    }

    public function testGetStateReturnsProviderState(): void
    {
        $expectedState = 'oauth_state_123';

        $this->provider
            ->expects($this->once())
            ->method('getState')
            ->willReturn($expectedState);

        $this->assertSame(
            $expectedState,
            $this->service->getState()
        );
    }

    public function testGetAccessTokenDelegatesToProvider(): void
    {
        $token = $this->createMock(AccessTokenInterface::class);

        $this->provider
            ->expects($this->once())
            ->method('getAccessToken')
            ->with('authorization-code')
            ->willReturn($token);

        $this->assertSame(
            $token,
            $this->service->getAccessToken('authorization-code')
        );
    }

    public function testGetUserProfileNormalizesGoogleProfile(): void
    {
        $token = $this->createMock(AccessTokenInterface::class);

        $this->provider
            ->expects($this->once())
            ->method('getResourceOwnerData')
            ->with($token)
            ->willReturn([
                'sub'            => 'google-id',
                'email'          => 'michel@example.com',
                'email_verified' => true,
                'name'           => 'Michel Hathier',
                'picture'        => 'https://avatar.example.com/avatar.jpg',
            ]);

        $profile = $this->service->getUserProfile($token);

        $this->assertSame('google-id', $profile['id']);
        $this->assertSame('michel@example.com', $profile['email']);
        $this->assertTrue($profile['email_verified']);
        $this->assertSame('Michel Hathier', $profile['name']);
        $this->assertSame(
            'https://avatar.example.com/avatar.jpg',
            $profile['avatar']
        );
    }

    public function testGetUserProfileUsesIdWhenSubIsMissing(): void
    {
        $token = $this->createMock(AccessTokenInterface::class);

        $this->provider
            ->expects($this->once())
            ->method('getResourceOwnerData')
            ->willReturn([
                'id'             => 'legacy-id',
                'email'          => 'michel@example.com',
                'email_verified' => true,
                'name'           => 'Michel Hathier',
            ]);

        $profile = $this->service->getUserProfile($token);

        $this->assertSame('legacy-id', $profile['id']);
    }

    public function testGetUserProfileNormalizesMissingValues(): void
    {
        $token = $this->createMock(AccessTokenInterface::class);

        $this->provider
            ->expects($this->once())
            ->method('getResourceOwnerData')
            ->willReturn([]);

        $profile = $this->service->getUserProfile($token);

        $this->assertSame('', $profile['id']);
        $this->assertSame('', $profile['email']);
        $this->assertFalse($profile['email_verified']);
        $this->assertSame('', $profile['name']);
        $this->assertNull($profile['avatar']);
    }

    public function testGetUserProfileConvertsTruthyValues(): void
    {
        $token = $this->createMock(AccessTokenInterface::class);

        $this->provider
            ->expects($this->once())
            ->method('getResourceOwnerData')
            ->willReturn([
                'sub'            => 'google-id',
                'email'          => 'michel@example.com',
                'email_verified' => '1',
                'name'           => 'Michel',
            ]);

        $profile = $this->service->getUserProfile($token);

        $this->assertTrue($profile['email_verified']);
    }

    public function testGetUserProfileReturnsNullAvatarWhenPictureIsEmpty(): void
    {
        $token = $this->createMock(AccessTokenInterface::class);

        $this->provider
            ->expects($this->once())
            ->method('getResourceOwnerData')
            ->willReturn([
                'sub'            => 'google-id',
                'email'          => 'michel@example.com',
                'email_verified' => true,
                'name'           => 'Michel',
                'picture'        => '   ',
            ]);

        $profile = $this->service->getUserProfile($token);

        $this->assertNull($profile['avatar']);
    }
}
