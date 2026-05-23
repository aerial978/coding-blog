<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\RegisterController;
use App\Core\Contract\FlashInterface;
use App\Core\FormId;
use App\Handler\Auth\RegisterGetHandler;
use App\Handler\Auth\RegisterPostHandler;
use App\Http\Contract\ResponderInterface;
use App\Http\Request;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Security\Guard\Contract\HoneypotGuardInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Security\Guard\Contract\SubmissionDelayGuardInterface;
use App\Security\Guard\Contract\TurnstileGuardInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use App\Support\ErrorListNormalizer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RegisterControllerTest extends TestCase
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
    private TurnstileGuardInterface&MockObject $turnstileGuard;

    private RegisterController $controller;

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
        $this->turnstileGuard       = $this->createMock(TurnstileGuardInterface::class);

        $getHandler = new RegisterGetHandler(
            $this->flash,
            $this->responder,
            $this->csrf,
            $this->honeypotValidator,
            $this->submissionDelayValidator,
        );

        $postHandler = new RegisterPostHandler(
            $this->securityService,
            $this->flash,
            $this->responder,
            $this->honeypotGuard,
            $this->submissionDelayGuard,
            $this->rateLimitGuard,
            $this->turnstileGuard,
            new ErrorListNormalizer(),
        );

        $this->controller = new RegisterController(
            $this->request,
            $getHandler,
            $postHandler,
        );
    }

    public function testRegisterDelegatesToGetHandlerWhenMethodIsGet(): void
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
                ['register_state', null, null],
            ]);

        $this->submissionDelayValidator
            ->expects($this->once())
            ->method('markFormStart')
            ->with(FormId::REGISTER);

        $this->csrf
            ->expects($this->once())
            ->method('generateToken')
            ->with(FormId::REGISTER)
            ->willReturn('csrf-register-token');

        $this->honeypotValidator
            ->expects($this->once())
            ->method('fieldName')
            ->willReturn('fax');

        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'security/register.html.twig',
                $this->callback(function (array $data): bool {
                    $this->assertSame('User registration', $data['title']);
                    $this->assertSame('form', $data['mode']);
                    $this->assertNull($data['obfuscated_email']);
                    $this->assertSame('csrf-register-token', $data['csrf_token']);
                    $this->assertSame([], $data['old']);
                    $this->assertSame('fax', $data['honeypot_name']);

                    $this->assertArrayNotHasKey('flashes', $data);
                    $this->assertArrayNotHasKey('turnstile_site_key', $data);

                    return true;
                })
            );

        $this->securityService
            ->expects($this->never())
            ->method('register');

        $this->controller->register();
    }

    public function testRegisterDelegatesToPostHandlerWhenMethodIsPost(): void
    {
        $form = [
            'username'              => 'john',
            'email'                 => 'john@example.com',
            'password'              => 'StrongPassword123!',
            'cf-turnstile-response' => 'token',
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

        $this->turnstileGuard
            ->expects($this->once())
            ->method('assertValid')
            ->willReturn(true);

        $this->rateLimitGuard
            ->expects($this->once())
            ->method('assertAllowed')
            ->willReturn(true);

        $this->securityService
            ->expects($this->once())
            ->method('register')
            ->with($form)
            ->willReturn([
                'ok'     => true,
                'errors' => [],
            ]);

        $this->flash
            ->expects($this->once())
            ->method('put')
            ->with('register_state', [
                'email' => 'john@example.com',
            ]);

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/register');

        $this->controller->register();
    }
}