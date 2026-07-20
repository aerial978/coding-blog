<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use App\Core\Contract\FlashInterface;
use App\Handler\Auth\ForgotPasswordGetHandler;
use App\Handler\Auth\ForgotPasswordPostHandler;
use App\Handler\Auth\ResetPasswordGetHandler;
use App\Handler\Auth\ResetPasswordPostHandler;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Security\Guard\Contract\HoneypotGuardInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Security\Guard\Contract\SubmissionDelayGuardInterface;
use App\Security\Guard\Contract\TurnstileGuardInterface;
use App\Service\Security\Contract\ResetPasswordServiceInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use App\Support\ErrorListNormalizer;
use Psr\Container\ContainerInterface;

final class PasswordRecoveryHandlerServiceProvider
{
    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    public static function getDefinitions(): array
    {
        return array_merge(
            self::getForgotPasswordHandlerDefinitions(),
            self::getResetPasswordHandlerDefinitions(),
        );
    }

    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    private static function getForgotPasswordHandlerDefinitions(): array
    {
        return [
            ForgotPasswordGetHandler::class => static function (
                ContainerInterface $container
            ): ForgotPasswordGetHandler {
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

                return new ForgotPasswordGetHandler(
                    $flash,
                    $responder,
                    $csrf,
                    $honeypot,
                    $submissionDelay,
                );
            },

            ForgotPasswordPostHandler::class => static function (
                ContainerInterface $container
            ): ForgotPasswordPostHandler {
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

                return new ForgotPasswordPostHandler(
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

    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    private static function getResetPasswordHandlerDefinitions(): array
    {
        return array_merge(
            self::getResetPasswordGetHandlerDefinitions(),
            self::getResetPasswordPostHandlerDefinitions(),
        );
    }

    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    private static function getResetPasswordGetHandlerDefinitions(): array
    {
        return [
            ResetPasswordGetHandler::class => static function (
                ContainerInterface $container
            ): ResetPasswordGetHandler {
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

                /** @var ResetPasswordServiceInterface $resetPasswordService */
                $resetPasswordService = $container->get(
                    ResetPasswordServiceInterface::class
                );

                return new ResetPasswordGetHandler(
                    $flash,
                    $responder,
                    $csrf,
                    $honeypot,
                    $submissionDelay,
                    $resetPasswordService,
                );
            },
        ];
    }

    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    private static function getResetPasswordPostHandlerDefinitions(): array
    {
        return [
            ResetPasswordPostHandler::class => static function (
                ContainerInterface $container
            ): ResetPasswordPostHandler {
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

                return new ResetPasswordPostHandler(
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
}
