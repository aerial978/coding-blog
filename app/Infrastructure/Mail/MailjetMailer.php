<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Core\Logger;
use App\Core\Mail\MailerInterface;
use Mailjet\Client;
use Mailjet\Resources;
use Mailjet\Response;
use RuntimeException;

/**
 * Mailer implementation using the Mailjet API.
 */
final class MailjetMailer implements MailerInterface
{
    public function __construct(
        private string $apiKey,
        private string $apiSecret,
        private string $fromEmail,
        private string $fromName,
    ) {
    }

    /**
     * @param array<string,string> $vars
     */
    public function send(string $toEmail, string $toName, string $subject, string $template, array $vars = []): bool
    {
        try {
            $templatePath = $this->resolveTemplatePath($template);
            $rawHtml      = $this->readTemplate($templatePath);
            $html         = $this->renderTemplate($rawHtml, $vars);

            $client  = $this->createClient();
            $payload = $this->buildPayload($toEmail, $toName, $subject, $html);

            $response = $this->postEmail($client, $payload);

            return $this->responseOk($response, $toEmail);
        } catch (\Throwable $e) {
            Logger::getLogger('mail')->error('Mailjet exception', ['exception' => $e->getMessage()]);
            return false;
        }
    }

    // ─────────────────────────────
    // Helpers (petites responsabilités)
    // ─────────────────────────────

    private function resolveTemplatePath(string $template): string
    {
        // /app/Infrastructure/Mail -> remonte de 3 niveaux à la racine projet
        return dirname(__DIR__, 3) . '/resources/mail/templates/' . $template;
    }

    private function readTemplate(string $path): string
    {
        if (!is_file($path)) {
            Logger::getLogger('mail')->error('Template email introuvable', ['template' => $path]);
            throw new RuntimeException('Template not found: ' . $path);
        }

        $body = file_get_contents($path);
        if ($body === false) {
            Logger::getLogger('mail')->error('Lecture template échouée', ['template' => $path]);
            throw new RuntimeException('Template read failed: ' . $path);
        }

        return $body;
    }

    /**
     * @param array<string,string> $vars
     */
    private function renderTemplate(string $html, array $vars): string
    {
        foreach ($vars as $k => $v) {
            $html = str_replace('{' . $k . '}', (string) $v, $html);
        }
        return $html;
    }

    private function createClient(): Client
    {
        return new Client($this->apiKey, $this->apiSecret, true, ['version' => 'v3.1']);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(string $toEmail, string $toName, string $subject, string $html): array
    {
        return [
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
                'HTMLPart' => $html,
            ]],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postEmail(Client $client, array $payload): Response
    {
        /** @var Response $resp */
        $resp = $client->post(Resources::$Email, ['body' => $payload]);
        return $resp;
    }

    private function responseOk(Response $response, string $toEmail): bool
    {
        // 1) HTTP layer
        if (!$this->checkHttpSuccess($response)) {
            return false;
        }

        // 2) Extract business payload
        $msg = $this->extractFirstMessage($response);

        // 3) Business status + logging (also logs on failure)
        return $this->handleFunctionalStatus($msg, $response, $toEmail);
    }

    /**
     * Check Mailjet HTTP response and log a detailed error when it is not successful.
     */
    private function checkHttpSuccess(Response $response): bool
    {
        if ($response->success()) {
            return true;
        }

        Logger::getLogger('mail')->error('Mailjet HTTP error', [
            'status' => $response->getStatus(),
            'reason' => $response->getReasonPhrase(),
            'body'   => $response->getBody(),
        ]);
        return false;
    }

    /**
     * Extract the first "Messages[0]" entry from Mailjet's payload, or null when missing.
     *
     * @return array<string,mixed>|null
     */
    private function extractFirstMessage(Response $response): ?array
    {
        /** @var array<string, mixed> $data */
        $data = $response->getData();

        $messages = $data['Messages'] ?? null;
        if (!is_array($messages)) {
            return null;
        }

        $first = $messages[0] ?? null;
        if (!is_array($first)) {
            return null;
        }

        /** @var array<string,mixed> $first */
        return $first;
    }

    /**
     * Validate functional Mailjet status, log accordingly, and return the outcome.
     *
     * @param array<string,mixed>|null $msg
     */
    private function handleFunctionalStatus(?array $msg, Response $response, string $toEmail): bool
    {
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
            'subject' => $msg['Subject'] ?? null,
        ]);
        return true;
    }
}
