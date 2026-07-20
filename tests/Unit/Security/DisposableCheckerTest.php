<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\DisposableChecker;
use PHPUnit\Framework\TestCase;

final class DisposableCheckerTest extends TestCase
{
    public function testIsDisposableReturnsFalseWhenEmailHasNoAtSign(): void
    {
        $checker = new DisposableChecker(['yopmail.com']);

        self::assertFalse($checker->isDisposable('invalid-email'));
        self::assertFalse($checker->isDisposable('user.example.com'));
    }

    public function testIsDisposableReturnsTrueForExactDomainMatch(): void
    {
        $checker = new DisposableChecker(['yopmail.com', 'mailinator.com']);

        self::assertTrue($checker->isDisposable('user@yopmail.com'));
        self::assertTrue($checker->isDisposable('user@mailinator.com'));
    }

    public function testIsDisposableNormalizesDomainCaseAndSpaces(): void
    {
        $checker = new DisposableChecker(['  YoPmAiL.CoM  ']);

        self::assertTrue($checker->isDisposable('user@yopmail.com'));
        self::assertTrue($checker->isDisposable('user@YOPMAIL.COM'));
        self::assertTrue($checker->isDisposable('user@  yopmail.com  '));
    }

    public function testIsDisposableReturnsTrueForSubdomainWhenBaseDomainIsBlacklisted(): void
    {
        $checker = new DisposableChecker(['yopmail.com']);

        self::assertTrue($checker->isDisposable('user@foo.yopmail.com'));
        self::assertTrue($checker->isDisposable('user@bar.baz.yopmail.com'));
    }

    public function testIsDisposableReturnsFalseWhenDomainNotBlacklisted(): void
    {
        $checker = new DisposableChecker(['yopmail.com']);

        self::assertFalse($checker->isDisposable('user@gmail.com'));
        self::assertFalse($checker->isDisposable('user@notyopmail.com'));
        self::assertFalse($checker->isDisposable('user@foo.gmail.com'));
    }

    public function testConstructorIgnoresEmptyStringEntries(): void
    {
        $checker = new DisposableChecker([
            '',
            '   ',
            'yopmail.com',
        ]);

        self::assertTrue($checker->isDisposable('user@yopmail.com'));
        self::assertFalse($checker->isDisposable('user@gmail.com'));
    }
}
