<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Contract\ResponderInterface;

class HomeController
{
    public function __construct(
        private ResponderInterface $responder,
    ) {
    }

    public function index(): void
    {
        $this->responder->render('home/index.html.twig', [
            'show_header' => true,
            'title'       => 'Home',
            'message'     => 'This is the home page.',
        ]);
    }
}
