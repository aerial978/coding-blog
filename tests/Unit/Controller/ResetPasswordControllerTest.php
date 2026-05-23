<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\ResetPasswordController;
use App\Core\Contract\FlashInterface;
use App\Core\FormId;
use App\Handler\Auth\ResetPasswordGetHandler;
use App\Handler\Auth\ResetPasswordPostHandler;
use App\Http\Contract\ResponderInterface;
use App\Http\Request;
use App\Model\Contract\UserTokenModelInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Security\Contract\TokenGeneratorInterface;
use App\Security\Guard\Contract\HoneypotGuardInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Security\Guard\Contract\SubmissionDelayGuardInterface;
use App\Security\Guard\Contract\TurnstileGuardInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use App\Service\Security\ResetPasswordService;
use App\Support\ErrorListNormalizer;
use App\Validation\Contract\FormValidatorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ResetPasswordControllerTest extends TestCase
{
    private Request&MockObject $request;
    private FlashInterface&MockObject $flash;
    private ResponderInterface&MockObject $responder;
    private CsrfTokenInterface&MockObject $csrf;
    private HoneypotValidatorInterface&MockObject $honeypotValidator;
    private SubmissionDelayValidatorInterface&MockObject $submissionDelayValidator;

    private FormValidatorInterface&MockObject $validator;
    private UserTokenModelInterface&MockObject $userTokenModel;
    private TokenGeneratorInterface&MockObject $tokenGenerator;

    private SecurityServiceInterface&MockObject $securityService;
    private HoneypotGuardInterface&MockObject $honeypotGuard;
    private SubmissionDelayGuardInterface&MockObject $submissionDelayGuard;
    private RateLimitGuardInterface&MockObject $rateLimitGuard;
    private TurnstileGuardInterface&MockObject $turnstileGuard;

    private ResetPasswordController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request                  = $this->createMock(Request::class);
        $this->flash                    = $this->createMock(FlashInterface::class);
        $this->responder                = $this->createMock(ResponderInterface::class);
        $this->csrf                     = $this->createMock(CsrfTokenInterface::class);
        $this->honeypotValidator        = $this->createMock(HoneypotValidatorInterface::class);
        $this->submissionDelayValidator = $this->createMock(SubmissionDelayValidatorInterface::class);

        $this->validator      = $this->createMock(FormValidatorInterface::class);
        $this->userTokenModel = $this->createMock(UserTokenModelInterface::class);
        $this->tokenGenerator = $this->createMock(TokenGeneratorInterface::class);

        $resetPasswordService = new ResetPasswordService(
            $this->validator,
            $this->userTokenModel,
            $this->tokenGenerator,
        );

        $this->securityService      = $this->createMock(SecurityServiceInterface::class);
        $this->honeypotGuard        = $this->createMock(HoneypotGuardInterface::class);
        $this->submissionDelayGuard = $this->createMock(SubmissionDelayGuardInterface::class);
        $this->rateLimitGuard       = $this->createMock(RateLimitGuardInterface::class);
        $this->turnstileGuard       = $this->createMock(TurnstileGuardInterface::class);

        $getHandler = new ResetPasswordGetHandler(
            $this->flash,
            $this->responder,
            $this->csrf,
            $this->honeypotValidator,
            $this->submissionDelayValidator,
            $resetPasswordService,
        );

        $postHandler = new ResetPasswordPostHandler(
            $this->securityService,
            $this->flash,
            $this->responder,
            $this->honeypotGuard,
            $this->submissionDelayGuard,
            $this->rateLimitGuard,
            $this->turnstileGuard,
            new ErrorListNormalizer(),
        );

        $this->controller = new ResetPasswordController(
            $this->request,
            $getHandler,
            $postHandler,
        );
    }

    public function testResetPasswordDelegatesToGetHandlerWhenMethodIsGet(): void
    {
        $rawToken     = '  valid-token  ';
        $trimmedToken = 'valid-token';
        $hash         = str_repeat('a', 32);

        $this->request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('GET');

        $this->request
            ->expects($this->once())
            ->method('query')
            ->with()
            ->willReturn([
                'token' => $rawToken,
            ]);

        $this->request
            ->expects($this->never())
            ->method('request');

        $this->submissionDelayValidator
            ->expects($this->once())
            ->method('markFormStart')
            ->with(FormId::RESET_PASSWORD);

        $this->flash
            ->expects($this->once())
            ->method('take')
            ->with('security_flags', [])
            ->willReturn([]);

        $this->tokenGenerator
            ->expects($this->once())
            ->method('hashToken')
            ->with($trimmedToken)
            ->willReturn($hash);

        $this->userTokenModel
            ->expects($this->once())
            ->method('findPasswordResetContextByHash')
            ->with($hash)
            ->willReturn([
                'user_id'    => 42,
                'used'       => 0,
                'is_expired' => 0,
            ]);

        $this->csrf
            ->expects($this->once())
            ->method('generateToken')
            ->with(FormId::RESET_PASSWORD)
            ->willReturn('csrf-reset-token');

        $this->honeypotValidator
            ->expects($this->once())
            ->method('fieldName')
            ->willReturn('fax');

        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'security/reset-password.html.twig',
                $this->callback(function (array $data) use ($trimmedToken): bool {
                    $this->assertSame('Réinitialiser le mot de passe', $data['title']);
                    $this->assertSame($trimmedToken, $data['token']);
                    $this->assertSame('csrf-reset-token', $data['csrf_token']);
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
            ->method('resetPassword');

        $this->controller->resetPassword();
    }

    public function testResetPasswordDelegatesToPostHandlerWhenMethodIsPost(): void
    {
        $form = [
            'token'            => 'valid-token',
            'password'         => 'StrongPassword123!',
            'password_confirm' => 'StrongPassword123!',
        ];

        $this->request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('POST');

        $this->request
            ->expects($this->never())
            ->method('query');

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

        $this->flash
            ->expects($this->once())
            ->method('take')
            ->with('security_flags', [])
            ->willReturn([]);

        $this->flash
            ->expects($this->once())
            ->method('put')
            ->with('security_flags', []);

        $this->turnstileGuard
            ->expects($this->never())
            ->method('assertValid');

        $this->securityService
            ->expects($this->once())
            ->method('resetPassword')
            ->with('valid-token', 'StrongPassword123!', 'StrongPassword123!')
            ->willReturn([]);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('success', $this->isType('string'));

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $this->controller->resetPassword();
    }
}