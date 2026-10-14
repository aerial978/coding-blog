<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\DashboardController;
use App\Http\Contract\ResponderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DashboardControllerTest extends TestCase
{
    private ResponderInterface&MockObject $responder;

    private DashboardController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->responder = $this->createMock(ResponderInterface::class);

        $this->controller = new DashboardController(
            $this->responder,
        );
    }

    public function testIndexRendersDashboardWithActiveNavigation(): void
    {
        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'dashboard/index.html.twig',
                [
                    'title'      => 'Tableau de bord',
                    'active_nav' => 'dashboard',
                ]
            );

        $this->responder
            ->expects($this->never())
            ->method('redirect');

        $this->controller->index();
    }
}
