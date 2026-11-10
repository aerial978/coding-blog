<?php

declare(strict_types=1);

namespace Tests\Unit\Handler\Account;

use App\Core\Contract\FlashInterface;
use App\Core\Contract\SessionInterface;
use App\Core\ErrorCode;
use App\Core\MessageManager;
use App\Handler\Account\AccountPasswordChangeHandler;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\RememberMeCookieManagerInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Service\Account\Contract\AccountPasswordChangeServiceInterface;
use PHPUnit\Framework\TestCase;

final class AccountPasswordChangeHandlerTest extends TestCase
{
    public function testHandleRedirectsWithSuccessAndUpdatesSessionOnSuccess(): void
    {
        $form = [
            'current_password'      => 'CurrentPassword1!',
            'new_password'          => 'NewSecurePassword2!',
            'password_confirmation' => 'NewSecurePassword2!',
        ];

        $service        = $this->createMock(AccountPasswordChangeServiceInterface::class);
        $flash          = $this->createMock(FlashInterface::class);
        $responder      = $this->createMock(ResponderInterface::class);
        $cookieManager  = $this->createMock(RememberMeCookieManagerInterface::class);
        $session        = $this->createMock(SessionInterface::class);
        $rateLimitGuard = $this->createAllowedRateLimitGuard();

        $service->expects($this->once())
            ->method('changePassword')
            ->with($form)
            ->willReturn(['ok' => true]);

        $cookieManager->expects($this->once())
            ->method('expireCookie');

        $session->expects($this->once())
            ->method('regenerateAndDeleteOld');

        $flash->expects($this->once())
            ->method('add')
            ->with(
                'success',
                MessageManager::get(ErrorCode::ACCOUNT_PASSWORD_UPDATE_SUCCESS)
            );

        $responder->expects($this->once())
            ->method('redirect')
            ->with('/account');

        $handler = new AccountPasswordChangeHandler(
            $service,
            $flash,
            $responder,
            $cookieManager,
            $session,
            $rateLimitGuard,
        );

        $handler->handle($form);
    }

    public function testHandleDoesNotUpdateSessionWhenCurrentPasswordIsInvalid(): void
    {
        $this->assertFailureDoesNotUpdateSession(
            ErrorCode::ACCOUNT_CURRENT_PASSWORD_INVALID
        );
    }

    public function testHandleDoesNotUpdateSessionWhenServiceFails(): void
    {
        $this->assertFailureDoesNotUpdateSession(
            ErrorCode::ACCOUNT_PASSWORD_UPDATE_FAILED
        );
    }

    public function testHandleDisplaysAllValidationErrorsWithoutUpdatingSession(): void
    {
        $form = [
            'current_password'      => '',
            'new_password'          => '',
            'password_confirmation' => '',
        ];

        $errors = [
            'current_password'      => ErrorCode::AUTH_FIELD_REQUIRED,
            'new_password'          => ErrorCode::AUTH_FIELD_REQUIRED,
            'password_confirmation' => ErrorCode::AUTH_FIELD_REQUIRED,
        ];

        $service        = $this->createMock(AccountPasswordChangeServiceInterface::class);
        $flash          = $this->createMock(FlashInterface::class);
        $responder      = $this->createMock(ResponderInterface::class);
        $cookieManager  = $this->createMock(RememberMeCookieManagerInterface::class);
        $session        = $this->createMock(SessionInterface::class);
        $rateLimitGuard = $this->createAllowedRateLimitGuard();

        $service->expects($this->once())
            ->method('changePassword')
            ->with($form)
            ->willReturn(['errors' => $errors]);

        $cookieManager->expects($this->never())
            ->method('expireCookie');

        $session->expects($this->never())
            ->method('regenerateAndDeleteOld');

        $flash->expects($this->exactly(3))
            ->method('add')
            ->with(
                'error',
                MessageManager::get(ErrorCode::AUTH_FIELD_REQUIRED)
            );

        $responder->expects($this->once())
            ->method('redirect')
            ->with('/account');

        $handler = new AccountPasswordChangeHandler(
            $service,
            $flash,
            $responder,
            $cookieManager,
            $session,
            $rateLimitGuard,
        );

        $handler->handle($form);
    }

    public function testHandleDoesNotCallServiceWhenRateLimitIsExceeded(): void
    {
        $form = [
            'current_password'      => 'CurrentPassword1!',
            'new_password'          => 'NewSecurePassword2!',
            'password_confirmation' => 'NewSecurePassword2!',
        ];

        $service        = $this->createMock(AccountPasswordChangeServiceInterface::class);
        $flash          = $this->createMock(FlashInterface::class);
        $responder      = $this->createMock(ResponderInterface::class);
        $cookieManager  = $this->createMock(RememberMeCookieManagerInterface::class);
        $session        = $this->createMock(SessionInterface::class);
        $rateLimitGuard = $this->createMock(RateLimitGuardInterface::class);

        $rateLimitGuard->expects($this->once())
            ->method('assertAllowed')
            ->with([
                'key'           => 'account_password_change',
                'limit'         => 5,
                'window_sec'    => 300,
                'redirect'      => '/account',
                'route_for_log' => '/account/password',
                'flash_type'    => 'error',
            ])
            ->willReturn(false);

        $service->expects($this->never())
            ->method('changePassword');

        $cookieManager->expects($this->never())
            ->method('expireCookie');

        $session->expects($this->never())
            ->method('regenerateAndDeleteOld');

        // Le RateLimitGuard gère lui-même le flash et la redirection.
        $flash->expects($this->never())
            ->method('add');

        $responder->expects($this->never())
            ->method('redirect');

        $handler = new AccountPasswordChangeHandler(
            $service,
            $flash,
            $responder,
            $cookieManager,
            $session,
            $rateLimitGuard,
        );

        $handler->handle($form);
    }

    private function assertFailureDoesNotUpdateSession(string $errorCode): void
    {
        $form = [
            'current_password'      => 'WrongPassword1!',
            'new_password'          => 'NewSecurePassword2!',
            'password_confirmation' => 'NewSecurePassword2!',
        ];

        $service        = $this->createMock(AccountPasswordChangeServiceInterface::class);
        $flash          = $this->createMock(FlashInterface::class);
        $responder      = $this->createMock(ResponderInterface::class);
        $cookieManager  = $this->createMock(RememberMeCookieManagerInterface::class);
        $session        = $this->createMock(SessionInterface::class);
        $rateLimitGuard = $this->createAllowedRateLimitGuard();

        $service->expects($this->once())
            ->method('changePassword')
            ->with($form)
            ->willReturn([
                'errors' => ['current_password' => $errorCode],
            ]);

        $cookieManager->expects($this->never())
            ->method('expireCookie');

        $session->expects($this->never())
            ->method('regenerateAndDeleteOld');

        $flash->expects($this->once())
            ->method('add')
            ->with('error', MessageManager::get($errorCode));

        $responder->expects($this->once())
            ->method('redirect')
            ->with('/account');

        $handler = new AccountPasswordChangeHandler(
            $service,
            $flash,
            $responder,
            $cookieManager,
            $session,
            $rateLimitGuard,
        );

        $handler->handle($form);
    }

    private function createAllowedRateLimitGuard(): RateLimitGuardInterface
    {
        $rateLimitGuard = $this->createMock(RateLimitGuardInterface::class);

        $rateLimitGuard->expects($this->once())
            ->method('assertAllowed')
            ->willReturn(true);

        return $rateLimitGuard;
    }
}
