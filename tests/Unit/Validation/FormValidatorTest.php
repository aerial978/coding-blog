<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use App\Core\ErrorCode;
use App\Validation\FormValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FormValidatorTest extends TestCase
{
    private FormValidator $v;

    protected function setUp(): void
    {
        $this->v = new FormValidator();
    }

    // ---------------- normalize ----------------

    public static function normalizeProvider(): array
    {
        return [
            'null'        => [null, ''],
            'int'         => [123, ''],
            'string'      => [' hello ', 'hello'],
            'empty'       => ['', ''],
            'spaces_only' => ['   ', ''],
        ];
    }

    #[DataProvider('normalizeProvider')]
    public function test_normalize(mixed $input, string $expected): void
    {
        $this->assertSame($expected, $this->v->normalize($input));
    }

    // ---------------- required ----------------

    public static function requiredProvider(): array
    {
        return [
            'empty'  => ['', ErrorCode::AUTH_FIELD_REQUIRED],
            'spaces' => ['   ', null],
            'ok'     => ['x', null],
        ];
    }

    #[DataProvider('requiredProvider')]
    public function test_required_rule(string $value, ?string $expected): void
    {
        $rule = $this->v->required();
        $this->assertSame($expected, $rule($value));
    }

    // ---------------- email ----------------

    public static function emailRuleProvider(): array
    {
        return [
            'empty' => ['', ErrorCode::AUTH_EMAIL_INVALID],
            'bad'   => ['foo@bar', ErrorCode::AUTH_EMAIL_INVALID],
            'good'  => ['john@example.test', null],
        ];
    }

    #[DataProvider('emailRuleProvider')]
    public function test_email_rule(string $value, ?string $expected): void
    {
        $rule = $this->v->email();
        $this->assertSame($expected, $rule($value));
    }

    public static function validateEmailFieldProvider(): array
    {
        return [
            'empty'      => ['', ErrorCode::AUTH_EMAIL_INVALID],
            'spaces'     => ['  ', ErrorCode::AUTH_EMAIL_INVALID],
            'bad_format' => ['a@b', ErrorCode::AUTH_EMAIL_INVALID],
            'ok'         => ['user@example.test', null],
            'trim_ok'    => ['  user@example.test  ', null],
        ];
    }

    #[DataProvider('validateEmailFieldProvider')]
    public function test_validateEmailField(string $email, ?string $expected): void
    {
        $this->assertSame($expected, $this->v->validateEmailField($email));
    }

    // ---------------- username ----------------

    public static function usernameProvider(): array
    {
        return [
            'too_short' => ['ab', ErrorCode::AUTH_USERNAME_INVALID],
            'too_long'  => ['abcdefghijklmnopqrstu', ErrorCode::AUTH_USERNAME_INVALID],
            'no_letter' => ['12345', ErrorCode::AUTH_USERNAME_INVALID],
            'bad_chars' => ['john-doe', ErrorCode::AUTH_USERNAME_INVALID],
            'ok_min'    => ['abc', null],
            'ok_mix'    => ['john_123', null],
            'ok_max'    => ['abcdefghijklmnopqrst', null],
        ];
    }

    #[DataProvider('usernameProvider')]
    public function test_username_rule(string $value, ?string $expected): void
    {
        $rule = $this->v->username();
        $this->assertSame($expected, $rule($value));
    }

    // ---------------- password ----------------

    public static function passwordProvider(): array
    {
        return [
            'too_short'    => ['Aa1!Aa1!', ErrorCode::AUTH_PASSWORD_INVALID],
            'no_upper'     => ['aa1!aa1!aa1!', ErrorCode::AUTH_PASSWORD_INVALID],
            'no_lower'     => ['AA1!AA1!AA1!', ErrorCode::AUTH_PASSWORD_INVALID],
            'no_digit'     => ['Aa!Aa!Aa!Aa!', ErrorCode::AUTH_PASSWORD_INVALID],
            'no_special'   => ['Aa1Aa1Aa1Aa1', ErrorCode::AUTH_PASSWORD_INVALID],
            'has_space'    => ['Aa1! aa1!aa1!', ErrorCode::AUTH_PASSWORD_INVALID],
            'valid_strong' => ['Aa1!Bb2@Cc3#', null],
        ];
    }

    #[DataProvider('passwordProvider')]
    public function test_password_rule(string $value, ?string $expected): void
    {
        $rule = $this->v->password();
        $this->assertSame($expected, $rule($value));
    }

    // ---------------- minLen / maxLen / inArray / regex ----------------

    public function test_minLen_rule(): void
    {
        $min5 = $this->v->minLen(5, 'ERR_MIN');
        $this->assertSame('ERR_MIN', $min5('foo'));
        $this->assertNull($min5('hello'));
    }

    public function test_maxLen_rule(): void
    {
        $max5 = $this->v->maxLen(5, 'ERR_MAX');
        $this->assertNull($max5('hello'));
        $this->assertSame('ERR_MAX', $max5('hellooo'));
    }

    public function test_inArray_rule(): void
    {
        $rule = $this->v->inArray(['admin', 'editor'], 'ERR_ROLE');
        $this->assertNull($rule('admin'));
        $this->assertSame('ERR_ROLE', $rule('guest'));
    }

    public function test_regex_rule(): void
    {
        $rule = $this->v->regex('/^\d{3}$/', 'ERR_CODE3');
        $this->assertNull($rule('123'));
        $this->assertSame('ERR_CODE3', $rule('12a'));
    }

    // ---------------- validate() moteur générique ----------------

    public function test_validate_generic_engine_applies_normalize_and_stops_on_first_error_per_field(): void
    {
        $schema = [
            'name'  => [$this->v->required(), $this->v->minLen(4, 'ERR_MIN4')],
            'email' => [$this->v->email()],
        ];

        $errors = $this->v->validate(['name' => '  ', 'email' => 'bad'], $schema);
        $this->assertSame([
            'name'  => ErrorCode::AUTH_FIELD_REQUIRED,
            'email' => ErrorCode::AUTH_EMAIL_INVALID,
        ], $errors);

        $errors = $this->v->validate(['name' => 'Bob', 'email' => 'bad'], $schema);
        $this->assertSame([
            'name'  => 'ERR_MIN4',
            'email' => ErrorCode::AUTH_EMAIL_INVALID,
        ], $errors);

        $errors = $this->v->validate(['name' => 'John', 'email' => 'bad'], $schema);
        $this->assertSame(['email' => ErrorCode::AUTH_EMAIL_INVALID], $errors);

        $errors = $this->v->validate(['name' => 'John', 'email' => 'john@example.test'], $schema);
        $this->assertSame([], $errors);
    }

    // ---------------- validateRegistration() ----------------

    public function test_validateRegistration_covers_all_fields_and_rules(): void
    {
        $errors = $this->v->validateRegistration([
            'username' => 'a',
            'email'    => 'bad',
            'password' => 'weak',
        ]);

        $this->assertCount(3, $errors);
        $this->assertContains(ErrorCode::AUTH_USERNAME_INVALID, $errors);
        $this->assertContains(ErrorCode::AUTH_EMAIL_INVALID, $errors);
        $this->assertContains(ErrorCode::AUTH_PASSWORD_INVALID, $errors);

        $errors = $this->v->validateRegistration([
            'username' => 'john_doe',
            'email'    => 'john@example.test',
            'password' => 'Aa1!Bb2@Cc3#',
        ]);

        $this->assertSame([], $errors);
    }
}
