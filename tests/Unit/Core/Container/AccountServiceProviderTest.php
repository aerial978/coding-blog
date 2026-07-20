<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Container;

use App\Core\Container\Provider\AccountServiceProvider;
use App\Model\Contract\UserModelInterface;
use App\Security\Contract\AuthCheckerInterface;
use App\Service\Account\AccountService;
use App\Service\Account\Contract\AccountServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

final class AccountServiceProviderTest extends TestCase
{
    public function testDefinitionsContainExpectedKeys(): void
    {
        $definitions = AccountServiceProvider::getDefinitions();

        $this->assertArrayHasKey(AccountService::class, $definitions);
        $this->assertArrayHasKey(AccountServiceInterface::class, $definitions);
    }

    public function testAccountServiceDefinitionCanBeResolved(): void
    {
        $definitions = AccountServiceProvider::getDefinitions();

        $accountService = $definitions[AccountService::class](
            $this->containerWith([
                AuthCheckerInterface::class => $this->createMock(AuthCheckerInterface::class),
                UserModelInterface::class   => $this->createMock(UserModelInterface::class),
            ])
        );

        $this->assertInstanceOf(AccountService::class, $accountService);
    }

    public function testAccountServiceInterfaceDefinitionCanBeResolved(): void
    {
        $definitions = AccountServiceProvider::getDefinitions();

        $accountService = $this->createMock(AccountServiceInterface::class);

        $resolvedService = $definitions[AccountServiceInterface::class](
            $this->containerWith([
                AccountService::class => $accountService,
            ])
        );

        $this->assertSame($accountService, $resolvedService);
        $this->assertInstanceOf(AccountServiceInterface::class, $resolvedService);
    }

    /**
     * @param array<string,mixed> $services
     *
     * @return ContainerInterface&MockObject
     */
    private function containerWith(array $services): ContainerInterface&MockObject
    {
        $container = $this->createMock(ContainerInterface::class);

        $container
            ->method('get')
            ->willReturnCallback(
                static function (string $id) use ($services): mixed {
                    if (!array_key_exists($id, $services)) {
                        throw new RuntimeException(sprintf(
                            'Service "%s" is not defined in test container.',
                            $id
                        ));
                    }

                    return $services[$id];
                }
            );

        return $container;
    }
}
