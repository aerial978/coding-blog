<?php

declare(strict_types=1);

namespace App\Security\Contract;

/**
 * Defines the contract for CSRF token generation and validation.
 *
 * Implementations of this interface are responsible for creating
 * and verifying tokens used to protect forms against Cross-Site
 * Request Forgery (CSRF) attacks.
 *
 * Tokens should be unique per form and per session, and their validity
 * must be checked upon each form submission.
 */
interface CsrfTokenInterface
{
    /**
     * Generates a CSRF token for a given form identifier.
     *
     * The token should be random, unique, and bound to the current session
     * to prevent replay or forgery attacks.
     *
     * @param string $formId
     *     Unique identifier of the protected form (e.g., 'register_form').
     *
     * @return string
     *     The generated CSRF token value.
     */
    public function generateToken(string $formId): string;
    /**
         * Validates a CSRF token for a given form identifier.
         *
         * Compares the submitted token against the one stored in the session
         * or another trusted storage location to confirm authenticity.
         *
         * @param string $formId
         *     The form identifier used when the token was generated.
         * @param string|null $token
         *     The token value received from the client. May be null if not provided.
         *
         * @return bool
         *     True if the token is valid and matches the stored value; false otherwise.
         */
    public function validateToken(string $formId, ?string $token): bool;
}
