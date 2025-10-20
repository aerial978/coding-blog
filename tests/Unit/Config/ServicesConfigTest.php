<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ServicesConfigTest extends TestCase
{
    private string $path;
    protected function setUp(): void
    {
        // tests/Unit/Config -> projet
        $this->path = \dirname(__DIR__, 3) . '/app/config/services.php';
    }

    #[Test]
    public function services_php_retourne_un_tableau_merge_des_providers_avec_cles_attendues(): void
    {
        /** @var mixed $defs */                    // ⟵ NE pas pré-spécifier un array de callables/objets
        $defs = require $this->path;
        self::assertIsArray($defs);
        self::assertNotEmpty($defs);

        $expectedKeys = [
            \PDO::class,
            \App\Core\View::class,
            \App\Http\Request::class,
            \App\Core\SessionManager::class,
            \App\Core\FlashService::class,
            \App\Security\CsrfTokenManager::class,
            \App\Core\Factory\RateLimiterFactory::class,
            \App\Security\TokenGenerator::class,
            \App\Core\Mail\MailerInterface::class,

            \App\Model\UserModel::class,
            \App\Model\UserTokenModel::class,
            \App\Service\SecurityService::class,

            \App\Controller\HomeController::class,
            \App\Controller\ErrorController::class,
            \App\Controller\SecurityController::class,
        ];
        foreach ($expectedKeys as $key) {
            self::assertArrayHasKey($key, $defs, "Clé manquante: $key");
        }
    }

    #[Test]
    public function chaque_entree_est_un_callable_ou_un_objet(): void
    {
        /** @var mixed $defs */
        $defs = require $this->path;
        self::assertIsArray($defs);

        foreach ($defs as $id => $entry) {
            $ok = \is_callable($entry) || \is_object($entry);
            self::assertTrue($ok, "L'entrée '$id' doit être un callable ou un objet.");
        }
    }
}
