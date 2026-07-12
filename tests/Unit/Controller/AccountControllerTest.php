<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\AccountController;
use App\Core\FormId;
use App\Http\Contract\ResponderInterface;
use App\Model\Entity\UserEntity;
use App\Security\Contract\CsrfTokenInterface;
use App\Service\Account\Contract\AccountServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AccountControllerTest extends TestCase
{
    private CsrfTokenInterface&MockObject $csrf;

    private ResponderInterface&MockObject $responder;

    private AccountServiceInterface&MockObject $accountService;

    private AccountController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->csrf = $this->createMock(CsrfTokenInterface::class);
        $this->responder = $this->createMock(ResponderInterface::class);
        $this->accountService = $this->createMock(
            AccountServiceInterface::class
        );

        $this->controller = new AccountController(
            $this->csrf,
            $this->responder,
            $this->accountService,
        );
    }

    public function testIndexRendersAuthenticatedAccountInformation(): void
    {
        $expectedToken = 'logout-csrf-token';

        $user = (new UserEntity())->hydrate([
            'user_id'          => 42,
            'username'         => 'michael',
            'email'            => 'michael@example.com',
            'email_2fa_enabled' => 1,
        ]);

        $this->accountService
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($user);

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
                    'title' => 'Mon compte',
                    'account' => [
                        'username'          => 'michael',
                        'email'             => 'michael@example.com',
                        'email_2fa_enabled' => true,
                    ],
                    'logout_csrf_token' => $expectedToken,
                ]
            );

        $this->responder
            ->expects($this->never())
            ->method('redirect');

        $this->controller->index();
    }

    public function testIndexRedirectsToLoginWhenAccountIsNotFound(): void
    {
        $this->accountService
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn(null);

        $this->csrf
            ->expects($this->never())
            ->method('generateToken');

        $this->responder
            ->expects($this->never())
            ->method('render');

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $this->controller->index();
    }
}