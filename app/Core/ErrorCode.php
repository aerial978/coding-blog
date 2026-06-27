<?php

namespace App\Core;

final class ErrorCode
{
    public const AUTH_FIELD_REQUIRED          = 'auth.field.required';

    // ==== Auth - Registration ====
    public const AUTH_USERNAME_EXISTS               = 'auth.registration.username_exists';
    public const AUTH_EMAIL_EXISTS                  = 'auth.registration.email_exists';
    public const AUTH_USERNAME_INVALID              = 'auth.registration.username_invalid';
    public const AUTH_EMAIL_INVALID                 = 'auth.registration.email_invalid';
    public const AUTH_PASSWORD_INVALID              = 'auth.registration.password_invalid';
    public const AUTH_ACCOUNT_SUCCESS               = 'auth.registration.success';
    public const AUTH_REGISTRATION_FAILED           = 'auth.registration.failed';
    public const AUTH_PASSWORD_REENTER              = 'auth.registration.password_reenter';
    public const AUTH_REGISTRATION_QUOTA_EXCEEDED   = 'auth.registration.quota_excedeed';
    public const AUTH_PASSWORD_TOO_COMMON           = 'auth.registration.password_common';
    public const AUTH_REGISTRATION_EMAIL_DISPOSABLE = 'auth.registration.email_disposable';

    public const AUTH_CSRF_INVALID              = 'auth.csrf_invalid';
    public const AUTH_RATE_LIMITED_DYNAMIC      = 'auth.rate_limited_dynamic';

    public const AUTH_CONFIRMATION_SUCCESS      = 'auth.confirmation.success';
    public const AUTH_INVALID_CONFIRM_TOKEN     = 'auth.confirmation.invalid_token';
    public const AUTH_ALREADY_CONFIRMED         = 'auth.confirmation.already_confirmed';
    public const AUTH_CONFIRM_TOKEN_USED        = 'auth.confirmation.token_used';
    public const AUTH_CONFIRM_EMAIL_SEND_FAILED = 'auth.confirmation.send_failed';
    public const AUTH_ACCOUNT_CONFIRMATION_SENT = 'auth.confirmation.email_sent';

    public const AUTH_RESEND_EMAIL_SENT         = 'auth.resend.email_sent';
    public const AUTH_RESEND_EMAIL_FAILED       = 'auth.resend.email_failed';
    public const AUTH_RESEND_QUOTA_EXCEEDED     = 'auth.resend.email_exceeded';

    public const AUTH_INVALID_CREDENTIALS       = 'auth.login.invalid_credentials';

    public const AUTH_PASSWORD_RESET_REQUESTED          = 'auth.password.reset.requested';
    public const AUTH_PASSWORD_RESET_EMAIL_SEND_FAILED  = 'auth.password.reset.send_failed';
    public const AUTH_PASSWORD_RESET_QUOTA_EXCEEDED     = 'auth.password.resend.email_exceeded';
    public const AUTH_PASSWORD_RESET_TOKEN_INVALID      = 'auth.password.reset.tokeen.invalid';
    public const AUTH_PASSWORD_RESET_SUCCESS            = 'auth.password.reset.success';
    public const AUTH_PASSWORD_RESET_PASSWORD_INVALID   = 'auth.password.reset.password.invalid';
    public const AUTH_PASSWORD_RESET_CONFIRM_MISMATCH   = 'auth.password.reset.confirm.mismatch';

    public const AUTH_GOOGLE_INVALID_RESPONSE         = 'auth.google.invalid_response';
    public const AUTH_GOOGLE_INVALID_STATE            = 'auth.google.invalid_state';
    public const AUTH_GOOGLE_PROFILE_INVALID          = 'auth.google.profile_invalid';
    public const AUTH_GOOGLE_LOCAL_ACCOUNT_NOT_FOUND  = 'auth.google.local_account_not_found';
    public const AUTH_GOOGLE_LINKED_ACCOUNT_INVALID   = 'auth.google.linked_account_invalid';
    public const AUTH_GOOGLE_USER_INVALID             = 'auth.google.user_invalid';
    public const AUTH_GOOGLE_LINK_CREATION_FAILED     = 'auth.google.link_creation_failed';
    public const AUTH_GOOGLE_TECHNICAL_ERROR          = 'auth.google.technical_error';
    public const AUTH_GOOGLE_LOCAL_ACCOUNT_INACTIVE   = 'auth.google.local_account_inactive';
    public const AUTH_GOOGLE_ACCESS_DENIED            = 'auth.google.access_denied';

    public const AUTH_LOGOUT_SUCCESS                    = 'auth.logout.success';

    public const AUTH_RETRY                     = 'auth.retry';
    public const AUTH_TECHNICAL_ERROR           = 'auth.technical_error';
    public const AUTH_FORM_EXPIRED              = 'auth.form.expired';
}
