<?php

declare(strict_types=1);

namespace App\Security\Contract;

/**
 * Defines the contract for secure token generation and hashing.
 *
 * Implementations of this interface are responsible for creating
 * cryptographically secure tokens and for producing hashed versions
 * that can be safely stored or compared without exposing the original
 * token value.
 *
 * Typical use cases include password reset tokens, account confirmation,
 * or API authentication tokens.
 */
interface TokenGeneratorInterface
{
    /**
     * Generates a cryptographically secure, URL-safe token.
     *
     * The token is typically a base64-encoded string derived from random bytes,
     * suitable for inclusion in URLs or email links.
     *
     * @param int $bytes
     *     Number of random bytes to generate before encoding (default: 32).
     *
     * @return string
     *     A URL-safe, random token string.
     */
    public function generateUrlSafeToken(int $bytes = 32): string;
    /**
         * Computes a secure hash of a given token.
         *
         * The hash should be one-way and suitable for storage in a database,
         * allowing tokens to be compared safely without revealing their plaintext.
         *
         * @param string $token
         *     The raw token value to hash.
         *
         * @return string
         *     The hashed representation of the token.
         */
    public function hashToken(string $token): string;
}
