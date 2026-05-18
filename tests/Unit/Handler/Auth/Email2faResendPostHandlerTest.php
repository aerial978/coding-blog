<?php

declare(strict_types=1);

namespace Tests\Unit\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Http\Contract\ResponderInterface;
use App\Handler\Auth\Email2faResendPostHandler;
use App\Model\Contract\UserModelInterface;
use App\Model\Entity\UserEntity;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\Email2faPendingSessionInterface;
use App\Security\Guard\Contract\HoneypotGuardInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Security\Guard\Contract\SubmissionDelayGuardInterface;
use App\Service\Security\Contract\Email2faServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class Email2faResendPostHandlerTest extends TestCase
{
    private Email2faServiceInterface&MockObject $email2faService;
    private Email2faPendingSessionInterface&MockObject $pendingSession;
    private UserModelInterface&MockObject $userModel;
    private FlashInterface&MockObject $flash;
    private ResponderInterface&MockObject $responder;
    private CsrfTokenInterface&MockObject $csrf;
    private HoneypotGuardInterface&MockObject $honeypotGuard;
    private SubmissionDelayGuardInterface&MockObject $submissionDelayGuard;
    private RateLimitGuardInterface&MockObject $rateLimitGuard;

    private Email2faResendPostHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->email2faService      = $this->createMock(Email2faServiceInterface::class);
        $this->pendingSession       = $this->createMock(Email2faPendingSessionInterface::class);
        $this->userModel            = $this->createMock(UserModelInterface::class);
        $this->flash                = $this->createMock(FlashInterface::class);
        $this->responder            = $this->createMock(ResponderInterface::class);
        $this->csrf                 = $this->createMock(CsrfTokenInterface::class);
        $this->honeypotGuard        = $this->createMock(HoneypotGuardInterface::class);
        $this->submissionDelayGuard = $this->createMock(SubmissionDelayGuardInterface::class);
        $this->rateLimitGuard       = $this->createMock(RateLimitGuardInterface::class);

        $this->handler = new Email2faResendPostHandler(
            $this->email2faService,
            $this->pendingSession,
            $this->userModel,
            $this->flash,
            $this->responder,
            $this->csrf,
            $this->honeypotGuard,
            $this->submissionDelayGuard,
            $this->rateLimitGuard,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validForm(): array
    {
        return [
            'csrf_token' => 'valid-csrf-token',
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
            ->with('email_2fa_resend_form', 'valid-csrf-token')
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

    private function enabled2faUser(int $userId = 42): UserEntity
    {
        $user = new UserEntity();
        $user->setUserId($userId);
        $user->setUsername('john');
        $user->setEmail('john@example.com');
        $user->setEmail2faEnabled(true);

        return $user;
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

        $this->email2faService
            ->expects($this->never())
            ->method('resendCode');

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

        $this->email2faService
            ->expects($this->never())
            ->method('resendCode');

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

        $this->email2faService
            ->expects($this->never())
            ->method('resendCode');

        $this->handler->handle($this->validForm());
    }

    public function testRedirectsTo2faPageWhenCsrfIsInvalid(): void
    {
        $this->mockValidPendingSession();

        $this->csrf
            ->expects($this->once())
            ->method('validateToken')
            ->with('email_2fa_resend_form', 'invalid-token')
            ->willReturn(false);

        $this->email2faService
            ->expects($this->never())
            ->method('resendCode');

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
            ->method('resendCode');

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
            ->method('resendCode');

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
            ->method('resendCode');

        $this->handler->handle($this->validForm());
    }

    public function testRedirectsToLoginWhenUserIsNotFound(): void
    {
        $this->mockValidPendingSession();
        $this->mockValidCsrf();
        $this->mockPassingGuards();

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(42)
            ->willReturn(null);

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

        $this->email2faService
            ->expects($this->never())
            ->method('resendCode');

        $this->handler->handle($this->validForm());
    }

    public function testRedirectsToLoginWhenUserHas2faDisabled(): void
    {
        $this->mockValidPendingSession();
        $this->mockValidCsrf();
        $this->mockPassingGuards();

        $user = new UserEntity();
        $user->setUserId(42);
        $user->setUsername('john');
        $user->setEmail('john@example.com');
        $user->setEmail2faEnabled(false);

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(42)
            ->willReturn($user);

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

        $this->email2faService
            ->expects($this->never())
            ->method('resendCode');

        $this->handler->handle($this->validForm());
    }

    public function testRedirectsTo2faPageWhenResendFails(): void
    {
        $this->mockValidPendingSession();
        $this->mockValidCsrf();
        $this->mockPassingGuards();

        $user = $this->enabled2faUser();

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(42)
            ->willReturn($user);

        $this->email2faService
            ->expects($this->once())
            ->method('resendCode')
            ->with($user)
            ->willReturn(false);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', 'Impossible de renvoyer le code de vérification. Veuillez réessayer.');

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login/2fa');

        $this->handler->handle($this->validForm());
    }

    public function testSuccessfulResendRedirectsTo2faPageWithInfoFlash(): void
    {
        $this->mockValidPendingSession();
        $this->mockValidCsrf();
        $this->mockPassingGuards();

        $user = $this->enabled2faUser();

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(42)
            ->willReturn($user);

        $this->email2faService
            ->expects($this->once())
            ->method('resendCode')
            ->with($user)
            ->willReturn(true);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('info', 'Un nouveau code de vérification vous a été envoyé par e-mail.');

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login/2fa');

        $this->handler->handle($this->validForm());
    }
}