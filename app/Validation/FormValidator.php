<?php

declare(strict_types=1);

namespace App\Validation;

use App\Core\ErrorCode;

/**
     * Validates user-submitted form data.
     *
     * This validator focuses on registration inputs and returns a map of field
     * names to error codes (from {@see ErrorCode}) when validation fails.
     * On success, it returns an empty array.
     */
final class FormValidator
{
    /**
     * Validates registration data.
     *
     * Validation rules:
     *  - username: 3–20 chars, letters/digits/underscore, must include at least one letter.
     *  - email: valid RFC-compliant email format.
     *  - password: minimum 12 chars, at least one lowercase, one uppercase, one digit,
     *              one non-alphanumeric, and must contain no whitespace.
     *
     * @param array<string, mixed> $data
     *     Expected keys: 'username', 'email', 'password', 'confirm_password' (optional here).
     *
     * @return array<string, string>
     *     Associative array of field => ErrorCode constant; empty array if valid.
     */
    public function validateRegistration(array $data): array
    {
        /** @var string $username */
        $username = is_string($data['username'] ?? null) ? trim($data['username']) : '';

        /** @var string $email */
        $email = is_string($data['email'] ?? null) ? trim($data['email']) : '';

        /** @var string $password */
        $password = is_string($data['password'] ?? null) ? $data['password'] : '';

        $errors = [];

        // username: 3–20 chars, lettres/chiffres/underscore, au moins une lettre
        if ($username === '' || preg_match('/^(?=.*[a-zA-Z])[a-zA-Z0-9_]{3,20}$/', $username) !== 1) {
            $errors['username'] = ErrorCode::AUTH_USERNAME_INVALID;
        }

        // email valide
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = ErrorCode::AUTH_EMAIL_INVALID;
        }

        // password: min 12, une minuscule, une majuscule, un chiffre, un spécial, pas d’espace
        if (
            $password === '' ||
            preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d])[^\s]{12,}$/', $password) !== 1
        ) {
            $errors['password'] = ErrorCode::AUTH_PASSWORD_INVALID;
        }

        // (optionnel) garde-fou supplémentaire contre les espaces
        if ($password !== '' && preg_match('/\s/', $password) === 1) {
            $errors['password'] = ErrorCode::AUTH_PASSWORD_INVALID;
        }

        return $errors;
    }
}
