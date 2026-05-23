<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\AccountController;
use App\Core\FormId;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\CsrfTokenInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AccountControllerTest extends TestCase
{
    private CsrfTokenInterface&MockObject $csrf;

    private ResponderInterface&MockObject $responder;

    private AccountController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->csrf      = $this->createMock(CsrfTokenInterface::class);
        $this->responder = $this->createMock(ResponderInterface::class);

        $this->controller = new AccountController(
            $this->csrf,
            $this->responder,
        );
    }

    public function testIndexRendersAccountPageWithLogoutCsrfToken(): void
    {
        $expectedToken = 'logout-csrf-token';

        $this->csrf
            ->expects($this->once())
            ->method('generateToken')
            ->with(FormId::LOGOUT)
            ->willReturn($expectedToken);

        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'account/index.html.twig',
                [
                    'title'             => 'Mon compte',
                    'logout_csrf_token' => $expectedToken,
                ]
            );

        $this->controller->index();
    }
}
