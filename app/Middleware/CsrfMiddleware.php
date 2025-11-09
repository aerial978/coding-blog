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
     * @inheritDoc
     *
     * Retourne false si la requête est bloquée (ex: CSRF invalide).
     */
    public function handle(Request $request, string $uri, string $method): bool
    {
        // LOG #1 — Middleware atteint
        Logger::getLogger('app')->info('csrf_mw_entry', [
            'method' => $method,
            'uri'    => $uri,
        ]);

        // On ne protège ici que certaines routes en POST
        if (strtoupper($method) !== 'POST') {
            return true;
        }

        // Si la route n'est pas dans la liste protégée, on laisse passer
        $formId = self::PROTECTED_POST_ROUTES[$uri] ?? null;
        if ($formId === null) {
            return true;
        }

        /** @var array<string,mixed> $post */
        $post           = $request->request();
        $submittedToken = isset($post['csrf_token']) && is_string($post['csrf_token'])
            ? $post['csrf_token']
            : null;

        if ($this->csrf->validateToken($formId, $submittedToken)) {
            return true; // OK, on continue jusqu'au contrôleur
        }

        // Token invalide → blocage
        Logger::getLogger('app')->warning('csrf_mw_block', [
            'uri'        => $uri,
            'form_id'    => $formId,
            'token'      => $submittedToken             ?? '(none)',
            'client_ip'  => $_SERVER['REMOTE_ADDR']     ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ]);

        // CSRF invalide → log + flash + redirection et on stoppe la chaîne
        $msg = Logger::logCodeAndGetMessage('auth', 'warning', ErrorCode::AUTH_CSRF_INVALID, [
            'form_id' => $formId,
            'route'   => $uri,
        ]);
        $this->flash->add('error', $msg);

        // On redirige vers le referer si dispo, sinon vers la racine
        $target = is_string($_SERVER['HTTP_REFERER'] ?? null) && $_SERVER['HTTP_REFERER'] !== ''
            ? (string)$_SERVER['HTTP_REFERER']
            : '/';
        header('Location: ' . $target, true, 302);

        return false;
    }
}
