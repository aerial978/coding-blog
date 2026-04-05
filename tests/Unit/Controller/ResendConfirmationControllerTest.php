<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\ResendConfirmationController;
use App\Core\Contract\FlashInterface;
use App\Core\View;
use App\Handler\Auth\ResendConfirmationGetHandler;
use App\Handler\Auth\ResendConfirmationPostHandler;
use App\Http\Contract\ResponderInterface;
use App\Http\Request;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Security\Guard\Contract\HoneypotGuardInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Security\Guard\Contract\SubmissionDelayGuardInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ResendConfirmationControllerTest extends TestCase
{
    private Request&MockObject $request;
    private View&MockObject $view;
    private FlashInterface&MockObject $flash;
    private ResponderInterface&MockObject $responder;
    private CsrfTokenInterface&MockObject $csrf;
    private HoneypotValidatorInterface&MockObject $honeypotValidator;
    private SubmissionDelayValidatorInterface&MockObject $submissionDelayValidator;

    private SecurityServiceInterface&MockObject $securityService;
    private HoneypotGuardInterface&MockObject $honeypotGuard;
    private SubmissionDelayGuardInterface&MockObject $submissionDelayGuard;
    private RateLimitGuardInterface&MockObject $rateLimitGuard;

    private ResendConfirmationController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = $this->createMock(Request::class);

        $this->view                     = $this->createMock(View::class);
        $this->flash                    = $this->createMock(FlashInterface::class);
        $this->responder                = $this->createMock(ResponderInterface::class);
        $this->csrf                     = $this->createMock(CsrfTokenInterface::class);
        $this->honeypotValidator        = $this->createMock(HoneypotValidatorInterface::class);
        $this->submissionDelayValidator = $this->createMock(SubmissionDelayValidatorInterface::class);

        $this->securityService      = $this->createMock(SecurityServiceInterface::class);
        $this->honeypotGuard        = $this->createMock(HoneypotGuardInterface::class);
        $this->submissionDelayGuard = $this->createMock(SubmissionDelayGuardInterface::class);
        $this->rateLimitGuard       = $this->createMock(RateLimitGuardInterface::class);

        $getHandler = new ResendConfirmationGetHandler(
            $this->view,
            $this->flash,
            $this->responder,
            $this->submissionDelayValidator,
            $this->csrf,
            $this->honeypotValidator,
        );

        $postHandler = new ResendConfirmationPostHandler(
            $this->securityService,
            $this->flash,
            $this->responder,
            $this->honeypotGuard,
            $this->submissionDelayGuard,
            $this->rateLimitGuard,
        );

        $this->controller = new ResendConfirmationController(
            $this->request,
            $getHandler,
            $postHandler,
        );
    }

    public function testResendConfirmationDelegatesToGetHandlerWhenMethodIsGet(): void
    {
        $this->request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('GET');

        $this->request
            ->expects($this->never())
            ->method('request');

        $this->flash
            ->expects($this->any())
            ->method('take')
            ->willReturn(null);

        $this->csrf
            ->expects($this->once())
            ->method('generateToken');

        $this->honeypotValidator
            ->expects($this->once())
            ->method('fieldName')
            ->willReturn('fax');

        $this->submissionDelayValidator
            ->expects($this->once())
            ->method('markFormStart')
            ->with('resend_confirm');

        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'security/resend-confirmation.html.twig',
                $this->isType('array')
            );

        $this->securityService
            ->expects($this->never())
            ->method('resendConfirmation');

        $this->controller->resendConfirmation();
    }

    public function testResendConfirmationDelegatesToPostHandlerWhenMethodIsPost(): void
    {
        $form = [
            'email' => 'john@example.com',
        ];

        $this->request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('POST');

        $this->request
            ->expects($this->once())
            ->method('request')
            ->willReturn($form);

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

        $this->securityService
            ->expects($this->once())
            ->method('resendConfirmation')
            ->with('john@example.com');

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('success', $this->isType('string'));

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/resend-confirmation');

        $this->controller->resendConfirmation();
    }
}
