<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\Email2faPendingSession;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Double\SessionFake;

final class Email2faPendingSessionTest extends TestCase
{
    public function testStartCreatesValidPendingSession(): void
    {
        $session = new SessionFake();
        $pendingSession = new Email2faPendingSession($session);

        $pendingSession->start(42, true);

        self::assertTrue($pendingSession->hasPending());
        self::assertSame(42, $pendingSession->getPendingUserId());
        self::assertTrue($pendingSession->wasRememberMeRequested());
        self::assertFalse($pendingSession->isExpired());
    }

    public function testStartCreatesPendingSessionWithoutRememberMe(): void
    {
        $session = new SessionFake();
        $pendingSession = new Email2faPendingSession($session);

        $pendingSession->start(42, false);

        self::assertTrue($pendingSession->hasPending());
        self::assertSame(42, $pendingSession->getPendingUserId());
        self::assertFalse($pendingSession->wasRememberMeRequested());
    }

    public function testHasPendingReturnsFalseWhenNoPendingSessionExists(): void
    {
        $session = new SessionFake();
        $pendingSession = new Email2faPendingSession($session);

        self::assertFalse($pendingSession->hasPending());
        self::assertNull($pendingSession->getPendingUserId());
        self::assertFalse($pendingSession->wasRememberMeRequested());
        self::assertTrue($pendingSession->isExpired());
    }

    public function testClearRemovesPendingSession(): void
    {
        $session = new SessionFake();
        $pendingSession = new Email2faPendingSession($session);

        $pendingSession->start(42, true);

        self::assertTrue($pendingSession->hasPending());

        $pendingSession->clear();

        self::assertFalse($pendingSession->hasPending());
        self::assertNull($pendingSession->getPendingUserId());
        self::assertFalse($pendingSession->wasRememberMeRequested());
        self::assertTrue($pendingSession->isExpired());
    }

    public function testStartRejectsInvalidUserId(): void
    {
        $session = new SessionFake();
        $pendingSession = new Email2faPendingSession($session);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The pending 2FA user ID must be a positive integer.');

        $pendingSession->start(0, false);
    }

    public function testIsExpiredReturnsFalseBeforeExpiration(): void
    {
        $session = new SessionFake();
        $pendingSession = new Email2faPendingSession($session, 600);

        $pendingSession->start(42, false);

        self::assertFalse($pendingSession->isExpired());
    }

    public function testIsExpiredReturnsTrueAfterExpiration(): void
    {
        $session = new SessionFake();
        $pendingSession = new Email2faPendingSession($session, -1);

        $pendingSession->start(42, false);

        self::assertTrue($pendingSession->isExpired());
    }

    public function testInvalidPayloadIsNotConsideredPending(): void
    {
        $session = new SessionFake();

        $session->set('auth_2fa_pending', 'invalid_payload');

        $pendingSession = new Email2faPendingSession($session);

        self::assertFalse($pendingSession->hasPending());
        self::assertNull($pendingSession->getPendingUserId());
        self::assertFalse($pendingSession->wasRememberMeRequested());
        self::assertTrue($pendingSession->isExpired());
    }

    public function testPayloadWithoutUserIdIsNotConsideredPending(): void
    {
        $session = new SessionFake();

        $session->set('auth_2fa_pending', [
            'started_at' => time(),
            'remember_me_requested' => true,
        ]);

        $pendingSession = new Email2faPendingSession($session);

        self::assertFalse($pendingSession->hasPending());
        self::assertNull($pendingSession->getPendingUserId());
        self::assertTrue($pendingSession->wasRememberMeRequested());
    }

    public function testPayloadWithoutStartedAtIsNotConsideredPending(): void
    {
        $session = new SessionFake();

        $session->set('auth_2fa_pending', [
            'user_id' => 42,
            'remember_me_requested' => true,
        ]);

        $pendingSession = new Email2faPendingSession($session);

        self::assertFalse($pendingSession->hasPending());
        self::assertSame(42, $pendingSession->getPendingUserId());
        self::assertTrue($pendingSession->isExpired());
    }

    public function testPayloadWithInvalidUserIdIsNotConsideredPending(): void
    {
        $session = new SessionFake();

        $session->set('auth_2fa_pending', [
            'user_id' => 0,
            'started_at' => time(),
            'remember_me_requested' => false,
        ]);

        $pendingSession = new Email2faPendingSession($session);

        self::assertFalse($pendingSession->hasPending());
        self::assertNull($pendingSession->getPendingUserId());
    }

    public function testPayloadWithInvalidStartedAtIsConsideredExpired(): void
    {
        $session = new SessionFake();

        $session->set('auth_2fa_pending', [
            'user_id' => 42,
            'started_at' => 0,
            'remember_me_requested' => false,
        ]);

        $pendingSession = new Email2faPendingSession($session);

        self::assertFalse($pendingSession->hasPending());
        self::assertTrue($pendingSession->isExpired());
    }
}