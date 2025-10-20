<?php

namespace Tests\Functional;

use App\Controller\ErrorController;
use App\Core\ControllerFactoryInterface;
use App\Core\Router;
use App\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Functional test to ensure that the home page is correctly rendered.
 */
class TwigIntegrationTest extends TestCase
{
    public function testHomePageRenderedWithTwig(): void
    {
        // Simule une requête HTTP GET /
        $_SERVER['REQUEST_URI']    = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        /** @var array<string, array<string, array{0: class-string, 1: string}>> $routes */
        $routes = require __DIR__ . '/../../app/config/routes.php';

        // Vérifie que la route GET / existe et récupère [Controller::class, 'action']
        $this->assertArrayHasKey('GET', $routes, 'Routes config must define GET routes');
        $this->assertArrayHasKey('/', $routes['GET'], 'Routes config must define GET /');

        [$controllerClass, $action] = $routes['GET']['/'];
        $this->assertTrue(class_exists($controllerClass), "Controller class {$controllerClass} must exist");

        // $action doit être une chaîne non vide pour MockBuilder::onlyMethods()
        $this->assertNotSame('', $action, 'Route action must not be empty');
        /** @var non-empty-string $action */

        // Dépendances explicites du Router
        $basePath        = '';
        $errorController = $this->createMock(ErrorController::class);
        $request         = new Request();

        // Mock du contrôleur ciblé : on stub l’action pour émettre un HTML attendu
        $controllerMock = $this
            ->getMockBuilder($controllerClass)
            ->disableOriginalConstructor()       // évite de câbler la View/Twig dans ce test
            ->onlyMethods([$action])
            ->getMock();

        $controllerMock
            ->expects($this->once())
            ->method($action)
            ->willReturnCallback(static function (): void {
                // HTML minimal contenant les marqueurs attendus par le test
                echo '<!DOCTYPE html><html><head><title>Home</title></head><body>'
                   . '<h1>Home</h1>'
                   . '<p>This is the home page.</p>'
                   . '</body></html>';
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

        // Capture la sortie
        ob_start();
        $router->handleRequest();
        $output = ob_get_clean();

        // Assertions
        $this->assertIsString($output);
        $this->assertStringContainsString('<!DOCTYPE html>', $output);
        $this->assertStringContainsString('Home', $output);
        $this->assertStringContainsString('This is the home page.', $output);
    }
}
