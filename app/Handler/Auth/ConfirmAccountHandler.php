<?php

declare(strict_types=1);

namespace App\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Http\Contract\ResponderInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use App\Support\Scalar;

final class ConfirmAccountHandler
{
    public function __construct(
        private SecurityServiceInterface $securityService,
        private FlashInterface $flash,
        private ResponderInterface $responder,
    ) {
    }

    public function handle(): void
    {
        $token = $this->getQueryToken();
        if ($token === null) {
            $this->flash->add('error', Logger::logCodeAndGetMessage('auth', 'warning', ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, [
                'reason' => 'missing_or_empty',
            ]));
            $this->responder->redirect('/coding-blog/resend-confirmation');
            return;
        }

        try {
            $result = $this->securityService->confirmAccount($token);
        } catch (\Throwable $e) {
            $this->flash->add('error', Logger::logCodeAndGetMessage('auth', 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'exception' => $e->getMessage(),
            ]));
            $this->responder->redirect('/coding-blog');
            return;
        }

        $this->handleConfirmOutcome($result);
    }

    private function getQueryToken(): ?string
    {
        return isset($_GET['token']) && is_string($_GET['token']) && $_GET['token'] !== ''
            ? $_GET['token']
            : null;
    }

    /** @param array<string,mixed> $result */
    private function handleConfirmOutcome(array $result): void
    {
        $code = Scalar::toString($result['error'] ?? null);
        $reason = is_string($result['reason'] ?? null) ? $result['reason'] : '';

        $action = $this->resolveConfirmAction($code, $reason);
        $this->applyConfirmAction($action);
    }

    /**
     * @return 'success'|'invalid_expired'|'invalid_not_found'|'used'|'already'|'technical'
     */
    private function resolveConfirmAction(string $code, string $reason): string
    {
        if ($code === '') {
            return 'success';
        }
        if ($code === (string) ErrorCode::AUTH_INVALID_CONFIRM_TOKEN && $reason === 'expired') {
            return 'invalid_expired';
        }
        if ($code === (string) ErrorCode::AUTH_INVALID_CONFIRM_TOKEN && $reason === 'not_found') {
            return 'invalid_not_found';
        }
        if ($code === (string) ErrorCode::AUTH_CONFIRM_TOKEN_USED) {
            return 'used';
        }
        if ($code === (string) ErrorCode::AUTH_ALREADY_CONFIRMED) {
            return 'already';
        }
        return 'technical';
    }

    /**
     * @param 'success'|'invalid_expired'|'invalid_not_found'|'used'|'already'|'technical' $action
     */
    private function applyConfirmAction(string $action): void
    {
        $map = [
            'success' => function (): void {
                $this->flash->add('success', Logger::logCodeAndGetMessage('auth', 'info', ErrorCode::AUTH_CONFIRMATION_SUCCESS));
                $this->responder->redirect('/coding-blog');
            },
            'invalid_expired' => function (): void {
                $this->flash->add('error', Logger::logCodeAndGetMessage('auth', 'warning', ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, ['reason' => 'expired']));
                $this->responder->redirect('/coding-blog/resend-confirmation');
            },
            'invalid_not_found' => function (): void {
                $this->flash->add('error', Logger::logCodeAndGetMessage('auth', 'warning', ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, ['reason' => 'not_found']));
                $this->responder->redirect('/coding-blog/resend-confirmation');
            },
            'used' => function (): void {
                $this->flash->add('info', Logger::logCodeAndGetMessage('auth', 'info', ErrorCode::AUTH_CONFIRM_TOKEN_USED));
                $this->responder->redirect('/coding-blog');
            },
            'already' => function (): void {
                $this->flash->add('info', Logger::logCodeAndGetMessage('auth', 'info', ErrorCode::AUTH_ALREADY_CONFIRMED));
                $this->responder->redirect('/coding-blog');
            },
            'technical' => function (): void {
                $this->flash->add('error', Logger::logCodeAndGetMessage('auth', 'error', ErrorCode::AUTH_TECHNICAL_ERROR));
                $this->responder->redirect('/coding-blog');
            },
        ];

        $map[$action]();
    }
}
