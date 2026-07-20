<?php

declare(strict_types=1);

namespace Tests\Unit\Service\OAuth;

use App\Service\OAuth\Contract\GoogleOAuthProfileMapperInterface;
use App\Service\OAuth\Contract\GoogleOAuthProviderInterface;
use App\Service\OAuth\GoogleOAuthService;
use League\OAuth2\Client\Token\AccessTokenInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GoogleOAuthServiceTest extends TestCase
{
    private GoogleOAuthProviderInterface&MockObject $provider;

    private GoogleOAuthProfileMapperInterface&MockObject $profileMapper;

    private GoogleOAuthService $service;

    protected function setUp(): void
    {
        $this->provider = $this->createMock(
            GoogleOAuthProviderInterface::class
        );

        $this->profileMapper = $this->createMock(
            GoogleOAuthProfileMapperInterface::class
        );

        $this->service = new GoogleOAuthService(
            $this->provider,
            $this->profileMapper
        );
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

    public function testGetUserProfileDelegatesProfileMapping(): void
    {
        $token = $this->createMock(AccessTokenInterface::class);

        $rawData = [
            'sub'            => 'google-id',
            'email'          => 'michael@example.com',
            'email_verified' => true,
            'name'           => 'Michael Doe',
            'picture'        => 'https://avatar.example.com/avatar.jpg',
        ];

        $mappedProfile = [
            'id'             => 'google-id',
            'email'          => 'michael@example.com',
            'email_verified' => true,
            'name'           => 'Michael Doe',
            'avatar'         => 'https://avatar.example.com/avatar.jpg',
        ];

        $this->provider
            ->expects($this->once())
            ->method('getResourceOwnerData')
            ->with($token)
            ->willReturn($rawData);

        $this->profileMapper
            ->expects($this->once())
            ->method('map')
            ->with($rawData)
            ->willReturn($mappedProfile);

        $this->assertSame(
            $mappedProfile,
            $this->service->getUserProfile($token)
        );
    }
}
