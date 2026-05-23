<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Contract\SessionInterface;
use App\Security\Contract\Email2faPendingSessionInterface;
use InvalidArgumentException;

final class Email2faPendingSession implements Email2faPendingSessionInterface
{
    private const SESSION_KEY = 'auth_2fa_pending';

    private const KEY_USER_ID = 'user_id';

    private const KEY_STARTED_AT = 'started_at';

    private const KEY_REMEMBER_ME_REQUESTED = 'remember_me_requested';

    private const DEFAULT_TTL_SECONDS = 600;

    public function __construct(
        private readonly SessionInterface $session,
        private readonly int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
    ) {
    }

    public function start(int $userId, bool $rememberMeRequested): void
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('The pending 2FA user ID must be a positive integer.');
        }

        $this->session->set(self::SESSION_KEY, [
            self::KEY_USER_ID               => $userId,
            self::KEY_STARTED_AT            => time(),
            self::KEY_REMEMBER_ME_REQUESTED => $rememberMeRequested,
        ]);
    }

    public function hasPending(): bool
    {
        $payload = $this->getPayload();

        return $payload                          !== null
            && $this->extractUserId($payload)    !== null
            && $this->extractStartedAt($payload) !== null;
    }

    public function getPendingUserId(): ?int
    {
        $payload = $this->getPayload();

        if ($payload === null) {
            return null;
        }

        return $this->extractUserId($payload);
    }

    public function wasRememberMeRequested(): bool
    {
        $payload = $this->getPayload();

        if ($payload === null) {
            return false;
        }

        return ($payload[self::KEY_REMEMBER_ME_REQUESTED] ?? false) === true;
    }

    public function isExpired(): bool
    {
        $payload = $this->getPayload();

        if ($payload === null) {
            return true;
        }

        $startedAt = $this->extractStartedAt($payload);

        if ($startedAt === null) {
            return true;
        }

        return time() > ($startedAt + $this->ttlSeconds);
    }

    public function clear(): void
    {
        $this->session->remove(self::SESSION_KEY);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getPayload(): ?array
    {
        $payload = $this->session->get(self::SESSION_KEY);

        if (!is_array($payload)) {
            return null;
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractUserId(array $payload): ?int
    {
        $userId = $payload[self::KEY_USER_ID] ?? null;

        if (!is_int($userId) || $userId <= 0) {
            return null;
        }

        return $userId;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractStartedAt(array $payload): ?int
    {
        $startedAt = $payload[self::KEY_STARTED_AT] ?? null;

        if (!is_int($startedAt) || $startedAt <= 0) {
            return null;
        }

        return $startedAt;
    }
}
