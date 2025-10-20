<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    /** @var array<string, mixed>|null */
    private ?array $getBackup = null;
    /** @var array<string, mixed>|null */
    private ?array $postBackup = null;

    protected function setUp(): void
    {
        // Sauvegarde de l'état global pour ne rien perturber
        /** @var array<string,mixed> $get */
        $get             = $_GET;
        $this->getBackup = $get;

        /** @var array<string,mixed> $post */
        $post             = $_POST;
        $this->postBackup = $post;
    }

    protected function tearDown(): void
    {
        // Restauration de l'état global
        if ($this->getBackup === null) {
            unset($_GET);
        } else {
            $_GET = $this->getBackup;
        }

        if ($this->postBackup === null) {
            unset($_POST);
        } else {
            $_POST = $this->postBackup;
        }
    }

    #[Test]
    public function request_returns_post_array(): void
    {
        $_POST = ['a' => 1, 'b' => 'x'];
        $req   = new Request();
        $data  = $req->request();
        self::assertSame(['a' => 1, 'b' => 'x'], $data);
    }

    #[Test]
    public function request_returns_empty_array_when_post_missing(): void
    {
        unset($_POST);
        $req = new Request();
        self::assertSame([], $req->request());
    }

    #[Test]
    public function query_returns_get_array(): void
    {
        $_GET = ['q' => 'hello', 'page' => '2'];
        $req  = new Request();
        $data = $req->query();
        self::assertSame(['q' => 'hello', 'page' => '2'], $data);
    }

    #[Test]
    public function query_returns_empty_array_when_get_missing(): void
    {
        unset($_GET);
        $req = new Request();
        self::assertSame([], $req->query());
    }
}
