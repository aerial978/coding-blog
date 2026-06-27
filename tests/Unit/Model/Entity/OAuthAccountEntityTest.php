<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Entity;

use App\Model\Entity\OAuthAccountEntity;
use PHPUnit\Framework\TestCase;

final class OAuthAccountEntityTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $entity = new OAuthAccountEntity();

        $this->assertNull($entity->getId());
        $this->assertNull($entity->getUserId());
        $this->assertNull($entity->getProvider());
        $this->assertNull($entity->getProviderUserId());
        $this->assertNull($entity->getEmail());
        $this->assertFalse($entity->isEmailVerified());
        $this->assertNull($entity->getCreatedAt());
        $this->assertNull($entity->getUpdatedAt());
    }

    public function testGettersAndSetters(): void
    {
        $entity = (new OAuthAccountEntity())
            ->setId(1)
            ->setUserId(42)
            ->setProvider('google')
            ->setProviderUserId('google_123')
            ->setEmail('michel@example.com')
            ->setEmailVerified(true)
            ->setCreatedAt('2026-06-20 10:00:00')
            ->setUpdatedAt('2026-06-20 11:00:00');

        $this->assertSame(1, $entity->getId());
        $this->assertSame(42, $entity->getUserId());
        $this->assertSame('google', $entity->getProvider());
        $this->assertSame('google_123', $entity->getProviderUserId());
        $this->assertSame('michel@example.com', $entity->getEmail());
        $this->assertTrue($entity->isEmailVerified());
        $this->assertSame('2026-06-20 10:00:00', $entity->getCreatedAt());
        $this->assertSame('2026-06-20 11:00:00', $entity->getUpdatedAt());
    }

    public function testSettersReturnSelf(): void
    {
        $entity = new OAuthAccountEntity();

        $this->assertSame($entity, $entity->setId(1));
        $this->assertSame($entity, $entity->setUserId(42));
        $this->assertSame($entity, $entity->setProvider('google'));
        $this->assertSame($entity, $entity->setProviderUserId('google_123'));
        $this->assertSame($entity, $entity->setEmail('michel@example.com'));
        $this->assertSame($entity, $entity->setEmailVerified(true));
        $this->assertSame($entity, $entity->setCreatedAt('2026-06-20 10:00:00'));
        $this->assertSame($entity, $entity->setUpdatedAt('2026-06-20 11:00:00'));
    }

    public function testHydrate(): void
    {
        $entity = (new OAuthAccountEntity())->hydrate([
            'id'               => 1,
            'user_id'          => 42,
            'provider'         => 'google',
            'provider_user_id' => 'google_123',
            'email'            => 'michel@example.com',
            'email_verified'   => true,
            'created_at'       => '2026-06-20 10:00:00',
            'updated_at'       => '2026-06-20 11:00:00',
        ]);

        $this->assertInstanceOf(OAuthAccountEntity::class, $entity);
        $this->assertSame(1, $entity->getId());
        $this->assertSame(42, $entity->getUserId());
        $this->assertSame('google', $entity->getProvider());
        $this->assertSame('google_123', $entity->getProviderUserId());
        $this->assertSame('michel@example.com', $entity->getEmail());
        $this->assertTrue($entity->isEmailVerified());
        $this->assertSame('2026-06-20 10:00:00', $entity->getCreatedAt());
        $this->assertSame('2026-06-20 11:00:00', $entity->getUpdatedAt());
    }
}
