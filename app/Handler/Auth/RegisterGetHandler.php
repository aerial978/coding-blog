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

final class RegisterGetHandler extends BaseController
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
        // === Extraction directe de renderRegisterForm() ===
        [$old, $state] = $this->consumeRegisterFlashes();
        $this->markRegisterStartIfEmptyOld($old);

        $mode             = $this->determineRegisterMode($state);
        $obfuscatedEmail  = $this->obfuscateEmailFromState($state);
        $turnstileSiteKey = $this->readTurnstileSiteKey();

        $viewData = $this->buildRegisterViewModel(
            $mode,
            $obfuscatedEmail,
            $old,
            $turnstileSiteKey
        );

        $this->responder->render(
            'security/register.html.twig',
            $this->withFlashes($viewData)
        );
    }

    /**
     * @return array{0:mixed,1:mixed} [old, state]
     */
    private function consumeRegisterFlashes(): array
    {
        $old   = $this->flash->take('old', []);
        $state = $this->flash->take('register_state', null);

        return [$old, $state];
    }

    private function markRegisterStartIfEmptyOld(mixed $old): void
    {
        if (empty($old)) {
            $this->submissionDelay->markFormStart('register');
        }
    }

    /**
     * @return 'check_email'|'form'
     */
    private function determineRegisterMode(mixed $state): string
    {
        return $state ? 'check_email' : 'form';
    }

    private function obfuscateEmailFromState(mixed $state): ?string
    {
        $email = (is_array($state) && is_string($state['email'] ?? null))
            ? $state['email']
            : null;

        if ($email === null) {
            return null;
        }

        $masked = preg_replace('/(^.).*(@.*$)/', '$1***$2', $email);
        return $masked !== null ? $masked : $email;
    }

    private function readTurnstileSiteKey(): string
    {
        return is_string($_ENV['TURNSTILE_SITE_KEY'] ?? null)
            ? trim($_ENV['TURNSTILE_SITE_KEY'])
            : '';
    }

    /**
     * @return array<string,mixed>
     */
    private function buildRegisterViewModel(
        string $mode,
        ?string $obfuscatedEmail,
        mixed $old,
        string $turnstileSiteKey
    ): array {
        return [
            'title'              => 'User Registration',
            'mode'               => $mode,
            'obfuscated_email'   => $obfuscatedEmail,
            'csrf_token'         => $this->csrf->generateToken(FormId::REGISTER),
            'old'                => is_array($old) ? $old : [],
            'honeypot_name'      => $this->honeypot->fieldName(),
            'turnstile_site_key' => $turnstileSiteKey,
        ];
    }
}
