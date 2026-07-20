<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Controller\AccountController;
use App\Controller\ErrorController;
use App\Core\Contract\FlashInterface;
use App\Core\ControllerFactoryInterface;
use App\Core\FormId;
use App\Core\Router;
use App\Http\Contract\ResponderInterface;
use App\Http\Request;
use App\Middleware\AuthenticationMiddleware;
use App\Model\Entity\UserEntity;
use App\Security\Contract\AuthCheckerInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Service\Account\Contract\AccountServiceInterface;
use PHPUnit\Framework\TestCase;

final class AccountPageTest extends TestCase
{
    /** @var array<string, string|null> */
    private array $serverBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->serverBackup = [
            'REQUEST_URI'    => $_SERVER['REQUEST_URI']    ?? null,
            'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? null,
        ];
    }

    protected function tearDown(): void
    {
        foreach ($this->serverBackup as $key => $value) {
            if ($value === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $value;
            }
        }

        parent::tearDown();
    }

    public function testGuestCannotAccessAccountPage(): void
    {
        $_SERVER['REQUEST_URI']    = '/coding-blog/account';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $authChecker = $this->createMock(AuthCheckerInterface::class);
        $flash       = $this->createMock(FlashInterface::class);
        $responder   = $this->createMock(ResponderInterface::class);
        $factory     = $this->createMock(ControllerFactoryInterface::class);

        $authChecker
            ->expects($this->once())
            ->method('isAuthenticated')
            ->willReturn(false);

        $flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'error',
                'Vous devez être connecté pour accéder à cette page.'
            );

        $responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $responder
            ->expects($this->never())
            ->method('render');

        $factory
            ->expects($this->never())
            ->method('create');

        $router = $this->makeRouter(
            $factory,
            $authChecker,
            $flash,
            $responder
        );

        $router->handleRequest();
    }

    public function testAuthenticatedUserCanAccessAccountPage(): void
    {
        $_SERVER['REQUEST_URI']    = '/coding-blog/account';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $authChecker    = $this->createMock(AuthCheckerInterface::class);
        $flash          = $this->createMock(FlashInterface::class);
        $responder      = $this->createMock(ResponderInterface::class);
        $csrf           = $this->createMock(CsrfTokenInterface::class);
        $accountService = $this->createMock(AccountServiceInterface::class);

        $user = (new UserEntity())->hydrate([
            'user_id'           => 42,
            'username'          => 'michael',
            'email'             => 'michael@example.com',
            'email_2fa_enabled' => 1,
        ]);

        $authChecker
            ->expects($this->once())
            ->method('isAuthenticated')
            ->willReturn(true);

        $flash
            ->expects($this->never())
            ->method('add');

        $accountService
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($user);

        $csrf
            ->expects($this->once())
            ->method('generateToken')
            ->with(FormId::LOGOUT)
            ->willReturn('logout-csrf-token');

        $responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'account/index.html.twig',
                [
                    'title'   => 'Mon compte',
                    'account' => [
                        'username'          => 'michael',
                        'email'             => 'michael@example.com',
                        'email_2fa_enabled' => true,
                    ],
                    'logout_csrf_token' => 'logout-csrf-token',
                ]
            );

        $responder
            ->expects($this->never())
            ->method('redirect');

        $controller = new AccountController(
            $csrf,
            $responder,
            $accountService
        );

        $factory = $this->factoryReturning(
            AccountController::class,
            $controller
        );

        $router = $this->makeRouter(
            $factory,
            $authChecker,
            $flash,
            $responder
        );

        $router->handleRequest();
    }

    public function testAuthenticatedUserIsRedirectedWhenAccountNoLongerExists(): void
    {
        $_SERVER['REQUEST_URI']    = '/coding-blog/account';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $authChecker    = $this->createMock(AuthCheckerInterface::class);
        $flash          = $this->createMock(FlashInterface::class);
        $responder      = $this->createMock(ResponderInterface::class);
        $csrf           = $this->createMock(CsrfTokenInterface::class);
        $accountService = $this->createMock(AccountServiceInterface::class);

        $authChecker
            ->expects($this->once())
            ->method('isAuthenticated')
            ->willReturn(true);

        $flash
            ->expects($this->never())
            ->method('add');

        $accountService
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn(null);

        $csrf
            ->expects($this->never())
            ->method('generateToken');

        $responder
            ->expects($this->never())
            ->method('render');

        $responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $controller = new AccountController(
            $csrf,
            $responder,
            $accountService
        );

        $factory = $this->factoryReturning(
            AccountController::class,
            $controller
        );

        $router = $this->makeRouter(
            $factory,
            $authChecker,
            $flash,
            $responder
        );

        $router->handleRequest();
    }

    private function makeRouter(
        ControllerFactoryInterface $factory,
        AuthCheckerInterface $authChecker,
        FlashInterface $flash,
        ResponderInterface $responder
    ): Router {
        /** @var array<string, array<string, array{0: class-string, 1: string}>> $routes */
        $routes = require __DIR__ . '/../../app/Config/routes.php';

        $request = new Request();

        $errorController = new ErrorController($responder);

        $router = new Router(
            $routes,
            '/coding-blog',
            $errorController,
            $request,
            $factory
        );

        $router->addMiddleware(
            new AuthenticationMiddleware(
                $authChecker,
                $flash,
                $responder
            )
        );

        return $router;
    }

    private function factoryReturning(
        string $expectedControllerClass,
        object $controller
    ): ControllerFactoryInterface {
        return new class (
            $expectedControllerClass,
            $controller
        ) implements ControllerFactoryInterface {
            public function __construct(
                private string $expectedControllerClass,
                private object $controller
            ) {
            }

            public function create(string $controllerClass): object
            {
                if ($controllerClass !== $this->expectedControllerClass) {
                    throw new \RuntimeException(
                        'Unexpected controller requested: ' . $controllerClass
                    );
                }

                return $this->controller;
            }
        };
    }
}
