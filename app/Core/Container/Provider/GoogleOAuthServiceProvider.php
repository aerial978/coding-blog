<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use App\Core\Contract\FlashInterface;
use App\Core\Contract\SessionInterface;
use App\Handler\OAuth\GoogleOAuthCallbackHandler;
use App\Handler\OAuth\GoogleOAuthStartHandler;
use App\Http\Contract\ResponderInterface;
use App\Model\Contract\OAuthAccountModelInterface;
use App\Model\Contract\UserModelInterface;
use App\Service\OAuth\Contract\GoogleOAuthProviderInterface;
use App\Service\OAuth\Contract\GoogleOAuthServiceInterface;
use App\Service\OAuth\Contract\OAuthUserProvisioningServiceInterface;
use App\Service\OAuth\Factory\GoogleOAuthProviderFactory;
use App\Service\OAuth\GoogleOAuthProviderAdapter;
use App\Service\OAuth\GoogleOAuthService;
use App\Service\OAuth\OAuthUserProvisioningService;
use Cocur\Slugify\Slugify;
use League\OAuth2\Client\Provider\Google;
use Psr\Container\ContainerInterface;

final class GoogleOAuthServiceProvider
{
    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    public static function getDefinitions(): array
    {
        return array_merge(
            self::getGoogleOAuthDefinitions(),
            self::getGoogleOAuthHandlerDefinitions(),
        );
    }

    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    private static function getGoogleOAuthDefinitions(): array
    {
        return [
            GoogleOAuthProviderFactory::class => static function (): GoogleOAuthProviderFactory {
                return new GoogleOAuthProviderFactory();
            },

            Google::class => static function (ContainerInterface $container): Google {
                /** @var GoogleOAuthProviderFactory $factory */
                $factory = $container->get(GoogleOAuthProviderFactory::class);

                return $factory->create();
            },

            GoogleOAuthProviderAdapter::class => static function (
                ContainerInterface $container
            ): GoogleOAuthProviderAdapter {
                /** @var Google $provider */
                $provider = $container->get(Google::class);

                return new GoogleOAuthProviderAdapter($provider);
            },

            GoogleOAuthProviderInterface::class => static function (
                ContainerInterface $container
            ): GoogleOAuthProviderInterface {
                /** @var GoogleOAuthProviderInterface $provider */
                $provider = $container->get(GoogleOAuthProviderAdapter::class);

                return $provider;
            },

            GoogleOAuthService::class => static function (
                ContainerInterface $container
            ): GoogleOAuthService {
                /** @var GoogleOAuthProviderInterface $provider */
                $provider = $container->get(GoogleOAuthProviderInterface::class);

                return new GoogleOAuthService($provider);
            },

            GoogleOAuthServiceInterface::class => static function (
                ContainerInterface $container
            ): GoogleOAuthServiceInterface {
                /** @var GoogleOAuthServiceInterface $service */
                $service = $container->get(GoogleOAuthService::class);

                return $service;
            },

            OAuthUserProvisioningService::class => static function (
                ContainerInterface $container
            ): OAuthUserProvisioningService {
                /** @var UserModelInterface $userModel */
                $userModel = $container->get(UserModelInterface::class);

                /** @var Slugify $slugify */
                $slugify = $container->get(Slugify::class);

                return new OAuthUserProvisioningService(
                    $userModel,
                    $slugify,
                );
            },

            OAuthUserProvisioningServiceInterface::class => static function (
                ContainerInterface $container
            ): OAuthUserProvisioningServiceInterface {
                /** @var OAuthUserProvisioningServiceInterface $service */
                $service = $container->get(OAuthUserProvisioningService::class);

                return $service;
            },
        ];
    }

    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    private static function getGoogleOAuthHandlerDefinitions(): array
    {
        return [
            GoogleOAuthStartHandler::class => static function (
                ContainerInterface $container
            ): GoogleOAuthStartHandler {
                /** @var GoogleOAuthServiceInterface $googleOAuthService */
                $googleOAuthService = $container->get(
                    GoogleOAuthServiceInterface::class
                );

                /** @var SessionInterface $session */
                $session = $container->get(SessionInterface::class);

                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);

                return new GoogleOAuthStartHandler(
                    $googleOAuthService,
                    $session,
                    $responder,
                );
            },

            GoogleOAuthCallbackHandler::class => static function (
                ContainerInterface $container
            ): GoogleOAuthCallbackHandler {
                /** @var GoogleOAuthServiceInterface $googleOAuthService */
                $googleOAuthService = $container->get(
                    GoogleOAuthServiceInterface::class
                );

                /** @var OAuthAccountModelInterface $oauthAccountModel */
                $oauthAccountModel = $container->get(
                    OAuthAccountModelInterface::class
                );

                /** @var UserModelInterface $userModel */
                $userModel = $container->get(UserModelInterface::class);

                /** @var OAuthUserProvisioningServiceInterface $oauthProvisioning */
                $oauthProvisioning = $container->get(
                    OAuthUserProvisioningServiceInterface::class
                );

                /** @var SessionInterface $session */
                $session = $container->get(SessionInterface::class);

                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);

                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);

                return new GoogleOAuthCallbackHandler(
                    $googleOAuthService,
                    $oauthAccountModel,
                    $userModel,
                    $oauthProvisioning,
                    $session,
                    $flash,
                    $responder,
                );
            },
        ];
    }
}
