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
        $user = $this->session->get('user');

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'session_id' => session_id(),
            'user'       => $user,
            'has_user'   => is_array($user) && isset($user['id']),
        ], JSON_PRETTY_PRINT);

        exit;
    }
}
