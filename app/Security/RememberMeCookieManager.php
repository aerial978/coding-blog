<?php

declare(strict_types=1);

namespace App\Security;

use App\Security\Contract\RememberMeCookieManagerInterface;

final class RememberMeCookieManager implements RememberMeCookieManagerInterface
{
    private const COOKIE_NAME = 'remember_me';
    private const COOKIE_TTL  = 2592000; // 30 days

    public function __construct(
        private readonly string $cookiePath
    ) {
    }

    public function createCookie(string $rawToken): void
    {
        $rawToken = trim($rawToken);

        if ($rawToken === '') {
            return;
        }

        setcookie(
            self::COOKIE_NAME,
            $rawToken,
            [
                'expires'  => time() + self::COOKIE_TTL,
                'path'     => $this->cookiePath,
                'secure'   => $this->isSecureRequest(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    public function expireCookie(): void
    {
        setcookie(
            self::COOKIE_NAME,
            '',
            [
                'expires'  => time() - 3600,
                'path'     => $this->cookiePath,
                'secure'   => $this->isSecureRequest(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    public function getCookieValue(): ?string
    {
        $value = $_COOKIE[self::COOKIE_NAME] ?? null;

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function isSecureRequest(): bool
    {
        return $this->isHttpsEnabled()
            || $this->isHttpsPort()
            || $this->isForwardedHttps();
    }

    private function isHttpsEnabled(): bool
    {
        $https = $_SERVER['HTTPS'] ?? null;

        return is_string($https)
            && $https             !== ''
            && strtolower($https) !== 'off';
    }

    private function isHttpsPort(): bool
    {
        $serverPort = $_SERVER['SERVER_PORT'] ?? null;

        return is_string($serverPort) && $serverPort === '443';
    }

    private function isForwardedHttps(): bool
    {
        $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null;

        return is_string($forwardedProto)
        && strtolower($forwardedProto) === 'https';
    }
}
