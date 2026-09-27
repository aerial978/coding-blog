<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\AccountController;
use App\Http\Contract\ResponderInterface;
use App\Model\Entity\UserEntity;
use App\Service\Account\Contract\AccountServiceInterface;
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

        $this->responder      = $this->createMock(ResponderInterface::class);
        $this->accountService = $this->createMock(
            AccountServiceInterface::class
        );

        $this->controller = new AccountController(
            $this->responder,
            $this->accountService,
        );
    }

    public function testIndexRendersAuthenticatedAccountInformation(): void
    {
        $user = (new UserEntity())->hydrate([
            'user_id'           => 42,
            'username'          => 'michael',
            'email'             => 'michael@example.com',
            'email_2fa_enabled' => 1,
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
                    'title'   => 'Mon compte',
                    'account' => [
                        'username'          => 'michael',
                        'email'             => 'michael@example.com',
                        'email_2fa_enabled' => true,
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
