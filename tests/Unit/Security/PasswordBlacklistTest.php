<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\PasswordBlacklist;
use PHPUnit\Framework\TestCase;

final class PasswordBlacklistTest extends TestCase
{
    public function testIsBlacklistedReturnsTrueForExactMatch(): void
    {
        $blacklist = new PasswordBlacklist(['password123', 'azerty']);

        self::assertTrue($blacklist->isBlacklisted('password123'));
        self::assertTrue($blacklist->isBlacklisted('azerty'));
    }

    public function testIsBlacklistedIgnoresCaseAndSurroundingSpaces(): void
    {
        $blacklist = new PasswordBlacklist(['  PaSsWoRd123  ', '  AZERTY ']);

        self::assertTrue($blacklist->isBlacklisted('password123'));
        self::assertTrue($blacklist->isBlacklisted('   PASSWORD123   '));
        self::assertTrue($blacklist->isBlacklisted('azerty'));
        self::assertTrue($blacklist->isBlacklisted('  AzErTy  '));
    }

    public function testConstructorIgnoresEmptyStrings(): void
    {
        $blacklist = new PasswordBlacklist(['', '   ', " \n\t "]);

        self::assertFalse($blacklist->isBlacklisted(''));
        self::assertFalse($blacklist->isBlacklisted('password123'));
    }

    public function testIsBlacklistedReturnsFalseWhenNotInList(): void
    {
        $blacklist = new PasswordBlacklist(['password123']);

        self::assertFalse($blacklist->isBlacklisted('not-in-list'));
        self::assertFalse($blacklist->isBlacklisted('password1234')); // proche mais différent
    }
}
