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
        /** @var mixed $defs */
        $defs = require $this->path;

        self::assertIsArray($defs);
        self::assertNotEmpty($defs);

        $expectedKeys = [
            // System / Http
            \App\Core\View::class,
            \App\Http\Request::class,
            \App\Core\Contract\SessionInterface::class,
            \App\Core\Contract\FlashInterface::class,
            \App\Security\Contract\CsrfTokenInterface::class,
            \App\Core\Contract\RateLimiterFactoryInterface::class,
            \App\Security\Contract\TokenGeneratorInterface::class,
            \App\Core\Mail\MailerInterface::class,

            // Models / services
            \App\Model\Contract\UserModelInterface::class,
            \App\Model\Contract\UserTokenModelInterface::class,
            \App\Service\Security\SecurityService::class,
            \App\Service\Security\Contract\SecurityServiceInterface::class,
            \App\Service\Security\LoginService::class,
            \App\Service\Security\LogoutService::class,

            // Controllers
            \App\Controller\HomeController::class,
            \App\Controller\ErrorController::class,
            \App\Controller\AccountController::class,
            \App\Controller\RegisterController::class,
            \App\Controller\ConfirmAccountController::class,
            \App\Controller\ResendConfirmationController::class,
            \App\Controller\LoginController::class,
            \App\Controller\LogoutController::class,
            \App\Controller\ForgotPasswordController::class,
            \App\Controller\ResetPasswordController::class,
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
