<?php

declare(strict_types=1);

namespace Tests\Unit\Service\OAuth\Factory;

use App\Service\OAuth\Factory\GoogleOAuthProviderFactory;
use League\OAuth2\Client\Provider\Google;
use PHPUnit\Framework\TestCase;

final class GoogleOAuthProviderFactoryTest extends TestCase
{
    private array $originalEnv = [];

    protected function setUp(): void
    {
        $this->originalEnv = $_ENV;
    }

    protected function tearDown(): void
    {
        $_ENV = $this->originalEnv;
    }

    public function testCreateReturnsGoogleProvider(): void
    {
        $_ENV['GOOGLE_CLIENT_ID']     = 'client-id';
        $_ENV['GOOGLE_CLIENT_SECRET'] = 'client-secret';
        $_ENV['GOOGLE_REDIRECT_URI']  = 'https://localhost/oauth/google';
        $_ENV['GOOGLE_OAUTH_SCOPES']  = 'openid email profile';

        $factory = new GoogleOAuthProviderFactory();

        $provider = $factory->create();

        $this->assertInstanceOf(Google::class, $provider);
    }

    public function testCreateReturnsGoogleProviderWithDefaultScopes(): void
    {
        unset($_ENV['GOOGLE_OAUTH_SCOPES']);

        $_ENV['GOOGLE_CLIENT_ID']     = 'client-id';
        $_ENV['GOOGLE_CLIENT_SECRET'] = 'client-secret';
        $_ENV['GOOGLE_REDIRECT_URI']  = 'https://localhost/oauth/google';

        $factory = new GoogleOAuthProviderFactory();

        $provider = $factory->create();

        $this->assertInstanceOf(Google::class, $provider);
    }

    public function testCreateReturnsGoogleProviderWhenEnvironmentVariablesAreMissing(): void
    {
        unset($_ENV['GOOGLE_CLIENT_ID']);
        unset($_ENV['GOOGLE_CLIENT_SECRET']);
        unset($_ENV['GOOGLE_REDIRECT_URI']);
        unset($_ENV['GOOGLE_OAUTH_SCOPES']);

        $factory = new GoogleOAuthProviderFactory();

        $provider = $factory->create();

        $this->assertInstanceOf(Google::class, $provider);
    }

    public function testCreateAcceptsScopesContainingExtraSpaces(): void
    {
        $_ENV['GOOGLE_CLIENT_ID']     = 'client-id';
        $_ENV['GOOGLE_CLIENT_SECRET'] = 'client-secret';
        $_ENV['GOOGLE_REDIRECT_URI']  = 'https://localhost/oauth/google';
        $_ENV['GOOGLE_OAUTH_SCOPES']  = '  openid   email    profile  ';

        $factory = new GoogleOAuthProviderFactory();

        $provider = $factory->create();

        $this->assertInstanceOf(Google::class, $provider);
    }
}
