<?php

namespace App\Controller;

class SecurityController extends BaseController
{
    public function register(): void
    {
        $this->render('security/register.html.twig', [
            'title' => 'User Registration'
        ]);
    }
}
