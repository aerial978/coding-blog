<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\BaseController;
use App\Core\FlashService;
use App\Core\SessionManager;
use App\Core\View;
use PHPUnit\Framework\TestCase;

/**
 * Petit contrôleur concret pour les tests :
 * - garde la vraie redirect()
 * - remplace terminate() pour ne pas quitter le process
 * - expose un getter pour vérifier l’appel
 */
final class TestableBaseController extends BaseController
{
    private int $terminateCalls = 0;
    public function __construct(View $view, FlashService $flash)
    {
        parent::__construct($view, $flash);
    }

    protected function terminate(): void
    {
        $this->terminateCalls++;
    }

    public function getTerminateCalls(): int
    {
        return $this->terminateCalls;
    }
}

final class BaseControllerTest extends TestCase
{
    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    private function assertArrayAndReturn(mixed $value): array
    {
        self::assertIsArray($value);
        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param mixed $value
     * @return array{
     *   error:   list<string>,
     *   success: list<string>,
     *   warning: list<string>,
     *   info:    list<string>,
     *   notice:  list<string>
     * }
     */
    private function assertFlashesAndReturn(mixed $value): array
    {
        self::assertIsArray($value);
        /** @var array{
         *   error:   list<string>,
         *   success: list<string>,
         *   warning: list<string>,
         *   info:    list<string>,
         *   notice:  list<string>
         * } $value
         */
        return $value;
    }

    private function makeView(string $rendered = ''): View
    {
        return new class ($rendered) extends View {
            public function __construct(private string $out)
            {
            }
            public function render(string $template, array $params = []): string
            {
                return $this->out ?: ('tpl=' . $template);
            }
        };
    }

    private function makeFlash(): FlashService
    {
        // ✅ Correction : FlashService ne prend qu’un seul argument
        return new FlashService(new SessionManager());
    }

    private function makeController(View $view, FlashService $flash): TestableBaseController
    {
        return new TestableBaseController($view, $flash);
    }

    public function testRenderEchoesViewOutput(): void
    {
        $view  = $this->makeView('hello');
        $flash = $this->makeFlash();
        $ctrl  = $this->makeController($view, $flash);

        ob_start();
        // ✅ Réflexion protégée sécurisée
        $ref    = new \ReflectionClass($ctrl);
        $parent = $ref->getParentClass();
        self::assertInstanceOf(\ReflectionClass::class, $parent);
        $m = $parent->getMethod('render');
        $m->setAccessible(true);

        $m->invoke($ctrl, 'any.html', ['a' => 1]);
        $out = ob_get_clean();

        self::assertSame('hello', $out);
    }

    public function testRedirectSendsLocationHeaderAndCallsTerminate(): void
    {
        $view  = $this->makeView();
        $flash = $this->makeFlash();
        $ctrl  = $this->makeController($view, $flash);

        // ✅ Réflexion protégée sécurisée
        $ref    = new \ReflectionClass($ctrl);
        $parent = $ref->getParentClass();
        self::assertInstanceOf(\ReflectionClass::class, $parent);
        $m = $parent->getMethod('redirect');
        $m->setAccessible(true);
        $m->invoke($ctrl, '/target');

        // 1) Vérifie l'en-tête Location si Xdebug est chargé
        if (\function_exists('xdebug_get_headers')) {
            /** @var array<int,string> $headers */
            $headers = \call_user_func('xdebug_get_headers');
            self::assertTrue(
                \in_array('Location: /target', $headers, true),
                'Header Location non trouvé: ' . json_encode($headers)
            );
        }

        // 2) terminate() a bien été appelée
        self::assertGreaterThanOrEqual(1, $ctrl->getTerminateCalls());
    }

    public function testWithFlashesMergesDataWithDefaultLevels(): void
    {
        $view  = $this->makeView();
        $flash = $this->makeFlash();
        $flash->add('error', 'Oops');
        $ctrl = $this->makeController($view, $flash);

        // ✅ Réflexion protégée sécurisée
        $ref    = new \ReflectionClass($ctrl);
        $parent = $ref->getParentClass();
        self::assertInstanceOf(\ReflectionClass::class, $parent);
        $m = $parent->getMethod('withFlashes');
        $m->setAccessible(true);

        $data = $this->assertArrayAndReturn($m->invoke($ctrl, ['foo' => 'bar']));

        self::assertSame('bar', $data['foo']);
        self::assertArrayHasKey('flashes', $data);

        $flashes = $this->assertFlashesAndReturn($data['flashes']);
        self::assertArrayHasKey('error', $flashes);
        self::assertCount(1, $flashes['error']);
    }

    public function testWithFlashesAcceptsCustomLevels(): void
    {
        $view  = $this->makeView();
        $flash = $this->makeFlash();
        $flash->add('notice', 'Heads up');
        $ctrl = $this->makeController($view, $flash);

        // ✅ Réflexion protégée sécurisée
        $ref    = new \ReflectionClass($ctrl);
        $parent = $ref->getParentClass();
        self::assertInstanceOf(\ReflectionClass::class, $parent);
        $m = $parent->getMethod('withFlashes');
        $m->setAccessible(true);

        $data = $this->assertArrayAndReturn($m->invoke($ctrl, [], ['notice']));

        self::assertArrayHasKey('flashes', $data);
        $flashes = $this->assertFlashesAndReturn($data['flashes']);

        self::assertArrayHasKey('notice', $flashes);
        self::assertCount(1, $flashes['notice']);

        foreach (['error', 'success', 'warning', 'info'] as $lvl) {
            self::assertArrayHasKey($lvl, $flashes);
            self::assertCount(0, $flashes[$lvl]);
        }
    }
}
