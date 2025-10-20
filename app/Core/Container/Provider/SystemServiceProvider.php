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
use App\Security\Contract\TokenGeneratorInterface;
use App\Security\CsrfTokenManager;
use App\Security\TokenGenerator;
use App\Validation\FormValidator;
use Cocur\Slugify\Slugify;
use Psr\Container\ContainerInterface;

/**
 * Provides definitions for system-level and infrastructure services.
 *
 * This service provider registers core components such as the database connection,
 * session and flash management, CSRF protection, rate limiting, logging, validation,
 * and mail transport configuration.
 *
 * Each entry defines how a service or dependency should be instantiated and retrieved
 * from the dependency injection container.
 */
final class SystemServiceProvider
{
    /**
     * Returns a list of system-level service definitions for the container.
     *
     * @return array<class-string|string, \Closure(ContainerInterface):mixed>
     *     Map of [service identifier => factory closure]
     */
    public static function getDefinitions(): array
    {
        return [
            \PDO::class => static function (): \PDO {
                return (new Database())->getConnection();
            },

            SqlHelper::class => static function (ContainerInterface $container): SqlHelper {
                /** @var \PDO $pdo */
                $pdo = $container->get(\PDO::class);
                return new SqlHelper($pdo);
            },

            View::class    => static fn (): View => new View(),
            Request::class => static fn (): Request => new Request(),

            'logger.app'   => static fn () => Logger::getLogger('app'),
            'logger.error' => static fn () => Logger::getLogger('error'),

            FormValidator::class => static fn (): FormValidator => new FormValidator(),
            Slugify::class       => static fn (): Slugify => new Slugify(),

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

            TokenGenerator::class          => static fn (): TokenGenerator => new TokenGenerator(),
            TokenGeneratorInterface::class => static function (ContainerInterface $container): TokenGeneratorInterface {
                /** @var TokenGeneratorInterface $tg */
                $tg = $container->get(TokenGenerator::class);
                return $tg;
            },

            MailerInterface::class => static function (ContainerInterface $container): MailerInterface {
                // Normalisation des vars d'env (évite les cast de mixed)
                $fromEmailRaw = $_ENV['MAIL_FROM_EMAIL']  ?? null;
                $fromNameRaw  = $_ENV['MAIL_FROM_NAME']   ?? null;
                $transportRaw = $_ENV['MAILER_TRANSPORT'] ?? null;

                $fromEmail = is_string($fromEmailRaw) ? $fromEmailRaw : 'no-reply@example.test';
                $fromName  = is_string($fromNameRaw) ? $fromNameRaw : 'Coding Blog';
                $transport = is_string($transportRaw) ? strtolower($transportRaw) : 'dummy';

                if ($transport === 'mailjet') {
                    $apiKeyRaw    = $_ENV['MJ_APIKEY_PUBLIC']  ?? null;
                    $apiSecretRaw = $_ENV['MJ_APIKEY_PRIVATE'] ?? null;

                    $apiKey    = is_string($apiKeyRaw) ? $apiKeyRaw : '';
                    $apiSecret = is_string($apiSecretRaw) ? $apiSecretRaw : '';

                    // MailjetMailer attend 4 paramètres (apiKey, apiSecret, fromEmail, fromName)
                    return new MailjetMailer($apiKey, $apiSecret, $fromEmail, $fromName);
                }

                // DummyMailer attend 2 paramètres (fromEmail, fromName)
                return new DummyMailer($fromEmail, $fromName);
            },
        ];
    }
}
