<?php

// TODO : Prévoir un système de traduction multi-langues (ex: fr.php, en.php) pour charger les messages dynamiquement selon la langue de l'utilisateur. Support multilingue facile à mettre en place plus tard.

namespace App\Core;

class MessageManager
{
    public static function get(string $code): string
    {
        $messages = [
            ErrorCode::AUTH_FIELD_REQUIRED            => 'Champ(s) requis.',

            // ==== Auth - Registration ====
            ErrorCode::AUTH_USERNAME_INVALID               => "Le nom d'utilisateur est invalide (3 à 20 caractères alphanumériques ou underscores).",
            ErrorCode::AUTH_USERNAME_EXISTS                => "Nom d'utilisateur déjà utilisé.",
            ErrorCode::AUTH_EMAIL_INVALID                  => 'Adresse e-mail invalide.',
            ErrorCode::AUTH_EMAIL_EXISTS                   => 'Cette adresse email est déjà associée à un compte.',
            ErrorCode::AUTH_PASSWORD_INVALID               => 'Le mot de passe ne respecte pas les règles de sécurité.',
            ErrorCode::AUTH_REGISTRATION_FAILED            => 'Une erreur est survenue lors de votre inscription.',
            ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED      => 'Impossible d’envoyer l’email de confirmation. Réessayez ci-dessous.',
            ErrorCode::AUTH_ACCOUNT_CONFIRMATION_SENT      => 'Un email de confirmation vous a été envoyé pour activer votre compte.',
            ErrorCode::AUTH_PASSWORD_REENTER               => 'Pour votre sécurité, le mot de passe a été effacé.',
            ErrorCode::AUTH_REGISTRATION_QUOTA_EXCEEDED    => 'Trop de créations de compte depuis votre adresse. Veuillez réessayer plus tard.',
            ErrorCode::AUTH_PASSWORD_TOO_COMMON            => 'Ce mot de passe est trop courant. Merci d’en choisir un plus robuste.',
            ErrorCode::AUTH_REGISTRATION_EMAIL_DISPOSABLE  => 'Ce type d’adresse e-mail ne peut pas être utilisé. Merci d’en choisir une plus fiable (pas d’adresse jetable).',

            ErrorCode::AUTH_CONFIRM_TOKEN_USED        => 'Ce lien a déjà été utilisé. Votre compte est probablement déjà confirmé. Vous pouvez maintenant vous connecter.',
            ErrorCode::AUTH_CONFIRMATION_SUCCESS      => 'Votre compte a été activé avec succès. Vous pouvez maintenant vous connecter.',
            ErrorCode::AUTH_INVALID_CONFIRM_TOKEN     => 'Le lien de confirmation est invalide ou a expiré. Veuillez demander un nouvel envoi.',
            ErrorCode::AUTH_ALREADY_CONFIRMED         => 'Votre compte est déjà activé. Vous pouvez vous connecter.',

            ErrorCode::AUTH_CSRF_INVALID              => 'Une erreur de sécurité est survenue. Veuillez réessayer.',
            ErrorCode::AUTH_RATE_LIMITED_DYNAMIC      => 'Trop de tentatives. Veuillez réessayer dans {time}.',

            // ==== Auth - Resend Confirmation ====
            ErrorCode::AUTH_RESEND_EMAIL_SENT         => 'Si un compte existe pour cette adresse, un nouveau lien d’activation vient d’être envoyé.',
            ErrorCode::AUTH_RESEND_EMAIL_FAILED       => 'Impossible d’envoyer le lien de confirmation pour le moment.',
            ErrorCode::AUTH_RESEND_QUOTA_EXCEEDED     => 'Vous avez demandé trop de renvois de confirmation. Veuillez patienter avant de réessayer.',

            // ==== Technique générale ====
            ErrorCode::AUTH_TECHNICAL_ERROR           => 'Une erreur technique est survenue. Merci de réessayer plus tard.',
            ErrorCode::AUTH_FORM_EXPIRED              => 'Pour des raisons de sécurité, ce formulaire a expiré. Merci de recommencer.',
        ];

        return $messages[$code] ?? $messages[ErrorCode::AUTH_TECHNICAL_ERROR];
    }
}
