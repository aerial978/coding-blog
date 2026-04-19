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
     * @param array<string,mixed> $data
     * @param list<'error'|'success'|'warning'|'info'> $levels
     * @return array<string,mixed>
     */
    protected function withFlashes(
        array $data = [],
        array $levels = ['error', 'success', 'warning', 'info']
    ): array {
        $data['flashes'] = $this->flash->consumeMany($levels);
        return $data;
    }
}
