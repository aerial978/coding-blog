<?php

declare(strict_types=1);

namespace App\Security;

use App\Security\Contract\TokenGeneratorInterface;
use InvalidArgumentException;

/**
 * Generates and hashes cryptographically secure tokens.
 *
 * This class provides utilities for creating random, URL-safe tokens
 * and securely hashing them before storage. The generated tokens are
 * typically used for account confirmation, password reset, or other
 * security-sensitive operations.
 *
 * Implements TokenGeneratorInterface to ensure consistent API usage
 * across the security layer.
 */
final class TokenGenerator implements TokenGeneratorInterface
{
    /**
     * Generates a cryptographically secure, URL-safe token.
     *
     * The token is generated using random bytes, then base64-encoded and
     * transformed into a URL-safe format by replacing "+" and "/" with
     * "-" and "_", and removing any padding characters ("=").
     *
     * @param int $bytes
     *     Number of random bytes to generate before encoding (default: 32).
     *
     * @return string
     *     A URL-safe, random token string suitable for inclusion in URLs.
     *
     * @throws \Exception
     *     If it fails to generate a cryptographically secure random value.
     */
    public function generateUrlSafeToken(int $bytes = 32): string
    {
        if ($bytes < 1) {
            throw new InvalidArgumentException('Token length must be at least 1 byte.');
        }

        $raw = random_bytes($bytes);
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * Produces a secure SHA-256 hash of the given token.
     *
     * The hash is returned in binary form (32 bytes), making it suitable
     * for efficient storage and secure comparison in databases.
     *
     * @param string $token
     *     The raw token value to be hashed.
     *
     * @return string
     *     The binary SHA-256 hash of the provided token.
     */
    public function hashToken(string $token): string
    {
        return hash('sha256', $token, true);
    }
}
