<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use App\Core\Contract\FlashInterface;
use App\Core\Contract\RateLimiterFactoryInterface;
use App\Http\Contract\ResponderInterface;
use App\Log\LogContextNormalizer;
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
use Psr\Container\ContainerInterface;

final class AuthGuardServiceProvider
{
    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    public static function getDefinitions(): array
    {
        return array_merge(
            self::getGuardDefinitions(),
            self::getGuardBindings(),
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

            SubmissionDelayGuardInterface::class => static function (
                ContainerInterface $container
            ): SubmissionDelayGuardInterface {
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
}
