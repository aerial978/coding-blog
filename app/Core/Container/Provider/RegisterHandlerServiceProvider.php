<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use App\Core\Contract\FlashInterface;
use App\Handler\Auth\RegisterGetHandler;
use App\Handler\Auth\RegisterPostHandler;
use App\Handler\Auth\ResendConfirmationGetHandler;
use App\Handler\Auth\ResendConfirmationPostHandler;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Security\Guard\Contract\HoneypotGuardInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Security\Guard\Contract\SubmissionDelayGuardInterface;
use App\Security\Guard\Contract\TurnstileGuardInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use App\Support\ErrorListNormalizer;
use Psr\Container\ContainerInterface;

final class RegisterHandlerServiceProvider
{
    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    public static function getDefinitions(): array
    {
        return array_merge(
            self::getRegisterHandlerDefinitions(),
            self::getResendConfirmationHandlerDefinitions(),
        );
    }

    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    private static function getRegisterHandlerDefinitions(): array
    {
        return [
            RegisterGetHandler::class => static function (
                ContainerInterface $container
            ): RegisterGetHandler {
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

                return new RegisterGetHandler(
                    $flash,
                    $responder,
                    $csrf,
                    $honeypot,
                    $submissionDelay,
                );
            },

            RegisterPostHandler::class => static function (
                ContainerInterface $container
            ): RegisterPostHandler {
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

                /** @var TurnstileGuardInterface $turnstileGuard */
                $turnstileGuard = $container->get(TurnstileGuardInterface::class);

                /** @var ErrorListNormalizer $errorListNormalizer */
                $errorListNormalizer = $container->get(ErrorListNormalizer::class);

                return new RegisterPostHandler(
                    $securityService,
                    $flash,
                    $responder,
                    $honeypotGuard,
                    $submissionDelayGuard,
                    $rateLimitGuard,
                    $turnstileGuard,
                    $errorListNormalizer,
                );
            },
        ];
    }

    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    private static function getResendConfirmationHandlerDefinitions(): array
    {
        return [
            ResendConfirmationGetHandler::class => static function (
                ContainerInterface $container
            ): ResendConfirmationGetHandler {
                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);

                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);

                /** @var SubmissionDelayValidatorInterface $submissionDelay */
                $submissionDelay = $container->get(
                    SubmissionDelayValidatorInterface::class
                );

                /** @var CsrfTokenInterface $csrf */
                $csrf = $container->get(CsrfTokenInterface::class);

                /** @var HoneypotValidatorInterface $honeypot */
                $honeypot = $container->get(HoneypotValidatorInterface::class);

                return new ResendConfirmationGetHandler(
                    $flash,
                    $responder,
                    $submissionDelay,
                    $csrf,
                    $honeypot,
                );
            },

            ResendConfirmationPostHandler::class => static function (
                ContainerInterface $container
            ): ResendConfirmationPostHandler {
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

                return new ResendConfirmationPostHandler(
                    $securityService,
                    $flash,
                    $responder,
                    $honeypotGuard,
                    $submissionDelayGuard,
                    $rateLimitGuard,
                );
            },
        ];
    }
}
