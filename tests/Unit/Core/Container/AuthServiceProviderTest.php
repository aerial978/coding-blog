<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Container;

use App\Core\Container\Provider\AuthServiceProvider;
use App\Core\Contract\FlashInterface;
use App\Core\Contract\RateLimiterFactoryInterface;
use App\Core\View;
use App\Handler\Auth\ForgotPasswordGetHandler;
use App\Handler\Auth\ForgotPasswordPostHandler;
use App\Handler\Auth\LoginGetHandler;
use App\Handler\Auth\LoginPostHandler;
use App\Handler\Auth\RegisterGetHandler;
use App\Handler\Auth\RegisterPostHandler;
use App\Handler\Auth\ResendConfirmationGetHandler;
use App\Handler\Auth\ResendConfirmationPostHandler;
use App\Handler\Auth\ResetPasswordGetHandler;
use App\Handler\Auth\ResetPasswordPostHandler;
use App\Http\Contract\ResponderInterface;
use App\Log\LogContextNormalizer;
use App\Model\Contract\UserTokenModelInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Security\Contract\TokenGeneratorInterface;
use App\Security\Contract\TurnstileValidatorInterface;
use App\Security\Guard\Contract\HoneypotGuardInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Security\Guard\Contract\SubmissionDelayGuardInterface;
use App\Security\Guard\Contract\TurnstileGuardInterface;
use App\Security\Guard\HoneypotGuard;
use App\Security\Guard\RateLimitGuard;
use App\Security\Guard\SubmissionDelayGuard;
use App\Security\Guard\TurnstileGuard;
use App\Service\Security\Contract\SecurityServiceInterface;
use App\Service\Security\ResetPasswordService;
use App\Support\ErrorListNormalizer;
use App\Validation\Contract\FormValidatorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class AuthServiceProviderTest extends TestCase
{
    /**
     * Petit conteneur de test.
     */
    private function makeContainer(array $services): ContainerInterface
    {
        return new class ($services) implements ContainerInterface {
            /**
             * @param array<string,mixed> $services
             */
            public function __construct(private array $services)
            {
            }

            public function get(string $id): mixed
            {
                if (!$this->has($id)) {
                    throw new class ('Service not found: ' . $id) extends \Exception implements \Psr\Container\NotFoundExceptionInterface {
                    };
                }

                return $this->services[$id];
            }

            public function has(string $id): bool
            {
                return array_key_exists($id, $this->services);
            }
        };
    }

    private function makeResetPasswordService(): ResetPasswordService
    {
        $validator      = $this->createMock(FormValidatorInterface::class);
        $userTokenModel = $this->createMock(UserTokenModelInterface::class);
        $tokenGen       = $this->createMock(TokenGeneratorInterface::class);

        return new ResetPasswordService(
            $validator,
            $userTokenModel,
            $tokenGen,
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function baseServices(): array
    {
        $view                     = $this->createMock(View::class);
        $flash                    = $this->createMock(FlashInterface::class);
        $responder                = $this->createMock(ResponderInterface::class);
        $csrf                     = $this->createMock(CsrfTokenInterface::class);
        $honeypotValidator        = $this->createMock(HoneypotValidatorInterface::class);
        $submissionDelayValidator = $this->createMock(SubmissionDelayValidatorInterface::class);
        $turnstileValidator       = $this->createMock(TurnstileValidatorInterface::class);
        $rateLimiterFactory       = $this->createMock(RateLimiterFactoryInterface::class);
        $securityService          = $this->createMock(SecurityServiceInterface::class);

        return [
            View::class                              => $view,
            FlashInterface::class                    => $flash,
            ResponderInterface::class                => $responder,
            CsrfTokenInterface::class                => $csrf,
            HoneypotValidatorInterface::class        => $honeypotValidator,
            SubmissionDelayValidatorInterface::class => $submissionDelayValidator,
            TurnstileValidatorInterface::class       => $turnstileValidator,
            RateLimiterFactoryInterface::class       => $rateLimiterFactory,
            SecurityServiceInterface::class          => $securityService,
            LogContextNormalizer::class              => new LogContextNormalizer(),
            ErrorListNormalizer::class               => new ErrorListNormalizer(),
            ResetPasswordService::class              => $this->makeResetPasswordService(),
        ];
    }

    public function testGetDefinitionsContainsExpectedKeys(): void
    {
        $definitions = AuthServiceProvider::getDefinitions();

        $this->assertArrayHasKey(HoneypotGuard::class, $definitions);
        $this->assertArrayHasKey(SubmissionDelayGuard::class, $definitions);
        $this->assertArrayHasKey(TurnstileGuard::class, $definitions);
        $this->assertArrayHasKey(RateLimitGuard::class, $definitions);

        $this->assertArrayHasKey(HoneypotGuardInterface::class, $definitions);
        $this->assertArrayHasKey(SubmissionDelayGuardInterface::class, $definitions);
        $this->assertArrayHasKey(TurnstileGuardInterface::class, $definitions);
        $this->assertArrayHasKey(RateLimitGuardInterface::class, $definitions);

        $this->assertArrayHasKey(RegisterGetHandler::class, $definitions);
        $this->assertArrayHasKey(RegisterPostHandler::class, $definitions);
        $this->assertArrayHasKey(LoginGetHandler::class, $definitions);
        $this->assertArrayHasKey(LoginPostHandler::class, $definitions);
        $this->assertArrayHasKey(ForgotPasswordGetHandler::class, $definitions);
        $this->assertArrayHasKey(ForgotPasswordPostHandler::class, $definitions);
        $this->assertArrayHasKey(ResendConfirmationGetHandler::class, $definitions);
        $this->assertArrayHasKey(ResendConfirmationPostHandler::class, $definitions);
        $this->assertArrayHasKey(ResetPasswordGetHandler::class, $definitions);
        $this->assertArrayHasKey(ResetPasswordPostHandler::class, $definitions);
    }

    public function testConcreteGuardDefinitionsReturnExpectedInstances(): void
    {
        $definitions = AuthServiceProvider::getDefinitions();
        $container   = $this->makeContainer($this->baseServices());

        $this->assertInstanceOf(
            HoneypotGuard::class,
            $definitions[HoneypotGuard::class]($container)
        );

        $this->assertInstanceOf(
            SubmissionDelayGuard::class,
            $definitions[SubmissionDelayGuard::class]($container)
        );

        $this->assertInstanceOf(
            TurnstileGuard::class,
            $definitions[TurnstileGuard::class]($container)
        );

        $this->assertInstanceOf(
            RateLimitGuard::class,
            $definitions[RateLimitGuard::class]($container)
        );
    }

    public function testGuardInterfaceAliasesReturnUnderlyingConcreteGuards(): void
    {
        $definitions = AuthServiceProvider::getDefinitions();

        $baseContainer = $this->makeContainer($this->baseServices());

        $honeypotGuard        = $definitions[HoneypotGuard::class]($baseContainer);
        $submissionDelayGuard = $definitions[SubmissionDelayGuard::class]($baseContainer);
        $turnstileGuard       = $definitions[TurnstileGuard::class]($baseContainer);
        $rateLimitGuard       = $definitions[RateLimitGuard::class]($baseContainer);

        $aliasContainer = $this->makeContainer([
            HoneypotGuard::class        => $honeypotGuard,
            SubmissionDelayGuard::class => $submissionDelayGuard,
            TurnstileGuard::class       => $turnstileGuard,
            RateLimitGuard::class       => $rateLimitGuard,
        ]);

        $this->assertSame(
            $honeypotGuard,
            $definitions[HoneypotGuardInterface::class]($aliasContainer)
        );

        $this->assertSame(
            $submissionDelayGuard,
            $definitions[SubmissionDelayGuardInterface::class]($aliasContainer)
        );

        $this->assertSame(
            $turnstileGuard,
            $definitions[TurnstileGuardInterface::class]($aliasContainer)
        );

        $this->assertSame(
            $rateLimitGuard,
            $definitions[RateLimitGuardInterface::class]($aliasContainer)
        );
    }

    public function testRegisterHandlersAreBuildable(): void
    {
        $definitions = AuthServiceProvider::getDefinitions();
        $services    = $this->baseServices();

        $services[HoneypotGuardInterface::class]        = $this->createMock(HoneypotGuardInterface::class);
        $services[SubmissionDelayGuardInterface::class] = $this->createMock(SubmissionDelayGuardInterface::class);
        $services[RateLimitGuardInterface::class]       = $this->createMock(RateLimitGuardInterface::class);
        $services[TurnstileGuardInterface::class]       = $this->createMock(TurnstileGuardInterface::class);

        $container = $this->makeContainer($services);

        $this->assertInstanceOf(
            RegisterGetHandler::class,
            $definitions[RegisterGetHandler::class]($container)
        );

        $this->assertInstanceOf(
            RegisterPostHandler::class,
            $definitions[RegisterPostHandler::class]($container)
        );
    }

    public function testOtherAuthHandlersAreBuildable(): void
    {
        $definitions = AuthServiceProvider::getDefinitions();
        $services    = $this->baseServices();

        $services[HoneypotGuardInterface::class]        = $this->createMock(HoneypotGuardInterface::class);
        $services[SubmissionDelayGuardInterface::class] = $this->createMock(SubmissionDelayGuardInterface::class);
        $services[RateLimitGuardInterface::class]       = $this->createMock(RateLimitGuardInterface::class);
        $services[TurnstileGuardInterface::class]       = $this->createMock(TurnstileGuardInterface::class);

        $container = $this->makeContainer($services);

        $this->assertInstanceOf(
            ResendConfirmationGetHandler::class,
            $definitions[ResendConfirmationGetHandler::class]($container)
        );

        $this->assertInstanceOf(
            ResendConfirmationPostHandler::class,
            $definitions[ResendConfirmationPostHandler::class]($container)
        );

        $this->assertInstanceOf(
            LoginGetHandler::class,
            $definitions[LoginGetHandler::class]($container)
        );

        $this->assertInstanceOf(
            LoginPostHandler::class,
            $definitions[LoginPostHandler::class]($container)
        );

        $this->assertInstanceOf(
            ForgotPasswordGetHandler::class,
            $definitions[ForgotPasswordGetHandler::class]($container)
        );

        $this->assertInstanceOf(
            ForgotPasswordPostHandler::class,
            $definitions[ForgotPasswordPostHandler::class]($container)
        );

        $this->assertInstanceOf(
            ResetPasswordGetHandler::class,
            $definitions[ResetPasswordGetHandler::class]($container)
        );

        $this->assertInstanceOf(
            ResetPasswordPostHandler::class,
            $definitions[ResetPasswordPostHandler::class]($container)
        );
    }
}
