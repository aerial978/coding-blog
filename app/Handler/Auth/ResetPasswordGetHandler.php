<?php

declare(strict_types=1);

namespace App\Handler\Auth;

use App\Controller\BaseController;
use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\FormId;
use App\Core\Logger;
use App\Core\View;
use App\Http\Contract\ResponderInterface;
use App\Model\Contract\UserTokenModelInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Security\Contract\TokenGeneratorInterface;

final class ResetPasswordGetHandler extends BaseController
{
    public function __construct(
        View $view,
        FlashInterface $flash,
        private ResponderInterface $responder,
        private UserTokenModelInterface $userTokenModel,
        private TokenGeneratorInterface $tokenGen,
        private SubmissionDelayValidatorInterface $submissionDelay,
        private CsrfTokenInterface $csrf,
        private HoneypotValidatorInterface $honeypot,
    ) {
        parent::__construct($view, $flash);
    }

    public function handle(): void
    {
        // token depuis l’URL (query string)
        $token = $this->readQueryToken();

        if ($token === '') {
            $this->neutralFail('missing_token');
            return;
        }

        // hash binaire 32 (comme vos autres flux)
        $hashBin = $this->tokenGen->hashToken($token);

        if (!is_string($hashBin) || strlen($hashBin) !== 32) {
            $this->neutralFail('invalid_hash');
            return;
        }

        $ctx = $this->userTokenModel->findPasswordResetContextByHash($hashBin);

        if ($ctx === null) {
            $this->neutralFail('not_found');
            return;
        }

        // ctx attendu: used / is_expired
        $used      = !empty($ctx['used']);
        $isExpired = !empty($ctx['is_expired']);

        if ($used || $isExpired) {
            $this->neutralFail($used ? 'used' : 'expired');
            return;
        }

        // OK → on affiche le formulaire
        $this->submissionDelay->markFormStart(FormId::RESET_PASSWORD);

        $this->responder->render('security/reset-password.html.twig', $this->withFlashes([
            'title'         => 'Réinitialiser le mot de passe',
            'csrf_token'    => $this->csrf->generateToken(FormId::RESET_PASSWORD),
            'honeypot_name' => $this->honeypot->fieldName(),

            // important : le token doit rester côté client pour le POST (hidden input)
            'token'         => $token,
        ]));
    }

    private function readQueryToken(): string
    {
        // Adaptez selon votre Request. Ici je fais une lecture simple via $_GET pour éviter mismatch.
        $raw = $_GET['token'] ?? null;

        if (!is_string($raw)) {
            return '';
        }

        $t = trim($raw);
        return $t !== '' ? $t : '';
    }

    private function neutralFail(string $reason): void
    {
        // Log technique (serveur)
        Logger::getLogger('auth')->info('reset_get_denied', [
            'reason' => $reason,
        ]);

        // Flash neutre (UI)
        $msg = Logger::logCodeAndGetMessage('auth', 'info', ErrorCode::AUTH_PASSWORD_RESET_REQUESTED, [
            'reason' => $reason,
        ]);

        $this->flash->add('success', $msg);

        $this->responder->redirect('/coding-blog/forgot-password');
    }
}