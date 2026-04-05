<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\AccountController;
use App\Core\Contract\FlashInterface;
use App\Core\View;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AccountControllerTest extends TestCase
{
    private View&MockObject $view;
    private FlashInterface&MockObject $flash;

    private AccountController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->view  = $this->createMock(View::class);
        $this->flash = $this->createMock(FlashInterface::class);

        $this->controller = new AccountController(
            $this->view,
            $this->flash,
        );
    }

    public function testIndexRendersAccountPageWithTitleAndFlashes(): void
    {
        $this->view
            ->expects($this->once())
            ->method('render')
            ->with(
                'account/index.html.twig',
                $this->callback(function (array $data): bool {
                    return isset($data['title'], $data['flashes'])
                        && $data['title'] === 'Mon compte'
                        && is_array($data['flashes']);
                })
            );

        $this->controller->index();
    }

    public function testIndexRendersAccountPage(): void
    {
        $this->view
            ->expects($this->once())
            ->method('render')
            ->with(
                'account/index.html.twig',
                $this->callback(function (array $data): bool {
                    return isset($data['title'])
                        && $data['title'] === 'Mon compte'
                        && is_array($data);
                })
            );

        $this->controller->index();
    }
}
