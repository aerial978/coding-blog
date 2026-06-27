<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\Email2faController;
use App\Core\Contract\FlashInterface;
use App\Core\Contract\SessionInterface;
use App\Handler\Auth\Email2faGetHandler;
use App\Handler\Auth\Email2faPostHandler;
use App\Handler\Auth\Email2faResendPostHandler;
use App\Http\Contract\ResponderInterface;
use App\Http\Request;
use App\Model\Contract\UserModelInterface;
use App\Model\Entity\UserEntity;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\Email2faPendingSessionInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\RememberMeCookieManagerInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Security\Guard\Contract\HoneypotGuardInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Security\Guard\Contract\SubmissionDelayGuardInterface;
use App\Service\Security\Contract\Email2faServiceInterface;
use App\Service\Security\Contract\RememberMeServiceInterface;
use App\Service\Security\Email2faService;
use PHPUnit\Framework\TestCase;

final class Email2faControllerTest extends TestCase
{
    public function testIndexDelegatesToGetHandler(): void
    {
        $request   = $this->createMock(Request::class);
        $responder = $this->createMock(ResponderInterface::class);

        $pendingSession = $this->createMock(Email2faPendingSessionInterface::class);
        $pendingSession->method('hasPending')->willReturn(true);
        $pendingSession->method('isExpired')->willReturn(false);

        $csrf = $this->createMock(CsrfTokenInterface::class);
        $csrf->method('generateToken')->willReturnMap([
            ['email_2fa_form', 'csrf-main-token'],
            ['email_2fa_resend_form', 'csrf-resend-token'],
        ]);

        $honeypot = $this->createMock(HoneypotValidatorInterface::class);
        $honeypot->method('fieldName')->willReturn('hp_field');

        $submissionDelay = $this->createMock(SubmissionDelayValidatorInterface::class);
        $submissionDelay
            ->expects($this->exactly(2))
            ->method('markFormStart');

        $responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'security/email-2fa.html.twig',
                $this->callback(
                    static fn (array $data): bool =>
                    ($data['csrf_token'] ?? null)           === 'csrf-main-token'
                    && ($data['csrf_token_resend'] ?? null) === 'csrf-resend-token'
                    && ($data['honeypot_name'] ?? null)     === 'hp_field'
                )
            );

        $controller = new Email2faController(
            $request,
            new Email2faGetHandler(
                $this->createMock(FlashInterface::class),
                $responder,
                $csrf,
                $honeypot,
                $submissionDelay,
                $pendingSession,
            ),
            $this->dummyPostHandler(),
            $this->dummyResendHandler(),
        );

        $controller->index();
    }

    public function testVerifyDelegatesRequestDataToPostFlow(): void
    {
        $form = [
            'csrf_token' => 'valid-csrf-token',
            'code'       => '123456',
        ];

        $request = $this->createMock(Request::class);
        $request
            ->expects($this->once())
            ->method('request')
            ->willReturn($form);

        $pendingSession = $this->createMock(Email2faPendingSessionInterface::class);
        $pendingSession->method('hasPending')->willReturn(true);
        $pendingSession->method('isExpired')->willReturn(false);
        $pendingSession->method('getPendingUserId')->willReturn(42);
        $pendingSession->method('wasRememberMeRequested')->willReturn(false);

        $csrf = $this->createMock(CsrfTokenInterface::class);
        $csrf
            ->expects($this->once())
            ->method('validateToken')
            ->with('email_2fa_form', 'valid-csrf-token')
            ->willReturn(true);

        $email2faService = $this->createMock(Email2faServiceInterface::class);
        $email2faService
            ->expects($this->once())
            ->method('verifyCode')
            ->with(42, '123456')
            ->willReturn(Email2faService::VERIFY_SUCCESS);

        $responder = $this->createMock(ResponderInterface::class);
        $responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog');

        $controller = new Email2faController(
            $request,
            $this->dummyGetHandler(),
            new Email2faPostHandler(
                $email2faService,
                $pendingSession,
                $this->createMock(SessionInterface::class),
                $this->createMock(FlashInterface::class),
                $responder,
                $csrf,
                $this->passingHoneypotGuard(),
                $this->passingSubmissionDelayGuard(),
                $this->passingRateLimitGuard(),
                $this->createMock(RememberMeServiceInterface::class),
                $this->createMock(RememberMeCookieManagerInterface::class),
            ),
            $this->dummyResendHandler(),
        );

        $controller->verify();
    }

    public function testResendDelegatesRequestDataToResendFlow(): void
    {
        $form = [
            'csrf_token' => 'valid-resend-csrf-token',
        ];

        $request = $this->createMock(Request::class);
        $request
            ->expects($this->once())
            ->method('request')
            ->willReturn($form);

        $pendingSession = $this->createMock(Email2faPendingSessionInterface::class);
        $pendingSession->method('hasPending')->willReturn(true);
        $pendingSession->method('isExpired')->willReturn(false);
        $pendingSession->method('getPendingUserId')->willReturn(42);

        $csrf = $this->createMock(CsrfTokenInterface::class);
        $csrf
            ->expects($this->once())
            ->method('validateToken')
            ->with('email_2fa_resend_form', 'valid-resend-csrf-token')
            ->willReturn(true);

        $user = new UserEntity();
        $user->setUserId(42);
        $user->setUsername('john');
        $user->setEmail('john@example.com');
        $user->setEmail2faEnabled(true);

        $userModel = $this->createMock(UserModelInterface::class);
        $userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(42)
            ->willReturn($user);

        $email2faService = $this->createMock(Email2faServiceInterface::class);
        $email2faService
            ->expects($this->once())
            ->method('resendCode')
            ->with($user)
            ->willReturn(true);

        $responder = $this->createMock(ResponderInterface::class);
        $responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login/2fa');

        $controller = new Email2faController(
            $request,
            $this->dummyGetHandler(),
            $this->dummyPostHandler(),
            new Email2faResendPostHandler(
                $email2faService,
                $pendingSession,
                $userModel,
                $this->createMock(FlashInterface::class),
                $responder,
                $csrf,
                $this->passingHoneypotGuard(),
                $this->passingSubmissionDelayGuard(),
                $this->passingRateLimitGuard(),
            ),
        );

        $controller->resend();
    }

    private function dummyGetHandler(): Email2faGetHandler
    {
        return new Email2faGetHandler(
            $this->createMock(FlashInterface::class),
            $this->createMock(ResponderInterface::class),
            $this->createMock(CsrfTokenInterface::class),
            $this->createMock(HoneypotValidatorInterface::class),
            $this->createMock(SubmissionDelayValidatorInterface::class),
            $this->createMock(Email2faPendingSessionInterface::class),
        );
    }

    private function dummyPostHandler(): Email2faPostHandler
    {
        return new Email2faPostHandler(
            $this->createMock(Email2faServiceInterface::class),
            $this->createMock(Email2faPendingSessionInterface::class),
            $this->createMock(SessionInterface::class),
            $this->createMock(FlashInterface::class),
            $this->createMock(ResponderInterface::class),
            $this->createMock(CsrfTokenInterface::class),
            $this->createMock(HoneypotGuardInterface::class),
            $this->createMock(SubmissionDelayGuardInterface::class),
            $this->createMock(RateLimitGuardInterface::class),
            $this->createMock(RememberMeServiceInterface::class),
            $this->createMock(RememberMeCookieManagerInterface::class),
        );
    }

    private function dummyResendHandler(): Email2faResendPostHandler
    {
        return new Email2faResendPostHandler(
            $this->createMock(Email2faServiceInterface::class),
            $this->createMock(Email2faPendingSessionInterface::class),
            $this->createMock(UserModelInterface::class),
            $this->createMock(FlashInterface::class),
            $this->createMock(ResponderInterface::class),
            $this->createMock(CsrfTokenInterface::class),
            $this->createMock(HoneypotGuardInterface::class),
            $this->createMock(SubmissionDelayGuardInterface::class),
            $this->createMock(RateLimitGuardInterface::class),
        );
    }

    private function passingHoneypotGuard(): HoneypotGuardInterface
    {
        $guard = $this->createMock(HoneypotGuardInterface::class);
        $guard->method('assertClean')->willReturn(true);

        return $guard;
    }

    private function passingSubmissionDelayGuard(): SubmissionDelayGuardInterface
    {
        $guard = $this->createMock(SubmissionDelayGuardInterface::class);
        $guard->method('assertPassed')->willReturn(true);

        return $guard;
    }

    private function passingRateLimitGuard(): RateLimitGuardInterface
    {
        $guard = $this->createMock(RateLimitGuardInterface::class);
        $guard->method('assertAllowed')->willReturn(true);

        return $guard;
    }
}
