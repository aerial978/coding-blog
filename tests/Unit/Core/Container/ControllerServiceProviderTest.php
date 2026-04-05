<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Container;

use App\Controller\AccountController;
use App\Controller\ConfirmAccountController;
use App\Controller\DebugController;
use App\Controller\ErrorController;
use App\Controller\ForgotPasswordController;
use App\Controller\HomeController;
use App\Controller\LoginController;
use App\Controller\RegisterController;
use App\Controller\ResendConfirmationController;
use App\Controller\ResetPasswordController;
use App\Core\Container\Provider\ControllerServiceProvider;
use App\Core\Contract\FlashInterface;
use App\Core\Contract\SessionInterface;
use App\Core\View;
use App\Handler\Auth\ConfirmAccountHandler;
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
use App\Http\Request;
use App\Model\Contract\UserModelInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class ControllerServiceProviderTest extends TestCase
{
    private function makeContainer(array $services): ContainerInterface
    {
        return new class ($services) implements ContainerInterface {
            public function __construct(private array $services)
            {
            }

            public function get(string $id): mixed
            {
                return $this->services[$id] ?? throw new \RuntimeException("Service not found: $id");
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        };
    }

    private function baseServices(): array
    {
        $view      = $this->createMock(View::class);
        $flash     = $this->createMock(FlashInterface::class);
        $userModel = $this->createMock(UserModelInterface::class);
        $request   = $this->createMock(Request::class);
        $responder = $this->createMock(ResponderInterface::class);
        $session   = $this->createMock(SessionInterface::class);

        // Dépendances communes simulées
        $csrf     = $this->createMock(\App\Security\Contract\CsrfTokenInterface::class);
        $honeypot = $this->createMock(\App\Security\Contract\HoneypotValidatorInterface::class);
        $delay    = $this->createMock(\App\Security\Contract\SubmissionDelayValidatorInterface::class);

        $securityService = $this->createMock(\App\Service\Security\Contract\SecurityServiceInterface::class);
        $honeypotGuard   = $this->createMock(\App\Security\Guard\Contract\HoneypotGuardInterface::class);
        $delayGuard      = $this->createMock(\App\Security\Guard\Contract\SubmissionDelayGuardInterface::class);
        $rateLimitGuard  = $this->createMock(\App\Security\Guard\Contract\RateLimitGuardInterface::class);
        $turnstileGuard  = $this->createMock(\App\Security\Guard\Contract\TurnstileGuardInterface::class);
        $errorNormalizer = new \App\Support\ErrorListNormalizer();

        // 👉 Handlers instanciés réellement
        $registerGet  = new RegisterGetHandler($view, $flash, $responder, $csrf, $honeypot, $delay);
        $registerPost = new RegisterPostHandler(
            $securityService,
            $flash,
            $responder,
            $honeypotGuard,
            $delayGuard,
            $rateLimitGuard,
            $turnstileGuard,
            $errorNormalizer
        );

        $loginGet  = new LoginGetHandler($view, $flash, $responder, $csrf, $honeypot, $delay);
        $loginPost = new LoginPostHandler(
            $securityService,
            $flash,
            $responder,
            $honeypotGuard,
            $delayGuard,
            $rateLimitGuard
        );

        $forgotGet  = new ForgotPasswordGetHandler($view, $flash, $responder, $csrf, $honeypot, $delay);
        $forgotPost = new ForgotPasswordPostHandler(
            $securityService,
            $flash,
            $responder,
            $honeypotGuard,
            $delayGuard,
            $rateLimitGuard
        );

        $resetGet = new ResetPasswordGetHandler(
            $view,
            $flash,
            $responder,
            $csrf,
            $honeypot,
            $delay,
            $this->createMock(\App\Service\Security\Contract\ResetPasswordServiceInterface::class)
        );

        $resetPost = new ResetPasswordPostHandler(
            $securityService,
            $flash,
            $responder,
            $honeypotGuard,
            $delayGuard,
            $rateLimitGuard,
            $turnstileGuard,
            $errorNormalizer
        );

        $confirmHandler = new ConfirmAccountHandler($securityService, $flash, $responder);

        $resendGet = new ResendConfirmationGetHandler(
            $view,
            $flash,
            $responder,
            $delay,
            $csrf,
            $honeypot
        );

        $resendPost = new ResendConfirmationPostHandler(
            $securityService,
            $flash,
            $responder,
            $honeypotGuard,
            $delayGuard,
            $rateLimitGuard
        );

        return [
            View::class               => $view,
            FlashInterface::class     => $flash,
            UserModelInterface::class => $userModel,
            Request::class            => $request,
            ResponderInterface::class => $responder,
            SessionInterface::class   => $session,

            // Handlers réels
            RegisterGetHandler::class            => $registerGet,
            RegisterPostHandler::class           => $registerPost,
            LoginGetHandler::class               => $loginGet,
            LoginPostHandler::class              => $loginPost,
            ForgotPasswordGetHandler::class      => $forgotGet,
            ForgotPasswordPostHandler::class     => $forgotPost,
            ResetPasswordGetHandler::class       => $resetGet,
            ResetPasswordPostHandler::class      => $resetPost,
            ConfirmAccountHandler::class         => $confirmHandler,
            ResendConfirmationGetHandler::class  => $resendGet,
            ResendConfirmationPostHandler::class => $resendPost,
        ];
    }

    public function testDefinitionsContainAllControllers(): void
    {
        $definitions = ControllerServiceProvider::getDefinitions();

        $this->assertArrayHasKey(HomeController::class, $definitions);
        $this->assertArrayHasKey(ErrorController::class, $definitions);
        $this->assertArrayHasKey(RegisterController::class, $definitions);
        $this->assertArrayHasKey(ConfirmAccountController::class, $definitions);
        $this->assertArrayHasKey(ResendConfirmationController::class, $definitions);
        $this->assertArrayHasKey(LoginController::class, $definitions);
        $this->assertArrayHasKey(ForgotPasswordController::class, $definitions);
        $this->assertArrayHasKey(ResetPasswordController::class, $definitions);
        $this->assertArrayHasKey(DebugController::class, $definitions);
        $this->assertArrayHasKey(AccountController::class, $definitions);
    }

    public function testHomeControllerIsBuildable(): void
    {
        $container   = $this->makeContainer($this->baseServices());
        $definitions = ControllerServiceProvider::getDefinitions();

        $controller = $definitions[HomeController::class]($container);

        $this->assertInstanceOf(HomeController::class, $controller);
    }

    public function testRegisterControllerIsBuildable(): void
    {
        $container   = $this->makeContainer($this->baseServices());
        $definitions = ControllerServiceProvider::getDefinitions();

        $controller = $definitions[RegisterController::class]($container);

        $this->assertInstanceOf(RegisterController::class, $controller);
    }

    public function testAuthControllersAreBuildable(): void
    {
        $container   = $this->makeContainer($this->baseServices());
        $definitions = ControllerServiceProvider::getDefinitions();

        $this->assertInstanceOf(
            ConfirmAccountController::class,
            $definitions[ConfirmAccountController::class]($container)
        );

        $this->assertInstanceOf(
            ResendConfirmationController::class,
            $definitions[ResendConfirmationController::class]($container)
        );

        $this->assertInstanceOf(
            LoginController::class,
            $definitions[LoginController::class]($container)
        );

        $this->assertInstanceOf(
            ForgotPasswordController::class,
            $definitions[ForgotPasswordController::class]($container)
        );

        $this->assertInstanceOf(
            ResetPasswordController::class,
            $definitions[ResetPasswordController::class]($container)
        );
    }

    public function testMiscControllersAreBuildable(): void
    {
        $container   = $this->makeContainer($this->baseServices());
        $definitions = ControllerServiceProvider::getDefinitions();

        $this->assertInstanceOf(
            ErrorController::class,
            $definitions[ErrorController::class]($container)
        );

        $this->assertInstanceOf(
            DebugController::class,
            $definitions[DebugController::class]($container)
        );

        $this->assertInstanceOf(
            AccountController::class,
            $definitions[AccountController::class]($container)
        );
    }
}
