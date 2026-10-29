<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\AccountController;
use App\Core\Contract\FlashInterface;
use App\Core\FormId;
use App\Handler\Account\AccountUsernameUpdateHandler;
use App\Http\Contract\ResponderInterface;
use App\Http\Request;
use App\Model\Entity\UserEntity;
use App\Security\Contract\CsrfTokenInterface;
use App\Service\Account\Contract\AccountServiceInterface;
use App\Service\Account\Contract\AccountUsernameUpdateServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AccountControllerTest extends TestCase
{
    private ResponderInterface&MockObject $responder;

    private AccountServiceInterface&MockObject $accountService;

    private AccountController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->responder = $this->createMock(ResponderInterface::class);

        $this->accountService = $this->createMock(
            AccountServiceInterface::class
        );

        $usernameUpdateService = $this->createMock(
            AccountUsernameUpdateServiceInterface::class
        );

        $flash = $this->createMock(FlashInterface::class);

        $usernameUpdateHandler = new AccountUsernameUpdateHandler(
            $usernameUpdateService,
            $flash,
            $this->responder,
        );

        $request = $this->createMock(Request::class);

        $csrf = $this->createMock(CsrfTokenInterface::class);

        $csrf
            ->method('generateToken')
            ->with(FormId::ACCOUNT_USERNAME_UPDATE)
            ->willReturn('test-csrf-token');

        $this->controller = new AccountController(
            $this->responder,
            $this->accountService,
            $usernameUpdateHandler,
            $request,
            $csrf,
        );
    }

    public function testIndexRendersAuthenticatedAccountInformation(): void
    {
        $user = (new UserEntity())->hydrate([
            'user_id'           => 42,
            'username'          => 'michael',
            'email'             => 'michael@example.com',
            'email_2fa_enabled' => 1,
            'role'              => 'MEMBER',
            'status'            => 'active',
            'created_at'        => '2026-01-15 10:30:00',
        ]);

        $this->accountService
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($user);

        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'account/index.html.twig',
                [
                    'title'      => 'Mon compte',
                    'active_nav' => 'account',
                    'csrf_token' => 'test-csrf-token',
                    'account'    => [
                        'username'          => 'michael',
                        'email'             => 'michael@example.com',
                        'email_2fa_enabled' => true,
                        'role'              => 'MEMBER',
                        'status'            => 'active',
                        'created_at'        => '2026-01-15 10:30:00',
                    ],
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

        $this->responder
            ->expects($this->never())
            ->method('render');

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/login');

        $this->controller->index();
    }
}
