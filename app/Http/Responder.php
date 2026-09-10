<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\AppConfig;
use App\Core\View;
use App\Http\Contract\ResponderInterface;

class Responder implements ResponderInterface
{
    public function __construct(
        private readonly View $view,
        private readonly ViewContextProvider $contextProvider,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): void
    {
        echo $this->view->render(
            $template,
            array_merge(
                $this->contextProvider->getContext(),
                $data
            )
        );
    }

    public function redirect(string $path): void
    {
        $location = preg_match('#^https?://#i', $path) === 1
            ? $path
            : AppConfig::getPath($path);

        $this->sendHeader('Location: ' . $location);
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
