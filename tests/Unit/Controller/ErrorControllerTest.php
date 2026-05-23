<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\ErrorController;
use App\Http\Contract\ResponderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ErrorControllerTest extends TestCase
{
    private ResponderInterface&MockObject $responder;

    private ErrorController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->responder = $this->createMock(ResponderInterface::class);

        $this->controller = new ErrorController(
            $this->responder,
        );
    }

    protected function tearDown(): void
    {
        http_response_code(200);

        parent::tearDown();
    }

    public function testNotFoundSets404AndRenders404Template(): void
    {
        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with('errors/404.html.twig');

        $this->controller->notFound();

        $this->assertSame(404, http_response_code());
    }

    public function testServerErrorSets500AndRenders500TemplateWithId(): void
    {
        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'errors/500.html.twig',
                [
                    'errorId' => 'ERR-123',
                ]
            );

        $this->controller->serverError('ERR-123');

        $this->assertSame(500, http_response_code());
    }

    public function testServerErrorSets500AndRenders500TemplateWithoutId(): void
    {
        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'errors/500.html.twig',
                [
                    'errorId' => null,
                ]
            );

        $this->controller->serverError();

        $this->assertSame(500, http_response_code());
    }
}
