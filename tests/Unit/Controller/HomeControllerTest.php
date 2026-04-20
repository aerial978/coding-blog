<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\HomeController;
use App\Core\FlashService;
use App\Core\SessionManager;
use App\Core\View;
use App\Http\Request;
use App\Model\Entity\UserEntity;
use App\Model\UserModel;
use App\Security\Contract\AuthCheckerInterface;
use App\Security\Contract\CsrfTokenInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Sous-contrôleur de test : on intercepte render() pour capturer
 * le template et les paramètres sans produire de sortie.
 */
final class TestableHomeController extends HomeController
{
    public ?string $lastTemplate = null;

    /** @var array<string,mixed> */
    public array $lastParams = [];

    public function __construct(
        View $view,
        UserModel $userModel,
        FlashService $flash,
        Request $request,
        AuthCheckerInterface $authChecker,
        CsrfTokenInterface $csrf,
    ) {
        parent::__construct($view, $userModel, $flash, $request, $authChecker, $csrf);
    }

    protected function render(string $template, array $params = []): void
    {
        $this->lastTemplate = $template;
        $this->lastParams   = $params;
    }
}

final class HomeControllerTest extends TestCase
{
    private function makeView(): View
    {
        return new class () extends View {
            public function render(string $template, array $params = []): string
            {
                return '';
            }
        };
    }

    private function makeFlash(): FlashService
    {
        return new FlashService(new SessionManager());
    }

    private function makeRequest(): Request
    {
        return $this->createMock(Request::class);
    }

    /**
     * @return UserModel&MockObject
     */
    private function mockUserModel(): UserModel
    {
        /** @var UserModel&MockObject $m */
        $m = $this->createMock(UserModel::class);

        return $m;
    }

    /**
     * @return AuthCheckerInterface&MockObject
     */
    private function mockAuthChecker(): AuthCheckerInterface
    {
        /** @var AuthCheckerInterface&MockObject $m */
        $m = $this->createMock(AuthCheckerInterface::class);

        return $m;
    }

    /**
     * @return CsrfTokenInterface&MockObject
     */
    private function mockCsrf(): CsrfTokenInterface
    {
        /** @var CsrfTokenInterface&MockObject $m */
        $m = $this->createMock(CsrfTokenInterface::class);

        return $m;
    }

    public function testIndexRendersHomeTemplateWithUsersAndStaticData(): void
    {
        $view        = $this->makeView();
        $flash       = $this->makeFlash();
        $request     = $this->makeRequest();
        $authChecker = $this->mockAuthChecker();
        $csrf        = $this->mockCsrf();

        $users = [
            (new UserEntity())->setUserId(1)->setUsername('Alice')->setEmail('a@example.test'),
            (new UserEntity())->setUserId(2)->setUsername('Bob')->setEmail('b@example.test'),
        ];

        $userModel = $this->mockUserModel();
        $userModel->expects($this->once())
            ->method('findAll')
            ->willReturn($users);

        $authChecker->expects($this->once())
            ->method('isAuthenticated')
            ->with($request)
            ->willReturn(false);

        $csrf->expects($this->never())
            ->method('generateToken');

        $ctrl = new TestableHomeController(
            $view,
            $userModel,
            $flash,
            $request,
            $authChecker,
            $csrf
        );

        $ctrl->index();

        self::assertSame('home/index.html.twig', $ctrl->lastTemplate);
        self::assertArrayHasKey('title', $ctrl->lastParams);
        self::assertArrayHasKey('message', $ctrl->lastParams);
        self::assertArrayHasKey('users', $ctrl->lastParams);
        self::assertArrayHasKey('show_header', $ctrl->lastParams);
        self::assertArrayHasKey('is_authenticated', $ctrl->lastParams);
        self::assertArrayHasKey('logout_csrf_token', $ctrl->lastParams);

        self::assertSame('Home', $ctrl->lastParams['title']);
        self::assertSame('This is the home page.', $ctrl->lastParams['message']);
        self::assertSame($users, $ctrl->lastParams['users']);
        self::assertTrue($ctrl->lastParams['show_header']);
        self::assertFalse($ctrl->lastParams['is_authenticated']);
        self::assertSame('', $ctrl->lastParams['logout_csrf_token']);

        self::assertArrayHasKey('flashes', $ctrl->lastParams);
        self::assertIsArray($ctrl->lastParams['flashes']);
    }

    public function testIndexMergesFlashesIntoRenderedData(): void
    {
        $view        = $this->makeView();
        $flash       = $this->makeFlash();
        $request     = $this->makeRequest();
        $authChecker = $this->mockAuthChecker();
        $csrf        = $this->mockCsrf();

        $flash->add('error', 'Oops');

        $userModel = $this->mockUserModel();
        $userModel->method('findAll')->willReturn([]);

        $authChecker->expects($this->once())
            ->method('isAuthenticated')
            ->with($request)
            ->willReturn(false);

        $csrf->expects($this->never())
            ->method('generateToken');

        $ctrl = new TestableHomeController(
            $view,
            $userModel,
            $flash,
            $request,
            $authChecker,
            $csrf
        );

        $ctrl->index();

        self::assertSame('home/index.html.twig', $ctrl->lastTemplate);
        self::assertArrayHasKey('flashes', $ctrl->lastParams);
        self::assertIsArray($ctrl->lastParams['flashes']);

        /** @var array<string, list<string>> $flashes */
        $flashes = $ctrl->lastParams['flashes'];

        self::assertArrayHasKey('error', $flashes);
        self::assertGreaterThanOrEqual(1, \count($flashes['error']));
    }

    public function testIndexAddsLogoutTokenWhenUserIsAuthenticated(): void
    {
        $view        = $this->makeView();
        $flash       = $this->makeFlash();
        $request     = $this->makeRequest();
        $authChecker = $this->mockAuthChecker();
        $csrf        = $this->mockCsrf();

        $userModel = $this->mockUserModel();
        $userModel->method('findAll')->willReturn([]);

        $authChecker->expects($this->once())
            ->method('isAuthenticated')
            ->with($request)
            ->willReturn(true);

        $csrf->expects($this->once())
            ->method('generateToken')
            ->with(\App\Core\FormId::LOGOUT)
            ->willReturn('logout-token-123');

        $ctrl = new TestableHomeController(
            $view,
            $userModel,
            $flash,
            $request,
            $authChecker,
            $csrf
        );

        $ctrl->index();

        self::assertTrue($ctrl->lastParams['is_authenticated']);
        self::assertSame('logout-token-123', $ctrl->lastParams['logout_csrf_token']);
    }
}
