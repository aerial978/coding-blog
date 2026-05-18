<?php

declare(strict_types=1);

namespace App\Core\Mail;

/**
 * Defines the contract for mailer implementations.
 *
 * Implementations of this interface are responsible for sending
 * templated email messages to recipients using various transport layers
 * (e.g., Mailjet API, SMTP, or dummy/test mailers).
 */
interface MailerInterface
{
    /**
     * Sends an email message using a predefined template.
     *
     * @param string $toEmail
     *     Recipient email address.
     * @param string $toName
     *     Recipient display name.
     * @param string $subject
     *     Subject line of the email.
     * @param string $template
     *     Name or path of the email template to render.
     * @param array<string,string> $vars
     *     Key-value pairs of variables to inject into the template.
     *
     * @return bool
     *     True if the message was successfully sent, false otherwise.
     */
    public function send(string $toEmail, string $toName, string $subject, string $template, array $vars = []): bool;

    /**
     * Sends the email 2FA verification code.
     *
     * This method is dedicated to the second authentication factor
     * during login. It sends a short-lived one-time verification code
     * by email after successful password validation.
     *
     * @param string $email
     *     Recipient email address.
     *
     * @param string $username
     *     Recipient display name.
     *
     * @param string $code
     *     The temporary one-time verification code.
     *
     * @param int $ttlMinutes
     *     Code validity duration in minutes.
     *
     * @return bool
     *     True if the email was successfully sent, false otherwise.
     */
    public function sendEmail2faCode(
        string $email,
        string $username,
        string $code,
        int $ttlMinutes
    ): bool;
}
