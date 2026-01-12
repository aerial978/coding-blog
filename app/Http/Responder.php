<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\View;
use App\Http\Contract\ResponderInterface;

final class Responder implements ResponderInterface
{
    public function __construct(
        private View $view,
    ) {
    }

    public function render(string $template, array $data = []): void
    {
        // Aligné avec BaseController : View::render() retourne du HTML
        echo $this->view->render($template, $data);
    }

    public function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }
}
