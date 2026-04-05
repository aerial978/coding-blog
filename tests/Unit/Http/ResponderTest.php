<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Core\View;
use App\Http\Responder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ResponderTest extends TestCase
{
    private View&MockObject $view;
    private TestableResponder $responder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->view      = $this->createMock(View::class);
        $this->responder = new TestableResponder($this->view);
    }

    public function testRenderEchoesRenderedHtmlFromView(): void
    {
        $template = 'security/login.html.twig';
        $data     = [
            'title'  => 'Connexion',
            'errors' => [],
        ];

        $this->view
            ->expects($this->once())
            ->method('render')
            ->with($template, $data)
            ->willReturn('<h1>Connexion</h1>');

        ob_start();
        $this->responder->render($template, $data);
        $output = ob_get_clean();

        $this->assertSame('<h1>Connexion</h1>', $output);
    }

    public function testRedirectSendsLocationHeaderAndTerminates(): void
    {
        $this->responder->redirect('/coding-blog/login');

        $this->assertSame('Location: /coding-blog/login', $this->responder->lastHeader);
        $this->assertTrue($this->responder->terminated);
    }
}

final class TestableResponder extends Responder
{
    public ?string $lastHeader = null;
    public bool $terminated    = false;

    protected function sendHeader(string $header): void
    {
        $this->lastHeader = $header;
    }

    protected function terminate(): void
    {
        $this->terminated = true;
    }
}
