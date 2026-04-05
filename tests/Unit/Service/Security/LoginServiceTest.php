<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Security;

use App\Core\Contract\SessionInterface;
use App\Core\ErrorCode;
use App\Model\Contract\UserModelInterface;
use App\Model\Entity\UserEntity;
use App\Service\Security\LoginService;
use App\Validation\Contract\FormValidatorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class LoginServiceTest extends TestCase
{
    private FormValidatorInterface&MockObject $validator;
    private UserModelInterface&MockObject $userModel;
    private SessionInterface&MockObject $session;

    private LoginService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REMOTE_ADDR']     = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $this->validator = $this->createMock(FormValidatorInterface::class);
        $this->userModel = $this->createMock(UserModelInterface::class);
        $this->session   = $this->createMock(SessionInterface::class);

        $this->service = new LoginService(
            $this->validator,
            $this->userModel,
            $this->session,
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
}
