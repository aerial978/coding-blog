<?php

declare(strict_types=1);

namespace App\Validation;

use App\Core\ErrorCode;
use App\Validation\Contract\FormValidatorInterface;

final class FormValidator implements FormValidatorInterface
{
    // ——— Patterns (réutilisés partout) ———
    private const USERNAME_PATTERN = '/^(?=.*[a-zA-Z])[a-zA-Z0-9_]{3,20}$/';
    private const PASSWORD_PATTERN = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d])[^\s]{12,}$/';
    // ——— Normalisation simple ———
    public function normalize(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    // ——— Fabriques de règles (retournent des closures) ———

    /** @return callable(string): (?string) */
    public function required(string $error = ErrorCode::AUTH_FIELD_REQUIRED): callable
    {
        return fn (string $value): ?string => ($value === '' ? $error : null);
    }

    /** @return callable(string): (?string) */
    public function email(string $error = ErrorCode::AUTH_EMAIL_INVALID): callable
    {
        return fn (string $value): ?string =>
            ($value === '' || filter_var($value, FILTER_VALIDATE_EMAIL) === false) ? $error : null;
    }

    /** @return callable(string): (?string) */
    public function username(string $error = ErrorCode::AUTH_USERNAME_INVALID): callable
    {
        return fn (string $value): ?string =>
            (preg_match(self::USERNAME_PATTERN, $value) === 1 ? null : $error);
    }

    /** @return callable(string): (?string) */
    public function password(string $error = ErrorCode::AUTH_PASSWORD_INVALID): callable
    {
        return fn (string $value): ?string =>
            (preg_match(self::PASSWORD_PATTERN, $value) === 1 ? null : $error);
    }

    /** @return callable(string): (?string) */
    public function minLen(int $minLen, string $error): callable
    {
        return fn (string $value): ?string => (mb_strlen($value) < $minLen ? $error : null);
    }

    /** @return callable(string): (?string) */
    public function maxLen(int $maxLen, string $error): callable
    {
        return fn (string $value): ?string => (mb_strlen($value) > $maxLen ? $error : null);
    }

    /** @param list<string> $allowed
     *  @return callable(string): (?string)
     */
    public function inArray(array $allowed, string $error): callable
    {
        return fn (string $value): ?string => (in_array($value, $allowed, true) ? null : $error);
    }

    /** @return callable(string): (?string) */
    public function regex(string $pattern, string $error): callable
    {
        return fn (string $value): ?string => (preg_match($pattern, $value) === 1 ? null : $error);
    }

    // ——— Moteur générique ———

    /**
     * @param array<string,mixed> $data
     * @param array<string, array<callable(string):(?string)>> $schema
     * @return array<string,string>  // champ => premier code d'erreur
     */
    public function validate(array $data, array $schema): array
    {
        $errors = [];
        foreach ($schema as $field => $rules) {
            $value = $this->normalize($data[$field] ?? null);
            foreach ($rules as $rule) {
                $error = $rule($value);
                if ($error !== null) {
                    $errors[$field] = $error;
                    break;
                }
            }
        }
        return $errors;
    }

    // ——— API “use-case” existante, réécrite sur le moteur générique ———

    /**
     * @param array<string,mixed> $data
     * @return list<string>   // liste plate de codes d'erreurs
     */
    public function validateRegistration(array $data): array
    {
        $byField = $this->validate($data, [
            'username' => [$this->required(), $this->username()],
            'email'    => [$this->email()],
            'password' => [$this->required(), $this->password()],
        ]);

        /** @var list<string> $out */
        $out = array_values($byField); // on aplatit: garde l’ordre d’apparition
        return $out;
    }

    /**
     * @param array<string,mixed> $data
     * @return list<string>
     */
    public function validateLogin(array $data): array
    {
        $byField = $this->validate($data, [
            'identifier' => [$this->required()],
            'password'   => [$this->required()],
        ]);

        /** @var list<string> $out */
        $out = array_values($byField);
        return $out;
    }

    /**
     * Validation e-mail réutilisable (resend, login, forgot…)
     */
    public function validateEmailField(string $email): ?string
    {
        $email = $this->normalize($email);
        return ($this->email())($email);
    }

    public function validatePasswordField(string $password): ?string
    {
        $password = $this->normalize($password);

        // Réutilise la règle "password()" déjà définie (regex + ErrorCode)
        return ($this->password())($password);
    }
}
