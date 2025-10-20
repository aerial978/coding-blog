<?php

declare(strict_types=1);

// tests/Unit/Controller/HomeControllerTest.php

namespace Tests\Unit\Controller;

use App\Controller\HomeController;
use App\Core\FlashService;
use App\Core\SessionManager;
use App\Core\View;
use App\Model\Entity\UserEntity;
use App\Model\UserModel;
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
    public function __construct(View $view, UserModel $userModel, FlashService $flash)
    {
        parent::__construct($view, $userModel, $flash);
    }

    protected function render(string $template, array $params = []): void
    {
        $this->lastTemplate = $template;
        $this->lastParams   = $params;
        // pas d'echo
    }
}

final class HomeControllerTest extends TestCase
{
    private function makeView(): View
    {
        // View factice : ne sera pas appelée (render est surchargée)
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

    /** @return UserModel&MockObject */
    private function mockUserModel(): UserModel
    {
        /** @var UserModel&MockObject $m */
        $m = $this->createMock(UserModel::class);
        return $m;
    }

    public function testIndexRendersHomeTemplateWithUsersAndStaticData(): void
    {
        $view  = $this->makeView();
        $flash = $this->makeFlash();
        $users = [
            (new UserEntity())->setUserId(1)->setUsername('Alice')->setEmail('a@example.test'),
            (new UserEntity())->setUserId(2)->setUsername('Bob')->setEmail('b@example.test'),
        ];
        $userModel = $this->mockUserModel();
        $userModel->expects($this->once())
            ->method('findAll')
            ->willReturn($users);
        $ctrl = new TestableHomeController($view, $userModel, $flash);
        $ctrl->index();
        // Vérifie le template
        self::assertSame('home/index.html.twig', $ctrl->lastTemplate);
        // Vérifie les données principales
        self::assertArrayHasKey('title', $ctrl->lastParams);
        self::assertArrayHasKey('message', $ctrl->lastParams);
        self::assertArrayHasKey('users', $ctrl->lastParams);
        self::assertSame('Home', $ctrl->lastParams['title']);
        self::assertSame('This is the home page.', $ctrl->lastParams['message']);
        self::assertSame($users, $ctrl->lastParams['users']);
        // Les flashes sont ajoutées par withFlashes()
        self::assertArrayHasKey('flashes', $ctrl->lastParams);
        self::assertIsArray($ctrl->lastParams['flashes']);
    }

    public function testIndexMergesFlashesIntoRenderedData(): void
    {
        $view  = $this->makeView();
        $flash = $this->makeFlash();
        // Ajoute une flash pour vérifier qu’elle est bien consommée et transmise
        $flash->add('error', 'Oops');
        $userModel = $this->mockUserModel();
        $userModel->method('findAll')->willReturn([]);
        $ctrl = new TestableHomeController($view, $userModel, $flash);
        $ctrl->index();
        self::assertSame('home/index.html.twig', $ctrl->lastTemplate);
        self::assertArrayHasKey('flashes', $ctrl->lastParams);
        self::assertIsArray($ctrl->lastParams['flashes']);

        /** @var array{
         *   error:   list<string>,
         *   success: list<string>,
         *   warning: list<string>,
         *   info:    list<string>,
         *   notice:  list<string>
         * } $flashes
         */
        $flashes = $ctrl->lastParams['flashes'];

        self::assertArrayHasKey('error', $flashes);
        self::assertGreaterThanOrEqual(1, \count($flashes['error']));
    }
}
