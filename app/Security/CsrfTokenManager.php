<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Contract\SessionInterface;
use App\Security\Contract\CsrfTokenInterface;

/**
 * Manages generation and validation of CSRF tokens.
 *
 * This class implements CSRF protection by generating random, session-bound
 * tokens for individual forms and validating them upon form submission.
 * Each token is single-use and automatically invalidated after validation.
 *
 * CSRF (Cross-Site Request Forgery) protection ensures that forms cannot
 * be submitted by unauthorized third-party sites or scripts.
 */
final class CsrfTokenManager implements CsrfTokenInterface
{
    /** @var string The session key under which CSRF tokens are stored */
    private const BAG = '_csrf';

    /**
     * Constructor.
     *
     * Initializes the CSRF token manager with a session handler.
     *
     * @param SessionInterface $session
     *     Session manager instance used for storing and retrieving tokens.
     */
    public function __construct(private SessionInterface $session)
    {
    }

    /**
     * Generates a new CSRF token for a given form.
     *
     * The generated token is stored in the session and linked
     * to the provided form identifier.
     *
     * @param string $formId
     *     The unique identifier of the form to protect.
     *
     * @return string
     *     The generated CSRF token string.
     *
     * @throws \Exception
     *     If it fails to generate a cryptographically secure random token.
     */
    public function generateToken(string $formId): string
    {
        /** @var mixed $raw */
        $raw = $this->session->get(self::BAG, []);
        /** @var array<string,mixed> $bag */
        $bag = is_array($raw) ? $raw : [];

        $token        = bin2hex(random_bytes(32));
        $bag[$formId] = $token;

        $this->session->set(self::BAG, $bag);
        return $token;
    }

    /**
     * Validates a CSRF token for a given form identifier.
     *
     * The validation compares the provided token with the one stored
     * in the session using a timing-safe comparison. Tokens are invalidated
     * immediately after validation to prevent reuse.
     *
     * @param string $formId
     *     The unique identifier of the form being validated.
     * @param string|null $token
     *     The token value submitted with the form.
     *
     * @return bool
     *     True if the token is valid and matches the stored value, false otherwise.
     */
    public function validateToken(string $formId, ?string $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        /** @var mixed $raw */
        $raw = $this->session->get(self::BAG, []);
        /** @var array<string,mixed> $bag */
        $bag = is_array($raw) ? $raw : [];

        // On récupère la valeur stockée de façon sûre (string|null)
        $stored = isset($bag[$formId]) && is_string($bag[$formId]) ? $bag[$formId] : null;

        $valid = is_string($stored) && hash_equals($stored, $token);

        unset($bag[$formId]);                // jeton à usage unique
        $this->session->set(self::BAG, $bag);

        return $valid;
    }

    /**
     * Generates a token for a specific form constant.
     *
     * This is a convenient alias of generateToken() used when referencing
     * forms via constants defined in FormId.
     *
     * @param string $formConstant
     *     The form identifier constant.
     *
     * @return string
     *     The generated CSRF token.
     */
    public function forForm(string $formConstant): string
    {
        return $this->generateToken($formConstant);
    }

    /**
     * Validates a token against a form constant.
     *
     * This is a convenience wrapper for validateToken(), typically used
     * in controllers when validating CSRF-protected forms.
     *
     * @param string $formConstant
     *     The form identifier constant.
     * @param string|null $token
     *     The submitted CSRF token.
     *
     * @return bool
     *     True if the token is valid; false otherwise.
     */
    public function isValidForForm(string $formConstant, ?string $token): bool
    {
        return $this->validateToken($formConstant, $token);
    }
}
