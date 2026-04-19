<?php

declare(strict_types=1);

namespace App\Security;

use App\Security\Contract\TurnstileValidatorInterface;

final class TurnstileValidator implements TurnstileValidatorInterface
{
    private string $secret;

    /** @var array<string, mixed>|null */
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

        if (!$this->isConfigured()) {
            return true;
        }

        if ($this->isEmptyToken($token)) {
            $this->setMissingTokenResponse();

            return false;
        }

        $response = $this->performValidationRequest((string) $token, $remoteIp);

        if ($response === false) {
            $this->setRequestFailedResponse();

            return false;
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($response, true);

        if (!is_array($data)) {
            $this->setInvalidJsonResponse();

            return false;
        }

        $this->lastResponse = $data;

        return !empty($data['success']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLastResponse(): ?array
    {
        return $this->lastResponse;
    }

    private function isEmptyToken(?string $token): bool
    {
        return $token === null || $token === '';
    }

    /**
     * @param string|null $remoteIp
     */
    private function performValidationRequest(string $token, ?string $remoteIp): string|false
    {
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

        $context = stream_context_create($opts);

        return file_get_contents(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            false,
            $context
        );
    }

    private function setMissingTokenResponse(): void
    {
        $this->lastResponse = [
            'success'     => false,
            'error-codes' => ['missing-input-response'],
            'diagnostic'  => 'empty_token',
        ];
    }

    private function setRequestFailedResponse(): void
    {
        $err = error_get_last();

        $this->lastResponse = [
            'success'     => false,
            'error-codes' => ['turnstile_request_failed'],
            'diagnostic'  => is_array($err) ? $err['message'] : 'unknown_error',
        ];
    }

    private function setInvalidJsonResponse(): void
    {
        $this->lastResponse = [
            'success'     => false,
            'error-codes' => ['turnstile_bad_response'],
            'diagnostic'  => 'invalid_json',
        ];
    }
}
