<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\AccountController;
use App\Core\Contract\FlashInterface;
use App\Core\Contract\SessionInterface;
use App\Core\ErrorCode;
use App\Core\FormId;
use App\Handler\Account\AccountPasswordChangeHandler;
use App\Handler\Account\AccountUsernameUpdateHandler;
use App\Http\Contract\ResponderInterface;
use App\Http\Request;
use App\Model\Entity\UserEntity;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\RememberMeCookieManagerInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Service\Account\Contract\AccountPasswordChangeServiceInterface;
use App\Service\Account\Contract\AccountServiceInterface;
use App\Service\Account\Contract\AccountUsernameUpdateServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AccountControllerTest extends TestCase
{
    private ResponderInterface&MockObject $responder;

    private AccountServiceInterface&MockObject $accountService;

    private AccountController $controller;

    private Request&MockObject $request;

    private AccountPasswordChangeServiceInterface&MockObject $passwordChangeService;

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

        $this->request = $this->createMock(Request::class);

        $csrf = $this->createMock(CsrfTokenInterface::class);

        $csrf
            ->method('generateToken')
            ->willReturnMap([
                [FormId::ACCOUNT_USERNAME_UPDATE, 'test-csrf-token'],
                [FormId::ACCOUNT_PASSWORD_CHANGE, 'test-password-csrf-token'],
            ]);

        $this->passwordChangeService = $this->createMock(
            AccountPasswordChangeServiceInterface::class
        );

        $cookieManager = $this->createMock(
            RememberMeCookieManagerInterface::class
        );

        $session = $this->createMock(
            SessionInterface::class
        );

        $rateLimitGuard = $this->createMock(
            RateLimitGuardInterface::class
        );

        $rateLimitGuard->method('assertAllowed')
            ->willReturn(true);

        $passwordChangeHandler = new AccountPasswordChangeHandler(
            $this->passwordChangeService,
            $flash,
            $this->responder,
            $cookieManager,
            $session,
            $rateLimitGuard,
        );

        $this->controller = new AccountController(
            $this->responder,
            $this->accountService,
            $usernameUpdateHandler,
            $this->request,
            $csrf,
            $passwordChangeHandler,
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
                    'title'               => 'Mon compte',
                    'active_nav'          => 'account',
                    'csrf_token'          => 'test-csrf-token',
                    'password_csrf_token' => 'test-password-csrf-token',
                    'account'             => [
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

    public function testUpdatePasswordDelegatesSubmittedFormWhenAuthenticated(): void
    {
        $user = new UserEntity();

        $form = [
            'current_password'      => 'CurrentPassword1!',
            'new_password'          => 'NewSecurePassword2!',
            'password_confirmation' => 'NewSecurePassword2!',
        ];

        $this->accountService
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($user);

        $this->request
            ->expects($this->once())
            ->method('request')
            ->willReturn($form);

        $this->passwordChangeService
            ->expects($this->once())
            ->method('changePassword')
            ->with($form)
            ->willReturn([
                'errors' => [
                    'current_password' => ErrorCode::ACCOUNT_CURRENT_PASSWORD_INVALID,
                ],
            ]);

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/account');

        $this->controller->updatePassword();
    }

    public function testUpdatePasswordRedirectsToLoginWhenAccountIsNotFound(): void
    {
        $this->accountService
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn(null);

        $this->request
            ->expects($this->never())
            ->method('request');

        $this->passwordChangeService
            ->expects($this->never())
            ->method('changePassword');

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/login');

        $this->controller->updatePassword();
    }
}
