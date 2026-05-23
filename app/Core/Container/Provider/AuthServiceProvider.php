<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use App\Core\Contract\FlashInterface;
use App\Core\Contract\RateLimiterFactoryInterface;
use App\Core\Contract\SessionInterface;
use App\Core\Mail\MailerInterface;
use App\Handler\Auth\Email2faGetHandler;
use App\Handler\Auth\Email2faPostHandler;
use App\Handler\Auth\Email2faResendPostHandler;
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
use App\Model\Contract\Email2faChallengeModelInterface;
use App\Model\Contract\UserModelInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\Email2faPendingSessionInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\RememberMeCookieManagerInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Security\Contract\TurnstileValidatorInterface;
use App\Security\Email2faPendingSession;
use App\Security\Guard\Contract\HoneypotGuardInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Security\Guard\Contract\SubmissionDelayGuardInterface;
use App\Security\Guard\Contract\TurnstileGuardInterface;
use App\Security\Guard\HoneypotGuard;
use App\Security\Guard\RateLimitGuard;
use App\Security\Guard\SubmissionDelayGuard;
use App\Security\Guard\TurnstileGuard;
use App\Service\Security\Contract\Email2faServiceInterface;
use App\Service\Security\Contract\RememberMeServiceInterface;
use App\Service\Security\Contract\ResetPasswordServiceInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use App\Service\Security\Email2faService;
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
            self::getEmail2faDefinitions(),
            self::getRegisterHandlerDefinitions(),
            self::getResendConfirmationHandlerDefinitions(),
            self::getLoginHandlerDefinitions(),
            self::getEmail2faHandlerDefinitions(),
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
    private static function getEmail2faDefinitions(): array
    {
        return [
            Email2faPendingSession::class => static function (ContainerInterface $container): Email2faPendingSession {
                /** @var SessionInterface $session */
                $session = $container->get(SessionInterface::class);

                return new Email2faPendingSession($session);
            },

            Email2faPendingSessionInterface::class => static function (
                ContainerInterface $container
            ): Email2faPendingSessionInterface {
                /** @var Email2faPendingSessionInterface $pendingSession */
                $pendingSession = $container->get(Email2faPendingSession::class);

                return $pendingSession;
            },

            Email2faService::class => static function (ContainerInterface $container): Email2faService {
                /** @var Email2faChallengeModelInterface $challengeModel */
                $challengeModel = $container->get(Email2faChallengeModelInterface::class);

                /** @var MailerInterface $mailer */
                $mailer = $container->get(MailerInterface::class);

                return new Email2faService($challengeModel, $mailer);
            },

            Email2faServiceInterface::class => static function (
                ContainerInterface $container
            ): Email2faServiceInterface {
                /** @var Email2faServiceInterface $service */
                $service = $container->get(Email2faService::class);

                return $service;
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
                /** @var RememberMeCookieManagerInterface $rememberMe */
                $rememberMe = $container->get(RememberMeCookieManagerInterface::class);

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

    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    private static function getForgotPasswordHandlerDefinitions(): array
    {
        return [
            ForgotPasswordGetHandler::class => static function (ContainerInterface $container): ForgotPasswordGetHandler {
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
                /** @var RememberMeCookieManagerInterface $rememberMeManager */
                $rememberMeManager = $container->get(RememberMeCookieManagerInterface::class);

                return new LogoutHandler(
                    $securityService,
                    $flash,
                    $responder,
                    $rememberMeManager,
                );
            },
        ];
    }

    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    private static function getEmail2faHandlerDefinitions(): array
    {
        return [
            Email2faGetHandler::class => static function (ContainerInterface $container): Email2faGetHandler {
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
                /** @var Email2faPendingSessionInterface $pendingSession */
                $pendingSession = $container->get(Email2faPendingSessionInterface::class);

                return new Email2faGetHandler(
                    $flash,
                    $responder,
                    $csrf,
                    $honeypot,
                    $submissionDelay,
                    $pendingSession,
                );
            },

            Email2faPostHandler::class => static function (ContainerInterface $container): Email2faPostHandler {
                /** @var Email2faServiceInterface $email2faService */
                $email2faService = $container->get(Email2faServiceInterface::class);
                /** @var Email2faPendingSessionInterface $pendingSession */
                $pendingSession = $container->get(Email2faPendingSessionInterface::class);
                /** @var SessionInterface $session */
                $session = $container->get(SessionInterface::class);
                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);
                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);
                /** @var HoneypotGuardInterface $honeypotGuard */
                $honeypotGuard = $container->get(HoneypotGuardInterface::class);
                /** @var CsrfTokenInterface $csrf */
                $csrf = $container->get(CsrfTokenInterface::class);
                /** @var SubmissionDelayGuardInterface $submissionDelayGuard */
                $submissionDelayGuard = $container->get(SubmissionDelayGuardInterface::class);
                /** @var RateLimitGuardInterface $rateLimitGuard */
                $rateLimitGuard = $container->get(RateLimitGuardInterface::class);
                /** @var RememberMeServiceInterface $rememberMeService */
                $rememberMeService = $container->get(RememberMeServiceInterface::class);
                /** @var RememberMeCookieManagerInterface $rememberMeManager */
                $rememberMeManager = $container->get(RememberMeCookieManagerInterface::class);

                return new Email2faPostHandler(
                    $email2faService,
                    $pendingSession,
                    $session,
                    $flash,
                    $responder,
                    $csrf,
                    $honeypotGuard,
                    $submissionDelayGuard,
                    $rateLimitGuard,
                    $rememberMeService,
                    $rememberMeManager,
                );
            },

            Email2faResendPostHandler::class => static function (
                ContainerInterface $container
            ): Email2faResendPostHandler {
                /** @var Email2faServiceInterface $email2faService */
                $email2faService = $container->get(Email2faServiceInterface::class);
                /** @var Email2faPendingSessionInterface $pendingSession */
                $pendingSession = $container->get(Email2faPendingSessionInterface::class);
                /** @var UserModelInterface $userModel */
                $userModel = $container->get(UserModelInterface::class);
                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);
                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);
                /** @var HoneypotGuardInterface $honeypotGuard */
                $honeypotGuard = $container->get(HoneypotGuardInterface::class);
                /** @var CsrfTokenInterface $csrf */
                $csrf = $container->get(CsrfTokenInterface::class);
                /** @var SubmissionDelayGuardInterface $submissionDelayGuard */
                $submissionDelayGuard = $container->get(SubmissionDelayGuardInterface::class);
                /** @var RateLimitGuardInterface $rateLimitGuard */
                $rateLimitGuard = $container->get(RateLimitGuardInterface::class);

                return new Email2faResendPostHandler(
                    $email2faService,
                    $pendingSession,
                    $userModel,
                    $flash,
                    $responder,
                    $csrf,
                    $honeypotGuard,
                    $submissionDelayGuard,
                    $rateLimitGuard,
                );
            },
        ];
    }
}
