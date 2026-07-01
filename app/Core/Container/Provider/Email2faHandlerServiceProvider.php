<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use App\Core\Contract\FlashInterface;
use App\Core\Contract\SessionInterface;
use App\Handler\Auth\Email2faGetHandler;
use App\Handler\Auth\Email2faPostHandler;
use App\Handler\Auth\Email2faResendPostHandler;
use App\Http\Contract\ResponderInterface;
use App\Model\Contract\UserModelInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\Email2faPendingSessionInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\RememberMeCookieManagerInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Security\Guard\Contract\HoneypotGuardInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Security\Guard\Contract\SubmissionDelayGuardInterface;
use App\Service\Security\Contract\Email2faServiceInterface;
use App\Service\Security\Contract\RememberMeServiceInterface;
use Psr\Container\ContainerInterface;

final class Email2faHandlerServiceProvider
{
    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    public static function getDefinitions(): array
    {
        return array_merge(
            self::getEmail2faGetHandlerDefinitions(),
            self::getEmail2faPostHandlerDefinitions(),
            self::getEmail2faResendHandlerDefinitions(),
        );
    }

    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    private static function getEmail2faGetHandlerDefinitions(): array
    {
        return [
            Email2faGetHandler::class => static function (
                ContainerInterface $container
            ): Email2faGetHandler {
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

                /** @var Email2faPendingSessionInterface $pendingSession */
                $pendingSession = $container->get(
                    Email2faPendingSessionInterface::class
                );

                return new Email2faGetHandler(
                    $flash,
                    $responder,
                    $csrf,
                    $honeypot,
                    $submissionDelay,
                    $pendingSession,
                );
            },
        ];
    }

    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    private static function getEmail2faPostHandlerDefinitions(): array
    {
        return [
            Email2faPostHandler::class => static function (
                ContainerInterface $container
            ): Email2faPostHandler {
                /** @var Email2faServiceInterface $email2faService */
                $email2faService = $container->get(
                    Email2faServiceInterface::class
                );

                /** @var Email2faPendingSessionInterface $pendingSession */
                $pendingSession = $container->get(
                    Email2faPendingSessionInterface::class
                );

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
                $submissionDelayGuard = $container->get(
                    SubmissionDelayGuardInterface::class
                );

                /** @var RateLimitGuardInterface $rateLimitGuard */
                $rateLimitGuard = $container->get(RateLimitGuardInterface::class);

                /** @var RememberMeServiceInterface $rememberMeService */
                $rememberMeService = $container->get(
                    RememberMeServiceInterface::class
                );

                /** @var RememberMeCookieManagerInterface $rememberMeManager */
                $rememberMeManager = $container->get(
                    RememberMeCookieManagerInterface::class
                );

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
        ];
    }

    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    private static function getEmail2faResendHandlerDefinitions(): array
    {
        return [
            Email2faResendPostHandler::class => static function (
                ContainerInterface $container
            ): Email2faResendPostHandler {
                /** @var Email2faServiceInterface $email2faService */
                $email2faService = $container->get(
                    Email2faServiceInterface::class
                );

                /** @var Email2faPendingSessionInterface $pendingSession */
                $pendingSession = $container->get(
                    Email2faPendingSessionInterface::class
                );

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
                $submissionDelayGuard = $container->get(
                    SubmissionDelayGuardInterface::class
                );

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
