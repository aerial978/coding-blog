<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Core\Logger;
use App\Core\Mail\MailerInterface;
use RuntimeException;

/**
 * Dummy mailer implementation for development and testing environments.
 *
 * Instead of sending actual emails, this class logs the email details
 * (recipient, subject, and content preview) to the application logger.
 * It is particularly useful when working in a local or test environment
 * where real email delivery should be avoided.
 */
final class DummyMailer implements MailerInterface
{
    /**
     * Constructor.
     *
     * Initializes the dummy mailer with sender details.
     *
     * @param string $fromEmail
     *     The email address of the sender.
     * @param string $fromName
     *     The display name of the sender.
     */
    public function __construct(
        private string $fromEmail,
        private string $fromName
    ) {
    }

    /**
     * Simulates sending an email and logs the message content.
     *
     * The method loads an HTML template, replaces placeholder variables,
     * and writes a preview of the resulting email to the `mail` logger.
     * No real email is sent.
     *
     * @param string $toEmail
     *     The recipient's email address.
     * @param string $toName
     *     The recipient's display name.
     * @param string $subject
     *     The subject line of the email.
     * @param string $template
     *     The name of the email template file to load.
     * @param array<string, string> $vars
     *     Key-value pairs used to replace placeholders in the template.
     *
     * @return bool
     *     Always returns true to indicate the simulated send was successful.
     *
     * @throws \RuntimeException
     *     If the specified template file does not exist.
     */
    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $template,
        array $vars = []
    ): bool {
        $templatePath = dirname(__DIR__, 3) . '/resources/mail/templates/' . $template;
        if (!is_file($templatePath)) {
            throw new RuntimeException("Template email introuvable : {$templatePath}");
        }

        $html = file_get_contents($templatePath);
        if ($html === false) {
            throw new RuntimeException("Impossible de lire le template : {$templatePath}");
        }

        foreach ($vars as $k => $v) {
            $html = str_replace('{' . $k . '}', (string) $v, $html);
        }

        Logger::getLogger('mail')->info('DummyMailer: email not sent (dev mode)', [
            'from'    => sprintf('%s <%s>', $this->fromName, $this->fromEmail),
            'to'      => sprintf('%s <%s>', $toName, $toEmail),
            'subject' => $subject,
            'preview' => mb_substr(strip_tags($html), 0, 200),
        ]);

        return true;
    }
}
