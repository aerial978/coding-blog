<?php

namespace App\Controller;

use App\Core\View;

/**
 * Base controller providing the Twig rendering engine to all child controllers.
 */
abstract class BaseController
{
    protected View $view;

    public function __construct()
    {
        $this->view = new View();
    }

    /**
     * Renders a Twig view.
     *
     * @param string $template Template name (e.g., 'home/index.html.twig')
     * @param array<string, mixed> $params Data to inject into the view
     */
    protected function render(string $template, array $params = []): void
    {
        echo $this->view->render($template, $params);
    }
}
