<?php

namespace Tests\Functional;

use App\Controller\ErrorController;
use App\Core\ControllerFactoryInterface;
use App\Core\Router;
use App\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Functional test of the routing system with the home page.
 */
class RoutingTest extends TestCase
{
    /**
     * Checks that the home page is reachable and displays the expected text.
     */
    public function testHomePageIsReachable(): void
    {
        // Simule la requête HTTP GET /
        $_SERVER['REQUEST_URI']    = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        /** @var array<string, array<string, array{0: class-string, 1: string}>> $routes */
        $routes = require __DIR__ . '/../../app/config/routes.php';

        // Récupère depuis la config le contrôleur et l’action ciblés par GET /
        $this->assertArrayHasKey('GET', $routes, 'Routes config must define GET routes');
        $this->assertArrayHasKey('/', $routes['GET'], 'Routes config must define GET /');

        [$controllerClass, $action] = $routes['GET']['/'];
        $this->assertTrue(class_exists($controllerClass), "Controller class {$controllerClass} must exist");

        // $action doit être non vide pour MockBuilder::onlyMethods()
        $this->assertNotSame('', $action, 'Route action must not be empty');
        /** @var non-empty-string $action */

        // Dépendances du Router
        $basePath        = '';
        $errorController = $this->createMock(ErrorController::class);
        $request         = new Request();

        // Mock du contrôleur ciblé : on stub l’action pour émettre un HTML minimal attendu
        $controllerMock = $this
            ->getMockBuilder($controllerClass)
            ->disableOriginalConstructor()     // évite d’avoir à câbler View/Twig, etc.
            ->onlyMethods([$action])
            ->getMock();

        $controllerMock
            ->expects($this->once())
            ->method($action)
            ->willReturnCallback(static function (): void {
                echo '<!DOCTYPE html><html><body>Home page</body></html>';
            });

        // Mock de la factory : retourne notre contrôleur mocké pour la classe attendue
        $factory = $this->createMock(ControllerFactoryInterface::class);
        $factory
            ->method('create')
            ->with($controllerClass)
            ->willReturn($controllerMock);

        // Instancie le Router avec ses dépendances explicites
        $router = new Router(
            $routes,
            $basePath,
            $errorController,
            $request,
            $factory
        );

        // Capture la sortie du contrôleur
        ob_start();
        $router->handleRequest();
        $output = ob_get_clean();

        // Assertions
        $this->assertIsString($output);
        $this->assertStringContainsString('Home page', $output);
        $this->assertStringContainsString('<!DOCTYPE html>', $output);
    }
}
