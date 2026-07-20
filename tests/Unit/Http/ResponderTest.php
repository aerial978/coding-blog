<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Core\Contract\FlashInterface;
use App\Core\Contract\SessionInterface;
use App\Core\View;
use App\Http\Responder;
use App\Http\ViewContextProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ResponderTest extends TestCase
{
    private View&MockObject $view;
    private FlashInterface&MockObject $flash;
    private SessionInterface&MockObject $session;

    private TestableResponder $responder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->view    = $this->createMock(View::class);
        $this->flash   = $this->createMock(FlashInterface::class);
        $this->session = $this->createMock(SessionInterface::class);

        $contextProvider = new ViewContextProvider(
            $this->flash,
            $this->session,
        );

        $this->responder = new TestableResponder(
            $this->view,
            $contextProvider,
        );
    }

    public function testRenderMergesGlobalContextWithViewData(): void
    {
        $_ENV['TURNSTILE_SITE_KEY'] = 'site-key';

        $template = 'security/login.html.twig';

        $flashes = [
            'success' => [],
            'error'   => [],
            'warning' => [],
            'info'    => [],
        ];

        $this->flash
            ->expects($this->once())
            ->method('consumeMany')
            ->willReturn($flashes);

        $this->session
            ->expects($this->once())
            ->method('get')
            ->with('user')
            ->willReturn(null);

        $this->session
            ->expects($this->once())
            ->method('has')
            ->with('auth_2fa_pending')
            ->willReturn(false);

        $data = [
            'title'  => 'Connexion',
            'errors' => [],
        ];

        $this->view
            ->expects($this->once())
            ->method('render')
            ->with(
                $template,
                $this->callback(function (array $mergedData) use ($data, $flashes): bool {
                    $this->assertSame($flashes, $mergedData['flashes']);
                    $this->assertNull($mergedData['auth_user']);
                    $this->assertFalse($mergedData['is_authenticated']);
                    $this->assertFalse($mergedData['email_2fa_pending']);
                    $this->assertFalse($mergedData['show_header']);
                    $this->assertSame('site-key', $mergedData['turnstile_site_key']);

                    $this->assertSame($data['title'], $mergedData['title']);
                    $this->assertSame($data['errors'], $mergedData['errors']);

                    return true;
                })
            )
            ->willReturn('<h1>Connexion</h1>');

        ob_start();

        $this->responder->render($template, $data);

        $output = ob_get_clean();

        $this->assertSame('<h1>Connexion</h1>', $output);
    }

    public function testRenderAllowsPageDataToOverrideGlobalContext(): void
    {
        $template = 'security/login.html.twig';

        $this->flash
            ->expects($this->once())
            ->method('consumeMany')
            ->willReturn([
                'success' => [],
                'error'   => [],
                'warning' => [],
                'info'    => [],
            ]);

        $this->session
            ->expects($this->once())
            ->method('get')
            ->with('user')
            ->willReturn(['id' => 42]);

        $this->session
            ->expects($this->once())
            ->method('has')
            ->with('auth_2fa_pending')
            ->willReturn(false);

        $this->view
            ->expects($this->once())
            ->method('render')
            ->with(
                $template,
                $this->callback(function (array $mergedData): bool {
                    $this->assertFalse($mergedData['show_header']);

                    return true;
                })
            )
            ->willReturn('<h1>Connexion</h1>');

        ob_start();

        $this->responder->render($template, [
            'show_header' => false,
        ]);

        ob_end_clean();
    }

    public function testRedirectSendsLocationHeaderAndTerminates(): void
    {
        $this->responder->redirect('/coding-blog/login');

        $this->assertSame(
            'Location: /coding-blog/login',
            $this->responder->lastHeader
        );

        $this->assertTrue($this->responder->terminated);
    }
}

final class TestableResponder extends Responder
{
    public ?string $lastHeader = null;

    public bool $terminated = false;

    protected function sendHeader(string $header): void
    {
        $this->lastHeader = $header;
    }

    protected function terminate(): void
    {
        $this->terminated = true;
    }
}
