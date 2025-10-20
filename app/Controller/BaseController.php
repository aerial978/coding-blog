<?php

namespace App\Controller;

use App\Core\Contract\FlashInterface;
use App\Core\View;

/**
 * Base controller providing the Twig rendering engine to all child controllers.
 */
abstract class BaseController
{
    protected View $view;
    protected FlashInterface $flash;

    public function __construct(View $view, FlashInterface $flash)
    {
        $this->view  = $view;
        $this->flash = $flash;
    }

    /**
     * @param array<string,mixed> $params
     */
    protected function render(string $template, array $params = []): void
    {
        echo $this->view->render($template, $params);
    }

    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        $this->terminate();
    }

    /** @codeCoverageIgnore */
    protected function terminate(): void
    {
        exit;
    }

    /**
     * Adds flash messages (grouped by level) into the provided data array.
     *
     * @param array<string,mixed>                     $data
     *     The view data array.
     * @param list<'error'|'success'|'warning'|'info'> $levels
     *     The flash message levels to include. Defaults to all standard levels.
     *
     * @return array<string,mixed>
     *     The data array with a 'flashes' key added.
     */
    protected function withFlashes(
        array $data = [],
        array $levels = ['error', 'success', 'warning', 'info']
    ): array {
        $data['flashes'] = $this->flash->consumeMany($levels);
        return $data;
    }
}
