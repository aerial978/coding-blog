<?php

declare(strict_types=1);

namespace App\Security;

use App\Security\Contract\TurnstileValidatorInterface;

final class TurnstileValidator implements TurnstileValidatorInterface
{
    private string $secret;
    /** @var array<string,mixed>|null */
    private ?array $lastResponse = null;

    public function __construct(?string $secret)
    {
        $this->secret = trim((string) $secret);
    }

    public function isConfigured(): bool
    {
        return $this->secret !== '';
    }

    /**
     * Valide le token renvoyé par Turnstile.
     *
     * @param string|null $token
     * @param string|null $remoteIp
     */
    public function validate(?string $token, ?string $remoteIp = null): bool
    {
        $this->lastResponse = null;

        // En dev, si aucune clé n’est configurée → on laisse passer pour ne pas bloquer.
        if (!$this->isConfigured()) {
            return true;
        }

        if ($token === null || $token === '') {
            $this->lastResponse = [
                'success'     => false,
                'error-codes' => ['missing-input-response'],
                'diagnostic'  => 'empty_token',
            ];
            return false;
        }

        $postData = http_build_query([
            'secret'   => $this->secret,
            'response' => $token,
            'remoteip' => $remoteIp,
        ]);

        $opts = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n"
                           . 'Content-Length: ' . strlen($postData) . "\r\n",
                'content' => $postData,
                'timeout' => 5,
            ],
        ];

        $context  = stream_context_create($opts);
        
        $response = @file_get_contents(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            false,
            $context
        );

        if ($response === false) {
            $err = error_get_last();

            $this->lastResponse = [
                'success' => false,
                'error-codes' => ['turnstile_request_failed'],
                'diagnostic' => is_array($err) && isset($err['message']) ? (string) $err['message'] : 'unknown_error',
            ];

            return false;
        }

        /** @var array<string,mixed>|null $data */
        $data = json_decode($response, true);

        if (!is_array($data)) {
            $this->lastResponse = [
                'success' => false,
                'error-codes' => ['turnstile_bad_response'],
                'diagnostic' => 'invalid_json',
            ];
            return false;
        }

        $this->lastResponse = $data;

        return !empty($data['success']);

    }

    /**
     * @return array<string,mixed>|null
     */
    public function getLastResponse(): ?array
    {
        return $this->lastResponse;
    }
}
