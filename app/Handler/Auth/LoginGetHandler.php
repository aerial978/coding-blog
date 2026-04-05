<?php

declare(strict_types=1);

namespace App\Handler\Auth;

use App\Controller\BaseController;
use App\Core\Contract\FlashInterface;
use App\Core\FormId;
use App\Core\View;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;

final class LoginGetHandler extends BaseController
{
    public function __construct(
        View $view,
        FlashInterface $flash,
        private ResponderInterface $responder,
        private CsrfTokenInterface $csrf,
        private HoneypotValidatorInterface $honeypot,
        private SubmissionDelayValidatorInterface $submissionDelay,
    ) {
        parent::__construct($view, $flash);
    }

    public function handle(): void
    {
        $old = $this->flash->take('old', []);

        if (empty($old)) {
            $this->submissionDelay->markFormStart('login');
        }

        $flags = $this->flash->take('security_flags', []);
        $flags = is_array($flags) ? $flags : [];

        $turnstileRequired = !empty($flags['turnstile_login']);

        $turnstileEnabled = $turnstileRequired;

        $this->responder->render(
            'security/login.html.twig',
            $this->withFlashes([
                'title'              => 'Login',
                'csrf_token'         => $this->csrf->generateToken(FormId::LOGIN),
                'old'                => is_array($old) ? $old : [],
                'honeypot_name'      => $this->honeypot->fieldName(),
                'turnstile_required' => $turnstileRequired,
                'turnstile_enabled'  => $turnstileEnabled,
                'turnstile_site_key' => $_ENV['TURNSTILE_SITE_KEY'] ?? '',
                // Si votre Responder/View injecte déjà les flashes via BaseController::withFlashes,
                // gardez la même mécanique. Sinon, ajoutez vos flashes dans le template via flash service.
            ])
        );
    }
}
