<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Centralized list of CSRF form identifiers.
 *
 * This class serves as a single source of truth for all CSRF token IDs
 * used across the application. It helps prevent hard-coded string values
 * from being scattered throughout the codebase, improving maintainability
 * and consistency when verifying CSRF tokens.
 */
final class FormId
{
    // Authentification
    public const REGISTER        = 'register_form';
    public const RESEND_CONFIRM  = 'resend_confirm_form';

    // Admin
}
