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
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class AuthServiceProviderTest extends TestCase
{
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

    /**
     * @return array<string, mixed>
     */
    private function baseServices(): array
    {
        return [
            View::class                              => $this->createMock(View::class),
            FlashInterface::class                    => $this->createMock(FlashInterface::class),
            ResponderInterface::class                => $this->createMock(ResponderInterface::class),
            CsrfTokenInterface::class                => $this->createMock(CsrfTokenInterface::class),
            HoneypotValidatorInterface::class        => $this->createMock(HoneypotValidatorInterface::class),
            SubmissionDelayValidatorInterface::class => $this->createMock(SubmissionDelayValidatorInterface::class),
            TurnstileValidatorInterface::class       => $this->createMock(TurnstileValidatorInterface::class),
            RateLimiterFactoryInterface::class       => $this->createMock(RateLimiterFactoryInterface::class),
            LogContextNormalizer::class              => new LogContextNormalizer(),
            ErrorListNormalizer::class               => new ErrorListNormalizer(),
            SecurityServiceInterface::class          => $this->createMock(SecurityServiceInterface::class),
            ResetPasswordServiceInterface::class     => $this->createMock(ResetPasswordServiceInterface::class),
        ];
    }

    public function testDefinitionsContainExpectedKeys(): void
    {
        $definitions = AuthServiceProvider::getDefinitions();

        $this->assertArrayHasKey(HoneypotGuard::class, $definitions);
        $this->assertArrayHasKey(SubmissionDelayGuard::class, $definitions);
        $this->assertArrayHasKey(TurnstileGuard::class, $definitions);
        $this->assertArrayHasKey(RateLimitGuard::class, $definitions);

        $this->assertArrayHasKey(HoneypotGuardInterface::class, $definitions);
        $this->assertArrayHasKey(RateLimitGuardInterface::class, $definitions);
        $this->assertArrayHasKey(SubmissionDelayGuardInterface::class, $definitions);
        $this->assertArrayHasKey(TurnstileGuardInterface::class, $definitions);

        $this->assertArrayHasKey(RegisterGetHandler::class, $definitions);
        $this->assertArrayHasKey(RegisterPostHandler::class, $definitions);
        $this->assertArrayHasKey(ResendConfirmationGetHandler::class, $definitions);
        $this->assertArrayHasKey(ResendConfirmationPostHandler::class, $definitions);
        $this->assertArrayHasKey(LoginGetHandler::class, $definitions);
        $this->assertArrayHasKey(LoginPostHandler::class, $definitions);
        $this->assertArrayHasKey(ForgotPasswordGetHandler::class, $definitions);
        $this->assertArrayHasKey(ForgotPasswordPostHandler::class, $definitions);
        $this->assertArrayHasKey(ResetPasswordGetHandler::class, $definitions);
        $this->assertArrayHasKey(ResetPasswordPostHandler::class, $definitions);
        $this->assertArrayHasKey(LogoutHandler::class, $definitions);
    }

    public function testGuardDefinitionsAreBuildable(): void
    {
        $definitions = AuthServiceProvider::getDefinitions();
        $container   = $this->makeContainer($this->baseServices());

        $honeypotGuard        = $definitions[HoneypotGuard::class]($container);
        $submissionDelayGuard = $definitions[SubmissionDelayGuard::class]($container);
        $turnstileGuard       = $definitions[TurnstileGuard::class]($container);
        $rateLimitGuard       = $definitions[RateLimitGuard::class]($container);

        $this->assertInstanceOf(HoneypotGuard::class, $honeypotGuard);
        $this->assertInstanceOf(SubmissionDelayGuard::class, $submissionDelayGuard);
        $this->assertInstanceOf(TurnstileGuard::class, $turnstileGuard);
        $this->assertInstanceOf(RateLimitGuard::class, $rateLimitGuard);
    }

    public function testGuardBindingsResolveCorrectly(): void
    {
        $definitions = AuthServiceProvider::getDefinitions();

        $baseContainer = $this->makeContainer($this->baseServices());

        $honeypotGuard        = $definitions[HoneypotGuard::class]($baseContainer);
        $rateLimitGuard       = $definitions[RateLimitGuard::class]($baseContainer);
        $submissionDelayGuard = $definitions[SubmissionDelayGuard::class]($baseContainer);
        $turnstileGuard       = $definitions[TurnstileGuard::class]($baseContainer);

        $aliasContainer = $this->makeContainer([
            HoneypotGuard::class        => $honeypotGuard,
            RateLimitGuard::class       => $rateLimitGuard,
            SubmissionDelayGuard::class => $submissionDelayGuard,
            TurnstileGuard::class       => $turnstileGuard,
        ]);

        $this->assertSame(
            $honeypotGuard,
            $definitions[HoneypotGuardInterface::class]($aliasContainer)
        );

        $this->assertSame(
            $rateLimitGuard,
            $definitions[RateLimitGuardInterface::class]($aliasContainer)
        );

        $this->assertSame(
            $submissionDelayGuard,
            $definitions[SubmissionDelayGuardInterface::class]($aliasContainer)
        );

        $this->assertSame(
            $turnstileGuard,
            $definitions[TurnstileGuardInterface::class]($aliasContainer)
        );
    }

    public function testGetHandlersAreBuildable(): void
    {
        $definitions = AuthServiceProvider::getDefinitions();
        $container   = $this->makeContainer($this->baseServices());

        $registerGet = $definitions[RegisterGetHandler::class]($container);
        $resendGet   = $definitions[ResendConfirmationGetHandler::class]($container);
        $loginGet    = $definitions[LoginGetHandler::class]($container);
        $forgotGet   = $definitions[ForgotPasswordGetHandler::class]($container);
        $resetGet    = $definitions[ResetPasswordGetHandler::class]($container);

        $this->assertInstanceOf(RegisterGetHandler::class, $registerGet);
        $this->assertInstanceOf(ResendConfirmationGetHandler::class, $resendGet);
        $this->assertInstanceOf(LoginGetHandler::class, $loginGet);
        $this->assertInstanceOf(ForgotPasswordGetHandler::class, $forgotGet);
        $this->assertInstanceOf(ResetPasswordGetHandler::class, $resetGet);
    }

    public function testPostHandlersAndLogoutHandlerAreBuildable(): void
    {
        $definitions = AuthServiceProvider::getDefinitions();

        $honeypotGuard        = $this->createMock(HoneypotGuardInterface::class);
        $submissionDelayGuard = $this->createMock(SubmissionDelayGuardInterface::class);
        $rateLimitGuard       = $this->createMock(RateLimitGuardInterface::class);
        $turnstileGuard       = $this->createMock(TurnstileGuardInterface::class);

        $container = $this->makeContainer($this->baseServices() + [
            HoneypotGuardInterface::class        => $honeypotGuard,
            SubmissionDelayGuardInterface::class => $submissionDelayGuard,
            RateLimitGuardInterface::class       => $rateLimitGuard,
            TurnstileGuardInterface::class       => $turnstileGuard,
        ]);

        $registerPost = $definitions[RegisterPostHandler::class]($container);
        $resendPost   = $definitions[ResendConfirmationPostHandler::class]($container);
        $loginPost    = $definitions[LoginPostHandler::class]($container);
        $forgotPost   = $definitions[ForgotPasswordPostHandler::class]($container);
        $resetPost    = $definitions[ResetPasswordPostHandler::class]($container);
        $logout       = $definitions[LogoutHandler::class]($container);

        $this->assertInstanceOf(RegisterPostHandler::class, $registerPost);
        $this->assertInstanceOf(ResendConfirmationPostHandler::class, $resendPost);
        $this->assertInstanceOf(LoginPostHandler::class, $loginPost);
        $this->assertInstanceOf(ForgotPasswordPostHandler::class, $forgotPost);
        $this->assertInstanceOf(ResetPasswordPostHandler::class, $resetPost);
        $this->assertInstanceOf(LogoutHandler::class, $logout);
    }
}
