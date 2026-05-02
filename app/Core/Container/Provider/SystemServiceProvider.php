<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use App\Core\Contract\FlashInterface;
use App\Core\Contract\RateLimiterFactoryInterface;
use App\Core\Contract\SessionInterface;
use App\Core\Contract\SqlHelperInterface;
use App\Core\Database;
use App\Core\Factory\RateLimiterFactory;
use App\Core\FlashService;
use App\Core\Logger;
use App\Core\Mail\MailerInterface;
use App\Core\SessionManager;
use App\Core\SqlHelper;
use App\Core\View;
use App\Handler\Auth\ConfirmAccountHandler;
use App\Http\Contract\ResponderInterface;
use App\Http\Request;
use App\Http\Responder;
use App\Infrastructure\Mail\DummyMailer;
use App\Infrastructure\Mail\MailjetMailer;
use App\Log\LogContextNormalizer;
use App\Middleware\AuthenticationMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RememberMeMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Model\Contract\UserTokenModelInterface;
use App\Security\Contract\AuthCheckerInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\RememberMeCookieManagerInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Security\Contract\TokenGeneratorInterface;
use App\Security\Contract\TurnstileValidatorInterface;
use App\Security\CsrfTokenManager;
use App\Security\HoneypotValidator;
use App\Security\RememberMeCookieManager;
use App\Security\SessionAuthChecker;
use App\Security\SubmissionDelayValidator;
use App\Security\TokenGenerator;
use App\Security\TurnstileValidator;
use App\Service\Security\Contract\RememberMeServiceInterface;
use App\Service\Security\RememberMeService;
use App\Support\ErrorListNormalizer;
use App\Validation\Contract\FormValidatorInterface;
use App\Validation\FormValidator;
use Cocur\Slugify\Slugify;
use Psr\Container\ContainerInterface;

final class SystemServiceProvider
{
    /**
     * @return array<string, callable(\Psr\Container\ContainerInterface): mixed>
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
            self::getApplicationDefinitions(),
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

            SqlHelperInterface::class => static function (ContainerInterface $container): SqlHelperInterface {
                /** @var SqlHelper $sql */
                $sql = $container->get(SqlHelper::class);
                return $sql;
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

            Responder::class => static function (ContainerInterface $container): Responder {
                /** @var View $view */
                $view = $container->get(View::class);
                return new Responder($view);
            },

            // Correction PHPStan: on force le type retourné (plus de "mixed")
            ResponderInterface::class => static function (ContainerInterface $container): ResponderInterface {
                /** @var Responder $responder */
                $responder = $container->get(Responder::class);
                return $responder;
            },
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

            FormValidatorInterface::class => static function (ContainerInterface $container): FormValidatorInterface {
                /** @var FormValidator $formValidator */
                $formValidator = $container->get(FormValidator::class);
                return $formValidator;
            },

            Slugify::class => static fn (): Slugify => new Slugify(),
        ];
    }

    /**
     * @return array<string, callable(): mixed|callable(ContainerInterface): mixed>
     */
    private static function getSessionDefinitions(): array
    {
        return [
            SessionManager::class => static fn (): SessionManager => new SessionManager(),

            SessionInterface::class => static function (ContainerInterface $container): SessionInterface {
                /** @var SessionManager $session */
                $session = $container->get(SessionManager::class);
                return $session;
            },

            FlashService::class => static function (ContainerInterface $container): FlashService {
                /** @var SessionInterface $session */
                $session = $container->get(SessionInterface::class);
                return new FlashService($session);
            },

            FlashInterface::class => static function (ContainerInterface $container): FlashInterface {
                /** @var FlashService $flash */
                $flash = $container->get(FlashService::class);
                return $flash;
            },

            CsrfTokenManager::class => static function (ContainerInterface $container): CsrfTokenManager {
                /** @var SessionInterface $session */
                $session = $container->get(SessionInterface::class);
                return new CsrfTokenManager($session);
            },

            RateLimiterFactory::class => static function (ContainerInterface $container): RateLimiterFactory {
                /** @var SessionInterface $session */
                $session = $container->get(SessionInterface::class);
                return new RateLimiterFactory($session);
            },

            RateLimiterFactoryInterface::class => static function (ContainerInterface $container): RateLimiterFactoryInterface {
                /** @var RateLimiterFactory $factory */
                $factory = $container->get(RateLimiterFactory::class);
                return $factory;
            },

            SubmissionDelayValidator::class => static function (ContainerInterface $container): SubmissionDelayValidator {
                /** @var SessionInterface $session */
                $session = $container->get(SessionInterface::class);

                // Correction PHPStan: pas de cast (int) sur mixed
                $min = self::getEnvInt('MIN_FORM_DELAY', 10);
                if ($min <= 0) {
                    $min = 10;
                }

                $max = self::getEnvInt('MAX_FORM_DELAY', 1800);
                if ($max <= 0) {
                    $max = 1800;
                }

                return new SubmissionDelayValidator($session, $min, $max);
            },
        ];
    }

    /**
     * @return array<string, callable(): mixed|callable(ContainerInterface): mixed>
     */
    private static function getSecurityDefinitions(): array
    {
        return array_merge(
            self::getSecurityCoreServices(),
            self::getSecurityBindings(),
            self::getSecurityMiddlewares()
        );
    }

    /**
     * Services applicatifs (handlers, etc.).
     *
     * @return array<string, callable(): mixed|callable(ContainerInterface): mixed>
     */
    private static function getApplicationDefinitions(): array
    {
        return [
            LogContextNormalizer::class => static fn (): LogContextNormalizer => new LogContextNormalizer(),

            ErrorListNormalizer::class => static fn (): ErrorListNormalizer => new ErrorListNormalizer(),

            ConfirmAccountHandler::class => static function (ContainerInterface $container): ConfirmAccountHandler {
                /** @var \App\Service\Security\Contract\SecurityServiceInterface $security */
                $security = $container->get(\App\Service\Security\Contract\SecurityServiceInterface::class);

                /** @var \App\Core\Contract\FlashInterface $flash */
                $flash = $container->get(\App\Core\Contract\FlashInterface::class);

                /** @var \App\Http\Contract\ResponderInterface $responder */
                $responder = $container->get(\App\Http\Contract\ResponderInterface::class);

                return new ConfirmAccountHandler($security, $flash, $responder);
            },
        ];
    }

    /**
     * Services "concrets" de sécurité (classes).
     *
     * @return array<string, callable(): mixed|callable(ContainerInterface): mixed>
     */
    private static function getSecurityCoreServices(): array
    {
        return [
            TokenGenerator::class => static fn (): TokenGenerator => new TokenGenerator(),

            HoneypotValidator::class => static function (): HoneypotValidator {
                $field = self::getEnvString('HONEYPOT_FIELD', 'fax');
                return new HoneypotValidator($field);
            },

            TurnstileValidator::class => static function (): TurnstileValidator {
                $secret = self::getEnvString('TURNSTILE_SECRET', '');
                return new TurnstileValidator($secret);
            },

            // Auth checker concret
            SessionAuthChecker::class => static function (ContainerInterface $container): SessionAuthChecker {
                /** @var SessionInterface $session */
                $session = $container->get(SessionInterface::class);
                return new SessionAuthChecker($session);
            },

            RememberMeCookieManager::class => static fn (): RememberMeCookieManager => new RememberMeCookieManager(),

            RememberMeService::class => static function (ContainerInterface $container): RememberMeService {
                /** @var UserTokenModelInterface $userTokenModel */
                $userTokenModel = $container->get(UserTokenModelInterface::class);

                /** @var TokenGeneratorInterface $tokenGenerator */
                $tokenGenerator = $container->get(TokenGeneratorInterface::class);

                /** @var SessionInterface $session */
                $session = $container->get(SessionInterface::class);

                return new RememberMeService($userTokenModel, $tokenGenerator, $session);
            },
        ];
    }

    /**
     * Bindings interface -> impl.
     *
     * @return array<string, callable(): mixed|callable(ContainerInterface): mixed>
     */
    private static function getSecurityBindings(): array
    {
        return [
            TokenGeneratorInterface::class => static function (ContainerInterface $container): TokenGeneratorInterface {
                /** @var TokenGenerator $tg */
                $tg = $container->get(TokenGenerator::class);
                return $tg;
            },

            CsrfTokenInterface::class => static function (ContainerInterface $container): CsrfTokenInterface {
                /** @var CsrfTokenManager $csrf */
                $csrf = $container->get(CsrfTokenManager::class);
                return $csrf;
            },

            AuthCheckerInterface::class => static function (ContainerInterface $container): AuthCheckerInterface {
                /** @var SessionAuthChecker $auth */
                $auth = $container->get(SessionAuthChecker::class);
                return $auth;
            },

            HoneypotValidatorInterface::class => static function (ContainerInterface $container): HoneypotValidatorInterface {
                /** @var HoneypotValidator $hp */
                $hp = $container->get(HoneypotValidator::class);
                return $hp;
            },

            SubmissionDelayValidatorInterface::class => static function (ContainerInterface $container): SubmissionDelayValidatorInterface {
                /** @var SubmissionDelayValidator $sd */
                $sd = $container->get(SubmissionDelayValidator::class);
                return $sd;
            },

            TurnstileValidatorInterface::class => static function (ContainerInterface $container): TurnstileValidatorInterface {
                /** @var TurnstileValidator $ts */
                $ts = $container->get(TurnstileValidator::class);
                return $ts;
            },

            RememberMeCookieManagerInterface::class => static function (ContainerInterface $container): RememberMeCookieManagerInterface {
                /** @var RememberMeCookieManager $manager */
                $manager = $container->get(RememberMeCookieManager::class);
                return $manager;
            },

            RememberMeServiceInterface::class => static function (ContainerInterface $container): RememberMeServiceInterface {
                /** @var RememberMeService $service */
                $service = $container->get(RememberMeService::class);
                return $service;
            },
        ];
    }

    /**
     * Middlewares.
     *
     * @return array<string, callable(): mixed|callable(ContainerInterface): mixed>
     */
    private static function getSecurityMiddlewares(): array
    {
        return [
            RememberMeMiddleware::class => static function (ContainerInterface $container): RememberMeMiddleware {
                /** @var AuthCheckerInterface $auth */
                $auth = $container->get(AuthCheckerInterface::class);

                /** @var RememberMeCookieManagerInterface $cookieManager */
                $cookieManager = $container->get(RememberMeCookieManagerInterface::class);

                /** @var RememberMeServiceInterface $rememberMe */
                $rememberMe = $container->get(RememberMeServiceInterface::class);

                return new RememberMeMiddleware($auth, $cookieManager, $rememberMe);
            },

            AuthenticationMiddleware::class => static function (ContainerInterface $container): AuthenticationMiddleware {
                /** @var AuthCheckerInterface $auth */
                $auth = $container->get(AuthCheckerInterface::class);
                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);
                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);

                return new AuthenticationMiddleware($auth, $flash, $responder);
            },

            CsrfMiddleware::class => static function (ContainerInterface $container): CsrfMiddleware {
                /** @var CsrfTokenInterface $csrf */
                $csrf = $container->get(CsrfTokenInterface::class);
                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);

                return new CsrfMiddleware($csrf, $flash);
            },

            SecurityHeadersMiddleware::class => static fn (): SecurityHeadersMiddleware => new SecurityHeadersMiddleware(),
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

        Logger::getLogger('auth')->info('mailer_transport_debug', [
            'transport' => $transport,
            'from'      => $fromEmail,
        ]);

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

    private static function getEnvInt(string $key, int $default): int
    {
        $value = $_ENV[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            $filtered = filter_var($value, \FILTER_VALIDATE_INT);
            return is_int($filtered) ? $filtered : $default;
        }

        return $default;
    }
}
