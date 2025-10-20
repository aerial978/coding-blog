<?php

declare(strict_types=1);

// tests/Unit/Controller/ErrorControllerTest.php

namespace Tests\Unit\Controller;

use App\Controller\ErrorController;
use App\Core\FlashService;
use App\Core\SessionManager;
use App\Core\View;
use PHPUnit\Framework\TestCase;

/**
 * Contrôleur de test : on capture le template et les params
 * en surchargeant render(), sans émettre de sortie réelle.
 */
final class TestableErrorController extends ErrorController
{
    public ?string $lastTemplate = null;
    /** @var array<string,mixed> */
    public array $lastParams = [];
    public function __construct(View $view, FlashService $flash)
    {
        parent::__construct($view, $flash);
    }

    // On intercepte le rendu
    protected function render(string $template, array $params = []): void
    {
        $this->lastTemplate = $template;
        $this->lastParams   = $params;
        // pas d'echo ici
    }
}

final class ErrorControllerTest extends TestCase
{
    private function makeView(): View
    {
        // View factice, jamais appelée (render() est surchargée dans le contrôleur)
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

    protected function tearDown(): void
    {
        // On remet un code HTTP "neutre" pour ne pas polluer d'autres tests
        http_response_code(200);
        parent::tearDown();
    }

    public function testNotFoundSets404AndRenders404Template(): void
    {
        $ctrl = new TestableErrorController($this->makeView(), $this->makeFlash());
        $ctrl->notFound();
        // Code HTTP
        self::assertSame(404, http_response_code());
        // Rendu
        self::assertSame('errors/404.html.twig', $ctrl->lastTemplate);
        self::assertSame([], $ctrl->lastParams);
    }

    public function testServerErrorSets500AndRenders500TemplateWithId(): void
    {
        $ctrl = new TestableErrorController($this->makeView(), $this->makeFlash());
        $ctrl->serverError('ERR-123');
        // Code HTTP
        self::assertSame(500, http_response_code());
        // Rendu + paramètres
        self::assertSame('errors/500.html.twig', $ctrl->lastTemplate);
        self::assertArrayHasKey('errorId', $ctrl->lastParams);
        self::assertSame('ERR-123', $ctrl->lastParams['errorId']);
    }

    public function testServerErrorSets500AndRenders500TemplateWithoutId(): void
    {
        $ctrl = new TestableErrorController($this->makeView(), $this->makeFlash());
        $ctrl->serverError();
        // pas d'ID

        // Code HTTP
        self::assertSame(500, http_response_code());
        // Rendu + paramètre null
        self::assertSame('errors/500.html.twig', $ctrl->lastTemplate);
        self::assertArrayHasKey('errorId', $ctrl->lastParams);
        self::assertNull($ctrl->lastParams['errorId']);
    }
}
