<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Entity;

use App\Model\Entity\UserTokenEntity;
use PHPUnit\Framework\TestCase;

final class UserTokenEntityTest extends TestCase
{
    public function testHydrateFromDbRowSetsAllFields(): void
    {
        // Simule un fetch PDO (colonnes snake_case)
        $row = [
            'id'         => 123,
            'user_id'    => 42,
            'type'       => 'confirmation',
            'token_hash' => random_bytes(32),
            'expires_at' => '2025-12-31 23:59:59',
            'used'       => 1, // peut être int(1) ou '1' selon PDO
            'used_at'    => '2025-10-04 12:00:00',
            'created_at' => '2025-10-03 09:00:00',
        ];
        $e = (new UserTokenEntity())->hydrate($row);
        $this->assertSame(123, $e->getId());
        $this->assertSame(42, $e->getUserId());
        $this->assertSame('confirmation', $e->getType());
        $this->assertSame($row['token_hash'], $e->getTokenHash());
        $this->assertSame('2025-12-31 23:59:59', $e->getExpiresAt());
        // isUsed() retourne ?bool : on reste souple sur l’assertion (truthy)
        $this->assertTrue((bool) $e->isUsed());
        $this->assertSame('2025-10-04 12:00:00', $e->getUsedAt());
        $this->assertSame('2025-10-03 09:00:00', $e->getCreatedAt());
    }

    public function testFluentSettersAndGetters(): void
    {
        $bin = random_bytes(32);
        $e   = (new UserTokenEntity())
            ->setId(7)
            ->setUserId(77)
            ->setType('confirmation')
            ->setTokenHash($bin)
            ->setExpiresAt('2030-01-01 00:00:00')
            ->setUsed(false)
            ->setUsedAt(null)
            ->setCreatedAt('2029-12-31 23:59:59');
        $this->assertSame(7, $e->getId());
        $this->assertSame(77, $e->getUserId());
        $this->assertSame('confirmation', $e->getType());
        $this->assertSame($bin, $e->getTokenHash());
        $this->assertSame('2030-01-01 00:00:00', $e->getExpiresAt());
        $this->assertFalse((bool) $e->isUsed());
        $this->assertNull($e->getUsedAt());
        $this->assertSame('2029-12-31 23:59:59', $e->getCreatedAt());
    }

    public function testOptionalFieldsAllowNull(): void
    {
        $e = new UserTokenEntity();
        // Champs optionnels
        $e->setUsedAt(null);
        $this->assertNull($e->getUsedAt());
        // used peut rester null tant qu’on n’a pas setUsed()
        $this->assertNull($e->isUsed());
        // Après setUsed(), on attend bien un bool
        $e->setUsed(true);
        $this->assertTrue($e->isUsed());
    }
}
