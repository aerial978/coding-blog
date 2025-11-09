<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Container;

use App\Controller\ErrorController;
use App\Controller\HomeController;
use App\Controller\SecurityController;
use App\Core\Container\Provider\ControllerServiceProvider;
use App\Core\Factory\RateLimiterFactory;
use App\Core\FlashService;
use App\Core\SessionManager;
use App\Core\SqlHelper;
use App\Core\View;
use App\Http\Request;
use App\Model\UserModel;
use App\Model\UserTokenModel;
use App\Security\CsrfTokenManager;
use App\Security\TokenGenerator;
use App\Service\Security\RegistrationService;
use App\Validation\FormValidator;
use Cocur\Slugify\Slugify;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Petit container factice pour le test.
 * Il retourne les instances enregistrées dans $services.
 */
final class ArrayContainer implements ContainerInterface
{
    /** @var array<class-string,object> */
    private array $services;
    /** @param array<class-string,object> $services */
    public function __construct(array $services)
    {
        $this->services = $services;
    }

    public function get(string $id): object
    {
        if (!$this->has($id)) {
            throw new class ("Service $id not found") extends \RuntimeException implements NotFoundExceptionInterface {
            };
        }
        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }
}

final class ControllerServiceProviderTest extends TestCase
{
    #[Test]
    public function getDefinitions_builds_all_controllers(): void
    {
        // Doubles / stubs légers pour les dépendances
        $view         = $this->createMock(View::class);
        $userModel    = $this->createStub(UserModel::class);
        $flash        = new FlashService(new SessionManager());
        $request      = new Request();
        $csrf         = new CsrfTokenManager(new SessionManager());
        $rateLimiter  = new RateLimiterFactory(new SessionManager());
        // Dépendances “lourdes” du SecurityService remplacées par stubs
        $validator    = new FormValidator();
        // ⟵ au lieu de $this->createStub(FormValidator::class)
        $userToken    = $this->createStub(UserTokenModel::class);
        $mailer       = $this->getMockBuilder(\App\Core\Mail\MailerInterface::class)->getMock();
        $sqlHelper    = $this->createStub(SqlHelper::class);
        $securitySvc  = new RegistrationService($validator, $userModel, $userToken, new Slugify(), $mailer, new TokenGenerator(), $sqlHelper);
        // Container factice avec les instances déjà construites
        $container = new ArrayContainer([
            View::class                   => $view,
            UserModel::class              => $userModel,
            FlashService::class           => $flash,
            Request::class                => $request,
            CsrfTokenManager::class       => $csrf,
            RateLimiterFactory::class     => $rateLimiter,
            RegistrationService::class    => $securitySvc,
        ]);
        $defs = ControllerServiceProvider::getDefinitions();
        // Construction réelle des contrôleurs via les closures
        $home = ($defs[HomeController::class])($container);
        $err  = ($defs[ErrorController::class])($container);
        $sec  = ($defs[SecurityController::class])($container);
        self::assertInstanceOf(HomeController::class, $home);
        self::assertInstanceOf(ErrorController::class, $err);
        self::assertInstanceOf(SecurityController::class, $sec);
    }
}
