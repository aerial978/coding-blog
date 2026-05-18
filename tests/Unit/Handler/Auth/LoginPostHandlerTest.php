<?php

declare(strict_types=1);

namespace Tests\Unit\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Handler\Auth\LoginPostHandler;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\RememberMeCookieManagerInterface;
use App\Security\Guard\Contract\HoneypotGuardInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Security\Guard\Contract\SubmissionDelayGuardInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class LoginPostHandlerTest extends TestCase
{
    private SecurityServiceInterface&MockObject $securityService;
    private FlashInterface&MockObject $flash;
    private ResponderInterface&MockObject $responder;
    private HoneypotGuardInterface&MockObject $honeypotGuard;
    private SubmissionDelayGuardInterface&MockObject $submissionDelayGuard;
    private RateLimitGuardInterface&MockObject $rateLimitGuard;
    private RememberMeCookieManagerInterface&MockObject $rememberMeCookieManager;

    private LoginPostHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->securityService         = $this->createMock(SecurityServiceInterface::class);
        $this->flash                   = $this->createMock(FlashInterface::class);
        $this->responder               = $this->createMock(ResponderInterface::class);
        $this->honeypotGuard           = $this->createMock(HoneypotGuardInterface::class);
        $this->submissionDelayGuard    = $this->createMock(SubmissionDelayGuardInterface::class);
        $this->rateLimitGuard          = $this->createMock(RateLimitGuardInterface::class);
        $this->rememberMeCookieManager = $this->createMock(RememberMeCookieManagerInterface::class);

        $this->handler = new LoginPostHandler(
            $this->securityService,
            $this->flash,
            $this->responder,
            $this->honeypotGuard,
            $this->submissionDelayGuard,
            $this->rateLimitGuard,
            $this->rememberMeCookieManager,
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function validForm(): array
    {
        return [
            'identifier'    => 'john@example.com',
            'password'      => 'StrongPassword123!',
        ];
    }

    private function allowAllGuards(): void
    {
        $this->honeypotGuard
            ->method('assertClean')
            ->willReturn(true);

        $this->submissionDelayGuard
            ->method('assertPassed')
            ->willReturn(true);

        $this->rateLimitGuard
            ->method('assertAllowed')
            ->willReturn(true);
    }

    public function testHandleStopsWhenHoneypotGuardRejects(): void
    {
        $form = $this->validForm();

        $this->honeypotGuard
            ->expects($this->once())
            ->method('assertClean')
            ->willReturn(false);

        $this->submissionDelayGuard
            ->expects($this->never())
            ->method('assertPassed');

        $this->rateLimitGuard
            ->expects($this->never())
            ->method('assertAllowed');

        $this->securityService
            ->expects($this->never())
            ->method('login');

        $this->handler->handle($form);
    }

    public function testHandleStopsWhenSubmissionDelayGuardRejects(): void
    {
        $form = $this->validForm();

        $this->honeypotGuard
            ->expects($this->once())
            ->method('assertClean')
            ->willReturn(true);

        $this->submissionDelayGuard
            ->expects($this->once())
            ->method('assertPassed')
            ->willReturn(false);

        $this->rateLimitGuard
            ->expects($this->never())
            ->method('assertAllowed');

        $this->securityService
            ->expects($this->never())
            ->method('login');

        $this->handler->handle($form);
    }

    public function testHandleStopsWhenRateLimitGuardRejects(): void
    {
        $form = $this->validForm();

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
            ->willReturn(false);

        $this->securityService
            ->expects($this->never())
            ->method('login');

        $this->handler->handle($form);
    }

    public function testHandleRedirectsToHomeWhenLoginSucceeds(): void
    {
        $form = $this->validForm();
        $this->allowAllGuards();

        $result = [
            'ok' => true,
        ];

        $this->securityService
            ->expects($this->once())
            ->method('login')
            ->with($form)
            ->willReturn($result);

        $this->flash
            ->expects($this->once())
            ->method('put')
            ->with('old', []);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('success', 'Connexion réussie.');

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog');

        $this->rememberMeCookieManager
            ->expects($this->never())
            ->method('createCookie');

        $this->handler->handle($form);
    }

    public function testHandleAddsErrorMessagesAndRedirectsToLoginWhenLoginFailsWithErrors(): void
    {
        $form = $this->validForm();
        $this->allowAllGuards();

        $result = [
            'errors' => ['ERR_1', 'ERR_2'],
        ];

        $this->securityService
            ->expects($this->once())
            ->method('login')
            ->with($form)
            ->willReturn($result);

        $this->flash
            ->expects($this->exactly(2))
            ->method('add')
            ->with(
                'error',
                $this->isType('string')
            );

        $this->flash
            ->expects($this->once())
            ->method('put')
            ->with('old', [
                'identifier' => 'john@example.com',
            ]);

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $this->handler->handle($form);
    }

    public function testHandleAddsGenericErrorAndRedirectsToLoginWhenLoginFailsWithoutErrors(): void
    {
        $form = $this->validForm();
        $this->allowAllGuards();

        $result = [
            'ok' => false,
        ];

        $this->securityService
            ->expects($this->once())
            ->method('login')
            ->with($form)
            ->willReturn($result);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', 'Échec de connexion.');

        $this->flash
            ->expects($this->once())
            ->method('put')
            ->with('old', [
                'identifier' => 'john@example.com',
            ]);

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $this->handler->handle($form);
    }

    public function testHandleUsesOldValuesFromResultWhenProvided(): void
    {
        $form = $this->validForm();
        $this->allowAllGuards();

        $result = [
            'errors' => ['ERR_1'],
            'old'    => [
                'identifier' => 'custom@example.com',
            ],
        ];

        $this->securityService
            ->expects($this->once())
            ->method('login')
            ->with($form)
            ->willReturn($result);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'error',
                $this->isType('string')
            );

        $this->flash
            ->expects($this->once())
            ->method('put')
            ->with('old', [
                'identifier' => 'custom@example.com',
            ]);

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $this->handler->handle($form);
    }

    public function testHandleCreatesRememberMeCookieWhenLoginReturnsRememberMeToken(): void
    {
        $form = [
            'identifier'  => 'john@example.com',
            'password'    => 'StrongPassword123!',
            'remember_me' => '1',
        ];

        $this->allowAllGuards();

        $this->securityService
            ->expects($this->once())
            ->method('login')
            ->with($form)
            ->willReturn([
                'ok'                => true,
                'remember_me_token' => 'raw-token',
            ]);

        $this->rememberMeCookieManager
            ->expects($this->once())
            ->method('createCookie')
            ->with('raw-token');

        $this->flash
            ->expects($this->once())
            ->method('put')
            ->with('old', []);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('success', 'Connexion réussie.');

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog');

        $this->handler->handle($form);
    }

    public function testHandleKeepsRememberMeOldValueWhenLoginFailsWithoutResultOld(): void
    {
        $form = [
        'identifier'  => 'john@example.com',
        'password'    => 'StrongPassword123!',
        'remember_me' => '1',
        ];

        $this->allowAllGuards();

        $this->securityService
        ->expects($this->once())
        ->method('login')
        ->with($form)
        ->willReturn([
            'ok' => false,
        ]);

        $this->flash
        ->expects($this->once())
        ->method('add')
        ->with('error', 'Échec de connexion.');

        $this->flash
        ->expects($this->once())
        ->method('put')
        ->with('old', [
            'identifier'  => 'john@example.com',
            'remember_me' => '1',
        ]);

        $this->responder
        ->expects($this->once())
        ->method('redirect')
        ->with('/coding-blog/login');

        $this->handler->handle($form);
    }

    public function testHandleDoesNotCreateRememberMeCookieWhenTokenIsEmpty(): void
    {
        $form = $this->validForm();

        $this->allowAllGuards();

        $this->securityService
        ->expects($this->once())
        ->method('login')
        ->with($form)
        ->willReturn([
            'ok'                => true,
            'remember_me_token' => '',
        ]);

        $this->rememberMeCookieManager
        ->expects($this->never())
        ->method('createCookie');

        $this->flash
        ->expects($this->once())
        ->method('put')
        ->with('old', []);

        $this->flash
        ->expects($this->once())
        ->method('add')
        ->with('success', 'Connexion réussie.');

        $this->responder
        ->expects($this->once())
        ->method('redirect')
        ->with('/coding-blog');

        $this->handler->handle($form);
    }

    public function testHandleRedirectsTo2faWhenLoginRequiresTwoFactor(): void
    {
        $form = [
            'identifier'  => 'john@example.com',
            'password'    => 'StrongPassword123!',
            'remember_me' => '1',
        ];

        $this->allowAllGuards();

        $this->securityService
            ->expects($this->once())
            ->method('login')
            ->with($form)
            ->willReturn([
                'two_factor_required' => true,
            ]);

        $this->rememberMeCookieManager
            ->expects($this->never())
            ->method('createCookie');

        $this->flash
            ->expects($this->once())
            ->method('put')
            ->with('old', []);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('info', 'Un code de vérification vous a été envoyé par e-mail.');

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login/2fa');

        $this->handler->handle($form);
    }
}
