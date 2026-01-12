<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\SecurityController;
use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\FormId;
use App\Core\View;
use App\Http\Contract\ResponderInterface;
use App\Http\Request;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Security\Contract\TurnstileValidatorInterface;
use App\Security\Exception\SuspiciousSubmissionException;
use App\Service\Security\Contract\SecurityServiceInterface;
use PHPUnit\Framework\TestCase;

final class SecurityControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REMOTE_ADDR']     = '1.2.3.4';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
        $_ENV['TURNSTILE_SITEKEY']  = 'sitekey';
        $_GET                       = [];
    }

    private function makeController(
        ?SecurityServiceInterface $service = null,
        ?Request $request = null,
        ?FlashInterface $flash = null,
        ?CsrfTokenInterface $csrf = null,
        ?HoneypotValidatorInterface $honeypot = null,
        ?SubmissionDelayValidatorInterface $delay = null,
        ?TurnstileValidatorInterface $turnstile = null,
        ?ResponderInterface $responder = null,
        ?View $view = null,
    ): SecurityController {
        $service   ??= $this->createMock(SecurityServiceInterface::class);
        $request   ??= $this->createMock(Request::class);
        $flash     ??= $this->createMock(FlashInterface::class);
        $csrf      ??= $this->createMock(CsrfTokenInterface::class);
        $honeypot  ??= $this->createMock(HoneypotValidatorInterface::class);
        $delay     ??= $this->createMock(SubmissionDelayValidatorInterface::class);
        $turnstile ??= $this->createMock(TurnstileValidatorInterface::class);
        $responder ??= $this->createMock(ResponderInterface::class);
        $view      ??= $this->createMock(View::class);

        return new SecurityController(
            $view,
            $service,
            $request,
            $flash,
            $csrf,
            $honeypot,
            $delay,
            $turnstile,
            $responder
        );
    }

    // ---------------------------------------------------------------------
    // REGISTER (GET)
    // ---------------------------------------------------------------------

    public function testRegisterGetRendersForm(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn('GET');

        $flash = $this->createMock(FlashInterface::class);
        $flash->method('take')->willReturnOnConsecutiveCalls([], null);

        $csrf = $this->createMock(CsrfTokenInterface::class);
        $csrf->expects(self::once())
            ->method('generateToken')
            ->with(FormId::REGISTER)
            ->willReturn('csrf123');

        $honeypot = $this->createMock(HoneypotValidatorInterface::class);
        $honeypot->expects(self::once())
            ->method('fieldName')
            ->willReturn('fax');

        $delay = $this->createMock(SubmissionDelayValidatorInterface::class);
        $delay->expects(self::once())->method('markFormStart')->with('register');

        $responder = $this->createMock(ResponderInterface::class);
        $responder->expects(self::once())
            ->method('render')
            ->with(
                'security/register.html.twig',
                self::callback(static function (array $data): bool {
                    return ($data['csrf_token'] ?? null)    === 'csrf123'
                        && ($data['honeypot_name'] ?? null) === 'fax'
                        && isset($data['turnstile_site_key']);
                })
            );
        $responder->expects(self::never())->method('redirect');

        $controller = $this->makeController(
            request: $request,
            flash: $flash,
            csrf: $csrf,
            honeypot: $honeypot,
            delay: $delay,
            responder: $responder
        );

        $controller->register();
    }

    // ---------------------------------------------------------------------
    // REGISTER (POST) - early exits + turnstile + exception
    // ---------------------------------------------------------------------

    public function testRegisterPostHoneypotTriggersRedirect(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('request')->willReturn([
            'email'    => 'a@b.com',
            'username' => 'bob',
        ]);

        $honeypot = $this->createMock(HoneypotValidatorInterface::class);
        $honeypot->method('assertClean')->willThrowException(
            new SuspiciousSubmissionException('honeypot_triggered')
        );

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::once())
            ->method('add')
            ->with('error', self::isType('string'));

        $responder = $this->createMock(ResponderInterface::class);
        $responder->expects(self::once())->method('redirect')->with('/coding-blog/register');
        $responder->expects(self::never())->method('render');

        $controller = $this->makeController(
            request: $request,
            flash: $flash,
            honeypot: $honeypot,
            responder: $responder
        );

        $controller->register();
    }

    public function testRegisterPostMinDelayNotMetRedirects(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('request')->willReturn([
            'email'    => 'a@b.com',
            'username' => 'bob',
        ]);

        $honeypot = $this->createMock(HoneypotValidatorInterface::class);
        $honeypot->method('assertClean'); // OK

        $delay = $this->createMock(SubmissionDelayValidatorInterface::class);
        $delay->method('assertDelayPassed')->willThrowException(
            new SuspiciousSubmissionException('min_delay_not_met', ['form' => 'register', 'elapsed' => 1, 'min' => 3])
        );

        $service = $this->createMock(SecurityServiceInterface::class);
        $service->expects(self::never())->method('register');

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::once())
            ->method('add')
            ->with('error', self::isType('string'));

        $responder = $this->createMock(ResponderInterface::class);
        $responder->expects(self::once())->method('redirect')->with('/coding-blog/register');

        $controller = $this->makeController(
            request: $request,
            flash: $flash,
            honeypot: $honeypot,
            delay: $delay,
            service: $service,
            responder: $responder
        );

        $controller->register();
    }

    public function testRegisterPostMaxDelayExceededRedirects(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('request')->willReturn([
            'email'    => 'a@b.com',
            'username' => 'bob',
        ]);

        $honeypot = $this->createMock(HoneypotValidatorInterface::class);
        $honeypot->method('assertClean'); // OK

        $delay = $this->createMock(SubmissionDelayValidatorInterface::class);
        $delay->method('assertDelayPassed')->willThrowException(
            new SuspiciousSubmissionException('max_delay_exceeded', ['form' => 'register', 'elapsed' => 999, 'max' => 30])
        );

        $service = $this->createMock(SecurityServiceInterface::class);
        $service->expects(self::never())->method('register');

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::once())
            ->method('add')
            ->with('error', self::isType('string'));

        $responder = $this->createMock(ResponderInterface::class);
        $responder->expects(self::once())->method('redirect')->with('/coding-blog/register');

        $controller = $this->makeController(
            request: $request,
            flash: $flash,
            honeypot: $honeypot,
            delay: $delay,
            service: $service,
            responder: $responder
        );

        $controller->register();
    }

    public function testRegisterPostTurnstileFailRedirects(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('request')->willReturn([
            'email'                 => 'a@b.com',
            'username'              => 'bob',
            'cf-turnstile-response' => 'bad-token',
        ]);

        $honeypot = $this->createMock(HoneypotValidatorInterface::class);
        $honeypot->method('assertClean');

        $delay = $this->createMock(SubmissionDelayValidatorInterface::class);
        $delay->method('assertDelayPassed');

        $turnstile = $this->createMock(TurnstileValidatorInterface::class);
        $turnstile->method('validate')->willReturn(false);
        $turnstile->method('getLastResponse')->willReturn(['error-codes' => ['invalid-input-response']]);

        $service = $this->createMock(SecurityServiceInterface::class);
        $service->expects(self::never())->method('register');

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::once())
            ->method('add')
            ->with('error', self::isType('string'));

        $responder = $this->createMock(ResponderInterface::class);
        $responder->expects(self::once())->method('redirect')->with('/coding-blog/register');

        $controller = $this->makeController(
            request: $request,
            flash: $flash,
            honeypot: $honeypot,
            delay: $delay,
            turnstile: $turnstile,
            service: $service,
            responder: $responder
        );

        $controller->register();
    }

    public function testRegisterPostServiceThrowsRedirectsAndKeepsOld(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('request')->willReturn([
            'email'                 => 'a@b.com',
            'username'              => 'bob',
            'cf-turnstile-response' => 'ok',
        ]);

        $honeypot = $this->createMock(HoneypotValidatorInterface::class);
        $honeypot->method('assertClean');

        $delay = $this->createMock(SubmissionDelayValidatorInterface::class);
        $delay->method('assertDelayPassed');

        $turnstile = $this->createMock(TurnstileValidatorInterface::class);
        $turnstile->method('validate')->willReturn(true);

        $service = $this->createMock(SecurityServiceInterface::class);
        $service->method('register')->willThrowException(new \RuntimeException('boom'));

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::once())->method('add')->with('error', self::isType('string'));
        $flash->expects(self::once())->method('put')->with('old', ['username' => 'bob', 'email' => 'a@b.com']);

        $responder = $this->createMock(ResponderInterface::class);
        $responder->expects(self::once())->method('redirect')->with('/coding-blog/register');

        $controller = $this->makeController(
            request: $request,
            flash: $flash,
            honeypot: $honeypot,
            delay: $delay,
            turnstile: $turnstile,
            service: $service,
            responder: $responder
        );

        $controller->register();
    }

    // ---------------------------------------------------------------------
    // REGISTER (POST) - outcomes (handleRegisterOutcome / handleRegisterErrorsIfAny)
    // ---------------------------------------------------------------------

    public function testRegisterPostConfirmEmailSendFailedRedirectsToResendAndStoresOldEmail(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('request')->willReturn([
            'email'                 => 'a@b.com',
            'username'              => 'bob',
            'cf-turnstile-response' => 'ok',
        ]);

        $honeypot = $this->createMock(HoneypotValidatorInterface::class);
        $honeypot->method('assertClean');

        $delay = $this->createMock(SubmissionDelayValidatorInterface::class);
        $delay->method('assertDelayPassed');

        $turnstile = $this->createMock(TurnstileValidatorInterface::class);
        $turnstile->method('validate')->willReturn(true);

        $service = $this->createMock(SecurityServiceInterface::class);
        $service->method('register')->willReturn([
            'errors' => [ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED],
            'old'    => ['email' => 'a@b.com'],
        ]);

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::once())->method('add')->with('error', self::isType('string'));
        $flash->expects(self::once())->method('put')->with('old', ['email' => 'a@b.com']);

        $responder = $this->createMock(ResponderInterface::class);
        $responder->expects(self::once())->method('redirect')->with('/coding-blog/resend-confirmation');

        $controller = $this->makeController(
            request: $request,
            flash: $flash,
            honeypot: $honeypot,
            delay: $delay,
            turnstile: $turnstile,
            service: $service,
            responder: $responder
        );

        $controller->register();
    }

    public function testRegisterPostGenericErrorsRedirectsToRegisterAndRestoresOld(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('request')->willReturn([
            'email'                 => 'a@b.com',
            'username'              => 'bob',
            'cf-turnstile-response' => 'ok',
        ]);

        $honeypot = $this->createMock(HoneypotValidatorInterface::class);
        $honeypot->method('assertClean');

        $delay = $this->createMock(SubmissionDelayValidatorInterface::class);
        $delay->method('assertDelayPassed');

        $turnstile = $this->createMock(TurnstileValidatorInterface::class);
        $turnstile->method('validate')->willReturn(true);

        $service = $this->createMock(SecurityServiceInterface::class);
        $service->method('register')->willReturn([
            'errors' => [
                ErrorCode::AUTH_USERNAME_EXISTS,
                ErrorCode::AUTH_PASSWORD_REENTER,
            ],
            'old' => ['username' => 'bob', 'email' => 'a@b.com'],
        ]);

        $flash = $this->createMock(FlashInterface::class);
        // 2 erreurs => 2 add('error', <string>)
        $flash->expects(self::exactly(2))
            ->method('add')
            ->with('error', self::isType('string'));
        $flash->expects(self::once())
            ->method('put')
            ->with('old', ['username' => 'bob', 'email' => 'a@b.com']);

        $responder = $this->createMock(ResponderInterface::class);
        $responder->expects(self::once())->method('redirect')->with('/coding-blog/register');

        $controller = $this->makeController(
            request: $request,
            flash: $flash,
            honeypot: $honeypot,
            delay: $delay,
            turnstile: $turnstile,
            service: $service,
            responder: $responder
        );

        $controller->register();
    }

    public function testRegisterPostSuccessOkRedirectsToRegisterAndStoresRegisterState(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('request')->willReturn([
            'email'                 => 'a@b.com',
            'username'              => 'bob',
            'cf-turnstile-response' => 'ok',
        ]);

        $honeypot = $this->createMock(HoneypotValidatorInterface::class);
        $honeypot->method('assertClean');

        $delay = $this->createMock(SubmissionDelayValidatorInterface::class);
        $delay->method('assertDelayPassed');

        $turnstile = $this->createMock(TurnstileValidatorInterface::class);
        $turnstile->method('validate')->willReturn(true);

        $service = $this->createMock(SecurityServiceInterface::class);
        $service->method('register')->willReturn(['ok' => true]);

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::once())
            ->method('put')
            ->with('register_state', ['email' => 'a@b.com']);

        $responder = $this->createMock(ResponderInterface::class);
        $responder->expects(self::once())->method('redirect')->with('/coding-blog/register');

        $controller = $this->makeController(
            request: $request,
            flash: $flash,
            honeypot: $honeypot,
            delay: $delay,
            turnstile: $turnstile,
            service: $service,
            responder: $responder
        );

        $controller->register();
    }

    // ---------------------------------------------------------------------
    // CONFIRM ACCOUNT
    // ---------------------------------------------------------------------

    public function testConfirmAccountMissingTokenRedirectsToResendConfirmation(): void
    {
        $_GET = []; // token absent

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::once())->method('add')->with('error', self::isType('string'));

        $responder = $this->createMock(ResponderInterface::class);
        $responder->expects(self::once())->method('redirect')->with('/coding-blog/resend-confirmation');

        $service = $this->createMock(SecurityServiceInterface::class);
        $service->expects(self::never())->method('confirmAccount');

        $controller = $this->makeController(
            service: $service,
            flash: $flash,
            responder: $responder
        );

        $controller->confirmAccount();
    }

    public function testConfirmAccountServiceThrowsRedirectsHome(): void
    {
        $_GET = ['token' => 't'];

        $service = $this->createMock(SecurityServiceInterface::class);
        $service->method('confirmAccount')->willThrowException(new \RuntimeException('boom'));

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::once())->method('add')->with('error', self::isType('string'));

        $responder = $this->createMock(ResponderInterface::class);
        $responder->expects(self::once())->method('redirect')->with('/coding-blog');

        $controller = $this->makeController(
            service: $service,
            flash: $flash,
            responder: $responder
        );

        $controller->confirmAccount();
    }

    /**
     * @dataProvider confirmOutcomesProvider
     */
    public function testConfirmAccountOutcomes(string $errorCode, string $reason, string $expectedFlashLevel, string $expectedRedirect): void
    {
        $_GET = ['token' => 't'];

        $service = $this->createMock(SecurityServiceInterface::class);
        $service->method('confirmAccount')->willReturn(array_filter([
            'error'  => $errorCode !== '' ? $errorCode : null,
            'reason' => $reason    !== '' ? $reason : null,
        ], static fn ($v) => $v !== null));

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::once())->method('add')->with($expectedFlashLevel, self::isType('string'));

        $responder = $this->createMock(ResponderInterface::class);
        $responder->expects(self::once())->method('redirect')->with($expectedRedirect);

        $controller = $this->makeController(
            service: $service,
            flash: $flash,
            responder: $responder
        );

        $controller->confirmAccount();
    }

    public static function confirmOutcomesProvider(): array
    {
        return [
            'success'           => ['', '', 'success', '/coding-blog'],
            'invalid_expired'   => [(string) ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, 'expired', 'error', '/coding-blog/resend-confirmation'],
            'invalid_not_found' => [(string) ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, 'not_found', 'error', '/coding-blog/resend-confirmation'],
            'used'              => [(string) ErrorCode::AUTH_CONFIRM_TOKEN_USED, '', 'info', '/coding-blog'],
            'already'           => [(string) ErrorCode::AUTH_ALREADY_CONFIRMED, '', 'info', '/coding-blog'],
            'technical_default' => ['some_unknown_error', '', 'error', '/coding-blog'],
        ];
    }

    // ---------------------------------------------------------------------
    // RESEND CONFIRMATION (GET)
    // ---------------------------------------------------------------------

    public function testResendConfirmationGetRendersForm(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn('GET');

        $flash = $this->createMock(FlashInterface::class);
        $flash->method('take')->willReturn([]); // old vide

        $csrf = $this->createMock(CsrfTokenInterface::class);
        $csrf->expects(self::once())
            ->method('generateToken')
            ->with(FormId::RESEND_CONFIRM)
            ->willReturn('csrf999');

        $honeypot = $this->createMock(HoneypotValidatorInterface::class);
        $honeypot->expects(self::once())->method('fieldName')->willReturn('fax');

        $delay = $this->createMock(SubmissionDelayValidatorInterface::class);
        $delay->expects(self::once())->method('markFormStart')->with('resend_confirm');

        $responder = $this->createMock(ResponderInterface::class);
        $responder->expects(self::once())
            ->method('render')
            ->with(
                'security/resend-confirmation.html.twig',
                self::callback(static function (array $data): bool {
                    return ($data['csrf_token'] ?? null)    === 'csrf999'
                        && ($data['honeypot_name'] ?? null) === 'fax';
                })
            );

        $controller = $this->makeController(
            request: $request,
            flash: $flash,
            csrf: $csrf,
            honeypot: $honeypot,
            delay: $delay,
            responder: $responder
        );

        $controller->resendConfirmation();
    }

    // ---------------------------------------------------------------------
    // RESEND CONFIRMATION (POST)
    // ---------------------------------------------------------------------

    public function testResendConfirmationPostHoneypotReturnsGenericSuccessAndRedirects(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('request')->willReturn(['email' => 'a@b.com']);

        $honeypot = $this->createMock(HoneypotValidatorInterface::class);
        $honeypot->method('assertClean')->willThrowException(new SuspiciousSubmissionException('honeypot'));

        $service = $this->createMock(SecurityServiceInterface::class);
        $service->expects(self::never())->method('resendConfirmation');

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::once())->method('add')->with('success', self::isType('string'));

        $responder = $this->createMock(ResponderInterface::class);
        $responder->expects(self::once())->method('redirect')->with('/coding-blog/resend-confirmation');

        $controller = $this->makeController(
            request: $request,
            honeypot: $honeypot,
            service: $service,
            flash: $flash,
            responder: $responder
        );

        $controller->resendConfirmation();
    }

    public function testResendConfirmationPostMinDelayNotMetInResendModeReturnsGenericSuccessAndRedirects(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('request')->willReturn(['email' => 'a@b.com']);

        $honeypot = $this->createMock(HoneypotValidatorInterface::class);
        $honeypot->method('assertClean'); // OK

        $delay = $this->createMock(SubmissionDelayValidatorInterface::class);
        $delay->method('assertDelayPassed')->willThrowException(
            new SuspiciousSubmissionException('min_delay_not_met', ['form' => 'resend_confirm', 'elapsed' => 1, 'min' => 3])
        );

        $service = $this->createMock(SecurityServiceInterface::class);
        $service->expects(self::never())->method('resendConfirmation');

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::once())->method('add')->with('success', self::isType('string'));

        $responder = $this->createMock(ResponderInterface::class);
        $responder->expects(self::once())->method('redirect')->with('/coding-blog/resend-confirmation');

        $controller = $this->makeController(
            request: $request,
            honeypot: $honeypot,
            delay: $delay,
            service: $service,
            flash: $flash,
            responder: $responder
        );

        $controller->resendConfirmation();
    }

    public function testResendConfirmationPostMaxDelayExceededRedirectsWithFormExpired(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('request')->willReturn(['email' => 'a@b.com']);

        $honeypot = $this->createMock(HoneypotValidatorInterface::class);
        $honeypot->method('assertClean'); // OK

        $delay = $this->createMock(SubmissionDelayValidatorInterface::class);
        $delay->method('assertDelayPassed')->willThrowException(
            new SuspiciousSubmissionException('max_delay_exceeded', ['form' => 'resend_confirm', 'elapsed' => 999, 'max' => 30])
        );

        $service = $this->createMock(SecurityServiceInterface::class);
        $service->expects(self::never())->method('resendConfirmation');

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::once())->method('add')->with('error', self::isType('string'));

        $responder = $this->createMock(ResponderInterface::class);
        $responder->expects(self::once())->method('redirect')->with('/coding-blog/resend-confirmation');

        $controller = $this->makeController(
            request: $request,
            honeypot: $honeypot,
            delay: $delay,
            service: $service,
            flash: $flash,
            responder: $responder
        );

        $controller->resendConfirmation();
    }

    public function testResendConfirmationPostServiceThrowsRedirectsWithError(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('request')->willReturn(['email' => 'a@b.com']);

        $honeypot = $this->createMock(HoneypotValidatorInterface::class);
        $honeypot->method('assertClean'); // OK

        $delay = $this->createMock(SubmissionDelayValidatorInterface::class);
        $delay->method('assertDelayPassed'); // OK

        $service = $this->createMock(SecurityServiceInterface::class);
        $service->method('resendConfirmation')->willThrowException(new \RuntimeException('boom'));

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::once())->method('add')->with('error', self::isType('string'));

        $responder = $this->createMock(ResponderInterface::class);
        $responder->expects(self::once())->method('redirect')->with('/coding-blog/resend-confirmation');

        $controller = $this->makeController(
            request: $request,
            honeypot: $honeypot,
            delay: $delay,
            service: $service,
            flash: $flash,
            responder: $responder
        );

        $controller->resendConfirmation();
    }

    public function testResendConfirmationPostOutcomeAlreadyConfirmedRedirectsHome(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('request')->willReturn(['email' => 'a@b.com']);

        $honeypot = $this->createMock(HoneypotValidatorInterface::class);
        $honeypot->method('assertClean'); // OK

        $delay = $this->createMock(SubmissionDelayValidatorInterface::class);
        $delay->method('assertDelayPassed'); // OK

        $service = $this->createMock(SecurityServiceInterface::class);
        $service->method('resendConfirmation')->willReturn(['error' => ErrorCode::AUTH_ALREADY_CONFIRMED]);

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::once())->method('add')->with('info', self::isType('string'));

        $responder = $this->createMock(ResponderInterface::class);
        $responder->expects(self::once())->method('redirect')->with('/coding-blog');

        $controller = $this->makeController(
            request: $request,
            honeypot: $honeypot,
            delay: $delay,
            service: $service,
            flash: $flash,
            responder: $responder
        );

        $controller->resendConfirmation();
    }

    public function testResendConfirmationPostOutcomeErrorRedirectsBack(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('request')->willReturn(['email' => 'a@b.com']);

        $honeypot = $this->createMock(HoneypotValidatorInterface::class);
        $honeypot->method('assertClean'); // OK

        $delay = $this->createMock(SubmissionDelayValidatorInterface::class);
        $delay->method('assertDelayPassed'); // OK

        $service = $this->createMock(SecurityServiceInterface::class);
        $service->method('resendConfirmation')->willReturn(['error' => ErrorCode::AUTH_TECHNICAL_ERROR]);

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::once())->method('add')->with('error', self::isType('string'));

        $responder = $this->createMock(ResponderInterface::class);
        $responder->expects(self::once())->method('redirect')->with('/coding-blog/resend-confirmation');

        $controller = $this->makeController(
            request: $request,
            honeypot: $honeypot,
            delay: $delay,
            service: $service,
            flash: $flash,
            responder: $responder
        );

        $controller->resendConfirmation();
    }

    public function testResendConfirmationPostOutcomeSuccessRedirectsBackWithSuccessFlash(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('request')->willReturn(['email' => 'a@b.com']);

        $honeypot = $this->createMock(HoneypotValidatorInterface::class);
        $honeypot->method('assertClean'); // OK

        $delay = $this->createMock(SubmissionDelayValidatorInterface::class);
        $delay->method('assertDelayPassed'); // OK

        $service = $this->createMock(SecurityServiceInterface::class);
        $service->method('resendConfirmation')->willReturn([]); // succès silencieux

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::once())->method('add')->with('success', self::isType('string'));

        $responder = $this->createMock(ResponderInterface::class);
        $responder->expects(self::once())->method('redirect')->with('/coding-blog/resend-confirmation');

        $controller = $this->makeController(
            request: $request,
            honeypot: $honeypot,
            delay: $delay,
            service: $service,
            flash: $flash,
            responder: $responder
        );

        $controller->resendConfirmation();
    }
}
