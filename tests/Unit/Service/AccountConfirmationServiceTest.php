<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Core\ErrorCode;
use App\Model\UserTokenModel;
use App\Security\Contract\TokenGeneratorInterface;
use App\Service\Security\AccountConfirmationService;
use PHPUnit\Framework\TestCase;

final class AccountConfirmationServiceTest extends TestCase
{
    private $tokens;
    // UserTokenModel&Mock
    private $gen;      // TokenGeneratorInterface&Mock

    protected function setUp(): void
    {
        $this->tokens = $this->createMock(UserTokenModel::class);
        $this->gen    = $this->createMock(TokenGeneratorInterface::class);
    }

    private function make(): AccountConfirmationService
    {
        return new AccountConfirmationService($this->tokens, $this->gen);
    }

    public function test_confirm_success(): void
    {
        $this->gen->method('hashToken')->willReturn(str_repeat('a', 32));
        $this->tokens->method('findConfirmationContextByHash')->willReturn([
            'user_id' => 123, 'status' => 'inactive', 'is_expired' => 0, 'used' => 0,
        ]);
        $this->tokens->method('activateByHash')->willReturn(true);
        $res = $this->make()->confirm('tok');
        $this->assertSame([], $res);
    }

    public function test_confirm_invalid_hash(): void
    {
        $this->gen->method('hashToken')->willReturn('short');
        $res = $this->make()->confirm('tok');
        $this->assertSame(['error' => ErrorCode::AUTH_TECHNICAL_ERROR], $res);
    }

    public function test_confirm_not_found(): void
    {
        $this->gen->method('hashToken')->willReturn(str_repeat('a', 32));
        $this->tokens->method('findConfirmationContextByHash')->willReturn(null);
        $res = $this->make()->confirm('tok');
        $this->assertSame(['error' => ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, 'reason' => 'not_found'], $res);
    }

    public function test_confirm_already_active(): void
    {
        $this->gen->method('hashToken')->willReturn(str_repeat('a', 32));
        $this->tokens->method('findConfirmationContextByHash')->willReturn([
            'user_id' => 7, 'status' => 'active', 'is_expired' => 0, 'used' => 0,
        ]);
        $res = $this->make()->confirm('tok');
        $this->assertSame(['error' => ErrorCode::AUTH_ALREADY_CONFIRMED], $res);
    }

    public function test_confirm_activate_fails(): void
    {
        $this->gen->method('hashToken')->willReturn(str_repeat('a', 32));
        $this->tokens->method('findConfirmationContextByHash')->willReturn([
            'user_id' => 7, 'status' => 'inactive', 'is_expired' => 0, 'used' => 0,
        ]);
        $this->tokens->method('activateByHash')->willReturn(false);
        $res = $this->make()->confirm('tok');
        $this->assertSame(['error' => ErrorCode::AUTH_TECHNICAL_ERROR, 'reason' => 'activate_failed'], $res);
    }

    public function test_confirm_expired_and_used_inconsistent(): void
    {
        $this->gen->method('hashToken')->willReturn(str_repeat('a', 32));
        $this->tokens->method('findConfirmationContextByHash')->willReturn([
        'user_id' => 10, 'status' => 'inactive', 'is_expired' => 1, 'used' => 1,
        ]);
        $res = $this->make()->confirm('tok');
        $this->assertSame(['error' => ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, 'reason' => 'expired'], $res);
    }

    public function test_confirm_expired_before_activation(): void
    {
        $this->gen->method('hashToken')->willReturn(str_repeat('a', 32));
        $this->tokens->method('findConfirmationContextByHash')->willReturn([
        'user_id' => 11, 'status' => 'inactive', 'is_expired' => 1, 'used' => 0,
        ]);
        $res = $this->make()->confirm('tok');
        $this->assertSame(['error' => ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, 'reason' => 'expired'], $res);
    }

    public function test_confirm_used_but_inactive(): void
    {
        $this->gen->method('hashToken')->willReturn(str_repeat('a', 32));
        $this->tokens->method('findConfirmationContextByHash')->willReturn([
        'user_id' => 12, 'status' => 'inactive', 'is_expired' => 0, 'used' => 1,
        ]);
        $res = $this->make()->confirm('tok');
        $this->assertSame(['error' => ErrorCode::AUTH_CONFIRM_TOKEN_USED, 'reason' => 'used'], $res);
    }
}
