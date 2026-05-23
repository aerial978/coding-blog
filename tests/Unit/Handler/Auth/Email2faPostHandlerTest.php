<?php

declare(strict_types=1);

namespace Tests\Unit\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Core\Contract\SessionInterface;
use App\Handler\Auth\Email2faPostHandler;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\Email2faPendingSessionInterface;
use App\Security\Contract\RememberMeCookieManagerInterface;
use App\Security\Guard\Contract\HoneypotGuardInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Security\Guard\Contract\SubmissionDelayGuardInterface;
use App\Service\Security\Contract\Email2faServiceInterface;
use App\Service\Security\Contract\RememberMeServiceInterface;
use App\Service\Security\Email2faService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class Email2faPostHandlerTest extends TestCase
{
    private Email2faServiceInterface&MockObject $email2faService;
    private Email2faPendingSessionInterface&MockObject $pendingSession;
    private SessionInterface&MockObject $session;
    private FlashInterface&MockObject $flash;
    private ResponderInterface&MockObject $responder;
    private CsrfTokenInterface&MockObject $csrf;
    private HoneypotGuardInterface&MockObject $honeypotGuard;
    private SubmissionDelayGuardInterface&MockObject $submissionDelayGuard;
    private RateLimitGuardInterface&MockObject $rateLimitGuard;
    private RememberMeServiceInterface&MockObject $rememberMeService;
    private RememberMeCookieManagerInterface&MockObject $rememberMeManager;

    private Email2faPostHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->email2faService      = $this->createMock(Email2faServiceInterface::class);
        $this->pendingSession       = $this->createMock(Email2faPendingSessionInterface::class);
        $this->session              = $this->createMock(SessionInterface::class);
        $this->flash                = $this->createMock(FlashInterface::class);
        $this->responder            = $this->createMock(ResponderInterface::class);
        $this->csrf                 = $this->createMock(CsrfTokenInterface::class);
        $this->honeypotGuard        = $this->createMock(HoneypotGuardInterface::class);
        $this->submissionDelayGuard = $this->createMock(SubmissionDelayGuardInterface::class);
        $this->rateLimitGuard       = $this->createMock(RateLimitGuardInterface::class);
        $this->rememberMeService    = $this->createMock(RememberMeServiceInterface::class);
        $this->rememberMeManager    = $this->createMock(RememberMeCookieManagerInterface::class);

        $this->handler = new Email2faPostHandler(
            $this->email2faService,
            $this->pendingSession,
            $this->session,
            $this->flash,
            $this->responder,
            $this->csrf,
            $this->honeypotGuard,
            $this->submissionDelayGuard,
            $this->rateLimitGuard,
            $this->rememberMeService,
            $this->rememberMeManager,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validForm(): array
    {
        return [
            'csrf_token' => 'valid-csrf-token',
            'code'       => '123456',
        ];
    }

    private function mockValidPendingSession(int $userId = 42): void
    {
        $this->pendingSession
            ->expects($this->once())
            ->method('hasPending')
            ->willReturn(true);

        $this->pendingSession
            ->expects($this->once())
            ->method('isExpired')
            ->willReturn(false);

        $this->pendingSession
            ->expects($this->once())
            ->method('getPendingUserId')
            ->willReturn($userId);
    }

    private function mockValidCsrf(): void
    {
        $this->csrf
            ->expects($this->once())
            ->method('validateToken')
            ->with('email_2fa_form', 'valid-csrf-token')
            ->willReturn(true);
    }

    private function mockPassingGuards(): void
    {
        $this->honeypotGuard
            ->expects($this->once())
            ->method('assertClean')
            ->willReturn(true);

        $this->submissionDelayGuard
            ->expects($this->once())
            ->method('assertPassed')
            ->willReturn(true);

        $this->rateLimitGuard
            ->expects($this->once())
            ->method('assertAllowed')
            ->willReturn(true);
    }

    public function testRedirectsToLoginWhenNoPendingSession(): void
    {
        $this->pendingSession
            ->expects($this->once())
            ->method('hasPending')
            ->willReturn(false);

        $this->pendingSession
            ->expects($this->once())
            ->method('clear');

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', 'Votre session de vérification a expiré. Veuillez vous reconnecter.');

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $this->handler->handle($this->validForm());
    }

    public function testRedirectsToLoginWhenPendingSessionIsExpired(): void
    {
        $this->pendingSession
            ->expects($this->once())
            ->method('hasPending')
            ->willReturn(true);

        $this->pendingSession
            ->expects($this->once())
            ->method('isExpired')
            ->willReturn(true);

        $this->pendingSession
            ->expects($this->once())
            ->method('clear');

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', 'Votre session de vérification a expiré. Veuillez vous reconnecter.');

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $this->handler->handle($this->validForm());
    }

    public function testRedirectsToLoginWhenPendingUserIdIsInvalid(): void
    {
        $this->pendingSession->method('hasPending')->willReturn(true);
        $this->pendingSession->method('isExpired')->willReturn(false);
        $this->pendingSession->method('getPendingUserId')->willReturn(null);

        $this->pendingSession
            ->expects($this->once())
            ->method('clear');

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', 'Votre session de vérification est invalide. Veuillez vous reconnecter.');

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $this->handler->handle($this->validForm());
    }

    public function testRedirectsTo2faPageWhenCsrfIsInvalid(): void
    {
        $this->mockValidPendingSession();

        $this->csrf
            ->expects($this->once())
            ->method('validateToken')
            ->with('email_2fa_form', 'invalid-token')
            ->willReturn(false);

        $this->email2faService
            ->expects($this->never())
            ->method('verifyCode');

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', 'Requête invalide. Veuillez réessayer.');

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login/2fa');

        $this->handler->handle([
            'csrf_token' => 'invalid-token',
            'code'       => '123456',
        ]);
    }

    public function testStopsWhenHoneypotGuardFails(): void
    {
        $this->mockValidPendingSession();
        $this->mockValidCsrf();

        $this->honeypotGuard
            ->expects($this->once())
            ->method('assertClean')
            ->willReturn(false);

        $this->email2faService
            ->expects($this->never())
            ->method('verifyCode');

        $this->handler->handle($this->validForm());
    }

    public function testStopsWhenSubmissionDelayGuardFails(): void
    {
        $this->mockValidPendingSession();
        $this->mockValidCsrf();

        $this->honeypotGuard
            ->expects($this->once())
            ->method('assertClean')
            ->willReturn(true);

        $this->submissionDelayGuard
            ->expects($this->once())
            ->method('assertPassed')
            ->willReturn(false);

        $this->email2faService
            ->expects($this->never())
            ->method('verifyCode');

        $this->handler->handle($this->validForm());
    }

    public function testStopsWhenRateLimitGuardFails(): void
    {
        $this->mockValidPendingSession();
        $this->mockValidCsrf();

        $this->honeypotGuard->method('assertClean')->willReturn(true);
        $this->submissionDelayGuard->method('assertPassed')->willReturn(true);

        $this->rateLimitGuard
            ->expects($this->once())
            ->method('assertAllowed')
            ->willReturn(false);

        $this->email2faService
            ->expects($this->never())
            ->method('verifyCode');

        $this->handler->handle($this->validForm());
    }

    public function testRedirectsTo2faPageWhenCodeIsInvalid(): void
    {
        $this->mockValidPendingSession();
        $this->mockValidCsrf();
        $this->mockPassingGuards();

        $this->email2faService
            ->expects($this->once())
            ->method('verifyCode')
            ->with(42, '000000')
            ->willReturn(Email2faService::VERIFY_INVALID);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', 'Le code de vérification est invalide ou expiré.');

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login/2fa');

        $this->handler->handle([
            'csrf_token' => 'valid-csrf-token',
            'code'       => '000000',
        ]);
    }

    public function testRedirectsToLoginWhenTooManyAttempts(): void
    {
        $this->mockValidPendingSession();
        $this->mockValidCsrf();
        $this->mockPassingGuards();

        $this->email2faService
            ->expects($this->once())
            ->method('verifyCode')
            ->with(42, '000000')
            ->willReturn(Email2faService::VERIFY_TOO_MANY_ATTEMPTS);

        $this->pendingSession
            ->expects($this->once())
            ->method('clear');

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', 'Trop de tentatives incorrectes. Veuillez recommencer la connexion.');

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $this->handler->handle([
            'csrf_token' => 'valid-csrf-token',
            'code'       => '000000',
        ]);
    }

    public function testSuccessfulVerificationCreatesSessionAndRedirectsHome(): void
    {
        $this->mockValidPendingSession();
        $this->mockValidCsrf();
        $this->mockPassingGuards();

        $this->email2faService
            ->expects($this->once())
            ->method('verifyCode')
            ->with(42, '123456')
            ->willReturn(Email2faService::VERIFY_SUCCESS);

        $this->pendingSession
            ->expects($this->once())
            ->method('wasRememberMeRequested')
            ->willReturn(false);

        $this->session
            ->expects($this->once())
            ->method('regenerateAndDeleteOld');

        $this->session
            ->expects($this->once())
            ->method('set')
            ->with('user', [
                'id'    => 42,
                'roles' => ['USER'],
            ]);

        $this->rememberMeService
            ->expects($this->never())
            ->method('createRememberMeToken');

        $this->pendingSession
            ->expects($this->once())
            ->method('clear');

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('success', 'Connexion réussie.');

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog');

        $this->handler->handle($this->validForm());
    }

    public function testSuccessfulVerificationCreatesRememberMeCookieWhenRequested(): void
    {
        $this->mockValidPendingSession();
        $this->mockValidCsrf();
        $this->mockPassingGuards();

        $this->email2faService
            ->method('verifyCode')
            ->willReturn(Email2faService::VERIFY_SUCCESS);

        $this->pendingSession
            ->expects($this->once())
            ->method('wasRememberMeRequested')
            ->willReturn(true);

        $this->rememberMeService
            ->expects($this->once())
            ->method('createRememberMeToken')
            ->with(42)
            ->willReturn('raw-remember-token');

        $this->rememberMeManager
            ->expects($this->once())
            ->method('createCookie')
            ->with('raw-remember-token');

        $this->session->expects($this->once())->method('regenerateAndDeleteOld');
        $this->session->expects($this->once())->method('set');
        $this->pendingSession->expects($this->once())->method('clear');

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog');

        $this->handler->handle($this->validForm());
    }

    public function testSuccessfulVerificationDoesNotCreateCookieWhenRememberMeTokenCreationFails(): void
    {
        $this->mockValidPendingSession();
        $this->mockValidCsrf();
        $this->mockPassingGuards();

        $this->email2faService
            ->method('verifyCode')
            ->willReturn(Email2faService::VERIFY_SUCCESS);

        $this->pendingSession
            ->expects($this->once())
            ->method('wasRememberMeRequested')
            ->willReturn(true);

        $this->rememberMeService
            ->expects($this->once())
            ->method('createRememberMeToken')
            ->with(42)
            ->willReturn(null);

        $this->rememberMeManager
            ->expects($this->never())
            ->method('createCookie');

        $this->session->expects($this->once())->method('regenerateAndDeleteOld');
        $this->session->expects($this->once())->method('set');
        $this->pendingSession->expects($this->once())->method('clear');

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog');

        $this->handler->handle($this->validForm());
    }
}
