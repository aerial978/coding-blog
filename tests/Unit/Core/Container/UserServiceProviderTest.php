<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Container;

use App\Core\Mail\MailerInterface;
use App\Core\SqlHelper;
use App\Model\UserModel;
use App\Model\UserTokenModel;
use App\Security\Contract\TokenGeneratorInterface;
use App\Security\TokenGenerator;
use App\Service\SecurityService;
use App\Validation\FormValidator;
use Cocur\Slugify\Slugify;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(\App\Core\Container\Provider\UserServiceProvider::class)]
final class UserServiceProviderTest extends TestCase
{
    #[Test]
    public function test_getDefinitions_builds_models_and_service(): void
    {
        $defs      = \App\Core\Container\Provider\UserServiceProvider::getDefinitions();
        $container = new FakeContainer();

        $container->set(SqlHelper::class, $this->createMock(SqlHelper::class));
        $container->set(FormValidator::class, new FormValidator());
        $container->set(Slugify::class, new Slugify());
        $container->set(MailerInterface::class, $this->createMock(MailerInterface::class));
        $container->set('logger.app', new NullLogger());
        $container->set(TokenGenerator::class, new TokenGenerator());

        foreach ($defs as $id => $factory) {
            if ($container->has($id)) {
                continue;
            }
            /** @var \Closure $factory */ // (optionnel) pour l’IDE
            $container->set($id, $factory($container));
        }

        $tg = $container->get(TokenGeneratorInterface::class);
        self::assertInstanceOf(TokenGenerator::class, $tg);
        $userModel  = $container->get(UserModel::class);
        $userTokens = $container->get(UserTokenModel::class);
        $service    = $container->get(SecurityService::class);
        self::assertInstanceOf(UserModel::class, $userModel);
        self::assertInstanceOf(UserTokenModel::class, $userTokens);
        self::assertInstanceOf(SecurityService::class, $service);
    }
}
