<?php

declare(strict_types=1);

namespace Tests\Unit\Service\OAuth;

use App\Service\OAuth\GoogleOAuthProviderAdapter;
use InvalidArgumentException;
use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GoogleOAuthProviderAdapterTest extends TestCase
{
    private Google&MockObject $provider;

    private GoogleOAuthProviderAdapter $adapter;

    protected function setUp(): void
    {
        $this->provider = $this->createMock(Google::class);

        $this->adapter = new GoogleOAuthProviderAdapter($this->provider);
    }

    public function testGetAuthorizationUrlDelegatesToProvider(): void
    {
        $url = 'https://accounts.google.com/o/oauth2/auth';

        $this->provider
            ->expects($this->once())
            ->method('getAuthorizationUrl')
            ->willReturn($url);

        $this->assertSame($url, $this->adapter->getAuthorizationUrl());
    }

    public function testGetStateDelegatesToProvider(): void
    {
        $state = 'oauth-state-123';

        $this->provider
            ->expects($this->once())
            ->method('getState')
            ->willReturn($state);

        $this->assertSame($state, $this->adapter->getState());
    }

    public function testGetAccessTokenDelegatesToProvider(): void
    {
        $token = new AccessToken([
            'access_token' => 'fake-access-token',
        ]);

        $this->provider
            ->expects($this->once())
            ->method('getAccessToken')
            ->with('authorization_code', [
                'code' => 'authorization-code',
            ])
            ->willReturn($token);

        $this->assertSame(
            $token,
            $this->adapter->getAccessToken('authorization-code')
        );
    }

    public function testGetResourceOwnerDataReturnsOwnerData(): void
    {
        $token = new AccessToken([
            'access_token' => 'fake-access-token',
        ]);

        $owner = $this->createMock(ResourceOwnerInterface::class);

        $owner
            ->expects($this->once())
            ->method('toArray')
            ->willReturn([
                'sub'            => 'google-id',
                'email'          => 'michel@example.com',
                'email_verified' => true,
                'name'           => 'Michel Hathier',
                'picture'        => 'https://example.com/avatar.jpg',
            ]);

        $this->provider
            ->expects($this->once())
            ->method('getResourceOwner')
            ->with($token)
            ->willReturn($owner);

        $data = $this->adapter->getResourceOwnerData($token);

        $this->assertSame('google-id', $data['sub']);
        $this->assertSame('michel@example.com', $data['email']);
        $this->assertTrue($data['email_verified']);
        $this->assertSame('Michel Hathier', $data['name']);
        $this->assertSame('https://example.com/avatar.jpg', $data['picture']);
    }

    public function testGetResourceOwnerDataThrowsExceptionWhenTokenIsInvalid(): void
    {
        $invalidToken = $this->createMock(AccessTokenInterface::class);

        $this->provider
            ->expects($this->never())
            ->method('getResourceOwner');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid OAuth access token.');

        $this->adapter->getResourceOwnerData($invalidToken);
    }
}
