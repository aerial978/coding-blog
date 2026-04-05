<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Contract\SessionInterface;

final class DebugController
{
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
     *     has_user: bool
     * }
     */
    private function buildPayload(): array
    {
        $rawUser = $this->session->get('user');
        $user    = $this->normalizeUser($rawUser);

        $sessionId = session_id();
        $sessionId = is_string($sessionId) ? $sessionId : '';

        return [
            'session_id' => $sessionId,
            'user'       => $user,
            'has_user'   => $user !== null && isset($user['id']),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeUser(mixed $rawUser): ?array
    {
        if (!is_array($rawUser)) {
            return null;
        }

        $user = [];

        foreach ($rawUser as $key => $value) {
            if (is_string($key)) {
                $user[$key] = $value;
            }
        }

        return $user;
    }
}
