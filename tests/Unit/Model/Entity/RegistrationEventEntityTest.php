<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Entity;

use App\Model\Entity\RegistrationEventEntity;
use PHPUnit\Framework\TestCase;

final class RegistrationEventEntityTest extends TestCase
{
    public function testSettersAndGettersWorkCorrectly(): void
    {
        $entity = new RegistrationEventEntity();

        $result = $entity
            ->setId(5)
            ->setUserId(12)
            ->setEmail('alice@example.com')
            ->setType('register')
            ->setCreatedAt('2026-04-05 12:00:00')
            ->setIp('127.0.0.1')
            ->setUserAgent('Mozilla/5.0');

        // Fluent interface
        $this->assertSame($entity, $result);

        // Assertions
        $this->assertSame(5, $entity->getId());
        $this->assertSame(12, $entity->getUserId());
        $this->assertSame('alice@example.com', $entity->getEmail());
        $this->assertSame('register', $entity->getType());
        $this->assertSame('2026-04-05 12:00:00', $entity->getCreatedAt());
        $this->assertSame('127.0.0.1', $entity->getIp());
        $this->assertSame('Mozilla/5.0', $entity->getUserAgent());
    }

    public function testNullableFieldsCanBeNull(): void
    {
        $entity = new RegistrationEventEntity();

        $entity
            ->setUserId(null)
            ->setIp(null)
            ->setUserAgent(null);

        $this->assertNull($entity->getUserId());
        $this->assertNull($entity->getIp());
        $this->assertNull($entity->getUserAgent());
    }
}
