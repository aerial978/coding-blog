<?php

declare(strict_types=1);

namespace App\Security;

final class PasswordBlacklist
{
    /** @var array<string,bool> */
    private array $map = [];

    /**
     * @param array<int,string> $passwords
     */
    public function __construct(array $passwords)
    {
        foreach ($passwords as $pwd) {
            $pwd = trim($pwd);
            if ($pwd === '') {
                continue;
            }

            $normalized             = mb_strtolower($pwd);
            $this->map[$normalized] = true;
        }
    }

    public function isBlacklisted(string $password): bool
    {
        $normalized = mb_strtolower(trim($password));
        return isset($this->map[$normalized]);
    }
}
