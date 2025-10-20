<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use App\Core\ErrorCode;
use App\Validation\FormValidator;
use PHPUnit\Framework\TestCase;

final class FormValidatorTest extends TestCase
{
    private FormValidator $validator;
    protected function setUp(): void
    {
        $this->validator = new FormValidator();
    }

    // ======================
    // Cas valide (happy path)
    // ======================
    public function testValidateRegistrationReturnsEmptyErrorsForValidData(): void
    {
        $data = [
            'username' => 'ValidUser123',
            'email'    => 'valid@example.com',
            'password' => 'Str0ng@Password!',
        ];
        $errors = $this->validator->validateRegistration($data);
        $this->assertSame([], $errors, 'Aucun message d’erreur attendu pour des données valides.');
    }

    // ======================
    // Username
    // ======================
    public function testInvalidUsernameTooShort(): void
    {
        $data = [
            'username' => 'ab',
            'email'    => 'valid@example.com',
            'password' => 'Str0ng@Password!',
        ];
        $errors = $this->validator->validateRegistration($data);
        $this->assertArrayHasKey('username', $errors);
        $this->assertSame(ErrorCode::AUTH_USERNAME_INVALID, $errors['username']);
    }

    public function testInvalidUsernameWithSpecialChars(): void
    {
        $data = [
            'username' => 'bad$user',
            'email'    => 'valid@example.com',
            'password' => 'Str0ng@Password!',
        ];
        $errors = $this->validator->validateRegistration($data);
        $this->assertArrayHasKey('username', $errors);
    }

    // ======================
    // Email
    // ======================
    public function testInvalidEmailFormat(): void
    {
        $data = [
            'username' => 'ValidUser',
            'email'    => 'not-an-email',
            'password' => 'Str0ng@Password!',
        ];
        $errors = $this->validator->validateRegistration($data);
        $this->assertArrayHasKey('email', $errors);
        $this->assertSame(ErrorCode::AUTH_EMAIL_INVALID, $errors['email']);
    }

    // ======================
    // Password
    // ======================
    public function testInvalidPasswordTooShort(): void
    {
        $data = [
            'username' => 'ValidUser',
            'email'    => 'valid@example.com',
            'password' => 'Short1!',
        ];
        $errors = $this->validator->validateRegistration($data);
        $this->assertArrayHasKey('password', $errors);
        $this->assertSame(ErrorCode::AUTH_PASSWORD_INVALID, $errors['password']);
    }

    public function testInvalidPasswordMissingUppercase(): void
    {
        $data = [
            'username' => 'ValidUser',
            'email'    => 'valid@example.com',
            'password' => 'nouppercase123!',
        ];
        $errors = $this->validator->validateRegistration($data);
        $this->assertArrayHasKey('password', $errors);
    }

    public function testInvalidPasswordWithWhitespace(): void
    {
        $data = [
            'username' => 'ValidUser',
            'email'    => 'valid@example.com',
            'password' => 'Str0ng Pass word!',
        ];
        $errors = $this->validator->validateRegistration($data);
        $this->assertArrayHasKey('password', $errors);
    }
}
