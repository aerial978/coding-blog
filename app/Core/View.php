<?php

namespace App\Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Class View
 *
 * Central service for rendering Twig templates.
 * - Loads views from the /Views directory.
 * - Adds global variables (e.g. base_path).
 * - Provides a simple render() method used by all controllers.
 */
class View
{
    private Environment $twig;

    /**
     * Initializes the Twig environment.
     *
     * - Sets up the template loader to use the /Views directory.
     * - Loads the base configuration (e.g., base_path).
     * - Creates the Twig Environment instance with debug mode enabled and no cache.
     * - Adds global variables accessible in all templates (e.g., base_path).
     */
    public function __construct()
    {
        $loader = new FilesystemLoader(dirname(__DIR__) . '/Views');

        /** @var array{base_path: string} $config */
        $config = require dirname(__DIR__) . '/Config/app.php';

        $this->twig = new Environment($loader, [
            'cache' => false,
            'debug' => true,
        ]);

        $this->twig->addGlobal('base_path', rtrim($config['base_path'], '/') . '/');
    }

    /**
     * Renders a Twig template with optional data.
     *
     * @param string $template Template path relative to /Views (e.g. "home/index.html.twig").
     * @param array<string, mixed> $data Variables passed to the template.
     * @return string Rendered HTML output.
     */
    public function render(string $template, array $data = []): string
    {
        return $this->twig->render($template, $data);
    }
}
