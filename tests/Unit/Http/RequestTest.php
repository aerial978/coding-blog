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

    /** @var array<string, mixed>|null */
    private ?array $serverBackup = null;

    protected function setUp(): void
    {
        /** @var array<string,mixed> $get */
        $get             = $_GET;
        $this->getBackup = $get;

        /** @var array<string,mixed> $post */
        $post             = $_POST;
        $this->postBackup = $post;

        /** @var array<string,mixed> $server */
        $server             = $_SERVER;
        $this->serverBackup = $server;
    }

    protected function tearDown(): void
    {
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

        if ($this->serverBackup === null) {
            unset($_SERVER);
        } else {
            $_SERVER = $this->serverBackup;
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

    #[Test]
    public function get_uri_returns_request_uri_when_present(): void
    {
        $_SERVER['REQUEST_URI'] = '/coding-blog/register?x=1';

        $req = new Request();

        self::assertSame('/coding-blog/register?x=1', $req->getUri());
    }

    #[Test]
    public function get_uri_returns_null_when_request_uri_missing(): void
    {
        unset($_SERVER['REQUEST_URI']);

        $req = new Request();

        self::assertNull($req->getUri());
    }

    #[Test]
    public function get_method_returns_uppercase_method(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'post';

        $req = new Request();

        self::assertSame('POST', $req->getMethod());
    }

    #[Test]
    public function get_method_returns_get_when_method_missing(): void
    {
        unset($_SERVER['REQUEST_METHOD']);

        $req = new Request();

        self::assertSame('GET', $req->getMethod());
    }
}
