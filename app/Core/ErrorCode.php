<?php

namespace App\Core;

final class ErrorCode
{
    public const AUTH_FIELD_REQUIRED          = 'auth.field.required';

    // ==== Auth - Registration ====
    public const AUTH_USERNAME_EXISTS         = 'auth.registration.username_exists';
    public const AUTH_EMAIL_EXISTS            = 'auth.registration.email_exists';
    public const AUTH_USERNAME_INVALID        = 'auth.registration.username_invalid';
    public const AUTH_EMAIL_INVALID           = 'auth.registration.email_invalid';
    public const AUTH_PASSWORD_INVALID        = 'auth.registration.password_invalid';
    public const AUTH_ACCOUNT_SUCCESS         = 'auth.registration.success';
    public const AUTH_REGISTRATION_FAILED     = 'auth.registration.failed';
    public const AUTH_PASSWORD_REENTER        = 'auth.registration.password_reenter';


    public const AUTH_CSRF_INVALID            = 'auth.csrf_invalid';
    public const AUTH_RATE_LIMITED_DYNAMIC    = 'auth.rate_limited_dynamic';

    public const AUTH_CONFIRMATION_SUCCESS      = 'auth.confirmation.success';
    public const AUTH_INVALID_CONFIRM_TOKEN     = 'auth.confirmation.invalid_token';
    public const AUTH_ALREADY_CONFIRMED         = 'auth.confirmation.already_confirmed';
    public const AUTH_CONFIRM_TOKEN_USED        = 'auth.confirmation.token_used';
    public const AUTH_CONFIRM_EMAIL_SEND_FAILED = 'auth.confirmation.send_failed';
    public const AUTH_ACCOUNT_CONFIRMATION_SENT = 'auth.confirmation.email_sent';

    public const AUTH_RESEND_EMAIL_SENT          = 'auth.resend.email_sent';
    public const AUTH_RESEND_EMAIL_FAILED        = 'auth.resend.email_failed';

    public const AUTH_TECHNICAL_ERROR         = 'auth.technical_error';
}
