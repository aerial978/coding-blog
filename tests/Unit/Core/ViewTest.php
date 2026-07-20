<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\View;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the View class which integrates the Twig template engine.
 */
final class ViewTest extends TestCase
{
    /**
     * Ensures that the render method returns valid HTML
     * when rendering an existing Twig template with provided variables.
     *
     * This test assumes the existence of a test template:
     * app/Views/test/sample.html.twig containing: <h1>{{ title }}</h1>
     */
    public function testRenderReturnsValidHtml(): void
    {
        $view = new View();

        $output = $view->render('test/sample.html.twig', [
            'title' => 'Twig test',
        ]);

        $this->assertStringContainsString('<h1>Twig test</h1>', $output);
    }

    /**
     * Ensures that rendering a non-existent Twig template
     * throws a LoaderError as expected.
     */
    public function testRenderThrowsExceptionOnInvalidTemplate(): void
    {
        $view = new View();

        $this->expectException(\Twig\Error\LoaderError::class);
        $view->render('test/does_not_exist.twig');
    }
}
