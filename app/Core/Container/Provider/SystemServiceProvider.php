<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use App\Core\Database;
use App\Core\Factory\RateLimiterFactory;
use App\Core\FlashService;
use App\Core\Logger;
use App\Core\Mail\MailerInterface;
use App\Core\SessionManager;
use App\Core\SqlHelper;
use App\Core\View;
use App\Http\Request;
use App\Infrastructure\Mail\DummyMailer;
use App\Infrastructure\Mail\MailjetMailer;
use App\Middleware\AuthenticationMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Security\Contract\AuthCheckerInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\TokenGeneratorInterface;
use App\Security\CsrfTokenManager;
use App\Security\SessionAuthChecker;
use App\Security\TokenGenerator;
use App\Validation\FormValidator;
use Cocur\Slugify\Slugify;
use Psr\Container\ContainerInterface;

/**
 * Fournit les définitions des services système et d’infrastructure.
 */
final class SystemServiceProvider
{
    /**
     * @return array<string, callable(\Psr\Container\ContainerInterface=): mixed>
     */
    public static function getDefinitions(): array
    {
        /** @var array<string, callable(\Psr\Container\ContainerInterface=): mixed> $definitions */
        $definitions = array_merge(
            self::getDatabaseDefinitions(),
            self::getHttpDefinitions(),
            self::getLoggerDefinitions(),
            self::getValidationDefinitions(),
            self::getSessionDefinitions(),
            self::getSecurityDefinitions(),
            self::getMailerDefinitions()
        );

        return $definitions;
    }

    /**
     * @return array<string, callable(): mixed|callable(ContainerInterface): mixed>
     */
    private static function getDatabaseDefinitions(): array
    {
        return [
            \PDO::class => static fn (): \PDO => (new Database())->getConnection(),

            SqlHelper::class => static function (ContainerInterface $container): SqlHelper {
                /** @var \PDO $pdo */
                $pdo = $container->get(\PDO::class);
                return new SqlHelper($pdo);
            },
        ];
    }

    /**
     * @return array<string, callable(): mixed|callable(ContainerInterface): mixed>
     */
    private static function getHttpDefinitions(): array
    {
        return [
            View::class    => static fn (): View => new View(),
            Request::class => static fn (): Request => new Request(),
        ];
    }

    /**
     * @return array<string, callable(): mixed|callable(ContainerInterface): mixed>
     */
    private static function getLoggerDefinitions(): array
    {
        return [
            'logger.app'   => static fn () => Logger::getLogger('app'),
            'logger.error' => static fn () => Logger::getLogger('error'),
        ];
    }

    /**
     * @return array<string, callable(): mixed|callable(ContainerInterface): mixed>
     */
    private static function getValidationDefinitions(): array
    {
        return [
            FormValidator::class => static fn (): FormValidator => new FormValidator(),
            Slugify::class       => static fn (): Slugify => new Slugify(),
        ];
    }

    /**
     * @return array<string, callable(): mixed|callable(ContainerInterface): mixed>
     */
    private static function getSessionDefinitions(): array
    {
        return [
            SessionManager::class => static fn (): SessionManager => new SessionManager(),

            FlashService::class => static function (ContainerInterface $container): FlashService {
                /** @var SessionManager $session */
                $session = $container->get(SessionManager::class);
                return new FlashService($session);
            },

            CsrfTokenManager::class => static function (ContainerInterface $container): CsrfTokenManager {
                /** @var SessionManager $session */
                $session = $container->get(SessionManager::class);
                return new CsrfTokenManager($session);
            },

            RateLimiterFactory::class => static function (ContainerInterface $container): RateLimiterFactory {
                /** @var SessionManager $session */
                $session = $container->get(SessionManager::class);
                return new RateLimiterFactory($session);
            },
        ];
    }

    /**
     * @return array<string, callable(): mixed|callable(ContainerInterface): mixed>
     */
    private static function getSecurityDefinitions(): array
    {
        return [
            TokenGenerator::class => static fn (): TokenGenerator => new TokenGenerator(),

            TokenGeneratorInterface::class => static function (ContainerInterface $container): TokenGeneratorInterface {
                /** @var TokenGeneratorInterface $tg */
                $tg = $container->get(TokenGenerator::class);
                return $tg;
            },

            CsrfTokenInterface::class => static function (ContainerInterface $container): CsrfTokenInterface {
                /** @var CsrfTokenInterface $csrf */
                $csrf = $container->get(CsrfTokenManager::class);
                return $csrf;
            },

            AuthCheckerInterface::class => static function (ContainerInterface $container): AuthCheckerInterface {
                /** @var \App\Core\SessionManager $session */
                $session = $container->get(\App\Core\SessionManager::class);
                return new SessionAuthChecker($session);
            },

            AuthenticationMiddleware::class => static function (ContainerInterface $container): AuthenticationMiddleware {
                /** @var AuthCheckerInterface $auth */
                $auth = $container->get(AuthCheckerInterface::class);
                /** @var \App\Core\Contract\FlashInterface $flash */
                $flash = $container->get(\App\Core\FlashService::class);
                return new AuthenticationMiddleware($auth, $flash);
            },

            CsrfMiddleware::class => static function (ContainerInterface $container): CsrfMiddleware {
                /** @var CsrfTokenInterface $csrf */
                $csrf = $container->get(CsrfTokenInterface::class);
                /** @var FlashService $flash */
                $flash = $container->get(FlashService::class);
                return new CsrfMiddleware($csrf, $flash);
            },

            RateLimitMiddleware::class => static function (ContainerInterface $container): RateLimitMiddleware {
                /** @var \App\Core\Contract\RateLimiterFactoryInterface $factory */
                $factory = $container->get(RateLimiterFactory::class);
                /** @var FlashService $flash */
                $flash = $container->get(FlashService::class);
                return new RateLimitMiddleware($factory, $flash);
            },
        ];
    }

    /**
     * @return array<string, callable(): mixed|callable(ContainerInterface): mixed>
     */
    private static function getMailerDefinitions(): array
    {
        return [
            MailerInterface::class => static fn (): MailerInterface => self::createMailerFromEnv(),
        ];
    }

    private static function createMailerFromEnv(): MailerInterface
    {
        $fromEmail = self::getEnvString('MAIL_FROM_EMAIL', 'no-reply@example.test');
        $fromName  = self::getEnvString('MAIL_FROM_NAME', 'Coding Blog');
        $transport = strtolower(self::getEnvString('MAILER_TRANSPORT', 'dummy'));

        if ($transport === 'mailjet') {
            $apiKey    = self::getEnvString('MJ_APIKEY_PUBLIC', '');
            $apiSecret = self::getEnvString('MJ_APIKEY_PRIVATE', '');
            return new MailjetMailer($apiKey, $apiSecret, $fromEmail, $fromName);
        }

        return new DummyMailer($fromEmail, $fromName);
    }

    private static function getEnvString(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? null;
        return is_string($value) ? $value : $default;
    }
}
