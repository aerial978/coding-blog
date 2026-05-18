<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\DebugController;
use App\Core\Contract\SessionInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DebugControllerTest extends TestCase
{
    private SessionInterface&MockObject $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->session = $this->createMock(SessionInterface::class);
    }

    public function testBuildPayloadWithUser(): void
    {
        $user = [
            'id'    => 42,
            'email' => 'john@example.com',
        ];

        $this->session
            ->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                ['user', null, $user],
                ['auth_2fa_pending', null, null],
            ]);

        $controller = new DebugController($this->session);

        $reflection = new \ReflectionClass($controller);
        $method     = $reflection->getMethod('buildPayload');
        $method->setAccessible(true);

        $data = $method->invoke($controller);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('session_id', $data);
        $this->assertSame($user, $data['user']);
        $this->assertTrue($data['has_user']);
        $this->assertNull($data['email_2fa_pending']);
        $this->assertFalse($data['has_email_2fa_pending']);
    }

    public function testBuildPayloadWithoutUser(): void
    {
        $this->session
        ->expects($this->exactly(2))
        ->method('get')
        ->willReturnMap([
            ['user', null, null],
            ['auth_2fa_pending', null, null],
        ]);

        $controller = new DebugController($this->session);

        $reflection = new \ReflectionClass($controller);
        $method     = $reflection->getMethod('buildPayload');
        $method->setAccessible(true);

        $data = $method->invoke($controller);

        $this->assertIsArray($data);
        $this->assertNull($data['user']);
        $this->assertFalse($data['has_user']);
        $this->assertNull($data['email_2fa_pending']);
        $this->assertFalse($data['has_email_2fa_pending']);
    }
}
