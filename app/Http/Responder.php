<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\View;
use App\Http\Contract\ResponderInterface;

class Responder implements ResponderInterface
{
    public function __construct(
        private View $view,
    ) {
    }

    public function render(string $template, array $data = []): void
    {
        echo $this->view->render($template, $data);
    }

    public function redirect(string $path): void
    {
        $this->sendHeader('Location: ' . $path);
        $this->terminate();
    }

    protected function sendHeader(string $header): void
    {
        header($header);
    }

    protected function terminate(): void
    {
        exit;
    }
}
