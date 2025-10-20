<?php

declare(strict_types=1);

namespace Tests\Double;

use App\Security\Contract\CsrfTokenInterface;

final class CsrfFake implements CsrfTokenInterface
{
    private bool $valid;
    private string $fixedToken;
    public function __construct(bool $valid = true, string $fixedToken = 'csrf_test_token')
    {
        $this->valid      = $valid;
        $this->fixedToken = $fixedToken;
    }

    public function setValid(bool $valid): void
    {
        $this->valid = $valid;
    }

    public function generateToken(string $formId): string
    {
        return $this->fixedToken;
    }

    public function validateToken(string $formId, ?string $token): bool
    {
        if (!$this->valid) {
            return false;
        }
        return is_string($token) && hash_equals($this->fixedToken, $token);
    }
}
