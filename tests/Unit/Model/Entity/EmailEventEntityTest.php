<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Entity;

use App\Model\Entity\EmailEventEntity;
use PHPUnit\Framework\TestCase;

final class EmailEventEntityTest extends TestCase
{
    public function testSettersAndGettersWorkCorrectly(): void
    {
        $entity = new EmailEventEntity();

        $result = $entity
            ->setId(10)
            ->setUserId(25)
            ->setEmail('alice@example.com')
            ->setType('password_reset')
            ->setCreatedAt('2026-04-05 10:30:00')
            ->setIp('127.0.0.1')
            ->setUserAgent('Mozilla/5.0');

        $this->assertSame($entity, $result);

        $this->assertSame(10, $entity->getId());
        $this->assertSame(25, $entity->getUserId());
        $this->assertSame('alice@example.com', $entity->getEmail());
        $this->assertSame('password_reset', $entity->getType());
        $this->assertSame('2026-04-05 10:30:00', $entity->getCreatedAt());
        $this->assertSame('127.0.0.1', $entity->getIp());
        $this->assertSame('Mozilla/5.0', $entity->getUserAgent());
    }

    public function testNullableFieldsCanBeSetToNull(): void
    {
        $entity = new EmailEventEntity();

        $entity
            ->setUserId(null)
            ->setIp(null)
            ->setUserAgent(null);

        $this->assertNull($entity->getUserId());
        $this->assertNull($entity->getIp());
        $this->assertNull($entity->getUserAgent());
    }

    public function testHydrateFillsEntityProperties(): void
    {
        $entity = new EmailEventEntity();

        $entity->hydrate([
            'id'         => 3,
            'user_id'    => 42,
            'email'      => 'bob@example.com',
            'type'       => 'confirm_resend',
            'created_at' => '2026-04-05 11:00:00',
            'ip'         => '192.168.1.10',
            'user_agent' => 'PHPUnit',
        ]);

        $this->assertSame(3, $entity->getId());
        $this->assertSame(42, $entity->getUserId());
        $this->assertSame('bob@example.com', $entity->getEmail());
        $this->assertSame('confirm_resend', $entity->getType());
        $this->assertSame('2026-04-05 11:00:00', $entity->getCreatedAt());
        $this->assertSame('192.168.1.10', $entity->getIp());
        $this->assertSame('PHPUnit', $entity->getUserAgent());
    }
}
