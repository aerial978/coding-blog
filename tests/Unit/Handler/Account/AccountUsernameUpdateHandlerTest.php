<?php

declare(strict_types=1);

namespace Tests\Unit\Handler\Account;

use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\MessageManager;
use App\Handler\Account\AccountUsernameUpdateHandler;
use App\Http\Contract\ResponderInterface;
use App\Service\Account\Contract\AccountUsernameUpdateServiceInterface;
use PHPUnit\Framework\TestCase;

final class AccountUsernameUpdateHandlerTest extends TestCase
{
    public function testHandleRedirectsWithSuccessFlashWhenUsernameIsUpdated(): void
    {
        $form = ['username' => 'new_username'];

        $service = $this->createMock(
            AccountUsernameUpdateServiceInterface::class
        );
        $flash     = $this->createMock(FlashInterface::class);
        $responder = $this->createMock(ResponderInterface::class);

        $service
            ->expects($this->once())
            ->method('update')
            ->with($form)
            ->willReturn([]);

        $flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'success',
                MessageManager::get(ErrorCode::ACCOUNT_USERNAME_UPDATE_SUCCESS)
            );

        $responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/account');

        $handler = new AccountUsernameUpdateHandler(
            $service,
            $flash,
            $responder
        );

        $handler->handle($form);
    }

    public function testHandleRedirectsWithErrorFlashWhenUpdateFails(): void
    {
        $form = ['username' => 'existing_username'];

        $errorCode = ErrorCode::ACCOUNT_USERNAME_EXISTS;

        $service = $this->createMock(
            AccountUsernameUpdateServiceInterface::class
        );
        $flash     = $this->createMock(FlashInterface::class);
        $responder = $this->createMock(ResponderInterface::class);

        $service
            ->expects($this->once())
            ->method('update')
            ->with($form)
            ->willReturn([
                'errors' => [$errorCode],
            ]);

        $flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'error',
                MessageManager::get($errorCode)
            );

        $responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/account');

        $handler = new AccountUsernameUpdateHandler(
            $service,
            $flash,
            $responder
        );

        $handler->handle($form);
    }
}
