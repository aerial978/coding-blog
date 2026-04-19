<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use App\Core\Contract\FlashInterface;
use App\Core\Contract\RateLimiterFactoryInterface;
use App\Core\View;
use App\Handler\Auth\ForgotPasswordGetHandler;
use App\Handler\Auth\ForgotPasswordPostHandler;
use App\Handler\Auth\LoginGetHandler;
use App\Handler\Auth\LoginPostHandler;
use App\Handler\Auth\LogoutHandler;
use App\Handler\Auth\RegisterGetHandler;
use App\Handler\Auth\RegisterPostHandler;
use App\Handler\Auth\ResendConfirmationGetHandler;
use App\Handler\Auth\ResendConfirmationPostHandler;
use App\Handler\Auth\ResetPasswordGetHandler;
use App\Handler\Auth\ResetPasswordPostHandler;
use App\Http\Contract\ResponderInterface;
use App\Log\LogContextNormalizer;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Security\Contract\TurnstileValidatorInterface;
use App\Security\Guard\Contract\HoneypotGuardInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Security\Guard\Contract\SubmissionDelayGuardInterface;
use App\Security\Guard\Contract\TurnstileGuardInterface;
use App\Security\Guard\HoneypotGuard;
use App\Security\Guard\RateLimitGuard;
use App\Security\Guard\SubmissionDelayGuard;
use App\Security\Guard\TurnstileGuard;
use App\Service\Security\Contract\ResetPasswordServiceInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use App\Support\ErrorListNormalizer;
use Psr\Container\ContainerInterface;

final class AuthServiceProvider
{
    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    public static function getDefinitions(): array
    {
        return array_merge(
            self::getGuardDefinitions(),
            self::getGuardBindings(),
            self::getRegisterHandlerDefinitions(),
            self::getResendConfirmationHandlerDefinitions(),
            self::getLoginHandlerDefinitions(),
            self::getForgotPasswordHandlerDefinitions(),
            self::getResetPasswordHandlerDefinitions(),
            self::getLogoutHandlerDefinitions(),
        );
    }

    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    private static function getGuardDefinitions(): array
    {
        return array_merge(
            self::getValidationGuardDefinitions(),
            self::getRateLimitGuardDefinitions(),
        );
    }

    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    private static function getValidationGuardDefinitions(): array
    {
        return [
            HoneypotGuard::class => static function (ContainerInterface $container): HoneypotGuard {
                /** @var HoneypotValidatorInterface $honeypot */
                $honeypot = $container->get(HoneypotValidatorInterface::class);
                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);
                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);
                /** @var LogContextNormalizer $logNormalizer */
                $logNormalizer = $container->get(LogContextNormalizer::class);

                return new HoneypotGuard(
                    $honeypot,
                    $flash,
                    $responder,
                    $logNormalizer,
                );
            },

            SubmissionDelayGuard::class => static function (ContainerInterface $container): SubmissionDelayGuard {
                /** @var SubmissionDelayValidatorInterface $submissionDelay */
                $submissionDelay = $container->get(SubmissionDelayValidatorInterface::class);
                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);
                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);
                /** @var LogContextNormalizer $logNormalizer */
                $logNormalizer = $container->get(LogContextNormalizer::class);

                return new SubmissionDelayGuard(
                    $submissionDelay,
                    $flash,
                    $responder,
                    $logNormalizer,
                );
            },

            TurnstileGuard::class => static function (ContainerInterface $container): TurnstileGuard {
                /** @var TurnstileValidatorInterface $turnstile */
                $turnstile = $container->get(TurnstileValidatorInterface::class);
                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);
                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);
                /** @var LogContextNormalizer $logNormalizer */
                $logNormalizer = $container->get(LogContextNormalizer::class);

                return new TurnstileGuard(
                    $turnstile,
                    $flash,
                    $responder,
                    $logNormalizer,
                );
            },
        ];
    }

    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    private static function getRateLimitGuardDefinitions(): array
    {
        return [
            RateLimitGuard::class => static function (ContainerInterface $container): RateLimitGuard {
                /** @var RateLimiterFactoryInterface $rateLimiterFactory */
                $rateLimiterFactory = $container->get(RateLimiterFactoryInterface::class);
                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);
                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);

                return new RateLimitGuard(
                    $rateLimiterFactory,
                    $flash,
                    $responder,
                );
            },
        ];
    }

    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    private static function getGuardBindings(): array
    {
        return [
            HoneypotGuardInterface::class => static function (ContainerInterface $container): HoneypotGuardInterface {
                /** @var HoneypotGuardInterface $guard */
                $guard = $container->get(HoneypotGuard::class);

                return $guard;
            },

            RateLimitGuardInterface::class => static function (ContainerInterface $container): RateLimitGuardInterface {
                /** @var RateLimitGuardInterface $guard */
                $guard = $container->get(RateLimitGuard::class);

                return $guard;
            },

            SubmissionDelayGuardInterface::class => static function (ContainerInterface $container): SubmissionDelayGuardInterface {
                /** @var SubmissionDelayGuardInterface $guard */
                $guard = $container->get(SubmissionDelayGuard::class);

                return $guard;
            },

            TurnstileGuardInterface::class => static function (ContainerInterface $container): TurnstileGuardInterface {
                /** @var TurnstileGuardInterface $guard */
                $guard = $container->get(TurnstileGuard::class);

                return $guard;
            },
        ];
    }

    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    private static function getRegisterHandlerDefinitions(): array
    {
        return [
            RegisterGetHandler::class => static function (ContainerInterface $container): RegisterGetHandler {
                /** @var View $view */
                $view = $container->get(View::class);
                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);
                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);
                /** @var CsrfTokenInterface $csrf */
                $csrf = $container->get(CsrfTokenInterface::class);
                /** @var HoneypotValidatorInterface $honeypot */
                $honeypot = $container->get(HoneypotValidatorInterface::class);
                /** @var SubmissionDelayValidatorInterface $submissionDelay */
                $submissionDelay = $container->get(SubmissionDelayValidatorInterface::class);

                return new RegisterGetHandler(
                    $view,
                    $flash,
                    $responder,
                    $csrf,
                    $honeypot,
                    $submissionDelay,
                );
            },

            RegisterPostHandler::class => static function (ContainerInterface $container): RegisterPostHandler {
                /** @var SecurityServiceInterface $securityService */
                $securityService = $container->get(SecurityServiceInterface::class);
                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);
                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);
                /** @var HoneypotGuardInterface $honeypotGuard */
                $honeypotGuard = $container->get(HoneypotGuardInterface::class);
                /** @var SubmissionDelayGuardInterface $submissionDelayGuard */
                $submissionDelayGuard = $container->get(SubmissionDelayGuardInterface::class);
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
            ResendConfirmationGetHandler::class => static function (ContainerInterface $container): ResendConfirmationGetHandler {
                /** @var View $view */
                $view = $container->get(View::class);
                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);
                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);
                /** @var SubmissionDelayValidatorInterface $submissionDelay */
                $submissionDelay = $container->get(SubmissionDelayValidatorInterface::class);
                /** @var CsrfTokenInterface $csrf */
                $csrf = $container->get(CsrfTokenInterface::class);
                /** @var HoneypotValidatorInterface $honeypot */
                $honeypot = $container->get(HoneypotValidatorInterface::class);

                return new ResendConfirmationGetHandler(
                    $view,
                    $flash,
                    $responder,
                    $submissionDelay,
                    $csrf,
                    $honeypot,
                );
            },

            ResendConfirmationPostHandler::class => static function (ContainerInterface $container): ResendConfirmationPostHandler {
                /** @var SecurityServiceInterface $securityService */
                $securityService = $container->get(SecurityServiceInterface::class);
                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);
                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);
                /** @var HoneypotGuardInterface $honeypotGuard */
                $honeypotGuard = $container->get(HoneypotGuardInterface::class);
                /** @var SubmissionDelayGuardInterface $submissionDelayGuard */
                $submissionDelayGuard = $container->get(SubmissionDelayGuardInterface::class);
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

    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    private static function getLoginHandlerDefinitions(): array
    {
        return [
            LoginGetHandler::class => static function (ContainerInterface $container): LoginGetHandler {
                /** @var View $view */
                $view = $container->get(View::class);
                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);
                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);
                /** @var CsrfTokenInterface $csrf */
                $csrf = $container->get(CsrfTokenInterface::class);
                /** @var HoneypotValidatorInterface $honeypot */
                $honeypot = $container->get(HoneypotValidatorInterface::class);
                /** @var SubmissionDelayValidatorInterface $submissionDelay */
                $submissionDelay = $container->get(SubmissionDelayValidatorInterface::class);

                return new LoginGetHandler(
                    $view,
                    $flash,
                    $responder,
                    $csrf,
                    $honeypot,
                    $submissionDelay,
                );
            },

            LoginPostHandler::class => static function (ContainerInterface $container): LoginPostHandler {
                /** @var SecurityServiceInterface $securityService */
                $securityService = $container->get(SecurityServiceInterface::class);
                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);
                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);
                /** @var HoneypotGuardInterface $honeypotGuard */
                $honeypotGuard = $container->get(HoneypotGuardInterface::class);
                /** @var SubmissionDelayGuardInterface $submissionDelayGuard */
                $submissionDelayGuard = $container->get(SubmissionDelayGuardInterface::class);
                /** @var RateLimitGuardInterface $rateLimitGuard */
                $rateLimitGuard = $container->get(RateLimitGuardInterface::class);

                return new LoginPostHandler(
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
    private static function getForgotPasswordHandlerDefinitions(): array
    {
        return [
            ForgotPasswordGetHandler::class => static function (ContainerInterface $container): ForgotPasswordGetHandler {
                /** @var View $view */
                $view = $container->get(View::class);
                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);
                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);
                /** @var CsrfTokenInterface $csrf */
                $csrf = $container->get(CsrfTokenInterface::class);
                /** @var HoneypotValidatorInterface $honeypot */
                $honeypot = $container->get(HoneypotValidatorInterface::class);
                /** @var SubmissionDelayValidatorInterface $submissionDelay */
                $submissionDelay = $container->get(SubmissionDelayValidatorInterface::class);

                return new ForgotPasswordGetHandler(
                    $view,
                    $flash,
                    $responder,
                    $csrf,
                    $honeypot,
                    $submissionDelay,
                );
            },

            ForgotPasswordPostHandler::class => static function (ContainerInterface $container): ForgotPasswordPostHandler {
                /** @var SecurityServiceInterface $securityService */
                $securityService = $container->get(SecurityServiceInterface::class);
                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);
                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);
                /** @var HoneypotGuardInterface $honeypotGuard */
                $honeypotGuard = $container->get(HoneypotGuardInterface::class);
                /** @var SubmissionDelayGuardInterface $submissionDelayGuard */
                $submissionDelayGuard = $container->get(SubmissionDelayGuardInterface::class);
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
        return [
            ResetPasswordGetHandler::class => static function (ContainerInterface $container): ResetPasswordGetHandler {
                /** @var View $view */
                $view = $container->get(View::class);
                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);
                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);
                /** @var CsrfTokenInterface $csrf */
                $csrf = $container->get(CsrfTokenInterface::class);
                /** @var HoneypotValidatorInterface $honeypot */
                $honeypot = $container->get(HoneypotValidatorInterface::class);
                /** @var SubmissionDelayValidatorInterface $submissionDelay */
                $submissionDelay = $container->get(SubmissionDelayValidatorInterface::class);
                /** @var ResetPasswordServiceInterface $resetPasswordService */
                $resetPasswordService = $container->get(ResetPasswordServiceInterface::class);

                return new ResetPasswordGetHandler(
                    $view,
                    $flash,
                    $responder,
                    $csrf,
                    $honeypot,
                    $submissionDelay,
                    $resetPasswordService,
                );
            },

            ResetPasswordPostHandler::class => static function (ContainerInterface $container): ResetPasswordPostHandler {
                /** @var SecurityServiceInterface $securityService */
                $securityService = $container->get(SecurityServiceInterface::class);
                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);
                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);
                /** @var HoneypotGuardInterface $honeypotGuard */
                $honeypotGuard = $container->get(HoneypotGuardInterface::class);
                /** @var SubmissionDelayGuardInterface $submissionDelayGuard */
                $submissionDelayGuard = $container->get(SubmissionDelayGuardInterface::class);
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

    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    private static function getLogoutHandlerDefinitions(): array
    {
        return [
            LogoutHandler::class => static function (ContainerInterface $container): LogoutHandler {
                /** @var SecurityServiceInterface $securityService */
                $securityService = $container->get(SecurityServiceInterface::class);
                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);
                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);

                return new LogoutHandler(
                    $securityService,
                    $flash,
                    $responder,
                );
            },
        ];
    }
}
