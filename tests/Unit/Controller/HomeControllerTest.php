<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\HomeController;
use App\Http\Contract\ResponderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class HomeControllerTest extends TestCase
{
    /**
     * @return ResponderInterface&MockObject
     */
    private function mockResponder(): ResponderInterface
    {
        /** @var ResponderInterface&MockObject $mock */
        $mock = $this->createMock(ResponderInterface::class);

        return $mock;
    }

    public function testIndexRendersHomeTemplateWithStaticData(): void
    {
        $responder = $this->mockResponder();

        $responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'home/index.html.twig',
                $this->callback(function (array $data): bool {
                    $this->assertSame('Home', $data['title']);
                    $this->assertSame('This is the home page.', $data['message']);
                    $this->assertTrue($data['show_header']);

                    $this->assertArrayNotHasKey('users', $data);
                    $this->assertArrayNotHasKey('logout_csrf_token', $data);
                    $this->assertArrayNotHasKey('flashes', $data);
                    $this->assertArrayNotHasKey('is_authenticated', $data);

                    return true;
                })
            );

        $controller = new HomeController($responder);

        $controller->index();
    }
}