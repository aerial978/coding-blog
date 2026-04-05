<?php

declare(strict_types=1);

namespace Tests\Unit\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Handler\Auth\RegisterPostHandler;
use App\Http\Contract\ResponderInterface;
use App\Security\Guard\Contract\HoneypotGuardInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Security\Guard\Contract\SubmissionDelayGuardInterface;
use App\Security\Guard\Contract\TurnstileGuardInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use App\Support\ErrorListNormalizer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RegisterPostHandlerTest extends TestCase
{
    private SecurityServiceInterface&MockObject $securityService;
    private FlashInterface&MockObject $flash;
    private ResponderInterface&MockObject $responder;
    private HoneypotGuardInterface&MockObject $honeypotGuard;
    private SubmissionDelayGuardInterface&MockObject $submissionDelayGuard;
    private RateLimitGuardInterface&MockObject $rateLimitGuard;
    private TurnstileGuardInterface&MockObject $turnstileGuard;
    private ErrorListNormalizer $errorListNormalizer;

    private RegisterPostHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->securityService      = $this->createMock(SecurityServiceInterface::class);
        $this->flash                = $this->createMock(FlashInterface::class);
        $this->responder            = $this->createMock(ResponderInterface::class);
        $this->honeypotGuard        = $this->createMock(HoneypotGuardInterface::class);
        $this->submissionDelayGuard = $this->createMock(SubmissionDelayGuardInterface::class);
        $this->rateLimitGuard       = $this->createMock(RateLimitGuardInterface::class);
        $this->turnstileGuard       = $this->createMock(TurnstileGuardInterface::class);
        $this->errorListNormalizer  = new ErrorListNormalizer();

        $this->handler = new RegisterPostHandler(
            $this->securityService,
            $this->flash,
            $this->responder,
            $this->honeypotGuard,
            $this->submissionDelayGuard,
            $this->rateLimitGuard,
            $this->turnstileGuard,
            $this->errorListNormalizer,
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function validForm(): array
    {
        return [
            'email'                 => 'john@example.com',
            'username'              => 'john',
            'password'              => 'StrongPassword123!',
            'cf-turnstile-response' => 'valid-turnstile-token',
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

        $this->turnstileGuard
            ->method('assertValid')
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

        $this->turnstileGuard
            ->expects($this->never())
            ->method('assertValid');

        $this->rateLimitGuard
            ->expects($this->never())
            ->method('assertAllowed');

        $this->securityService
            ->expects($this->never())
            ->method('register');

        $this->handler->handle($form);
    }

    public function testHandleAddsInfoFlashAndStopsWhenRateLimitGuardRejects(): void
    {
        $form = $this->validForm();

        $this->honeypotGuard
            ->method('assertClean')
            ->willReturn(true);

        $this->submissionDelayGuard
            ->method('assertPassed')
            ->willReturn(true);

        $this->turnstileGuard
            ->method('assertValid')
            ->willReturn(true);

        $this->rateLimitGuard
            ->expects($this->once())
            ->method('assertAllowed')
            ->willReturn(false);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'info',
                $this->isType('string')
            );

        $this->securityService
            ->expects($this->never())
            ->method('register');

        $this->handler->handle($form);
    }

    public function testHandleRepliesTechnicalErrorWhenRegisterThrowsException(): void
    {
        $form = $this->validForm();
        $this->allowAllGuards();

        $this->securityService
            ->expects($this->once())
            ->method('register')
            ->with($form)
            ->willThrowException(new \RuntimeException('database down'));

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
                'username' => 'john',
                'email'    => 'john@example.com',
            ]);

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/register');

        $this->handler->handle($form);
    }

    public function testHandleAddsErrorFlashesAndRedirectsToRegisterWhenBusinessErrorsOccur(): void
    {
        $form = $this->validForm();
        $this->allowAllGuards();

        $result = [
            'errors' => ['ERR_1', 'ERR_2'],
        ];

        $this->securityService
            ->expects($this->once())
            ->method('register')
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
                'username' => 'john',
                'email'    => 'john@example.com',
            ]);

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/register');

        $this->handler->handle($form);
    }

    public function testHandleRedirectsToResendConfirmationWhenConfirmationEmailSendFailed(): void
    {
        $form = $this->validForm();
        $this->allowAllGuards();

        $result = [
            'errors' => [ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED],
        ];

        $this->securityService
            ->expects($this->once())
            ->method('register')
            ->with($form)
            ->willReturn($result);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', $this->isType('string'));

        $this->flash
            ->expects($this->once())
            ->method('put')
            ->with('old', [
                'email' => 'john@example.com',
            ]);

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/resend-confirmation');

        $this->handler->handle($form);
    }

    public function testHandleStoresRegisterStateAndRedirectsWhenRegistrationSucceeds(): void
    {
        $form = $this->validForm();
        $this->allowAllGuards();

        $result = [
            'ok'     => true,
            'errors' => [],
        ];

        $this->securityService
            ->expects($this->once())
            ->method('register')
            ->with($form)
            ->willReturn($result);

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

        $this->handler->handle($form);
    }

    public function testHandleUsesOldValuesFromResultWhenProvided(): void
    {
        $form = $this->validForm();
        $this->allowAllGuards();

        $result = [
            'errors' => ['ERR_1'],
            'old'    => [
                'email'    => 'custom@example.com',
                'username' => 'custom-user',
            ],
        ];

        $this->securityService
            ->expects($this->once())
            ->method('register')
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
                'email'    => 'custom@example.com',
                'username' => 'custom-user',
            ]);

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/register');

        $this->handler->handle($form);
    }
}
