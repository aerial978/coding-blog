<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use Psr\Container\ContainerInterface;
use App\Core\Contract\FlashInterface;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Security\Contract\TurnstileValidatorInterface;
use App\Core\Contract\RateLimiterFactoryInterface;
use App\Security\Guard\HoneypotGuard;
use App\Security\Guard\SubmissionDelayGuard;
use App\Security\Guard\TurnstileGuard;
use App\Security\Guard\RateLimitGuard;
use App\Handler\Auth\RegisterGetHandler;
use App\Handler\Auth\RegisterPostHandler;
use App\Core\View;
use App\Handler\Auth\ResendConfirmationGetHandler;
use App\Handler\Auth\ResendConfirmationPostHandler;
use App\Handler\Auth\LoginGetHandler;
use App\Handler\Auth\LoginPostHandler;
use App\Log\LogContextNormalizer;
use App\Support\ErrorListNormalizer;
use App\Handler\Auth\ForgotPasswordGetHandler;
use App\Handler\Auth\ForgotPasswordPostHandler; // plus tard, quand vous l’aurez créé


final class AuthServiceProvider
{
    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    public static function getDefinitions(): array
    {
        return [
            // -----------------
            // Guards réutilisables
            // -----------------
            HoneypotGuard::class => static function (ContainerInterface $c): HoneypotGuard {
                return new HoneypotGuard(
                    $c->get(HoneypotValidatorInterface::class),
                    $c->get(FlashInterface::class),
                    $c->get(ResponderInterface::class),
                    $c->get(LogContextNormalizer::class),
                );
            },

            SubmissionDelayGuard::class => static function (ContainerInterface $c): SubmissionDelayGuard {
                return new SubmissionDelayGuard(
                    $c->get(SubmissionDelayValidatorInterface::class),
                    $c->get(FlashInterface::class),
                    $c->get(ResponderInterface::class),
                    $c->get(LogContextNormalizer::class),
                );
            },

            TurnstileGuard::class => static function (ContainerInterface $c): TurnstileGuard {
                return new TurnstileGuard(
                    $c->get(TurnstileValidatorInterface::class),
                    $c->get(FlashInterface::class),
                    $c->get(ResponderInterface::class),
                    $c->get(LogContextNormalizer::class),
                );
            },

            RateLimitGuard::class => static function (ContainerInterface $c): RateLimitGuard {
                return new RateLimitGuard(
                    $c->get(RateLimiterFactoryInterface::class),
                    $c->get(FlashInterface::class),
                    $c->get(ResponderInterface::class),
                );
            },

            // -----------------
            // Handlers Register
            // -----------------
            RegisterGetHandler::class => static function (ContainerInterface $c): RegisterGetHandler {
                return new RegisterGetHandler(
                    $c->get(View::class),
                    $c->get(FlashInterface::class),
                    $c->get(ResponderInterface::class),
                    $c->get(CsrfTokenInterface::class),
                    $c->get(HoneypotValidatorInterface::class),
                    $c->get(SubmissionDelayValidatorInterface::class),
                );
            },

            RegisterPostHandler::class => static function (ContainerInterface $c): RegisterPostHandler {
                return new RegisterPostHandler(
                    $c->get(SecurityServiceInterface::class),
                    $c->get(FlashInterface::class),
                    $c->get(ResponderInterface::class),
                    $c->get(HoneypotGuard::class),
                    $c->get(SubmissionDelayGuard::class),
                    $c->get(RateLimitGuard::class),
                    $c->get(TurnstileGuard::class),
                    $c->get(ErrorListNormalizer::class),
                );
            },

            ResendConfirmationGetHandler::class => static function (ContainerInterface $c): ResendConfirmationGetHandler {
                return new ResendConfirmationGetHandler(
                    $c->get(View::class),
                    $c->get(FlashInterface::class),
                    $c->get(ResponderInterface::class),
                    $c->get(SubmissionDelayValidatorInterface::class),
                    $c->get(CsrfTokenInterface::class),
                    $c->get(HoneypotValidatorInterface::class),
                );
            },

            ResendConfirmationPostHandler::class => static function (ContainerInterface $c): ResendConfirmationPostHandler {
                return new ResendConfirmationPostHandler(
                    $c->get(SecurityServiceInterface::class),
                    $c->get(FlashInterface::class),
                    $c->get(ResponderInterface::class),
                    $c->get(HoneypotGuard::class),
                    $c->get(SubmissionDelayGuard::class),
                    $c->get(RateLimitGuard::class),
                );
            },

            LoginGetHandler::class => static function (ContainerInterface $c): LoginGetHandler {
                return new LoginGetHandler(
                    $c->get(View::class),
                    $c->get(FlashInterface::class),
                    $c->get(ResponderInterface::class),
                    $c->get(CsrfTokenInterface::class),
                    $c->get(HoneypotValidatorInterface::class),
                    $c->get(SubmissionDelayValidatorInterface::class),
                );
            },

            LoginPostHandler::class => static function (ContainerInterface $c): LoginPostHandler {
                return new LoginPostHandler(
                    $c->get(SecurityServiceInterface::class),
                    $c->get(FlashInterface::class),
                    $c->get(ResponderInterface::class),
                    $c->get(HoneypotGuard::class),
                    $c->get(SubmissionDelayGuard::class),
                    $c->get(RateLimitGuard::class),
                );
            },

            ForgotPasswordGetHandler::class => static function (ContainerInterface $c): ForgotPasswordGetHandler {
                return new ForgotPasswordGetHandler(
                    $c->get(View::class),
                    $c->get(FlashInterface::class),
                    $c->get(ResponderInterface::class),
                    $c->get(CsrfTokenInterface::class),
                    $c->get(HoneypotValidatorInterface::class),
                    $c->get(SubmissionDelayValidatorInterface::class),
                );
            },

            ForgotPasswordPostHandler::class => static function (ContainerInterface $c): ForgotPasswordPostHandler {
                return new ForgotPasswordPostHandler(
                    $c->get(SecurityServiceInterface::class),
                    $c->get(FlashInterface::class),
                    $c->get(ResponderInterface::class),
                    $c->get(HoneypotGuard::class),
                    $c->get(SubmissionDelayGuard::class),
                    $c->get(RateLimitGuard::class),
                );
            },
        ];
    }
}
