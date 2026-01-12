<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\DisposableEmailChecker;
use PHPUnit\Framework\TestCase;

final class DisposableEmailCheckerTest extends TestCase
{
    public function testIsDisposableReturnsFalseWhenEmailHasNoAtSign(): void
    {
        $checker = new DisposableEmailChecker(['yopmail.com']);

        self::assertFalse($checker->isDisposable('invalid-email'));
        self::assertFalse($checker->isDisposable('user.example.com'));
    }

    public function testIsDisposableReturnsTrueForExactDomainMatch(): void
    {
        $checker = new DisposableEmailChecker(['yopmail.com', 'mailinator.com']);

        self::assertTrue($checker->isDisposable('user@yopmail.com'));
        self::assertTrue($checker->isDisposable('user@mailinator.com'));
    }

    public function testIsDisposableNormalizesDomainCaseAndSpaces(): void
    {
        $checker = new DisposableEmailChecker(['  YoPmAiL.CoM  ']);

        self::assertTrue($checker->isDisposable('user@yopmail.com'));
        self::assertTrue($checker->isDisposable('user@YOPMAIL.COM'));
        self::assertTrue($checker->isDisposable('user@  yopmail.com  ')); // trim côté checker
    }

    public function testIsDisposableReturnsTrueForSubdomainWhenBaseDomainIsBlacklisted(): void
    {
        $checker = new DisposableEmailChecker(['yopmail.com']);

        self::assertTrue($checker->isDisposable('user@foo.yopmail.com'));
        self::assertTrue($checker->isDisposable('user@bar.baz.yopmail.com'));
    }

    public function testIsDisposableReturnsFalseWhenDomainNotBlacklisted(): void
    {
        $checker = new DisposableEmailChecker(['yopmail.com']);

        self::assertFalse($checker->isDisposable('user@gmail.com'));
        self::assertFalse($checker->isDisposable('user@notyopmail.com'));
        self::assertFalse($checker->isDisposable('user@foo.gmail.com')); // base "gmail.com" non blacklistée
    }

    public function testConstructorIgnoresInvalidDomainEntries(): void
    {
        $checker = new DisposableEmailChecker([
            '',                 // string vide
            '   ',              // string vide après trim
            null,               // non-string
            123,                // non-string
            'yopmail.com',      // valide
        ]);

        // Le domaine valide doit être pris en compte
        self::assertTrue($checker->isDisposable('user@yopmail.com'));

        // Les entrées invalides ne doivent rien casser
        self::assertFalse($checker->isDisposable('user@gmail.com'));
    }
}
