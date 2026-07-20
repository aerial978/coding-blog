<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Security;

use App\Core\Contract\SessionInterface;
use App\Core\ErrorCode;
use App\Model\Contract\UserModelInterface;
use App\Model\Entity\UserEntity;
use App\Security\Contract\Email2faPendingSessionInterface;
use App\Service\Security\Contract\Email2faServiceInterface;
use App\Service\Security\Contract\RememberMeServiceInterface;
use App\Service\Security\LoginService;
use App\Validation\Contract\FormValidatorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class LoginServiceTest extends TestCase
{
    private FormValidatorInterface&MockObject $validator;
    private UserModelInterface&MockObject $userModel;
    private SessionInterface&MockObject $session;
    private RememberMeServiceInterface&MockObject $rememberMeService;
    private Email2faServiceInterface&MockObject $email2faService;
    private Email2faPendingSessionInterface&MockObject $email2faPendingSession;

    private LoginService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REMOTE_ADDR']     = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $this->validator              = $this->createMock(FormValidatorInterface::class);
        $this->userModel              = $this->createMock(UserModelInterface::class);
        $this->session                = $this->createMock(SessionInterface::class);
        $this->rememberMeService      = $this->createMock(RememberMeServiceInterface::class);
        $this->email2faService        = $this->createMock(Email2faServiceInterface::class);
        $this->email2faPendingSession = $this->createMock(Email2faPendingSessionInterface::class);

        $this->service = new LoginService(
            $this->validator,
            $this->userModel,
            $this->session,
            $this->rememberMeService,
            $this->email2faService,
            $this->email2faPendingSession,
        );
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
        parent::tearDown();
    }

    private function validForm(): array
    {
        return [
            'identifier' => 'john@example.com',
            'password'   => 'Password123!',
        ];
    }

    public function testLoginReturnsValidationErrors(): void
    {
        $form = $this->validForm();

        $this->validator
            ->expects($this->once())
            ->method('validateLogin')
            ->willReturn(['ERR_FORM']);

        $this->userModel
            ->expects($this->never())
            ->method('findAuthByEmail');

        $result = $this->service->login($form);

        $this->assertSame(['ERR_FORM'], $result['errors']);
        $this->assertSame(['identifier' => 'john@example.com'], $result['old']);
    }

    public function testLoginReturnsInvalidCredentialsWhenUserNotFound(): void
    {
        $form = $this->validForm();

        $this->validator->method('validateLogin')->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('findAuthByEmail')
            ->with('john@example.com')
            ->willReturn(null);

        $result = $this->service->login($form);

        $this->assertSame([ErrorCode::AUTH_INVALID_CREDENTIALS], $result['errors']);
    }

    public function testLoginReturnsInvalidCredentialsWhenPasswordIsWrong(): void
    {
        $form = $this->validForm();

        $user = new UserEntity();
        $user->setPassword(password_hash('other-password', PASSWORD_ARGON2I));

        $this->validator->method('validateLogin')->willReturn([]);

        $this->userModel
            ->method('findAuthByEmail')
            ->willReturn($user);

        $result = $this->service->login($form);

        $this->assertSame([ErrorCode::AUTH_INVALID_CREDENTIALS], $result['errors']);
    }

    public function testLoginReturnsInvalidCredentialsWhenUserNotActive(): void
    {
        $form = $this->validForm();

        $user = new UserEntity();
        $user->setPassword(password_hash('Password123!', PASSWORD_ARGON2I));
        $user->setStatus('pending');

        $this->validator->method('validateLogin')->willReturn([]);

        $this->userModel
            ->method('findAuthByEmail')
            ->willReturn($user);

        $result = $this->service->login($form);

        $this->assertSame([ErrorCode::AUTH_INVALID_CREDENTIALS], $result['errors']);
    }

    public function testLoginReturnsTechnicalErrorWhenUserIdIsInvalid(): void
    {
        $form = $this->validForm();

        $user = new UserEntity();
        $user->setPassword(password_hash('Password123!', PASSWORD_ARGON2I));
        $user->setStatus('active');
        // userId volontairement absent ou 0

        $this->validator->method('validateLogin')->willReturn([]);

        $this->userModel
            ->method('findAuthByEmail')
            ->willReturn($user);

        $result = $this->service->login($form);

        $this->assertSame([ErrorCode::AUTH_TECHNICAL_ERROR], $result['errors']);
    }

    public function testLoginSuccessCreatesSession(): void
    {
        $form = $this->validForm();

        $user = new UserEntity();
        $user->setUserId(42);
        $user->setPassword(password_hash('Password123!', PASSWORD_ARGON2I));
        $user->setStatus('active');

        $this->validator->method('validateLogin')->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('findAuthByEmail')
            ->with('john@example.com')
            ->willReturn($user);

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

        $result = $this->service->login($form);

        $this->assertSame(['ok' => true], $result);
    }

    public function testLoginUsesUsernameLookupWhenIdentifierIsNotEmail(): void
    {
        $form = [
            'identifier' => 'john',
            'password'   => 'Password123!',
        ];

        $user = new UserEntity();
        $user->setUserId(1);
        $user->setPassword(password_hash('Password123!', PASSWORD_ARGON2I));
        $user->setStatus('active');

        $this->validator->method('validateLogin')->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('findAuthByUsername')
            ->with('john')
            ->willReturn($user);

        $this->session->method('regenerateAndDeleteOld');
        $this->session->method('set');

        $this->rememberMeService
            ->expects($this->never())
            ->method('createRememberMeToken');

        $result = $this->service->login($form);

        $this->assertSame(['ok' => true], $result);
    }

    public function testLoginReturnsTechnicalErrorOnThrowable(): void
    {
        $form = $this->validForm();

        $this->validator
            ->expects($this->once())
            ->method('validateLogin')
            ->willThrowException(new \RuntimeException('failure'));

        $result = $this->service->login($form);

        $this->assertSame([ErrorCode::AUTH_TECHNICAL_ERROR], $result['errors']);
        $this->assertSame(['identifier' => 'john@example.com'], $result['old']);
    }

    public function testLoginSuccessWithRememberMeReturnsRememberMeToken(): void
    {
        $form = [
            'identifier'  => 'john@example.com',
            'password'    => 'Password123!',
            'remember_me' => '1',
        ];

        $user = new UserEntity();
        $user->setUserId(42);
        $user->setPassword(password_hash('Password123!', PASSWORD_ARGON2I));
        $user->setStatus('active');

        $this->validator->method('validateLogin')->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('findAuthByEmail')
            ->with('john@example.com')
            ->willReturn($user);

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
            ->expects($this->once())
            ->method('createRememberMeToken')
            ->with(42)
            ->willReturn('raw-token');

        $result = $this->service->login($form);

        $this->assertSame([
            'ok'                => true,
            'remember_me_token' => 'raw-token',
        ], $result);
    }

    public function testLoginSuccessWithRememberMeOnReturnsRememberMeToken(): void
    {
        $form = [
            'identifier'  => 'john@example.com',
            'password'    => 'Password123!',
            'remember_me' => 'on',
        ];

        $user = new UserEntity();
        $user->setUserId(42);
        $user->setPassword(password_hash('Password123!', PASSWORD_ARGON2I));
        $user->setStatus('active');

        $this->validator->method('validateLogin')->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('findAuthByEmail')
            ->with('john@example.com')
            ->willReturn($user);

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
            ->expects($this->once())
            ->method('createRememberMeToken')
            ->with(42)
            ->willReturn('raw-token');

        $result = $this->service->login($form);

        $this->assertSame([
            'ok'                => true,
            'remember_me_token' => 'raw-token',
        ], $result);
    }

    public function testLoginSuccessWithRememberMeReturnsOkWhenTokenCreationFails(): void
    {
        $form = [
            'identifier'  => 'john@example.com',
            'password'    => 'Password123!',
            'remember_me' => '1',
        ];

        $user = new UserEntity();
        $user->setUserId(42);
        $user->setPassword(password_hash('Password123!', PASSWORD_ARGON2I));
        $user->setStatus('active');

        $this->validator->method('validateLogin')->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('findAuthByEmail')
            ->with('john@example.com')
            ->willReturn($user);

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
            ->expects($this->once())
            ->method('createRememberMeToken')
            ->with(42)
            ->willReturn(null);

        $result = $this->service->login($form);

        $this->assertSame(['ok' => true], $result);
    }

    public function testLoginWithEmail2faEnabledStartsPendingSessionAndDoesNotOpenAuthenticatedSession(): void
    {
        $form = [
            'identifier'  => 'john@example.com',
            'password'    => 'Password123!',
            'remember_me' => '1',
        ];

        $user = new UserEntity();
        $user->setUserId(42);
        $user->setEmail('john@example.com');
        $user->setUsername('john');
        $user->setPassword(password_hash('Password123!', PASSWORD_ARGON2I));
        $user->setStatus('active');
        $user->setEmail2faEnabled(true);

        $this->validator->method('validateLogin')->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('findAuthByEmail')
            ->with('john@example.com')
            ->willReturn($user);

        $this->email2faService
            ->expects($this->once())
            ->method('generateAndSendCode')
            ->with($user)
            ->willReturn(true);

        $this->email2faPendingSession
            ->expects($this->once())
            ->method('start')
            ->with(42, true);

        $this->session
            ->expects($this->never())
            ->method('regenerateAndDeleteOld');

        $this->session
            ->expects($this->never())
            ->method('set');

        $this->rememberMeService
            ->expects($this->never())
            ->method('createRememberMeToken');

        $result = $this->service->login($form);

        $this->assertSame(['two_factor_required' => true], $result);
    }

    public function testLoginWithEmail2faEnabledReturnsTechnicalErrorWhenCodeSendFails(): void
    {
        $form = [
            'identifier' => 'john@example.com',
            'password'   => 'Password123!',
        ];

        $user = new UserEntity();
        $user->setUserId(42);
        $user->setEmail('john@example.com');
        $user->setUsername('john');
        $user->setPassword(password_hash('Password123!', PASSWORD_ARGON2I));
        $user->setStatus('active');
        $user->setEmail2faEnabled(true);

        $this->validator->method('validateLogin')->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('findAuthByEmail')
            ->with('john@example.com')
            ->willReturn($user);

        $this->email2faService
            ->expects($this->once())
            ->method('generateAndSendCode')
            ->with($user)
            ->willReturn(false);

        $this->email2faPendingSession
            ->expects($this->never())
            ->method('start');

        $this->session
            ->expects($this->never())
            ->method('regenerateAndDeleteOld');

        $this->session
            ->expects($this->never())
            ->method('set');

        $result = $this->service->login($form);

        $this->assertSame([ErrorCode::AUTH_TECHNICAL_ERROR], $result['errors']);
        $this->assertSame(['identifier' => 'john@example.com'], $result['old']);
    }
}
