<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Core\Logger;
use App\Core\Mail\MailerInterface;
use Mailjet\Client;
use Mailjet\Resources;
use Mailjet\Response;

/**
 * Mailer implementation using the Mailjet API.
 *
 * This class provides real email sending capabilities through the Mailjet service.
 * It loads HTML templates, injects dynamic variables, and delivers messages via
 * Mailjet’s REST API using the official SDK.
 *
 * Typical use cases include registration confirmation, password reset,
 * and other transactional emails.
 */
final class MailjetMailer implements MailerInterface
{
    /**
     * Constructor.
     *
     * Initializes the Mailjet client configuration with API credentials
     * and default sender information.
     *
     * @param string $apiKey
     *     The public Mailjet API key.
     * @param string $apiSecret
     *     The private Mailjet API secret.
     * @param string $fromEmail
     *     The sender email address.
     * @param string $fromName
     *     The sender display name.
     */
    public function __construct(
        private string $apiKey,
        private string $apiSecret,
        private string $fromEmail,
        private string $fromName,
    ) {
    }

    /**
     * Sends an email using the Mailjet API and an HTML template.
     *
     * The method performs the following steps:
     * 1. Loads the specified template file.
     * 2. Replaces placeholder variables with provided values.
     * 3. Submits the message to Mailjet’s API for delivery.
     * 4. Logs both success and error events for diagnostic purposes.
     *
     * @param string $toEmail
     *     Recipient’s email address.
     * @param string $toName
     *     Recipient’s display name.
     * @param string $subject
     *     Subject line of the email.
     * @param string $template
     *     Template filename (must exist under `/resources/mail/templates/`).
     * @param array<string,string> $vars
     *     Key-value pairs of placeholders and their corresponding values.
     *
     * @return bool
     *     True if the email was successfully sent; false otherwise.
     */
    public function send(string $toEmail, string $toName, string $subject, string $template, array $vars = []): bool
    {
        try {
            $templatePath = dirname(__DIR__, 3) . '/resources/mail/templates/' . $template;

            if (!file_exists($templatePath)) {
                Logger::getLogger('mail')->error('Template email introuvable', ['template' => $templatePath]);
                return false;
            }

            $body = file_get_contents($templatePath);
            if ($body === false) {
                Logger::getLogger('mail')->error('Lecture template échouée', ['template' => $templatePath]);
                return false;
            }

            foreach ($vars as $k => $v) {
                $body = str_replace('{' . $k . '}', (string)$v, $body);
            }

            $mj      = new Client($this->apiKey, $this->apiSecret, true, ['version' => 'v3.1']);
            $payload = [
                'Messages' => [[
                    'From' => [
                        'Email' => $this->fromEmail,
                        'Name'  => $this->fromName,
                    ],
                    'To' => [[
                        'Email' => $toEmail,
                        'Name'  => $toName,
                    ]],
                    'Subject'  => $subject,
                    'HTMLPart' => $body,
                ]]
            ];

            /** @var Response $response */
            $response = $mj->post(Resources::$Email, ['body' => $payload]);

            if (!$response->success()) {
                Logger::getLogger('mail')->error('Mailjet HTTP error', [
                    'status' => $response->getStatus(),
                    'reason' => $response->getReasonPhrase(),
                    'body'   => $response->getBody(),
                ]);
                return false;
            }

            /** @var array{Messages?: list<array<string,mixed>>} $data */
            $data = $response->getData();

            $msg = null;
            if (isset($data['Messages'][0])) {
                /** @var array<string,mixed> $msg */
                $msg = $data['Messages'][0];
            }

            if (!is_array($msg) || (($msg['Status'] ?? '') !== 'success')) {
                Logger::getLogger('mail')->error('Mailjet functional error', [
                    'status'         => $response->getStatus(),
                    'message_status' => $msg['Status'] ?? null,
                    'errors'         => $msg['Errors'] ?? null,
                    'to'             => $toEmail,
                    'from'           => $this->fromEmail,
                ]);
                return false;
            }

            Logger::getLogger('mail')->info('Mailjet sent', [
                'to'      => $toEmail,
                'subject' => $subject,
            ]);
            return true;
        } catch (\Throwable $e) {
            Logger::getLogger('mail')->error('Mailjet exception', ['exception' => $e->getMessage()]);
            return false;
        }
    }
}
