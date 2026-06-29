<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use App\Core\Contract\FlashInterface;
use App\Handler\Auth\LoginGetHandler;
use App\Handler\Auth\LoginPostHandler;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\RememberMeCookieManagerInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Security\Guard\Contract\HoneypotGuardInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Security\Guard\Contract\SubmissionDelayGuardInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use Psr\Container\ContainerInterface;

final class LoginHandlerServiceProvider
{
    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    public static function getDefinitions(): array
    {
        return [
            LoginGetHandler::class => static function (
                ContainerInterface $container
            ): LoginGetHandler {
                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);

                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);

                /** @var CsrfTokenInterface $csrf */
                $csrf = $container->get(CsrfTokenInterface::class);

                /** @var HoneypotValidatorInterface $honeypot */
                $honeypot = $container->get(HoneypotValidatorInterface::class);

                /** @var SubmissionDelayValidatorInterface $submissionDelay */
                $submissionDelay = $container->get(
                    SubmissionDelayValidatorInterface::class
                );

                return new LoginGetHandler(
                    $flash,
                    $responder,
                    $csrf,
                    $honeypot,
                    $submissionDelay,
                );
            },

            LoginPostHandler::class => static function (
                ContainerInterface $container
            ): LoginPostHandler {
                /** @var SecurityServiceInterface $securityService */
                $securityService = $container->get(SecurityServiceInterface::class);

                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);

                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);

                /** @var HoneypotGuardInterface $honeypotGuard */
                $honeypotGuard = $container->get(HoneypotGuardInterface::class);

                /** @var SubmissionDelayGuardInterface $submissionDelayGuard */
                $submissionDelayGuard = $container->get(
                    SubmissionDelayGuardInterface::class
                );

                /** @var RateLimitGuardInterface $rateLimitGuard */
                $rateLimitGuard = $container->get(RateLimitGuardInterface::class);

                /** @var RememberMeCookieManagerInterface $rememberMe */
                $rememberMe = $container->get(
                    RememberMeCookieManagerInterface::class
                );

                return new LoginPostHandler(
                    $securityService,
                    $flash,
                    $responder,
                    $honeypotGuard,
                    $submissionDelayGuard,
                    $rateLimitGuard,
                    $rememberMe,
                );
            },
        ];
    }
}
