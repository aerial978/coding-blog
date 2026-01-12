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

/**
 * Middleware CSRF – valide automatiquement le token CSRF pour certaines routes POST.
 *
 * Il valide le token soumis avec le formulaire. En cas d’échec, il journalise,
 * ajoute un message flash, effectue une redirection et STOPPE la chaîne (return false).
 */
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

    /**
     * {@inheritdoc}
     *
     * Returns false when the request is blocked (e.g. invalid CSRF).
     */
    public function handle(Request $request, string $uri, string $method): bool
    {
        // Entry log (kept as-is)
        Logger::getLogger('app')->info('csrf_mw_entry', [
            'method' => $method,
            'uri'    => $uri,
        ]);

        // Decide early if this route/method requires CSRF validation
        $formId = $this->resolveProtectedFormId($method, $uri);
        if ($formId === null) {
            return true; // not a protected POST route
        }

        // Extract submitted token and validate
        $submittedToken = $this->extractSubmittedToken($request);
        if ($this->csrf->validateToken($formId, $submittedToken)) {
            return true; // OK → continue to controller
        }

        // Invalid token → block the request
        $this->blockInvalidCsrf($uri, $formId, $submittedToken);
        return false;
    }

    /**
     * Decide whether the route must be protected and return its FormId or null otherwise.
     */
    private function resolveProtectedFormId(string $method, string $uri): ?string
    {
        if (strtoupper($method) !== 'POST') {
            return null;
        }
        /** @var ?string $formId */
        $formId = self::PROTECTED_POST_ROUTES[$uri] ?? null;
        return $formId;
    }

    /**
     * Extract the submitted CSRF token from the request body.
     */
    private function extractSubmittedToken(Request $request): ?string
    {
        /** @var array<string,mixed> $post */
        $post = $request->request();
        return (isset($post['csrf_token']) && is_string($post['csrf_token']) && $post['csrf_token'] !== '')
            ? $post['csrf_token']
            : null;
    }

    /**
     * Log, flash, and redirect when CSRF validation fails.
     */
    private function blockInvalidCsrf(string $uri, string $formId, ?string $submittedToken): void
    {
        // Technical log of the block
        Logger::getLogger('app')->warning('csrf_mw_block', [
            'uri'        => $uri,
            'form_id'    => $formId,
            'token'      => $submittedToken             ?? '(none)',
            'client_ip'  => $_SERVER['REMOTE_ADDR']     ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ]);

        // User-facing message
        $msg = Logger::logCodeAndGetMessage('auth', 'warning', ErrorCode::AUTH_CSRF_INVALID, [
            'form_id' => $formId,
            'route'   => $uri,
        ]);
        $this->flash->add('error', $msg);

        // Redirect back (referer) or to home
        $target = is_string($_SERVER['HTTP_REFERER'] ?? null) && $_SERVER['HTTP_REFERER'] !== ''
            ? (string) $_SERVER['HTTP_REFERER']
            : '/';
        header('Location: ' . $target, true, 302);
    }
}
