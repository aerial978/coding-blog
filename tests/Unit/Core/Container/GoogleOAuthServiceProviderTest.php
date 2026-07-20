<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Container;

use App\Core\Container\Provider\GoogleOAuthServiceProvider;
use App\Core\Contract\FlashInterface;
use App\Core\Contract\SessionInterface;
use App\Handler\OAuth\GoogleOAuthCallbackHandler;
use App\Handler\OAuth\GoogleOAuthStartHandler;
use App\Http\Contract\ResponderInterface;
use App\Model\Contract\OAuthAccountModelInterface;
use App\Model\Contract\UserModelInterface;
use App\Service\OAuth\Contract\GoogleOAuthProfileMapperInterface;
use App\Service\OAuth\Contract\GoogleOAuthProviderInterface;
use App\Service\OAuth\Contract\GoogleOAuthServiceInterface;
use App\Service\OAuth\Contract\OAuthUserProvisioningServiceInterface;
use App\Service\OAuth\Factory\GoogleOAuthProviderFactory;
use App\Service\OAuth\GoogleOAuthProfileMapper;
use App\Service\OAuth\GoogleOAuthProviderAdapter;
use App\Service\OAuth\GoogleOAuthService;
use App\Service\OAuth\OAuthUserProvisioningService;
use Cocur\Slugify\Slugify;
use League\OAuth2\Client\Provider\Google;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

final class GoogleOAuthServiceProviderTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        $this->envBackup = $_ENV;

        $_ENV['GOOGLE_CLIENT_ID']     = 'client-id';
        $_ENV['GOOGLE_CLIENT_SECRET'] = 'client-secret';
        $_ENV['GOOGLE_REDIRECT_URI']  = 'http://localhost/coding-blog/auth/google/callback';
        $_ENV['GOOGLE_OAUTH_SCOPES']  = 'openid email profile';
    }

    protected function tearDown(): void
    {
        $_ENV = $this->envBackup;
    }

    public function testGetDefinitionsReturnsExpectedDefinitions(): void
    {
        $definitions = GoogleOAuthServiceProvider::getDefinitions();

        $this->assertArrayHasKey(GoogleOAuthProviderFactory::class, $definitions);
        $this->assertArrayHasKey(Google::class, $definitions);
        $this->assertArrayHasKey(GoogleOAuthProviderAdapter::class, $definitions);
        $this->assertArrayHasKey(GoogleOAuthProviderInterface::class, $definitions);
        $this->assertArrayHasKey(GoogleOAuthService::class, $definitions);
        $this->assertArrayHasKey(GoogleOAuthServiceInterface::class, $definitions);
        $this->assertArrayHasKey(OAuthUserProvisioningService::class, $definitions);
        $this->assertArrayHasKey(OAuthUserProvisioningServiceInterface::class, $definitions);
        $this->assertArrayHasKey(GoogleOAuthStartHandler::class, $definitions);
        $this->assertArrayHasKey(GoogleOAuthCallbackHandler::class, $definitions);
        $this->assertArrayHasKey(GoogleOAuthProfileMapper::class, $definitions);
        $this->assertArrayHasKey(GoogleOAuthProfileMapperInterface::class, $definitions);
    }

    public function testGoogleOAuthServiceDefinitionsCanBeResolved(): void
    {
        $definitions = GoogleOAuthServiceProvider::getDefinitions();

        $factory = $definitions[GoogleOAuthProviderFactory::class](
            $this->containerWith([])
        );

        $google = $definitions[Google::class](
            $this->containerWith([
                GoogleOAuthProviderFactory::class => $factory,
            ])
        );

        $adapter = $definitions[GoogleOAuthProviderAdapter::class](
            $this->containerWith([
                Google::class => $google,
            ])
        );

        $provider = $definitions[GoogleOAuthProviderInterface::class](
            $this->containerWith([
                GoogleOAuthProviderAdapter::class => $adapter,
            ])
        );

        $profileMapper = $definitions[GoogleOAuthProfileMapper::class](
            $this->containerWith([])
        );

        $profileMapperInterface = $definitions[GoogleOAuthProfileMapperInterface::class](
            $this->containerWith([
                GoogleOAuthProfileMapper::class => $profileMapper,
            ])
        );

        $service = $definitions[GoogleOAuthService::class](
            $this->containerWith([
                GoogleOAuthProviderInterface::class      => $provider,
                GoogleOAuthProfileMapperInterface::class => $profileMapperInterface,
            ])
        );

        $serviceInterface = $definitions[GoogleOAuthServiceInterface::class](
            $this->containerWith([
                GoogleOAuthService::class => $service,
            ])
        );

        $provisioning = $definitions[OAuthUserProvisioningService::class](
            $this->containerWith([
                UserModelInterface::class => $this->createMock(UserModelInterface::class),
                Slugify::class            => new Slugify(),
            ])
        );

        $provisioningInterface = $definitions[OAuthUserProvisioningServiceInterface::class](
            $this->containerWith([
                OAuthUserProvisioningService::class => $provisioning,
            ])
        );

        $this->assertInstanceOf(GoogleOAuthProviderFactory::class, $factory);
        $this->assertInstanceOf(Google::class, $google);
        $this->assertInstanceOf(GoogleOAuthProviderAdapter::class, $adapter);
        $this->assertInstanceOf(GoogleOAuthProviderInterface::class, $provider);
        $this->assertInstanceOf(GoogleOAuthService::class, $service);
        $this->assertInstanceOf(GoogleOAuthServiceInterface::class, $serviceInterface);
        $this->assertInstanceOf(GoogleOAuthProfileMapper::class, $profileMapper);
        $this->assertInstanceOf(GoogleOAuthProfileMapperInterface::class, $profileMapperInterface);
        $this->assertInstanceOf(OAuthUserProvisioningService::class, $provisioning);
        $this->assertInstanceOf(OAuthUserProvisioningServiceInterface::class, $provisioningInterface);
    }

    public function testGoogleOAuthHandlerDefinitionsCanBeResolved(): void
    {
        $definitions = GoogleOAuthServiceProvider::getDefinitions();

        $oauthService      = $this->createMock(GoogleOAuthServiceInterface::class);
        $session           = $this->createMock(SessionInterface::class);
        $responder         = $this->createMock(ResponderInterface::class);
        $oauthAccountModel = $this->createMock(OAuthAccountModelInterface::class);
        $userModel         = $this->createMock(UserModelInterface::class);
        $provisioning      = $this->createMock(OAuthUserProvisioningServiceInterface::class);
        $flash             = $this->createMock(FlashInterface::class);

        $startHandler = $definitions[GoogleOAuthStartHandler::class](
            $this->containerWith([
                GoogleOAuthServiceInterface::class => $oauthService,
                SessionInterface::class            => $session,
                ResponderInterface::class          => $responder,
            ])
        );

        $callbackHandler = $definitions[GoogleOAuthCallbackHandler::class](
            $this->containerWith([
                GoogleOAuthServiceInterface::class           => $oauthService,
                OAuthAccountModelInterface::class            => $oauthAccountModel,
                UserModelInterface::class                    => $userModel,
                OAuthUserProvisioningServiceInterface::class => $provisioning,
                SessionInterface::class                      => $session,
                FlashInterface::class                        => $flash,
                ResponderInterface::class                    => $responder,
            ])
        );

        $this->assertInstanceOf(GoogleOAuthStartHandler::class, $startHandler);
        $this->assertInstanceOf(GoogleOAuthCallbackHandler::class, $callbackHandler);
    }

    /**
     * @param array<string,mixed> $services
     *
     * @return ContainerInterface&MockObject
     */
    private function containerWith(array $services): ContainerInterface&MockObject
    {
        $container = $this->createMock(ContainerInterface::class);

        $container
            ->method('get')
            ->willReturnCallback(
                static function (string $id) use ($services): mixed {
                    if (!array_key_exists($id, $services)) {
                        throw new RuntimeException(sprintf(
                            'Service "%s" is not defined in test container.',
                            $id
                        ));
                    }

                    return $services[$id];
                }
            );

        return $container;
    }
}
