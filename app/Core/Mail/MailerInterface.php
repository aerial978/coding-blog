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
}
