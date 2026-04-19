<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\AccountController;
use App\Core\Contract\FlashInterface;
use App\Core\FormId;
use App\Core\View;
use App\Security\Contract\CsrfTokenInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AccountControllerTest extends TestCase
{
    private View&MockObject $view;
    private FlashInterface&MockObject $flash;
    private CsrfTokenInterface&MockObject $csrf;

    private AccountController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->view  = $this->createMock(View::class);
        $this->flash = $this->createMock(FlashInterface::class);
        $this->csrf  = $this->createMock(CsrfTokenInterface::class);

        $this->controller = new AccountController(
            $this->view,
            $this->flash,
            $this->csrf,
        );
    }

    public function testIndexRendersAccountPageWithTitleFlashesAndLogoutCsrfToken(): void
    {
        $expectedToken   = 'logout-csrf-token';
        $expectedFlashes = [
            'error'   => [],
            'success' => [],
            'warning' => [],
            'info'    => [],
        ];

        $this->csrf
            ->expects($this->once())
            ->method('generateToken')
            ->with(FormId::LOGOUT)
            ->willReturn($expectedToken);

        $this->flash
            ->expects($this->once())
            ->method('consumeMany')
            ->with(['error', 'success', 'warning', 'info'])
            ->willReturn($expectedFlashes);

        $this->view
            ->expects($this->once())
            ->method('render')
            ->with(
                'account/index.html.twig',
                $this->callback(function (array $data) use ($expectedToken, $expectedFlashes): bool {
                    return isset($data['title'], $data['logout_csrf_token'], $data['flashes'])
                        && $data['title']             === 'Mon compte'
                        && $data['logout_csrf_token'] === $expectedToken
                        && $data['flashes']           === $expectedFlashes;
                })
            )
            ->willReturn('');

        $this->controller->index();
    }
}
