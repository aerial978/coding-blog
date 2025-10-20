<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Contract\FlashInterface;
use App\Core\Contract\RateLimiterFactoryInterface;
use App\Core\ErrorCode;
use App\Core\FormId;
use App\Core\Logger;
use App\Core\MessageManager;
use App\Core\View;
use App\Http\Request;
use App\Security\Contract\CsrfTokenInterface;
use App\Service\Contract\SecurityServiceInterface;

/**
 * Handles user-facing security flows (registration, email confirmation,
 * and resending confirmation links). The controller is intentionally thin
 * and delegates the business rules to SecurityService.
 */
final class SecurityController extends BaseController
{
    /**
     * Handles user-facing security flows (registration, email confirmation,
     * and resending confirmation links). The controller is intentionally thin
     * and delegates the business rules to SecurityService.
     */
    public function __construct(View $view, private SecurityServiceInterface $securityService, private Request $request, FlashInterface $flash, private CsrfTokenInterface $csrf, private RateLimiterFactoryInterface $rateLimiterFactory)
    {
        parent::__construct($view, $flash);
    }

    /**
     * Registration endpoint (GET/POST).
     *
     * GET:
     *  - Renders the registration form.
     *  - If a previous successful POST set a "register_state", switches the
     *    view to a "check your email" state and obfuscates the address.
     *
     * POST:
     *  - Enforces rate limiting (returns a human-friendly wait message and
     *    preserves minimal input on throttle).
     *  - Validates CSRF token (logs a warning, flashes an error, preserves
     *    input and redirects back on failure).
     *  - Delegates to SecurityService::register(). On technical failure,
     *    logs the exception and redirects back with an error and preserved input.
     *  - If the service returns errors:
     *      * When the error is "confirm email send failed", redirects to the
     *        resend-confirmation page with the email pre-filled.
     *      * Otherwise, flashes each business error and redirects back to the form.
     *  - On success, stores a lightweight state (‘register_state’) to display
     *    the “check your email” screen on the next GET and redirects to /register.
     */
    public function register(): void
    {
        $channel = 'auth';
        // GET : afficher le formulaire
        if ($this->request->getMethod() === 'GET') {
            $old   = $this->flash->take('old', []);
            $state = $this->flash->take('register_state', null);
            $mode  = $state ? 'check_email' : 'form';

            // Obfuscation e-mail sûre (string ou null, preg_replace peut retourner null)
            $stateEmail = is_array($state) && isset($state['email']) && is_string($state['email']) ? $state['email'] : null;
            $obfuscated = $stateEmail !== null ? (preg_replace('/(^.).*(@.*$)/', '$1***$2', $stateEmail) ?? $stateEmail) : null;

            $this->render('security/register.html.twig', $this->withFlashes([
                'title'            => 'User Registration',
                'mode'             => $mode,
                'obfuscated_email' => $obfuscated,
                'csrf_token'       => $this->csrf->generateToken(FormId::REGISTER),
                'old'              => is_array($old) ? $old : [],
            ]));
            return;
        }

        $limiter = $this->rateLimiterFactory->create('registration', 5, 300);
        if (!$limiter->isAllowed()) {
            $retry = $limiter->getRetryAfter();
            header('Retry-After: ' . $retry);

            $min      = intdiv($retry, 60);
            $sec      = $retry % 60;
            $timeStr  = $min > 0 ? "{$min} min et {$sec} s" : "{$sec} s";
            $template = MessageManager::get(ErrorCode::AUTH_RATE_LIMITED_DYNAMIC);
            $waitMsg  = str_replace('{time}', $timeStr, $template);

            /** @var array<string,mixed> $form */
            $form     = $this->request->request();
            $username = isset($form['username']) && is_string($form['username']) ? $form['username'] : '';
            $email    = isset($form['email'])    && is_string($form['email']) ? $form['email'] : '';

            Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_RATE_LIMITED_DYNAMIC, [
                'retry_after' => $retry,
                'route'       => '/register',
                'username'    => $username,
                'email'       => $email,
            ]);

            $this->flash->add('error', $waitMsg);
            $this->flash->put('old', ['username' => $username, 'email' => $email]);
            $this->flash->add('info', MessageManager::get(ErrorCode::AUTH_PASSWORD_REENTER));
            $this->redirect('/coding-blog/register');
            return;
        }

        /** @var array<string,mixed> $form */
        $form = $this->request->request();

        $csrfToken = isset($form['csrf_token']) && is_string($form['csrf_token']) ? $form['csrf_token'] : null;
        $email     = isset($form['email'])      && is_string($form['email']) ? $form['email'] : '';
        $username  = isset($form['username'])   && is_string($form['username']) ? $form['username'] : '';

        if (!$this->csrf->validateToken(FormId::REGISTER, $csrfToken)) {
            $msg = Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_CSRF_INVALID, [
                'email'    => $email,
                'username' => $username,
                'route'    => '/register',
            ]);
            $this->flash->add('error', $msg);
            $this->flash->put('old', ['username' => $username, 'email' => $email]);
            $this->redirect('/coding-blog/register');
            return;
        }

        $limiter->recordAttempt();

        try {
            $result = $this->securityService->register($form);
        } catch (\Throwable $e) {
            $username = isset($form['username']) && is_string($form['username']) ? $form['username'] : '';
            $email    = isset($form['email'])    && is_string($form['email']) ? $form['email'] : '';

            $msg = Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'exception' => $e->getMessage(),
                'email'     => $email ?: null,
                'username'  => $username ?: null,
            ]);

            $this->flash->add('error', $msg);
            $this->flash->put('old', [
                'username' => $username,
                'email'    => $email,
            ]);
            $this->redirect('/coding-blog/register');
            return;
        }

        $errors = [];
        if (isset($result['errors']) && is_array($result['errors'])) {
            foreach ($result['errors'] as $v) {
                if (is_string($v) || is_int($v) || is_float($v)) {
                    $errors[] = (string) $v;
                }
            }
        }

        if ($errors !== []) {
            if (in_array((string) ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED, $errors, true)) {
                $msg = Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED, [
                    'email'    => $email,
                    'username' => $username,
                ]);
                $this->flash->add('error', $msg);
                $this->flash->put('old', ['email' => $email]);
                $this->redirect('/coding-blog/resend-confirmation');
                return;
            }

            foreach ($errors as $code) {
                $this->flash->add('error', MessageManager::get($code));
            }
            $this->flash->put('old', isset($result['old']) && is_array($result['old']) ? $result['old'] : [
                'username' => $username,
                'email'    => $email,
            ]);
            $this->redirect('/coding-blog/register');
            return;
        }

        // Succès
        if (!empty($result['ok'])) {
            $this->flash->put('register_state', ['email' => $email]);
            Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_ACCOUNT_CONFIRMATION_SENT, [
                'email'    => $email,
                'username' => $username,
            ]);
        }

        $this->redirect('/coding-blog/register');
    }

    /**
     * Email confirmation endpoint (GET).
     *
     * Flow:
     *  - Validates the presence of a non-empty token in the query string.
     *  - Delegates to SecurityService::confirmAccount($token) and handles:
     *      * Technical errors → flash error and redirect to login.
     *      * Business outcomes:
     *          - expired/not_found → redirect to resend-confirmation with error
     *          - already confirmed or token used → info, redirect to login
     *          - generic error → flash technical error and redirect to login
     *  - On success → flash success and redirect to login.
     */
    public function confirmAccount(): void
    {
        $channel = 'auth';

        // Récupère et normalise le token depuis la query string
        $token = isset($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : null;
        if ($token === null || $token === '') {
            $msg = Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, [
                'reason' => 'missing_or_empty',
            ]);
            $this->flash->add('error', $msg);
            $this->redirect('/coding-blog/resend-confirmation');
            return;
        }

        try {
            /** @var array<string,mixed> $result */
            $result = $this->securityService->confirmAccount($token);
        } catch (\Throwable $e) {
            $msg = Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'exception' => $e->getMessage(),
            ]);
            $this->flash->add('error', $msg);
            $this->redirect('/coding-blog');
            return;
        }

        $errorVal = $result['error'] ?? null;
        $code     = (is_string($errorVal) || is_int($errorVal) || is_float($errorVal)) ? (string) $errorVal : '';

        $reasonVal = $result['reason'] ?? null;
        $reason    = is_string($reasonVal) ? $reasonVal : '';

        if ($code !== '') {
            if ($code === (string) ErrorCode::AUTH_INVALID_CONFIRM_TOKEN && $reason === 'expired') {
                $msg = Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, [
                    'reason' => $reason,
                ]);
                $this->flash->add('error', $msg);
                $this->redirect('/coding-blog/resend-confirmation');
                return;
            }

            if ($code === (string) ErrorCode::AUTH_CONFIRM_TOKEN_USED) {
                $msg = Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_CONFIRM_TOKEN_USED);
                $this->flash->add('info', $msg);
                $this->redirect('/coding-blog');
                return;
            }

            if ($code === (string) ErrorCode::AUTH_ALREADY_CONFIRMED) {
                $msg = Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_ALREADY_CONFIRMED);
                $this->flash->add('info', $msg);
                $this->redirect('/coding-blog');
                return;
            }

            if ($code === (string) ErrorCode::AUTH_INVALID_CONFIRM_TOKEN && $reason === 'not_found') {
                $msg = Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, [
                    'reason' => 'not_found',
                ]);
                $this->flash->add('error', $msg);
                $this->redirect('/coding-blog/resend-confirmation');
                return;
            }

            // Fallback technique
            $msg = Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR);
            $this->flash->add('error', $msg);
            $this->redirect('/coding-blog');
            return;
        }

        // Succès
        $msg = Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_CONFIRMATION_SUCCESS);
        $this->flash->add('success', $msg);
        $this->redirect('/coding-blog');
    }

    /**
     * Resend-confirmation endpoint (GET/POST).
     *
     * GET:
     *  - Renders the form with a fresh CSRF token and any preserved input.
     *
     * POST:
     *  - Enforces rate limiting (sends Retry-After and flashes a wait message).
     *  - Validates CSRF token (flashes error and redirects back on failure).
     *  - Delegates to SecurityService::resendConfirmation($email).
     *    * On technical failure: logs the exception, flashes error, redirects back.
     *    * On business error AUTH_ALREADY_CONFIRMED: flashes info and redirects to login.
     *    * On any other business error: flashes error and redirects back.
     *  - On success: flashes a generic “email sent” message and stays on the form.
     */
    public function resendConfirmation(): void
    {
        $channel = 'auth';

        // GET : formulaire
        if ($this->request->getMethod() === 'GET') {
            $old = $this->flash->take('old', []);
            $this->render('security/resend-confirmation.html.twig', $this->withFlashes([
                'title'      => 'Renvoyer le lien de confirmation',
                'csrf_token' => $this->csrf->generateToken(FormId::RESEND_CONFIRM),
                'old'        => is_array($old) ? $old : [],
            ]));
            return;
        }

        /** @var array<string,mixed> $form */
        $form = $this->request->request();

        // Rate limit
        $limiter = $this->rateLimiterFactory->create('resend_confirmation', 5, 600);
        if (!$limiter->isAllowed()) {
            $retry = $limiter->getRetryAfter();
            header('Retry-After: ' . $retry);

            $min     = intdiv($retry, 60);
            $sec     = $retry % 60;
            $timeStr = $min > 0 ? "{$min} min et {$sec} s" : "{$sec} s";

            $tpl     = MessageManager::get(ErrorCode::AUTH_RATE_LIMITED_DYNAMIC);
            $waitMsg = str_replace('{time}', $timeStr, $tpl);

            $this->flash->add('error', $waitMsg);
            $this->redirect('/coding-blog/resend-confirmation');
            return;
        }

        // CSRF : typé string|null pour PHPStan
        $csrfToken = isset($form['csrf_token']) && is_string($form['csrf_token']) ? $form['csrf_token'] : null;
        if (!$this->csrf->validateToken(FormId::RESEND_CONFIRM, $csrfToken)) {
            $msg = Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_CSRF_INVALID, [
                'route' => '/resend-confirmation',
            ]);
            $this->flash->add('error', $msg);
            $this->redirect('/coding-blog/resend-confirmation');
            return;
        }

        $limiter->recordAttempt();

        // Email saisi, normalisé
        $email = isset($form['email']) && is_string($form['email']) ? $form['email'] : '';

        try {
            /** @var array<string,mixed> $result */
            $result = $this->securityService->resendConfirmation($email);
        } catch (\Throwable $e) {
            $msg = Logger::logCodeAndGetMessage($channel, 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'exception' => $e->getMessage(),
                'email'     => $email,
            ]);
            $this->flash->add('error', $msg);
            $this->redirect('/coding-blog/resend-confirmation');
            return;
        }

        $errorVal = $result['error'] ?? null;
        $code     = (is_string($errorVal) || is_int($errorVal) || is_float($errorVal)) ? (string) $errorVal : '';

        if ($code !== '') {
            if ($code === (string) ErrorCode::AUTH_ALREADY_CONFIRMED) {
                $msg  = Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_ALREADY_CONFIRMED);
                $this->flash->add('info', $msg);
                $this->redirect('/coding-blog');
                return;
            }

            $msg = Logger::logCodeAndGetMessage($channel, 'error', $code, ['email' => $email]);
            $this->flash->add('error', $msg);
            $this->redirect('/coding-blog/resend-confirmation');
            return;
        }

        // Succès : on reste sur le formulaire avec un message de succès
        $msg = Logger::logCodeAndGetMessage($channel, 'info', ErrorCode::AUTH_RESEND_EMAIL_SENT, [
            'email' => $email,
        ]);
        $this->flash->add('success', $msg);
        $this->redirect('/coding-blog/resend-confirmation');
    }
}
