<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Contract\FlashInterface;
use App\Core\View;

final class AccountController extends BaseController
{
    public function __construct(View $view, FlashInterface $flash)
    {
        parent::__construct($view, $flash);
    }

    public function index(): void
    {
        // Si on est ici, c’est que le middleware a laissé passer.
        $this->render('account/index.html.twig', $this->withFlashes([
            'title' => 'Mon compte',
        ]));
    }
}
