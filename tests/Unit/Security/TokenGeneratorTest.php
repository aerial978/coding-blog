<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\TokenGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TokenGeneratorTest extends TestCase
{
    private TokenGenerator $gen;
    protected function setUp(): void
    {
        $this->gen = new TokenGenerator();
    }

    #[Test]
    public function generate_default32_is_url_safe_and_has_expected_length(): void
    {
        $t = $this->gen->generateUrlSafeToken();
        // 32 bytes par défaut
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $t, 'Doit être base64url-safe');
        // 32 bytes -> base64 length 44 avec un "=" padding -> 43 après trim("=")
        self::assertSame(43, \strlen($t));
    }

    #[Test]
    public function generate_16bytes_is_url_safe_and_has_expected_length(): void
    {
        $t = $this->gen->generateUrlSafeToken(16);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $t);
        // 16 bytes -> base64 length 24 avec "==" -> 22 après trim("=")
        self::assertSame(22, \strlen($t));
    }

    #[Test]
    public function generate_is_random_enough_for_basic_uniqueness(): void
    {
        $seen = [];
        for ($i = 0; $i < 100; $i++) {
            $tok        = $this->gen->generateUrlSafeToken(16);
            $seen[$tok] = true;
        }
        self::assertCount(100, $seen, '100 tokens devraient être uniques');
    }

    #[Test]
    public function hashToken_returns_32_bytes_binary_and_matches_hex_sha256(): void
    {
        $token = 'Hello, world!';
        $bin   = $this->gen->hashToken($token);
        // 32 octets (sha256 binaire)
        self::assertSame(32, \strlen($bin));
        // Vérifie l’égalité avec la version hexadécimale classique
        self::assertSame(\hash('sha256', $token), \bin2hex($bin));
        // Déterminisme
        self::assertSame($bin, $this->gen->hashToken($token));
    }
}
