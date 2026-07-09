<?php

declare(strict_types=1);

namespace Tests\Unit\Service\OAuth;

use App\Service\OAuth\GoogleOAuthProfileMapper;
use PHPUnit\Framework\TestCase;

final class GoogleOAuthProfileMapperTest extends TestCase
{
    private GoogleOAuthProfileMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new GoogleOAuthProfileMapper();
    }

    public function testMapUsesSubBeforeId(): void
    {
        $profile = $this->mapper->map([
            'sub'            => 'google-sub-id',
            'id'             => 'legacy-id',
            'email'          => 'user@example.com',
            'email_verified' => true,
            'name'           => 'John Doe',
            'picture'        => 'https://example.com/avatar.jpg',
        ]);

        $this->assertSame('google-sub-id', $profile['id']);
    }

    public function testMapFallsBackToIdWhenSubIsMissing(): void
    {
        $profile = $this->mapper->map([
            'id' => 'legacy-id',
        ]);

        $this->assertSame('legacy-id', $profile['id']);
    }

    public function testMapTrimsEmailAndName(): void
    {
        $profile = $this->mapper->map([
            'email' => '  user@example.com  ',
            'name'  => '  John Doe  ',
        ]);

        $this->assertSame('user@example.com', $profile['email']);
        $this->assertSame('John Doe', $profile['name']);
    }

    public function testMapConvertsEmailVerifiedTruthyValues(): void
    {
        $this->assertTrue($this->mapper->map(['email_verified' => true])['email_verified']);
        $this->assertTrue($this->mapper->map(['email_verified' => 'true'])['email_verified']);
        $this->assertTrue($this->mapper->map(['email_verified' => 1])['email_verified']);
        $this->assertTrue($this->mapper->map(['email_verified' => '1'])['email_verified']);
    }

    public function testMapConvertsEmailVerifiedFalsyValues(): void
    {
        $this->assertFalse($this->mapper->map(['email_verified' => false])['email_verified']);
        $this->assertFalse($this->mapper->map(['email_verified' => 'false'])['email_verified']);
        $this->assertFalse($this->mapper->map(['email_verified' => 0])['email_verified']);
        $this->assertFalse($this->mapper->map(['email_verified' => '0'])['email_verified']);
        $this->assertFalse($this->mapper->map(['email_verified' => null])['email_verified']);
    }

    public function testMapReturnsNullAvatarWhenPictureIsEmpty(): void
    {
        $profile = $this->mapper->map([
            'picture' => '   ',
        ]);

        $this->assertNull($profile['avatar']);
    }

    public function testMapReturnsNullAvatarWhenPictureIsMissing(): void
    {
        $profile = $this->mapper->map([]);

        $this->assertNull($profile['avatar']);
    }

    public function testMapNormalizesMissingValues(): void
    {
        $profile = $this->mapper->map([]);

        $this->assertSame('', $profile['id']);
        $this->assertSame('', $profile['email']);
        $this->assertFalse($profile['email_verified']);
        $this->assertSame('', $profile['name']);
        $this->assertNull($profile['avatar']);
    }
}
