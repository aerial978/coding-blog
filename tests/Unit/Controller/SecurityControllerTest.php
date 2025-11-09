<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Security;

use App\Controller\SecurityController;
use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\FormId;
use App\Core\View;
use App\Http\Request;
use App\Security\Contract\CsrfTokenInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Exception interne pour interrompre proprement le flux avant redirect()
 */
final class TestAbortControllerFlow extends \RuntimeException
{
}

final class SecurityControllerTest extends TestCase
{
    /** @var MockObject&View */
    private $view;
    /** @var MockObject&SecurityServiceInterface */
    private $securityService;
    /** @var MockObject&Request */
    private $request;
    /** @var MockObject&FlashInterface */
    private $flash;
    /** @var MockObject&CsrfTokenInterface */
    private $csrf;
    protected function setUp(): void
    {
        $this->view            = $this->createMock(View::class);
        $this->securityService = $this->createMock(SecurityServiceInterface::class);
        $this->request         = $this->createMock(Request::class);
        $this->flash           = $this->createMock(FlashInterface::class);
        $this->csrf            = $this->createMock(CsrfTokenInterface::class);
        $_GET                  = [];
        $_SERVER               = [];
    }

    private function makeController(): SecurityController
    {
        return new SecurityController($this->view, $this->securityService, $this->request, $this->flash, $this->csrf);
    }

    // --------------------------------------------------------------------
    // 1) REGISTER (GET)
    // --------------------------------------------------------------------
    public function test_register_get_renders_form_with_csrf_and_old(): void
    {
        $this->request->method('getMethod')->willReturn('GET');
        $this->flash->method('take')->willReturnMap([
            ['old', [], ['username' => 'olduser', 'email' => 'old@mail.test']],
            ['register_state', null, null],
        ]);
        $this->csrf->expects($this->once())
            ->method('generateToken')
            ->with(FormId::REGISTER)
            ->willReturn('csrf123');
        $this->view->expects($this->once())
            ->method('render')
            ->with('security/register.html.twig', $this->callback(fn (array $ctx) =>
                    ($ctx['csrf_token'] ?? null) === 'csrf123'
                    && ($ctx['mode'] ?? null)    === 'form'
                    && isset($ctx['old'])))
            ->willReturn('HTML');
        $controller = $this->makeController();
        // Capture la sortie HTML pour ne pas polluer PHPUnit
        ob_start();
        $controller->register();
        ob_end_clean();
        $this->addToAssertionCount(1);
    }

    // --------------------------------------------------------------------
    // 2) REGISTER (POST) — succès
    // --------------------------------------------------------------------
    public function test_register_post_success_puts_state(): void
    {
        $this->request->method('getMethod')->willReturn('POST');
        $form = ['username' => 'john', 'email' => 'john@test.com', 'password' => 'pwd'];
        $this->request->method('request')->willReturn($form);
        $this->securityService
            ->expects($this->once())
            ->method('register')
            ->with($form)
            ->willReturn(['ok' => true]);
        $this->flash
            ->expects($this->once())
            ->method('put')
            ->with('register_state', ['email' => 'john@test.com'])
            ->willThrowException(new TestAbortControllerFlow());
        $controller = $this->makeController();
        try {
            $controller->register();
            $this->fail('Flow should have been aborted before redirect');
        } catch (TestAbortControllerFlow) {
            $this->addToAssertionCount(1);
        }
    }

    // --------------------------------------------------------------------
    // 3) REGISTER (POST) — erreurs validation
    // --------------------------------------------------------------------
    public function test_register_post_with_errors_flashes_and_puts_old(): void
    {
        $this->request->method('getMethod')->willReturn('POST');
        $form = ['username' => '', 'email' => 'bad', 'password' => ''];
        $this->request->method('request')->willReturn($form);
        $this->securityService
            ->method('register')
            ->willReturn(['errors' => [ErrorCode::AUTH_EMAIL_INVALID]]);
        $this->flash->expects($this->atLeastOnce())
            ->method('add')
            ->with($this->isType('string'), $this->isType('string'));
        $this->flash->expects($this->once())
            ->method('put')
            ->with('old', $this->callback(fn ($old) =>
                isset($old['username'], $old['email'])))
            ->willThrowException(new TestAbortControllerFlow());
        $controller = $this->makeController();
        try {
            $controller->register();
            $this->fail('Flow should have been aborted');
        } catch (TestAbortControllerFlow) {
            $this->addToAssertionCount(1);
        }
    }

    // --------------------------------------------------------------------
    // 4) CONFIRM ACCOUNT — token manquant
    // --------------------------------------------------------------------
    public function test_confirm_missing_token_flashes_error(): void
    {
        $_GET = [];
        $this->flash->expects($this->once())
            ->method('add')
            ->with('error', $this->isType('string'))
            ->willThrowException(new TestAbortControllerFlow());
        $controller = $this->makeController();
        try {
            $controller->confirmAccount();
            $this->fail('Flow should have been aborted');
        } catch (TestAbortControllerFlow) {
            $this->addToAssertionCount(1);
        }
    }

    // --------------------------------------------------------------------
    // 5) CONFIRM ACCOUNT — succès nominal
    // --------------------------------------------------------------------
    public function test_confirm_success_flashes_success(): void
    {
        $_GET = ['token' => 'abc'];
        $this->securityService
            ->expects($this->once())
            ->method('confirmAccount')
            ->with('abc')
            ->willReturn(['error' => null]);
        $this->flash->expects($this->once())
            ->method('add')
            ->with('success', $this->isType('string'))
            ->willThrowException(new TestAbortControllerFlow());
        $controller = $this->makeController();
        try {
            $controller->confirmAccount();
            $this->fail('Flow should have been aborted');
        } catch (TestAbortControllerFlow) {
            $this->addToAssertionCount(1);
        }
    }

    // --------------------------------------------------------------------
    // 6) RESEND (GET)
    // --------------------------------------------------------------------
    public function test_resend_get_renders_form_with_csrf(): void
    {
        $this->request->method('getMethod')->willReturn('GET');
        $this->flash->method('take')->willReturnMap([
            ['old', [], []],
        ]);
        $this->csrf->expects($this->once())
            ->method('generateToken')
            ->with(FormId::RESEND_CONFIRM)
            ->willReturn('csrf-resend');
        $this->view->expects($this->once())
            ->method('render')
            ->with('security/resend-confirmation.html.twig', $this->callback(fn (array $ctx) =>
                    ($ctx['csrf_token'] ?? null) === 'csrf-resend'))
            ->willReturn('HTML');
        $controller = $this->makeController();
        // Capture la sortie HTML pour éviter affichage
        ob_start();
        $controller->resendConfirmation();
        ob_end_clean();
        $this->addToAssertionCount(1);
    }

    // --------------------------------------------------------------------
    // 7) RESEND (POST) — déjà confirmé
    // --------------------------------------------------------------------
    public function test_resend_post_already_confirmed_flashes_info(): void
    {
        $this->request->method('getMethod')->willReturn('POST');
        $this->request->method('request')->willReturn(['email' => 'john@test.com']);
        $this->securityService
            ->expects($this->once())
            ->method('resendConfirmation')
            ->with('john@test.com')
            ->willReturn(['error' => ErrorCode::AUTH_ALREADY_CONFIRMED]);
        $this->flash->expects($this->once())
            ->method('add')
            ->with('info', $this->isType('string'))
            ->willThrowException(new TestAbortControllerFlow());
        $controller = $this->makeController();
        try {
            $controller->resendConfirmation();
            $this->fail('Flow should have been aborted');
        } catch (TestAbortControllerFlow) {
            $this->addToAssertionCount(1);
        }
    }

    // --------------------------------------------------------------------
    // 8) RESEND (POST) — succès nominal
    // --------------------------------------------------------------------
    public function test_resend_post_success_generic_flashes_success(): void
    {
        $this->request->method('getMethod')->willReturn('POST');
        $this->request->method('request')->willReturn(['email' => 'john@test.com']);
        $this->securityService
            ->expects($this->once())
            ->method('resendConfirmation')
            ->with('john@test.com')
            ->willReturn([]);
        // succès

        $this->flash->expects($this->once())
            ->method('add')
            ->with('success', $this->isType('string'))
            ->willThrowException(new TestAbortControllerFlow());
        $controller = $this->makeController();
        try {
            $controller->resendConfirmation();
            $this->fail('Flow should have been aborted');
        } catch (TestAbortControllerFlow) {
            $this->addToAssertionCount(1);
        }
    }

    // --------------------------------------------------------------------
    // REGISTER (POST) — exception technique pendant SecurityService::register()
    // Couvre le catch + handleRegisterTechnicalError()
    // --------------------------------------------------------------------
    public function test_register_post_throws_exception_adds_flash_and_puts_old(): void
    {
        $this->request->method('getMethod')->willReturn('POST');
        $form = ['username' => 'john', 'email' => 'john@test.com'];
        $this->request->method('request')->willReturn($form);
        // Simule une panne du service
        $this->securityService
            ->method('register')
            ->willThrowException(new \RuntimeException('DB down'));
        // handleRegisterTechnicalError() fait: flash->add(...); flash->put('old', ...); redirect()
        $this->flash->expects($this->once())
            ->method('add')
            ->with('error', $this->isType('string'));
        // On coupe le flux AVANT redirect() pour ne pas faire planter PHPUnit
        $this->flash->expects($this->once())
            ->method('put')
            ->with('old', ['username' => 'john', 'email' => 'john@test.com'])
            ->willThrowException(new TestAbortControllerFlow());
        $controller = $this->makeController();
        try {
            $controller->register();
            $this->fail('Le flux aurait dû être interrompu avant redirect()');
        } catch (TestAbortControllerFlow) {
            $this->addToAssertionCount(1);
        }
    }

    // --------------------------------------------------------------------
    // REGISTER (POST) — AUTH_CONFIRM_EMAIL_SEND_FAILED
    // Couvre la branche spéciale dans handleRegisterOutcome()
    // --------------------------------------------------------------------
    public function test_register_post_email_send_failed_redirects_to_resend_and_puts_old_email(): void
    {
        $this->request->method('getMethod')->willReturn('POST');
        $form = ['username' => 'john', 'email' => 'john@test.com'];
        $this->request->method('request')->willReturn($form);
        $this->securityService
            ->method('register')
            ->willReturn([
                'errors' => [ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED],
            ]);
        // Ajout du flash d'erreur spécifique
        $this->flash->expects($this->once())
            ->method('add')
            ->with('error', $this->isType('string'));
        // La branche met uniquement l'email dans "old", puis redirect('/resend-confirmation')
        $this->flash->expects($this->once())
            ->method('put')
            ->with('old', ['email' => 'john@test.com'])
            ->willThrowException(new TestAbortControllerFlow());
        $controller = $this->makeController();
        try {
            $controller->register();
            $this->fail('Le flux aurait dû être interrompu avant redirect()');
        } catch (TestAbortControllerFlow) {
            $this->addToAssertionCount(1);
        }
    }

    // --------------------------------------------------------------------
    // CONFIRM (GET) — le service lève une exception → catch + redirect('/coding-blog')
    // --------------------------------------------------------------------
    public function test_confirm_service_throws_adds_error_and_redirects_home(): void
    {
        $_GET = ['token' => 'tok'];
        $this->securityService
            ->method('confirmAccount')
            ->willThrowException(new \RuntimeException('boom'));
        $this->flash->expects($this->once())
            ->method('add')
            ->with('error', $this->isType('string'))
            ->willThrowException(new TestAbortControllerFlow());
        $controller = $this->makeController();
        try {
            $controller->confirmAccount();
            $this->fail('Flow should have been aborted before redirect');
        } catch (TestAbortControllerFlow) {
            $this->addToAssertionCount(1);
        }
    }

    // --------------------------------------------------------------------
    // CONFIRM (GET) — token invalide expiré → erreur + redirect('/resend-confirmation')
    // --------------------------------------------------------------------
    public function test_confirm_invalid_token_expired_redirects_to_resend(): void
    {
        $_GET = ['token' => 'tok'];
        $this->securityService
            ->method('confirmAccount')
            ->willReturn(['error' => ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, 'reason' => 'expired']);
        $this->flash->expects($this->once())
            ->method('add')
            ->with('error', $this->isType('string'))
            ->willThrowException(new TestAbortControllerFlow());
        $controller = $this->makeController();
        try {
            $controller->confirmAccount();
            $this->fail('Flow should have been aborted');
        } catch (TestAbortControllerFlow) {
            $this->addToAssertionCount(1);
        }
    }

    // --------------------------------------------------------------------
    // CONFIRM (GET) — token invalide not_found → erreur + redirect('/resend-confirmation')
    // --------------------------------------------------------------------
    public function test_confirm_invalid_token_not_found_redirects_to_resend(): void
    {
        $_GET = ['token' => 'tok'];
        $this->securityService
            ->method('confirmAccount')
            ->willReturn(['error' => ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, 'reason' => 'not_found']);
        $this->flash->expects($this->once())
            ->method('add')
            ->with('error', $this->isType('string'))
            ->willThrowException(new TestAbortControllerFlow());
        $controller = $this->makeController();
        try {
            $controller->confirmAccount();
            $this->fail('Flow should have been aborted');
        } catch (TestAbortControllerFlow) {
            $this->addToAssertionCount(1);
        }
    }

    // --------------------------------------------------------------------
    // CONFIRM (GET) — token déjà utilisé → info + redirect('/coding-blog')
    // --------------------------------------------------------------------
    public function test_confirm_token_used_redirects_home_with_info(): void
    {
        $_GET = ['token' => 'tok'];
        $this->securityService
            ->method('confirmAccount')
            ->willReturn(['error' => ErrorCode::AUTH_CONFIRM_TOKEN_USED]);
        $this->flash->expects($this->once())
            ->method('add')
            ->with('info', $this->isType('string'))
            ->willThrowException(new TestAbortControllerFlow());
        $controller = $this->makeController();
        try {
            $controller->confirmAccount();
            $this->fail('Flow should have been aborted');
        } catch (TestAbortControllerFlow) {
            $this->addToAssertionCount(1);
        }
    }

    // --------------------------------------------------------------------
    // CONFIRM (GET) — déjà confirmé → info + redirect('/coding-blog')
    // --------------------------------------------------------------------
    public function test_confirm_already_confirmed_redirects_home_with_info(): void
    {
        $_GET = ['token' => 'tok'];
        $this->securityService
            ->method('confirmAccount')
            ->willReturn(['error' => ErrorCode::AUTH_ALREADY_CONFIRMED]);
        $this->flash->expects($this->once())
            ->method('add')
            ->with('info', $this->isType('string'))
            ->willThrowException(new TestAbortControllerFlow());
        $controller = $this->makeController();
        try {
            $controller->confirmAccount();
            $this->fail('Flow should have been aborted');
        } catch (TestAbortControllerFlow) {
            $this->addToAssertionCount(1);
        }
    }

    // --------------------------------------------------------------------
    // CONFIRM (GET) — fallback technique (code non vide inattendu) → erreur + redirect('/coding-blog')
    // --------------------------------------------------------------------
    public function test_confirm_unknown_error_code_triggers_fallback_technical_error(): void
    {
        $_GET = ['token' => 'tok'];
        // Code non vide et non géré par les branches ci-dessus → fallback
        $this->securityService
            ->method('confirmAccount')
            ->willReturn(['error' => 'SOME_OTHER_CODE']);
        $this->flash->expects($this->once())
            ->method('add')
            ->with('error', $this->isType('string'))
            ->willThrowException(new TestAbortControllerFlow());
        $controller = $this->makeController();
        try {
            $controller->confirmAccount();
            $this->fail('Flow should have been aborted');
        } catch (TestAbortControllerFlow) {
            $this->addToAssertionCount(1);
        }
    }

    // --------------------------------------------------------------------
    // RESEND (POST) — le service lève une exception → catch + redirect('/resend-confirmation')
    // --------------------------------------------------------------------
    public function test_resend_post_service_throws_adds_error_and_redirects_to_resend(): void
    {
        $this->request->method('getMethod')->willReturn('POST');
        $this->request->method('request')->willReturn(['email' => 'john@test.com']);
        // Simule une panne dans le service
        $this->securityService
            ->method('resendConfirmation')
            ->willThrowException(new \RuntimeException('boom'));
        // Le contrôleur doit flasher une erreur puis rediriger.
        // On intercepte le flux en faisant lever notre exception de test sur add()
        $this->flash->expects($this->once())
            ->method('add')
            ->with('error', $this->isType('string'))
            ->willThrowException(new TestAbortControllerFlow());
        $controller = $this->makeController();
        try {
            $controller->resendConfirmation();
            $this->fail('Le flux aurait dû être interrompu avant redirect()');
        } catch (TestAbortControllerFlow) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_register_get_with_register_state_obfuscates_email(): void
    {
        // On force la méthode en GET
        $this->request->method('getMethod')->willReturn('GET');
        // Le flash "register_state" contient un email -> le contrôleur doit passer en mode "check_email"
        $this->flash->method('take')->willReturnMap([
            ['old', [], []],
            ['register_state', null, ['email' => 'john.doe@test.com']],
        ]);
        // CSRF attendu dans le contexte rendu
        $this->csrf->expects($this->once())
            ->method('generateToken')
            ->with(FormId::REGISTER)
            ->willReturn('csrf123');
        // On vérifie le rendu : mode = check_email et email obfusqué
        $this->view->expects($this->once())
            ->method('render')
            ->with('security/register.html.twig', $this->callback(function (array $ctx): bool {

                return ($ctx['mode'] ?? null)       === 'check_email'
                    && ($ctx['csrf_token'] ?? null) === 'csrf123'
                    && array_key_exists('obfuscated_email', $ctx)
                    // j***@test.com attendu (première lettre, *** puis domaine)
                    && $ctx['obfuscated_email'] === 'j***@test.com';
            }))
            ->willReturn('HTML');
        $controller = $this->makeController();
        // On capture la sortie pour ne pas polluer PHPUnit
        ob_start();
        $controller->register();
        ob_end_clean();
        $this->addToAssertionCount(1);
    }

    public function test_register_post_with_errors_uses_old_from_service_result(): void
    {
        // On force un POST sur /register
        $this->request->method('getMethod')->willReturn('POST');
        $this->request->method('request')->willReturn([
            'username' => 'orig',
            'email'    => 'orig@test.com',
            'password' => 'x',
        ]);
        // Le service renvoie une erreur + un "old" explicite
        $serviceOld = ['username' => 'u1', 'email' => 'e1@test.com'];
        $this->securityService
            ->method('register')
            ->willReturn([
                'errors' => [ErrorCode::AUTH_EMAIL_INVALID],
                'old'    => $serviceOld,
            ]);
        // Le contrôleur flashe au moins une erreur…
        $this->flash->expects($this->atLeastOnce())
            ->method('add')
            ->with('error', $this->isType('string'));
        // …et doit reprendre EXACTEMENT l'ancien "old" fourni par le service
        $this->flash->expects($this->once())
            ->method('put')
            ->with('old', $serviceOld)
            ->willThrowException(new TestAbortControllerFlow());
        // coupe le flux avant redirect()

        $controller = $this->makeController();
        try {
            $controller->register();
            $this->fail('Le flux aurait dû être interrompu avant redirect()');
        } catch (TestAbortControllerFlow) {
            $this->addToAssertionCount(1);
        }
    }

    // --------------------------------------------------------------------
    // RESEND (POST) — erreur générique (≠ already confirmed)
    // --------------------------------------------------------------------
    public function test_resend_post_generic_error_flashes_error_and_redirects_to_resend(): void
    {
        // POST /resend-confirmation
        $this->request->method('getMethod')->willReturn('POST');
        $this->request->method('request')->willReturn(['email' => 'john@test.com']);
        // Le service renvoie un code d'erreur ≠ AUTH_ALREADY_CONFIRMED
        $this->securityService
            ->expects($this->once())
            ->method('resendConfirmation')
            ->with('john@test.com')
            ->willReturn(['error' => ErrorCode::AUTH_TECHNICAL_ERROR]);
        // Le contrôleur doit flasher une erreur, puis rediriger vers /resend-confirmation
        // On coupe le flux juste après le flash pour éviter le redirect()
        $this->flash->expects($this->once())
            ->method('add')
            ->with('error', $this->isType('string'))
            ->willThrowException(new TestAbortControllerFlow());
        $controller = $this->makeController();
        try {
            $controller->resendConfirmation();
            $this->fail('Le flux aurait dû être interrompu avant redirect()');
        } catch (TestAbortControllerFlow) {
            $this->addToAssertionCount(1);
        }
    }
}
