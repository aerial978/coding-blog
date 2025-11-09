<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\FormId;
use App\Core\Logger;
use App\Core\MessageManager;
use App\Core\View;
use App\Http\Request;
use App\Security\Contract\CsrfTokenInterface;
use App\Service\Security\Contract\SecurityServiceInterface;

/**
 * Handles user-facing security flows (registration, email confirmation,
 * and resending confirmation links). The controller is intentionally thin
 * and delegates the business rules to SecurityService.
 */
// ... entête et use identiques ...

final class SecurityController extends BaseController
{
    public function __construct(
        View $view,
        private SecurityServiceInterface $securityService,
        private Request $request,
        FlashInterface $flash,
        private CsrfTokenInterface $csrf,
    ) {
        parent::__construct($view, $flash);
    }

    // -------------------------
    // 1) REGISTER (GET / POST)
    // -------------------------
    public function register(): void
    {
        if ($this->isGet()) {
            $this->renderRegisterForm();
            return;
        }

        $form     = $this->request->request();
        $email    = $this->strOrEmpty($form['email']    ?? null);
        $username = $this->strOrEmpty($form['username'] ?? null);

        try {
            $result = $this->securityService->register($form);
        } catch (\Throwable $e) {
            $this->handleRegisterTechnicalError($e, $email, $username);
            return;
        }

        $this->handleRegisterOutcome($result, $email, $username);
    }

    // -------------------------
    // 2) CONFIRM ACCOUNT (GET)
    // -------------------------
    public function confirmAccount(): void
    {
        $token = $this->getQueryToken();
        if ($token === null) {
            $this->flash->add('error', Logger::logCodeAndGetMessage('auth', 'warning', ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, [
                'reason' => 'missing_or_empty',
            ]));
            $this->redirect('/coding-blog/resend-confirmation');
            return;
        }

        try {
            $result = $this->securityService->confirmAccount($token);
        } catch (\Throwable $e) {
            $this->flash->add('error', Logger::logCodeAndGetMessage('auth', 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'exception' => $e->getMessage(),
            ]));
            $this->redirect('/coding-blog');
            return;
        }

        $this->handleConfirmOutcome($result);
    }

    // ---------------------------------
    // 3) RESEND CONFIRMATION (GET/POST)
    // ---------------------------------
    public function resendConfirmation(): void
    {
        if ($this->isGet()) {
            $this->renderResendForm();
            return;
        }

        $form     = $this->request->request();
        $email    = $this->strOrEmpty($form['email'] ?? null);

        try {
            $result = $this->securityService->resendConfirmation($email);
        } catch (\Throwable $e) {
            $this->flash->add('error', Logger::logCodeAndGetMessage('auth', 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'exception' => $e->getMessage(),
                'email'     => $email,
            ]));
            $this->redirect('/coding-blog/resend-confirmation');
            return;
        }

        $this->handleResendOutcome($result, $email);
    }

    // =========================
    // ------- HELPERS ---------
    // =========================

    private function isGet(): bool
    {
        return $this->request->getMethod() === 'GET';
    }

    /**
     * @param mixed $value
     */
    private function strOrEmpty(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    // -------- REGISTER helpers --------

    private function renderRegisterForm(): void
    {
        $old   = $this->flash->take('old', []);
        $state = $this->flash->take('register_state', null);
        $mode  = $state ? 'check_email' : 'form';

        $stateEmail = is_array($state) && is_string($state['email'] ?? null) ? $state['email'] : null;
        $obfuscated = $stateEmail !== null
            ? (preg_replace('/(^.).*(@.*$)/', '$1***$2', $stateEmail) ?: $stateEmail)
            : null;

        $this->render('security/register.html.twig', $this->withFlashes([
            'title'            => 'User Registration',
            'mode'             => $mode,
            'obfuscated_email' => $obfuscated,
            'csrf_token'       => $this->csrf->generateToken(FormId::REGISTER),
            'old'              => is_array($old) ? $old : [],
        ]));
    }

    private function handleRegisterTechnicalError(\Throwable $exception, string $email, string $username): void
    {
        $this->flash->add('error', Logger::logCodeAndGetMessage('auth', 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
            'exception' => $exception->getMessage(),
            'email'     => $email ?: null,
            'username'  => $username ?: null,
        ]));
        $this->flash->put('old', ['username' => $username, 'email' => $email]);
        $this->redirect('/coding-blog/register');
    }

    /** @param array<string,mixed> $result */
    private function handleRegisterOutcome(array $result, string $email, string $username): void
    {
        $errors = $this->normalizeErrors($result['errors'] ?? null);

        if ($errors !== []) {
            if (in_array((string) ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED, $errors, true)) {
                $this->flash->add('error', Logger::logCodeAndGetMessage('auth', 'error', ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED, [
                    'email' => $email, 'username' => $username,
                ]));
                $this->flash->put('old', ['email' => $email]);
                $this->redirect('/coding-blog/resend-confirmation');
                return;
            }

            foreach ($errors as $code) {
                $this->flash->add('error', MessageManager::get($code));
            }

            $old = (isset($result['old']) && is_array($result['old']))
                ? $result['old']
                : ['username' => $username, 'email' => $email];

            $this->flash->put('old', $old);
            $this->redirect('/coding-blog/register');
            return;
        }

        if (!empty($result['ok'])) {
            $this->flash->put('register_state', ['email' => $email]);
            // @codeCoverageIgnoreStart
            Logger::logCodeAndGetMessage('auth', 'info', ErrorCode::AUTH_ACCOUNT_CONFIRMATION_SENT, [
                'email' => $email, 'username' => $username,
            ]);
            // @codeCoverageIgnoreEnd
        }

        $this->redirect('/coding-blog/register');
    }

    // -------- CONFIRM helpers --------

    private function getQueryToken(): ?string
    {
        return isset($_GET['token']) && is_string($_GET['token']) && $_GET['token'] !== ''
            ? $_GET['token']
            : null;
    }

    /** @param array<string,mixed> $result */
    private function handleConfirmOutcome(array $result): void
    {
        $code   = $this->stringify($result['error'] ?? null);
        $reason = is_string($result['reason'] ?? null) ? $result['reason'] : '';

        if ($code === '') {
            $this->flash->add('success', Logger::logCodeAndGetMessage('auth', 'info', ErrorCode::AUTH_CONFIRMATION_SUCCESS));
            $this->redirect('/coding-blog');
            return;
        }

        if ($code === (string) ErrorCode::AUTH_INVALID_CONFIRM_TOKEN && $reason === 'expired') {
            $this->flash->add('error', Logger::logCodeAndGetMessage('auth', 'warning', ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, ['reason' => 'expired']));
            $this->redirect('/coding-blog/resend-confirmation');
            return;
        }

        if ($code === (string) ErrorCode::AUTH_INVALID_CONFIRM_TOKEN && $reason === 'not_found') {
            $this->flash->add('error', Logger::logCodeAndGetMessage('auth', 'warning', ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, ['reason' => 'not_found']));
            $this->redirect('/coding-blog/resend-confirmation');
            return;
        }

        if ($code === (string) ErrorCode::AUTH_CONFIRM_TOKEN_USED) {
            $this->flash->add('info', Logger::logCodeAndGetMessage('auth', 'info', ErrorCode::AUTH_CONFIRM_TOKEN_USED));
            $this->redirect('/coding-blog');
            return;
        }

        if ($code === (string) ErrorCode::AUTH_ALREADY_CONFIRMED) {
            $this->flash->add('info', Logger::logCodeAndGetMessage('auth', 'info', ErrorCode::AUTH_ALREADY_CONFIRMED));
            $this->redirect('/coding-blog');
            return;
        }

        // Fallback technique
        $this->flash->add('error', Logger::logCodeAndGetMessage('auth', 'error', ErrorCode::AUTH_TECHNICAL_ERROR));
        $this->redirect('/coding-blog');
    }

    // -------- RESEND helpers --------

    private function renderResendForm(): void
    {
        $old = $this->flash->take('old', []);
        $this->render('security/resend-confirmation.html.twig', $this->withFlashes([
            'title'      => 'Renvoyer le lien de confirmation',
            'csrf_token' => $this->csrf->generateToken(FormId::RESEND_CONFIRM),
            'old'        => is_array($old) ? $old : [],
        ]));
    }

    /** @param array<string,mixed> $result */
    private function handleResendOutcome(array $result, string $email): void
    {
        $code = $this->stringify($result['error'] ?? null);

        if ($code === (string) ErrorCode::AUTH_ALREADY_CONFIRMED) {
            $this->flash->add('info', Logger::logCodeAndGetMessage('auth', 'info', ErrorCode::AUTH_ALREADY_CONFIRMED));
            $this->redirect('/coding-blog');
            return;
        }

        if ($code !== '') {
            $this->flash->add('error', Logger::logCodeAndGetMessage('auth', 'error', $code, ['email' => $email]));
            $this->redirect('/coding-blog/resend-confirmation');
            return;
        }

        $this->flash->add('success', Logger::logCodeAndGetMessage('auth', 'info', ErrorCode::AUTH_RESEND_EMAIL_SENT, [
            'email' => $email,
        ]));
        $this->redirect('/coding-blog/resend-confirmation');
    }

    // -------- Shared small utils --------

    /** @return list<string> */
    private function normalizeErrors(mixed $errors): array
    {
        if (!is_array($errors)) {
            return [];
        }
        $out = [];
        foreach ($errors as $value) {
            if (is_string($value) || is_int($value) || is_float($value)) {
                $out[] = (string) $value;
            }
        }
        return $out;
    }

    private function stringify(mixed $value): string
    {
        return is_string($value) || is_int($value) || is_float($value) ? (string)$value : '';
    }
}
