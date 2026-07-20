<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Contract\SessionInterface;

final class DebugController
{
    private const EMAIL_2FA_PENDING_KEY = 'auth_2fa_pending';

    public function __construct(private SessionInterface $session)
    {
    }

    public function whoami(): void
    {
        $data = $this->buildPayload();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * @return array{
     *     session_id: string,
     *     user: array<string, mixed>|null,
     *     has_user: bool,
     *     email_2fa_pending: array<string, mixed>|null,
     *     has_email_2fa_pending: bool
     * }
     */
    private function buildPayload(): array
    {
        $user            = $this->normalizeArray($this->session->get('user'));
        $email2faPending = $this->normalizeArray(
            $this->session->get(self::EMAIL_2FA_PENDING_KEY)
        );

        $sessionId = session_id();
        $sessionId = is_string($sessionId) ? $sessionId : '';

        return [
            'session_id'             => $sessionId,
            'user'                   => $user,
            'has_user'               => $user !== null && isset($user['id']),
            'email_2fa_pending'      => $email2faPending,
            'has_email_2fa_pending'  => $email2faPending !== null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeArray(mixed $raw): ?array
    {
        if (!is_array($raw)) {
            return null;
        }

        $data = [];

        foreach ($raw as $key => $value) {
            if (is_string($key)) {
                $data[$key] = $value;
            }
        }

        return $data;
    }
}
