<?php

declare(strict_types=1);

namespace Tests\Unit\Service\OAuth\Factory;

use App\Service\OAuth\Factory\GoogleOAuthProviderFactory;
use League\OAuth2\Client\Provider\Google;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GoogleOAuthProviderFactoryTest extends TestCase
{
    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalEnv = $_ENV;
    }

    protected function tearDown(): void
    {
        $_ENV = $this->originalEnv;

        parent::tearDown();
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

    public function testCreateAcceptsHttpRedirectUri(): void
    {
        $_ENV['GOOGLE_CLIENT_ID']     = 'client-id';
        $_ENV['GOOGLE_CLIENT_SECRET'] = 'client-secret';
        $_ENV['GOOGLE_REDIRECT_URI']  = 'http://localhost/oauth/google';
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

    public function testCreateRejectsMissingRedirectUri(): void
    {
        $_ENV['GOOGLE_CLIENT_ID']     = 'client-id';
        $_ENV['GOOGLE_CLIENT_SECRET'] = 'client-secret';
        unset($_ENV['GOOGLE_REDIRECT_URI']);

        $factory = new GoogleOAuthProviderFactory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'GOOGLE_REDIRECT_URI must be defined as a non-empty URL.'
        );

        $factory->create();
    }

    public function testCreateRejectsEmptyRedirectUri(): void
    {
        $_ENV['GOOGLE_CLIENT_ID']     = 'client-id';
        $_ENV['GOOGLE_CLIENT_SECRET'] = 'client-secret';
        $_ENV['GOOGLE_REDIRECT_URI']  = '   ';

        $factory = new GoogleOAuthProviderFactory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'GOOGLE_REDIRECT_URI must be defined as a non-empty URL.'
        );

        $factory->create();
    }

    public function testCreateRejectsInvalidRedirectUri(): void
    {
        $_ENV['GOOGLE_CLIENT_ID']     = 'client-id';
        $_ENV['GOOGLE_CLIENT_SECRET'] = 'client-secret';
        $_ENV['GOOGLE_REDIRECT_URI']  = 'not-a-valid-url';

        $factory = new GoogleOAuthProviderFactory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'GOOGLE_REDIRECT_URI must be a valid URL.'
        );

        $factory->create();
    }

    public function testCreateRejectsUnsupportedRedirectUriScheme(): void
    {
        $_ENV['GOOGLE_CLIENT_ID']     = 'client-id';
        $_ENV['GOOGLE_CLIENT_SECRET'] = 'client-secret';
        $_ENV['GOOGLE_REDIRECT_URI']  = 'ftp://example.com/oauth/google';

        $factory = new GoogleOAuthProviderFactory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'GOOGLE_REDIRECT_URI must use the http or https scheme.'
        );

        $factory->create();
    }
}
