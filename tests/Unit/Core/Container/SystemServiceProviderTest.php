<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Container;

use App\Core\Container\Provider\SystemServiceProvider;
use App\Core\Factory\RateLimiterFactory;
use App\Core\FlashService;
use App\Core\Mail\MailerInterface;
use App\Core\SessionManager;
use App\Core\SqlHelper;
use App\Core\View;
use App\Http\Request;
use App\Infrastructure\Mail\DummyMailer;
use App\Infrastructure\Mail\MailjetMailer;
use App\Security\CsrfTokenManager;
use App\Security\TokenGenerator;
use App\Validation\FormValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class SystemServiceProviderTest extends TestCase
{
    /** @var array<string, string|null> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        $keys = ['MAILER_TRANSPORT','MAIL_FROM_EMAIL','MAIL_FROM_NAME','MJ_APIKEY_PUBLIC','MJ_APIKEY_PRIVATE'];
        foreach ($keys as $k) {
            $raw                 = $_ENV[$k] ?? null;
            $this->envBackup[$k] = is_string($raw) ? $raw : null; // <-- normalisation en ?string
        }

        $_ENV['MAIL_FROM_EMAIL']  = 'no-reply@example.test';
        $_ENV['MAIL_FROM_NAME']   = 'Coding Blog';
        $_ENV['MAILER_TRANSPORT'] = 'dummy';
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $k => $v) {
            if ($v === null) {
                unset($_ENV[$k]);
            } else {
                $_ENV[$k] = $v;
            }
        }
    }

    /** Appelle une closure de factory avec ou sans arg. container selon sa signature. */
    private function callFactory(callable $factory, ContainerInterface $c): mixed
    {
        $rf = new \ReflectionFunction(\Closure::fromCallable($factory));
        return $rf->getNumberOfParameters() === 0 ? $factory() : $factory($c);
    }

    #[Test]
    public function getDefinitions_builds_core_services_without_db(): void
    {
        $defs = SystemServiceProvider::getDefinitions();
        $c    = new FakeContainer();
        // On ne veut aucune vraie connexion DB.
        $pdo = $this->getMockBuilder(\PDO::class)->disableOriginalConstructor()->getMock();
        $c->set(\PDO::class, $pdo);
        $c->set(SessionManager::class, new SessionManager());
        $c->set(View::class, new View());
        $this->assertArrayHasKey(View::class, $defs);
        $this->assertInstanceOf(View::class, $this->callFactory($defs[View::class], $c));
        $this->assertArrayHasKey(Request::class, $defs);
        $this->assertInstanceOf(Request::class, $this->callFactory($defs[Request::class], $c));
        $this->assertArrayHasKey(FormValidator::class, $defs);
        $this->assertInstanceOf(FormValidator::class, $this->callFactory($defs[FormValidator::class], $c));
        $this->assertArrayHasKey(SessionManager::class, $defs);
        $this->assertInstanceOf(SessionManager::class, $this->callFactory($defs[SessionManager::class], $c));
        $this->assertArrayHasKey(FlashService::class, $defs);
        $this->assertInstanceOf(FlashService::class, $this->callFactory($defs[FlashService::class], $c));
        $this->assertArrayHasKey(CsrfTokenManager::class, $defs);
        $this->assertInstanceOf(CsrfTokenManager::class, $this->callFactory($defs[CsrfTokenManager::class], $c));
        $this->assertArrayHasKey(RateLimiterFactory::class, $defs);
        $this->assertInstanceOf(RateLimiterFactory::class, $this->callFactory($defs[RateLimiterFactory::class], $c));
        $this->assertArrayHasKey(TokenGenerator::class, $defs);
        $this->assertInstanceOf(TokenGenerator::class, $this->callFactory($defs[TokenGenerator::class], $c));
        $this->assertArrayHasKey(SqlHelper::class, $defs);
        $this->assertInstanceOf(SqlHelper::class, $this->callFactory($defs[SqlHelper::class], $c));
        // Dummy mailer par défaut
        $this->assertArrayHasKey(MailerInterface::class, $defs);
        $mailer = $this->callFactory($defs[MailerInterface::class], $c);
        $this->assertInstanceOf(MailerInterface::class, $mailer);
        $this->assertInstanceOf(DummyMailer::class, $mailer);
    }

    #[Test]
    public function getDefinitions_selects_mailjet_when_env_is_mailjet(): void
    {
        $_ENV['MAILER_TRANSPORT']  = 'mailjet';
        $_ENV['MJ_APIKEY_PUBLIC']  = 'test_pub';
        $_ENV['MJ_APIKEY_PRIVATE'] = 'test_priv';
        $defs                      = SystemServiceProvider::getDefinitions();
        $c                         = new FakeContainer();
        $c->set(SessionManager::class, new SessionManager());
        $c->set(View::class, new View());
        $factory = $defs[MailerInterface::class];
        $mailer  = $this->callFactory($factory, $c);
        $this->assertInstanceOf(MailerInterface::class, $mailer);
        $this->assertInstanceOf(MailjetMailer::class, $mailer);
    }
}
