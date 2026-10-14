<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Contract\ResponderInterface;

final class DashboardController
{
    public function __construct(
        private ResponderInterface $responder,
    ) {
    }

    public function index(): void
    {
        $this->responder->render('dashboard/index.html.twig', [
            'title'      => 'Tableau de bord',
            'active_nav' => 'dashboard',
        ]);
    }
}
