<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Container;

use App\Core\Container\Provider\SystemServiceProvider;
use App\Core\Contract\FlashInterface;
use App\Core\Contract\RateLimiterFactoryInterface;
use App\Core\Contract\SessionInterface;
use App\Core\Contract\SqlHelperInterface;
use App\Core\Factory\RateLimiterFactory;
use App\Core\FlashService;
use App\Core\Mail\MailerInterface;
use App\Core\SessionManager;
use App\Core\SqlHelper;
use App\Core\View;
use App\Handler\Auth\ConfirmAccountHandler;
use App\Http\Contract\ResponderInterface;
use App\Http\Request;
use App\Infrastructure\Mail\DummyMailer;
use App\Log\LogContextNormalizer;
use App\Middleware\AuthenticationMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Security\Contract\AuthCheckerInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Security\Contract\TokenGeneratorInterface;
use App\Security\Contract\TurnstileValidatorInterface;
use App\Security\CsrfTokenManager;
use App\Security\HoneypotValidator;
use App\Security\SessionAuthChecker;
use App\Security\SubmissionDelayValidator;
use App\Security\TokenGenerator;
use App\Security\TurnstileValidator;
use App\Service\Security\Contract\SecurityServiceInterface;
use App\Support\ErrorListNormalizer;
use App\Validation\Contract\FormValidatorInterface;
use App\Validation\FormValidator;
use Cocur\Slugify\Slugify;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class SystemServiceProviderTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->envBackup = [
            'MAILER_TRANSPORT' => $_ENV['MAILER_TRANSPORT'] ?? null,
            'MAIL_FROM_EMAIL'  => $_ENV['MAIL_FROM_EMAIL']  ?? null,
            'MAIL_FROM_NAME'   => $_ENV['MAIL_FROM_NAME']   ?? null,
            'HONEYPOT_FIELD'   => $_ENV['HONEYPOT_FIELD']   ?? null,
            'TURNSTILE_SECRET' => $_ENV['TURNSTILE_SECRET'] ?? null,
            'MIN_FORM_DELAY'   => $_ENV['MIN_FORM_DELAY']   ?? null,
            'MAX_FORM_DELAY'   => $_ENV['MAX_FORM_DELAY']   ?? null,
        ];
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }

        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $services
     */
    private function makeContainer(array $services): ContainerInterface
    {
        return new class ($services) implements ContainerInterface {
            /**
             * @param array<string, mixed> $services
             */
            public function __construct(private array $services)
            {
            }

            public function get(string $id): mixed
            {
                if (!$this->has($id)) {
                    throw new \RuntimeException('Service not found: ' . $id);
                }

                return $this->services[$id];
            }

            public function has(string $id): bool
            {
                return array_key_exists($id, $this->services);
            }
        };
    }

    public function testDefinitionsContainExpectedKeys(): void
    {
        $definitions = SystemServiceProvider::getDefinitions();

        $this->assertArrayHasKey(\PDO::class, $definitions);
        $this->assertArrayHasKey(SqlHelper::class, $definitions);
        $this->assertArrayHasKey(SqlHelperInterface::class, $definitions);

        $this->assertArrayHasKey(View::class, $definitions);
        $this->assertArrayHasKey(Request::class, $definitions);

        $this->assertArrayHasKey(FormValidator::class, $definitions);
        $this->assertArrayHasKey(FormValidatorInterface::class, $definitions);
        $this->assertArrayHasKey(Slugify::class, $definitions);

        $this->assertArrayHasKey(SessionManager::class, $definitions);
        $this->assertArrayHasKey(SessionInterface::class, $definitions);
        $this->assertArrayHasKey(FlashService::class, $definitions);
        $this->assertArrayHasKey(FlashInterface::class, $definitions);
        $this->assertArrayHasKey(CsrfTokenManager::class, $definitions);
        $this->assertArrayHasKey(RateLimiterFactory::class, $definitions);
        $this->assertArrayHasKey(RateLimiterFactoryInterface::class, $definitions);
        $this->assertArrayHasKey(SubmissionDelayValidator::class, $definitions);

        $this->assertArrayHasKey(TokenGenerator::class, $definitions);
        $this->assertArrayHasKey(HoneypotValidator::class, $definitions);
        $this->assertArrayHasKey(TurnstileValidator::class, $definitions);
        $this->assertArrayHasKey(SessionAuthChecker::class, $definitions);

        $this->assertArrayHasKey(TokenGeneratorInterface::class, $definitions);
        $this->assertArrayHasKey(CsrfTokenInterface::class, $definitions);
        $this->assertArrayHasKey(AuthCheckerInterface::class, $definitions);
        $this->assertArrayHasKey(HoneypotValidatorInterface::class, $definitions);
        $this->assertArrayHasKey(SubmissionDelayValidatorInterface::class, $definitions);
        $this->assertArrayHasKey(TurnstileValidatorInterface::class, $definitions);

        $this->assertArrayHasKey(AuthenticationMiddleware::class, $definitions);
        $this->assertArrayHasKey(CsrfMiddleware::class, $definitions);
        $this->assertArrayHasKey(SecurityHeadersMiddleware::class, $definitions);

        $this->assertArrayHasKey(LogContextNormalizer::class, $definitions);
        $this->assertArrayHasKey(ErrorListNormalizer::class, $definitions);
        $this->assertArrayHasKey(ConfirmAccountHandler::class, $definitions);

        $this->assertArrayHasKey(MailerInterface::class, $definitions);
        $this->assertArrayHasKey('logger.app', $definitions);
        $this->assertArrayHasKey('logger.error', $definitions);
    }

    public function testHttpDefinitionsAreBuildable(): void
    {
        $definitions = SystemServiceProvider::getDefinitions();

        $container = $this->makeContainer([]);

        $view    = $definitions[View::class]($container);
        $request = $definitions[Request::class]($container);

        $this->assertInstanceOf(View::class, $view);
        $this->assertInstanceOf(Request::class, $request);
    }

    public function testValidationAndApplicationDefinitionsAreBuildable(): void
    {
        $definitions = SystemServiceProvider::getDefinitions();

        $emptyContainer = $this->makeContainer([]);

        $validator       = $definitions[FormValidator::class]($emptyContainer);
        $slugify         = $definitions[Slugify::class]($emptyContainer);
        $logNormalizer   = $definitions[LogContextNormalizer::class]($emptyContainer);
        $errorNormalizer = $definitions[ErrorListNormalizer::class]($emptyContainer);

        $this->assertInstanceOf(FormValidator::class, $validator);
        $this->assertInstanceOf(Slugify::class, $slugify);
        $this->assertInstanceOf(LogContextNormalizer::class, $logNormalizer);
        $this->assertInstanceOf(ErrorListNormalizer::class, $errorNormalizer);

        $security  = $this->createMock(SecurityServiceInterface::class);
        $flash     = $this->createMock(FlashInterface::class);
        $responder = $this->createMock(ResponderInterface::class);

        $container = $this->makeContainer([
            SecurityServiceInterface::class => $security,
            FlashInterface::class           => $flash,
            ResponderInterface::class       => $responder,
        ]);

        $handler = $definitions[ConfirmAccountHandler::class]($container);

        $this->assertInstanceOf(ConfirmAccountHandler::class, $handler);
    }

    public function testSessionAndSecurityDefinitionsAreBuildableAndAliasesResolveCorrectly(): void
    {
        $_ENV['HONEYPOT_FIELD']   = 'fax_test';
        $_ENV['TURNSTILE_SECRET'] = 'secret_test';
        $_ENV['MIN_FORM_DELAY']   = '15';
        $_ENV['MAX_FORM_DELAY']   = '900';

        $definitions = SystemServiceProvider::getDefinitions();

        $session = $this->createMock(SessionInterface::class);

        $sessionAliasContainer = $this->makeContainer([
            SessionManager::class => $session,
        ]);

        $this->assertSame(
            $session,
            $definitions[SessionInterface::class]($sessionAliasContainer)
        );

        $flashContainer = $this->makeContainer([
            SessionInterface::class => $session,
        ]);
        $flashService = $definitions[FlashService::class]($flashContainer);

        $this->assertInstanceOf(FlashService::class, $flashService);

        $flashAliasContainer = $this->makeContainer([
            FlashService::class => $flashService,
        ]);

        $this->assertSame(
            $flashService,
            $definitions[FlashInterface::class]($flashAliasContainer)
        );

        $csrfManager = $definitions[CsrfTokenManager::class]($flashContainer);
        $this->assertInstanceOf(CsrfTokenManager::class, $csrfManager);

        $csrfAliasContainer = $this->makeContainer([
            CsrfTokenManager::class => $csrfManager,
        ]);

        $this->assertSame(
            $csrfManager,
            $definitions[CsrfTokenInterface::class]($csrfAliasContainer)
        );

        $rateLimiterFactory = $definitions[RateLimiterFactory::class]($flashContainer);
        $this->assertInstanceOf(RateLimiterFactory::class, $rateLimiterFactory);

        $rateAliasContainer = $this->makeContainer([
            RateLimiterFactory::class => $rateLimiterFactory,
        ]);

        $this->assertSame(
            $rateLimiterFactory,
            $definitions[RateLimiterFactoryInterface::class]($rateAliasContainer)
        );

        $submissionDelay = $definitions[SubmissionDelayValidator::class]($flashContainer);
        $this->assertInstanceOf(SubmissionDelayValidator::class, $submissionDelay);

        $submissionAliasContainer = $this->makeContainer([
            SubmissionDelayValidator::class => $submissionDelay,
        ]);

        $this->assertSame(
            $submissionDelay,
            $definitions[SubmissionDelayValidatorInterface::class]($submissionAliasContainer)
        );

        $emptyContainer = $this->makeContainer([]);

        $tokenGenerator     = $definitions[TokenGenerator::class]($emptyContainer);
        $honeypotValidator  = $definitions[HoneypotValidator::class]($emptyContainer);
        $turnstileValidator = $definitions[TurnstileValidator::class]($emptyContainer);
        $authChecker        = $definitions[SessionAuthChecker::class]($flashContainer);

        $this->assertInstanceOf(TokenGenerator::class, $tokenGenerator);
        $this->assertInstanceOf(HoneypotValidator::class, $honeypotValidator);
        $this->assertInstanceOf(TurnstileValidator::class, $turnstileValidator);
        $this->assertInstanceOf(SessionAuthChecker::class, $authChecker);

        $this->assertSame(
            $tokenGenerator,
            $definitions[TokenGeneratorInterface::class](
                $this->makeContainer([TokenGenerator::class => $tokenGenerator])
            )
        );

        $this->assertSame(
            $honeypotValidator,
            $definitions[HoneypotValidatorInterface::class](
                $this->makeContainer([HoneypotValidator::class => $honeypotValidator])
            )
        );

        $this->assertSame(
            $turnstileValidator,
            $definitions[TurnstileValidatorInterface::class](
                $this->makeContainer([TurnstileValidator::class => $turnstileValidator])
            )
        );

        $this->assertSame(
            $authChecker,
            $definitions[AuthCheckerInterface::class](
                $this->makeContainer([SessionAuthChecker::class => $authChecker])
            )
        );
    }

    public function testSecurityMiddlewaresAreBuildable(): void
    {
        $definitions = SystemServiceProvider::getDefinitions();

        $authChecker = $this->createMock(AuthCheckerInterface::class);
        $flash       = $this->createMock(FlashInterface::class);
        $responder   = $this->createMock(ResponderInterface::class);
        $csrf        = $this->createMock(CsrfTokenInterface::class);

        $authContainer = $this->makeContainer([
            AuthCheckerInterface::class => $authChecker,
            FlashInterface::class       => $flash,
            ResponderInterface::class   => $responder,
        ]);

        $csrfContainer = $this->makeContainer([
            CsrfTokenInterface::class => $csrf,
            FlashInterface::class     => $flash,
        ]);

        $authenticationMiddleware  = $definitions[AuthenticationMiddleware::class]($authContainer);
        $csrfMiddleware            = $definitions[CsrfMiddleware::class]($csrfContainer);
        $securityHeadersMiddleware = $definitions[SecurityHeadersMiddleware::class]($this->makeContainer([]));

        $this->assertInstanceOf(AuthenticationMiddleware::class, $authenticationMiddleware);
        $this->assertInstanceOf(CsrfMiddleware::class, $csrfMiddleware);
        $this->assertInstanceOf(SecurityHeadersMiddleware::class, $securityHeadersMiddleware);
    }

    public function testMailerDefinitionReturnsDummyMailerWhenTransportIsDummy(): void
    {
        $_ENV['MAILER_TRANSPORT'] = 'dummy';
        $_ENV['MAIL_FROM_EMAIL']  = 'noreply@example.test';
        $_ENV['MAIL_FROM_NAME']   = 'Coding Blog Test';

        $definitions = SystemServiceProvider::getDefinitions();

        $emptyContainer = $this->makeContainer([]);

        $mailer = $definitions[MailerInterface::class]($emptyContainer);

        $this->assertInstanceOf(DummyMailer::class, $mailer);
    }
}
