<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\ForgotPasswordController;
use App\Core\Contract\FlashInterface;
use App\Core\FormId;
use App\Handler\Auth\ForgotPasswordGetHandler;
use App\Handler\Auth\ForgotPasswordPostHandler;
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

final class ForgotPasswordControllerTest extends TestCase
{
    private Request&MockObject $request;
    private FlashInterface&MockObject $flash;
    private ResponderInterface&MockObject $responder;
    private CsrfTokenInterface&MockObject $csrf;
    private HoneypotValidatorInterface&MockObject $honeypotValidator;
    private SubmissionDelayValidatorInterface&MockObject $submissionDelayValidator;

    private SecurityServiceInterface&MockObject $securityService;
    private HoneypotGuardInterface&MockObject $honeypotGuard;
    private SubmissionDelayGuardInterface&MockObject $submissionDelayGuard;
    private RateLimitGuardInterface&MockObject $rateLimitGuard;

    private ForgotPasswordController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request                  = $this->createMock(Request::class);
        $this->flash                    = $this->createMock(FlashInterface::class);
        $this->responder                = $this->createMock(ResponderInterface::class);
        $this->csrf                     = $this->createMock(CsrfTokenInterface::class);
        $this->honeypotValidator        = $this->createMock(HoneypotValidatorInterface::class);
        $this->submissionDelayValidator = $this->createMock(SubmissionDelayValidatorInterface::class);

        $this->securityService      = $this->createMock(SecurityServiceInterface::class);
        $this->honeypotGuard        = $this->createMock(HoneypotGuardInterface::class);
        $this->submissionDelayGuard = $this->createMock(SubmissionDelayGuardInterface::class);
        $this->rateLimitGuard       = $this->createMock(RateLimitGuardInterface::class);

        $getHandler = new ForgotPasswordGetHandler(
            $this->flash,
            $this->responder,
            $this->csrf,
            $this->honeypotValidator,
            $this->submissionDelayValidator,
        );

        $postHandler = new ForgotPasswordPostHandler(
            $this->securityService,
            $this->flash,
            $this->responder,
            $this->honeypotGuard,
            $this->submissionDelayGuard,
            $this->rateLimitGuard,
            null,
        );

        $this->controller = new ForgotPasswordController(
            $this->request,
            $getHandler,
            $postHandler,
        );
    }

    public function testForgotPasswordDelegatesToGetHandlerWhenMethodIsGet(): void
    {
        $this->request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('GET');

        $this->request
            ->expects($this->never())
            ->method('request');

        $this->flash
            ->expects($this->exactly(2))
            ->method('take')
            ->willReturnMap([
                ['old', [], []],
                ['security_flags', [], []],
            ]);

        $this->csrf
            ->expects($this->once())
            ->method('generateToken')
            ->with(FormId::FORGOT_PASSWORD)
            ->willReturn('csrf-forgot-token');

        $this->honeypotValidator
            ->expects($this->once())
            ->method('fieldName')
            ->willReturn('fax');

        $this->submissionDelayValidator
            ->expects($this->once())
            ->method('markFormStart')
            ->with(FormId::FORGOT_PASSWORD);

        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'security/forgot-password.html.twig',
                $this->callback(function (array $data): bool {
                    $this->assertSame('Forgot password', $data['title']);
                    $this->assertSame('csrf-forgot-token', $data['csrf_token']);
                    $this->assertSame([], $data['old']);
                    $this->assertSame('fax', $data['honeypot_name']);
                    $this->assertFalse($data['turnstile_required']);
                    $this->assertFalse($data['turnstile_enabled']);

                    $this->assertArrayNotHasKey('flashes', $data);
                    $this->assertArrayNotHasKey('turnstile_site_key', $data);

                    return true;
                })
            );

        $this->securityService
            ->expects($this->never())
            ->method('forgotPassword');

        $this->controller->ForgotPassword();
    }

    public function testForgotPasswordDelegatesToPostHandlerWhenMethodIsPost(): void
    {
        $form = [
            'identifier' => 'john@example.com',
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
            ->method('forgotPassword')
            ->with('john@example.com');

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('success', $this->isType('string'));

        $this->flash
            ->expects($this->once())
            ->method('put')
            ->with('old', ['identifier' => 'john@example.com']);

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/forgot-password');

        $this->controller->ForgotPassword();
    }
}