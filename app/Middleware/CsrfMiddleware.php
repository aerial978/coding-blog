<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\FormId;
use App\Core\Logger;
use App\Http\Middleware\MiddlewareInterface;
use App\Http\Request;
use App\Security\Contract\CsrfTokenInterface;

final class CsrfMiddleware implements MiddlewareInterface
{
    /** @var array<string,string> route POST => FormId */
    private const PROTECTED_POST_ROUTES = [
        '/register'            => FormId::REGISTER,
        '/resend-confirmation' => FormId::RESEND_CONFIRM,
    ];

    public function __construct(
        private CsrfTokenInterface $csrf,
        private FlashInterface $flash
    ) {
    }

    public function handle(Request $request, string $uri, string $method): bool
    {
        Logger::getLogger('app')->info('csrf_mw_entry', [
            'method' => $method,
            'uri'    => $uri,
        ]);

        $formId = $this->resolveProtectedFormId($method, $uri);
        if ($formId === null) {
            return true;
        }

        $submittedToken = $this->extractSubmittedToken($request);
        if ($this->csrf->validateToken($formId, $submittedToken)) {
            return true;
        }

        $this->blockInvalidCsrf($uri, $formId, $submittedToken);
        return false;
    }

    private function resolveProtectedFormId(string $method, string $uri): ?string
    {
        if (strtoupper($method) !== 'POST') {
            return null;
        }

        /** @var ?string $formId */
        $formId = self::PROTECTED_POST_ROUTES[$uri] ?? null;
        return $formId;
    }

    private function extractSubmittedToken(Request $request): ?string
    {
        /** @var array<string,mixed> $post */
        $post = $request->request();

        if (!isset($post['csrf_token']) || !is_string($post['csrf_token'])) {
            return null;
        }

        $token = trim($post['csrf_token']);
        return $token !== '' ? $token : null;
    }

    /**
     * Log, flash, and redirect when CSRF validation fails.
     */
    private function blockInvalidCsrf(string $uri, string $formId, ?string $submittedToken): void
    {
        $this->logCsrfBlocked($uri, $formId, $submittedToken);
        $this->flashCsrfInvalid($uri, $formId);

        $target = $this->resolveRedirectTarget('/');
        $this->redirect($target);
    }

    private function logCsrfBlocked(string $uri, string $formId, ?string $submittedToken): void
    {
        Logger::getLogger('app')->warning('csrf_mw_block', [
            'uri'        => $uri,
            'form_id'    => $formId,
            'token'      => $this->tokenOrNone($submittedToken),
            'client_ip'  => $this->serverString('REMOTE_ADDR', 'unknown'),
            'user_agent' => $this->serverString('HTTP_USER_AGENT', 'unknown'),
        ]);
    }

    private function flashCsrfInvalid(string $uri, string $formId): void
    {
        $msg = Logger::logCodeAndGetMessage('auth', 'warning', ErrorCode::AUTH_CSRF_INVALID, [
            'form_id' => $formId,
            'route'   => $uri,
        ]);

        $this->flash->add('error', $msg);
    }

    private function resolveRedirectTarget(string $default): string
    {
        $referer = $this->serverString('HTTP_REFERER', '');
        return $referer !== '' ? $referer : $default;
    }

    private function redirect(string $target): void
    {
        header('Location: ' . $target, true, 302);
    }

    private function tokenOrNone(?string $token): string
    {
        return ($token !== null && $token !== '') ? $token : '(none)';
    }

    private function serverString(string $key, string $default): string
    {
        $value = $_SERVER[$key] ?? null;
        return is_string($value) && $value !== '' ? $value : $default;
    }
}
