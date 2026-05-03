<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\LoginController;
use App\Core\Contract\FlashInterface;
use App\Core\View;
use App\Handler\Auth\LoginGetHandler;
use App\Handler\Auth\LoginPostHandler;
use App\Http\Contract\ResponderInterface;
use App\Http\Request;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\RememberMeCookieManagerInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Security\Guard\Contract\HoneypotGuardInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Security\Guard\Contract\SubmissionDelayGuardInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class LoginControllerTest extends TestCase
{
    private Request&MockObject $request;
    private View&MockObject $view;
    private FlashInterface&MockObject $flash;
    private ResponderInterface&MockObject $responder;
    private CsrfTokenInterface&MockObject $csrf;
    private SecurityServiceInterface&MockObject $securityService;
    private HoneypotValidatorInterface&MockObject $honeypotValidator;
    private SubmissionDelayValidatorInterface&MockObject $submissionDelayValidator;
    private HoneypotGuardInterface&MockObject $honeypotGuard;
    private SubmissionDelayGuardInterface&MockObject $submissionDelayGuard;
    private RateLimitGuardInterface&MockObject $rateLimitGuard;
    private RememberMeCookieManagerInterface&MockObject $rememberMeCookieManager;

    private LoginController $controller;

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
        $this->securityService          = $this->createMock(SecurityServiceInterface::class);
        $this->honeypotGuard            = $this->createMock(HoneypotGuardInterface::class);
        $this->submissionDelayGuard     = $this->createMock(SubmissionDelayGuardInterface::class);
        $this->rateLimitGuard           = $this->createMock(RateLimitGuardInterface::class);
        $this->rememberMeCookieManager  = $this->createMock(RememberMeCookieManagerInterface::class);

        $getHandler = new LoginGetHandler(
            $this->view,
            $this->flash,
            $this->responder,
            $this->csrf,
            $this->honeypotValidator,
            $this->submissionDelayValidator,
        );

        $postHandler = new LoginPostHandler(
            $this->securityService,
            $this->flash,
            $this->responder,
            $this->honeypotGuard,
            $this->submissionDelayGuard,
            $this->rateLimitGuard,
            $this->rememberMeCookieManager,
        );

        $this->controller = new LoginController(
            $this->request,
            $getHandler,
            $postHandler,
        );
    }

    public function testLoginDelegatesToGetHandlerWhenMethodIsGet(): void
    {
        $this->request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('GET');

        $this->request
            ->expects($this->never())
            ->method('request');

        // Exemple typique : récupération des flash messages
        $this->flash
            ->expects($this->any())
            ->method('take')
            ->willReturn(null);

        $this->csrf
            ->expects($this->once())
            ->method('generateToken');

        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'security/login.html.twig',
                $this->isType('array')
            );

        $this->securityService
            ->expects($this->never())
            ->method('login');

        $this->controller->login();
    }

    public function testLoginDelegatesToPostHandlerWhenMethodIsPost(): void
    {
        $form = [
            'email'    => 'john@example.com',
            'password' => 'password123',
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
            ->method('login')
            ->with($form)
            ->willReturn([
                'ok'     => true,
                'errors' => [],
            ]);

        $this->responder
            ->expects($this->once())
            ->method('redirect');

        $this->controller->login();

        $this->rememberMeCookieManager
            ->expects($this->never())
            ->method('createCookie');
    }
}
