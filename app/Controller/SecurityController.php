<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\FormId;
use App\Core\Logger;
use App\Core\MessageManager;
use App\Core\View;
use App\Http\Contract\ResponderInterface;
use App\Http\Request;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Security\Contract\TurnstileValidatorInterface;
use App\Security\Exception\SuspiciousSubmissionException;
use App\Service\Security\Contract\SecurityServiceInterface;

final class SecurityController extends BaseController
{
    public function __construct(
        View $view,
        private SecurityServiceInterface $securityService,
        private Request $request,
        FlashInterface $flash,
        private CsrfTokenInterface $csrf,
        private HoneypotValidatorInterface $honeypot,
        private SubmissionDelayValidatorInterface $submissionDelay,
        private TurnstileValidatorInterface $turnstile,
        private ResponderInterface $responder,
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

        /** @var array<string,mixed> $form */
        $form = $this->request->request();
        $this->handleRegisterPost($form);
    }

    /**
     * Orchestrateur du POST /register.
     *
     * @param array<string,mixed> $form
     */
    private function handleRegisterPost(array $form): void
    {
        $email    = $this->strOrEmpty($form['email'] ?? null);
        $username = $this->strOrEmpty($form['username'] ?? null);

        // Honeypot (flux register : on notifie l’erreur et on redirige)
        if (!$this->assertHoneypotOrRedirectForRegister($form, $email, $username, '/coding-blog/register')) {
            return;
        }

        $contextBase = $this->buildRegisterContextBase($email, $username);

        if (!$this->assertDelayOrRedirectForRegister('register', '/coding-blog/register', $contextBase)) {
            return;
        }

        if (!$this->checkTurnstileOrRedirectForRegister($form, '/coding-blog/register', $contextBase)) {
            return;
        }

        try {
            $result = $this->securityService->register($form);
        } catch (\Throwable $e) {
            $this->handleRegisterTechnicalError($e, $email, $username);
            return;
        }

        $this->handleRegisterOutcome($result, $email, $username);
    }

    /**
     * Honeypot : pour le flux register on affiche une erreur et on redirige vers le formulaire.
     *
     * @param array<string,mixed> $form
     */
    private function assertHoneypotOrRedirectForRegister(
        array $form,
        string $email,
        string $username,
        string $redirectPath
    ): bool {
        try {
            $this->honeypot->assertClean($form);
            return true;
        } catch (SuspiciousSubmissionException $e) {
            $this->flash->add('error', Logger::logCodeAndGetMessage(
                'auth',
                'warning',
                ErrorCode::AUTH_TECHNICAL_ERROR,
                [
                    'reason'   => 'honeypot',
                    'email'    => $email ?: null,
                    'username' => $username ?: null,
                ]
            ));
            $this->responder->redirect($redirectPath);
            return false;
        }
    }

    /**
     * Construit le contexte de base pour logs/flash.
     *
     * @return array<string,mixed>
     */
    private function buildRegisterContextBase(string $email, string $username): array
    {
        return [
            'email'    => $email ?: null,
            'username' => $username ?: null,
        ];
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

    // ---------------------------------
    // 3) RESEND CONFIRMATION (GET/POST)
    // ---------------------------------
    public function resendConfirmation(): void
    {
        if ($this->isGet()) {
            $this->renderResendForm();
            return;
        }

        $this->handleResendConfirmationPost();
    }

    private function handleResendConfirmationPost(): void
    {
        $form  = $this->request->request();
        $email = $this->strOrEmpty($form['email'] ?? null);

        // Honeypot (politique resend = succès silencieux)
        if (!$this->assertHoneypotOrSilentSuccess($form, $email, '/coding-blog/resend-confirmation')) {
            return;
        }

        $contextBase = ['email' => $email ?: null];

        if (!$this->assertDelayOrRedirectForResend('resend_confirm', '/coding-blog/resend-confirmation', $contextBase)) {
            return;
        }

        $result = $this->callResendConfirmationSafely($email);
        if ($result === null) {
            $this->responder->redirect('/coding-blog/resend-confirmation');
            return;
        }

        $this->handleResendOutcome($result, $email);
    }

    /**
     * @param array<string,mixed> $form
     */
    private function assertHoneypotOrSilentSuccess(array $form, string $email, string $redirectPath): bool
    {
        try {
            $this->honeypot->assertClean($form);
            return true;
        } catch (SuspiciousSubmissionException $e) {
            $this->flash->add('success', Logger::logCodeAndGetMessage(
                'auth',
                'warning',
                ErrorCode::AUTH_RESEND_EMAIL_SENT,
                ['reason' => 'honeypot', 'email' => $email ?: null]
            ));
            $this->responder->redirect($redirectPath);
            return false;
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function callResendConfirmationSafely(string $email): ?array
    {
        try {
            return $this->securityService->resendConfirmation($email);
        } catch (\Throwable $e) {
            $this->flash->add('error', Logger::logCodeAndGetMessage('auth', 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'exception' => $e->getMessage(),
                'email'     => $email,
            ]));
            return null;
        }
    }

    // -------------------------
    // 4) LOGIN (GET / POST)
    // -------------------------
    public function login(): void
    {
        if ($this->isGet()) {
            $this->renderLoginForm();
            return;
        }

        // POST placeholder (logic implemented later)
        $this->responder->redirect('/coding-blog/login');
    }

    // -------------------------
    // 5) LOGOUT (POST)
    // -------------------------
    public function logout(): void
    {
        // POST placeholder (logic implemented later)
        $this->responder->redirect('/coding-blog');
    }

    // ----------------------------------
    // 6) FORGOT PASSWORD (GET / POST)
    // ----------------------------------
    public function forgotPassword(): void
    {
        if ($this->isGet()) {
            $this->renderForgotPasswordForm();
            return;
        }

        // POST placeholder (logic implemented later)
        $this->responder->redirect('/coding-blog/forgot-password');
    }

    // ----------------------------------
    // 7) RESET PASSWORD (GET / POST)
    // ----------------------------------
    public function resetPassword(): void
    {
        if ($this->isGet()) {
            $this->renderResetPasswordForm();
            return;
        }

        // POST placeholder (logic implemented later)
        $this->responder->redirect('/coding-blog/reset-password');
    }


    // =========================
    // ------- HELPERS ---------
    // =========================

    private function isGet(): bool
    {
        return $this->request->getMethod() === 'GET';
    }

    private function strOrEmpty(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    // -------- REGISTER helpers --------

    /**
     * Orchestrateur minimal, sans branche métier.
     */
    private function renderRegisterForm(): void
    {
        [$old, $state] = $this->consumeRegisterFlashes();
        $this->markRegisterStartIfEmptyOld($old);

        $mode              = $this->determineRegisterMode($state);
        $obfuscatedEmail   = $this->obfuscateEmailFromState($state);
        $turnstileSiteKey  = $this->readTurnstileSiteKey();

        $viewData = $this->buildRegisterViewModel(
            $mode,
            $obfuscatedEmail,
            $old,
            $turnstileSiteKey
        );

        $this->responder->render(
            'security/register.html.twig',
            $this->withFlashes($viewData)
        );
    }

    /**
     * @return array{0:mixed,1:mixed} [old, state]
     */
    private function consumeRegisterFlashes(): array
    {
        $old   = $this->flash->take('old', []);
        $state = $this->flash->take('register_state', null);

        return [$old, $state];
    }

    /**
     * Marque le début de remplissage du formulaire si aucun "old" n’existe.
     */
    private function markRegisterStartIfEmptyOld(mixed $old): void
    {
        if (empty($old)) {
            $this->submissionDelay->markFormStart('register');
        }
    }

    /**
     * @return 'check_email'|'form'
     */
    private function determineRegisterMode(mixed $state): string
    {
        return $state ? 'check_email' : 'form';
    }

    /**
     * Obfusque l’email provenant de l’état (si présent), sinon null.
     */
    private function obfuscateEmailFromState(mixed $state): ?string
    {
        $email = (is_array($state) && is_string($state['email'] ?? null))
            ? $state['email']
            : null;

        if ($email === null) {
            return null;
        }

        // Conserve une valeur sûre si preg_replace retourne false.
        $masked = preg_replace('/(^.).*(@.*$)/', '$1***$2', $email);
        return $masked !== null ? $masked : $email;
    }

    /**
     * Lit la clé Turnstile de l’environnement de manière défensive.
     */
    private function readTurnstileSiteKey(): string
    {
        return is_string($_ENV['TURNSTILE_SITEKEY'] ?? null)
            ? trim($_ENV['TURNSTILE_SITEKEY'])
            : '';
    }

    /**
     * Construit le view-model minimal et typé pour le template.
     *
     * @return array<string,mixed>
     */
    private function buildRegisterViewModel(
        string $mode,
        ?string $obfuscatedEmail,
        mixed $old,
        string $turnstileSiteKey
    ): array {
        return [
            'title'              => 'User Registration',
            'mode'               => $mode,
            'obfuscated_email'   => $obfuscatedEmail,
            'csrf_token'         => $this->csrf->generateToken(FormId::REGISTER),
            'old'                => is_array($old) ? $old : [],
            'honeypot_name'      => $this->honeypot->fieldName(),
            'turnstile_site_key' => $turnstileSiteKey,
        ];
    }

    private function handleRegisterTechnicalError(\Throwable $exception, string $email, string $username): void
    {
        $this->flash->add('error', Logger::logCodeAndGetMessage('auth', 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
            'exception' => $exception->getMessage(),
            'email'     => $email ?: null,
            'username'  => $username ?: null,
        ]));
        $this->flash->put('old', ['username' => $username, 'email' => $email]);
        $this->responder->redirect('/coding-blog/register');
    }

    /**
     * @param array<string,mixed> $result
     */
    private function handleRegisterOutcome(array $result, string $email, string $username): void
    {
        $errors = $this->normalizeErrors($result['errors'] ?? null);

        if ($this->handleRegisterErrorsIfAny($errors, $result, $email, $username)) {
            return;
        }

        $this->handleRegisterSuccessFlag($result, $email, $username);
        $this->responder->redirect('/coding-blog/register');
    }

    /**
     * @param list<string> $errors
     * @param array<string,mixed> $result
     */
    private function handleRegisterErrorsIfAny(array $errors, array $result, string $email, string $username): bool
    {
        if ($errors === []) {
            return false;
        }

        if (in_array((string) ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED, $errors, true)) {
            $this->flash->add('error', Logger::logCodeAndGetMessage('auth', 'error', ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED, [
                'email'     => $email,
                'username'  => $username,
            ]));
            $this->flash->put('old', ['email' => $email]);
            $this->responder->redirect('/coding-blog/resend-confirmation');
            return true;
        }

        foreach ($errors as $code) {
            $this->flash->add('error', MessageManager::get($code));
        }

        $old = (isset($result['old']) && is_array($result['old']))
            ? $result['old']
            : ['username' => $username, 'email' => $email];

        $this->flash->put('old', $old);
        $this->responder->redirect('/coding-blog/register');
        return true;
    }

    /**
     * @param array<string,mixed> $result
     */
    private function handleRegisterSuccessFlag(array $result, string $email, string $username): void
    {
        if (!empty($result['ok'])) {
            $this->flash->put('register_state', ['email' => $email]);
            // @codeCoverageIgnoreStart
            Logger::logCodeAndGetMessage('auth', 'info', ErrorCode::AUTH_ACCOUNT_CONFIRMATION_SENT, [
                'email'     => $email,
                'username'  => $username,
            ]);
            // @codeCoverageIgnoreEnd
        }
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

    // -------- RESEND helpers --------

    private function renderResendForm(): void
    {
        $old = $this->flash->take('old', []);

        if (empty($old)) {
            $this->submissionDelay->markFormStart('resend_confirm');
        }

        $this->responder->render('security/resend-confirmation.html.twig', $this->withFlashes([
            'title'         => 'Renvoyer le lien de confirmation',
            'csrf_token'    => $this->csrf->generateToken(FormId::RESEND_CONFIRM),
            'old'           => is_array($old) ? $old : [],
            'honeypot_name' => $this->honeypot->fieldName(),
        ]));
    }

    /** @param array<string,mixed> $result */
    private function handleResendOutcome(array $result, string $email): void
    {
        $code = $this->stringify($result['error'] ?? null);

        if ($code === (string) ErrorCode::AUTH_ALREADY_CONFIRMED) {
            $this->flash->add('info', Logger::logCodeAndGetMessage('auth', 'info', ErrorCode::AUTH_ALREADY_CONFIRMED));
            $this->responder->redirect('/coding-blog');
            return;
        }

        if ($code !== '') {
            $this->flash->add('error', Logger::logCodeAndGetMessage('auth', 'error', $code, ['email' => $email]));
            $this->responder->redirect('/coding-blog/resend-confirmation');
            return;
        }

        $this->flash->add('success', Logger::logCodeAndGetMessage('auth', 'info', ErrorCode::AUTH_RESEND_EMAIL_SENT, [
            'email' => $email,
        ]));
        $this->responder->redirect('/coding-blog/resend-confirmation');
    }

    // -------- LOGIN / RECOVERY helpers --------

    private function renderLoginForm(): void
    {
        $old = $this->flash->take('old', []);

        if (empty($old)) {
            $this->submissionDelay->markFormStart('login');
        }

        $this->responder->render('security/login.html.twig', $this->withFlashes([
            'title'         => 'Login',
            'csrf_token'    => $this->csrf->generateToken(FormId::LOGIN),
            'old'           => is_array($old) ? $old : [],
            'honeypot_name' => $this->honeypot->fieldName(),
        ]));
    }

    private function renderForgotPasswordForm(): void
    {
        $old = $this->flash->take('old', []);

        if (empty($old)) {
            $this->submissionDelay->markFormStart('forgot_password');
        }

        $this->responder->render('security/forgot-password.html.twig', $this->withFlashes([
            'title'         => 'Forgot password',
            'csrf_token'    => $this->csrf->generateToken(FormId::FORGOT_PASSWORD),
            'old'           => is_array($old) ? $old : [],
            'honeypot_name' => $this->honeypot->fieldName(),
        ]));
    }

    private function renderResetPasswordForm(): void
    {
        $old = $this->flash->take('old', []);

        if (empty($old)) {
            $this->submissionDelay->markFormStart('reset_password');
        }

        // Le token sera géré plus tard (GET query + validation + erreurs),
        // mais on peut déjà le passer au template de manière neutre.
        $token = $this->getQueryToken();

        $this->responder->render('security/reset-password.html.twig', $this->withFlashes([
            'title'         => 'Reset password',
            'csrf_token'    => $this->csrf->generateToken(FormId::RESET_PASSWORD),
            'old'           => is_array($old) ? $old : [],
            'honeypot_name' => $this->honeypot->fieldName(),
            'token'         => $token,
        ]));
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
        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : '';
    }

    /**
     * @param 'success'|'error'|'info'|'warning' $flashType
     * @param array<string, array<int|string, mixed>|bool|float|int|string|\Stringable|null> $logCtx
     */
    private function flashAndRedirect(string $flashType, string $errorCode, array $logCtx, string $redirectPath): bool
    {
        $this->flash->add($flashType, Logger::logCodeAndGetMessage('auth', 'warning', $errorCode, $logCtx));
        $this->responder->redirect($redirectPath);
        return false;
    }

    /**
     * @param array<string,mixed> $contextBase
     */
    private function assertDelayOrRedirectForRegister(string $formId, string $redirectPath, array $contextBase): bool
    {
        try {
            $this->submissionDelay->assertDelayPassed($formId);
            return true;
        } catch (SuspiciousSubmissionException $e) {
            $reason  = $e->getReason();
            $context = $e->getContext();

            $ctx = $contextBase + [
                'form'    => $context['form']    ?? $formId,
                'elapsed' => $context['elapsed'] ?? null,
                'min'     => $context['min']     ?? null,
                'max'     => $context['max']     ?? null,
            ];

            $logCtx = $this->normalizeLogContext($ctx + ['reason' => $reason]);

            $errorCode = match ($reason) {
                'max_delay_exceeded' => ErrorCode::AUTH_FORM_EXPIRED,
                default              => ErrorCode::AUTH_TECHNICAL_ERROR, // inclut min_delay_not_met + fallback
            };

            return $this->flashAndRedirect('error', $errorCode, $logCtx, $redirectPath);
        }
    }

    /**
     * @param array<string,mixed> $contextBase
     */
    private function assertDelayOrRedirectForResend(string $formId, string $redirectPath, array $contextBase): bool
    {
        try {
            $this->submissionDelay->assertDelayPassed($formId);
            return true;
        } catch (SuspiciousSubmissionException $e) {
            return $this->handleResendDelayViolation($e, $formId, $redirectPath, $contextBase);
        }
    }

    /**
     * @param array<string,mixed> $contextBase
     */
    private function handleResendDelayViolation(
        SuspiciousSubmissionException $exception,
        string $formId,
        string $redirectPath,
        array $contextBase
    ): bool {
        $reason  = $exception->getReason();
        $context = $exception->getContext();

        $ctx = $contextBase + [
            'form'    => $context['form']    ?? $formId,
            'elapsed' => $context['elapsed'] ?? null,
            'min'     => $context['min']     ?? null,
            'max'     => $context['max']     ?? null,
        ];

        $logCtx = $this->normalizeLogContext($ctx + ['reason' => $reason]);

        // Politique resend : succès silencieux sauf form expired
        [$flashType, $errorCode] = $this->resolveResendDelayPolicy($reason);

        return $this->flashAndRedirect($flashType, $errorCode, $logCtx, $redirectPath);
    }

    /**
     * @return array{0:'success'|'error',1:string}
     */
    private function resolveResendDelayPolicy(string $reason): array
    {
        return match ($reason) {
            'max_delay_exceeded' => ['error',   ErrorCode::AUTH_FORM_EXPIRED],
            default              => ['success', ErrorCode::AUTH_RESEND_EMAIL_SENT],
        };
    }

    /**
     * @param array<string,mixed> $form
     * @param array<string,mixed> $contextBase
     */
    private function checkTurnstileOrRedirectForRegister(array $form, string $redirectPath, array $contextBase): bool
    {
        $token = $this->strOrEmpty($form['cf-turnstile-response'] ?? null);
        $ip    = (isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR']))
            ? $_SERVER['REMOTE_ADDR']
            : null;

        if ($this->turnstile->validate($token, $ip)) {
            return true;
        }

        return $this->handleRegisterTurnstileFailure($redirectPath, $contextBase);
    }

    /**
     * @param array<string,mixed> $contextBase
     */
    private function handleRegisterTurnstileFailure(string $redirectPath, array $contextBase): bool
    {
        $resp = $this->turnstile->getLastResponse();

        $ctx = $contextBase + [
            'reason'    => 'turnstile_failed',
            'cf_errors' => is_array($resp['error-codes'] ?? null) ? $resp['error-codes'] : null,
        ];

        $logCtx = $this->normalizeLogContext($ctx);

        $this->flash->add(
            'error',
            Logger::logCodeAndGetMessage('auth', 'warning', ErrorCode::AUTH_TECHNICAL_ERROR, $logCtx)
        );
        $this->responder->redirect($redirectPath);

        return false;
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string, array<int|string, mixed>|bool|float|int|string|\Stringable|null>
     */
    private function normalizeLogContext(array $context): array
    {
        $out = [];

        foreach ($context as $k => $v) {
            $out[$k] = $this->normalizeLogValue($v);
        }

        return $out;
    }

    /**
     * Normalise une valeur arbitraire en un type "loggable" sans fuite d'information.
     *
     * @return array<int|string, mixed>|bool|float|int|string|\Stringable|null
     */
    private function normalizeLogValue(mixed $value): array|bool|float|int|string|\Stringable|null
    {
        if (
            is_string($value)
            || is_int($value)
            || is_float($value)
            || is_bool($value)
            || $value === null
            || $value instanceof \Stringable
        ) {
            return $value;
        }

        if (is_array($value)) {
            /** @var array<int|string, mixed> $value */
            return $value;
        }

        if (is_object($value)) {
            return get_debug_type($value);
        }

        if (is_resource($value)) {
            return 'resource(' . get_resource_type($value) . ')';
        }

        return 'unknown';
    }
}
