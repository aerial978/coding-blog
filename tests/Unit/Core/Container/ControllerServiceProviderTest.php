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
use App\Controller\LogoutController;
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
use App\Handler\Auth\LogoutHandler;
use App\Handler\Auth\RegisterGetHandler;
use App\Handler\Auth\RegisterPostHandler;
use App\Handler\Auth\ResendConfirmationGetHandler;
use App\Handler\Auth\ResendConfirmationPostHandler;
use App\Handler\Auth\ResetPasswordGetHandler;
use App\Handler\Auth\ResetPasswordPostHandler;
use App\Http\Request;
use App\Model\Contract\UserModelInterface;
use App\Security\Contract\AuthCheckerInterface;
use App\Security\Contract\CsrfTokenInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use App\Controller\Email2faController;
use App\Handler\Auth\Email2faGetHandler;
use App\Handler\Auth\Email2faPostHandler;
use App\Handler\Auth\Email2faResendPostHandler;

final class ControllerServiceProviderTest extends TestCase
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
            View::class                          => $this->createMock(View::class),
            FlashInterface::class                => $this->createMock(FlashInterface::class),
            SessionInterface::class              => $this->createMock(SessionInterface::class),
            UserModelInterface::class            => $this->createMock(UserModelInterface::class),
            Request::class                       => $this->createMock(Request::class),
            AuthCheckerInterface::class          => $this->createMock(AuthCheckerInterface::class),
            CsrfTokenInterface::class            => $this->createMock(CsrfTokenInterface::class),
            ConfirmAccountHandler::class         => $this->instantiateWithoutConstructor(ConfirmAccountHandler::class),
            RegisterGetHandler::class            => $this->instantiateWithoutConstructor(RegisterGetHandler::class),
            RegisterPostHandler::class           => $this->instantiateWithoutConstructor(RegisterPostHandler::class),
            ResendConfirmationGetHandler::class  => $this->instantiateWithoutConstructor(ResendConfirmationGetHandler::class),
            ResendConfirmationPostHandler::class => $this->instantiateWithoutConstructor(ResendConfirmationPostHandler::class),
            LoginGetHandler::class               => $this->instantiateWithoutConstructor(LoginGetHandler::class),
            LoginPostHandler::class              => $this->instantiateWithoutConstructor(LoginPostHandler::class),
            LogoutHandler::class                 => $this->instantiateWithoutConstructor(LogoutHandler::class),
            ForgotPasswordGetHandler::class      => $this->instantiateWithoutConstructor(ForgotPasswordGetHandler::class),
            ForgotPasswordPostHandler::class     => $this->instantiateWithoutConstructor(ForgotPasswordPostHandler::class),
            ResetPasswordGetHandler::class       => $this->instantiateWithoutConstructor(ResetPasswordGetHandler::class),
            ResetPasswordPostHandler::class      => $this->instantiateWithoutConstructor(ResetPasswordPostHandler::class),
            Email2faGetHandler::class        => $this->instantiateWithoutConstructor(Email2faGetHandler::class),
            Email2faPostHandler::class       => $this->instantiateWithoutConstructor(Email2faPostHandler::class),
            Email2faResendPostHandler::class => $this->instantiateWithoutConstructor(Email2faResendPostHandler::class),
        ];
    }

    public function testDefinitionsContainExpectedKeys(): void
    {
        $definitions = ControllerServiceProvider::getDefinitions();

        $this->assertArrayHasKey(HomeController::class, $definitions);
        $this->assertArrayHasKey(ErrorController::class, $definitions);
        $this->assertArrayHasKey(AccountController::class, $definitions);

        $this->assertArrayHasKey(RegisterController::class, $definitions);
        $this->assertArrayHasKey(ConfirmAccountController::class, $definitions);
        $this->assertArrayHasKey(ResendConfirmationController::class, $definitions);
        $this->assertArrayHasKey(LoginController::class, $definitions);
        $this->assertArrayHasKey(LogoutController::class, $definitions);
        $this->assertArrayHasKey(ForgotPasswordController::class, $definitions);
        $this->assertArrayHasKey(ResetPasswordController::class, $definitions);

        $this->assertArrayHasKey(DebugController::class, $definitions);

        $this->assertArrayHasKey(Email2faController::class, $definitions);
    }

    public function testCoreControllerDefinitionsAreBuildable(): void
    {
        $definitions = ControllerServiceProvider::getDefinitions();
        $container   = $this->makeContainer($this->baseServices());

        $home    = $definitions[HomeController::class]($container);
        $error   = $definitions[ErrorController::class]($container);
        $account = $definitions[AccountController::class]($container);

        $this->assertInstanceOf(HomeController::class, $home);
        $this->assertInstanceOf(ErrorController::class, $error);
        $this->assertInstanceOf(AccountController::class, $account);
    }

    public function testAuthControllerDefinitionsAreBuildable(): void
    {
        $definitions = ControllerServiceProvider::getDefinitions();
        $container   = $this->makeContainer($this->baseServices());

        $register = $definitions[RegisterController::class]($container);
        $confirm  = $definitions[ConfirmAccountController::class]($container);
        $resend   = $definitions[ResendConfirmationController::class]($container);
        $login    = $definitions[LoginController::class]($container);
        $logout   = $definitions[LogoutController::class]($container);
        $forgot   = $definitions[ForgotPasswordController::class]($container);
        $reset    = $definitions[ResetPasswordController::class]($container);
        $email2fa = $definitions[Email2faController::class]($container);

        $this->assertInstanceOf(RegisterController::class, $register);
        $this->assertInstanceOf(ConfirmAccountController::class, $confirm);
        $this->assertInstanceOf(ResendConfirmationController::class, $resend);
        $this->assertInstanceOf(LoginController::class, $login);
        $this->assertInstanceOf(LogoutController::class, $logout);
        $this->assertInstanceOf(ForgotPasswordController::class, $forgot);
        $this->assertInstanceOf(ResetPasswordController::class, $reset);
        $this->assertInstanceOf(Email2faController::class, $email2fa);
    }

    public function testUtilityControllerDefinitionsAreBuildable(): void
    {
        $definitions = ControllerServiceProvider::getDefinitions();
        $container   = $this->makeContainer($this->baseServices());

        $debug = $definitions[DebugController::class]($container);

        $this->assertInstanceOf(DebugController::class, $debug);
    }

    private function instantiateWithoutConstructor(string $class): object
    {
        $reflection = new \ReflectionClass($class);

        return $reflection->newInstanceWithoutConstructor();
    }
}
