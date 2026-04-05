<?php

declare(strict_types=1);

namespace App\Security\Guard;

use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Http\Contract\ResponderInterface;
use App\Log\LogContextNormalizer;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Exception\SuspiciousSubmissionException;
use App\Security\Guard\Contract\HoneypotGuardInterface;

final class HoneypotGuard implements HoneypotGuardInterface
{
    public function __construct(
        private HoneypotValidatorInterface $honeypot,
        private FlashInterface $flash,
        private ResponderInterface $responder,
        private LogContextNormalizer $logNormalizer,
    ) {
    }

    /**
     * Honeypot générique.
     *
     * En cas de suspicion : flash + redirect, retourne false.
     * Sinon : retourne true.
     *
     * @param array{
     *   form: array<string, mixed>,
     *   redirect: string,
     *   flash_type?: 'success'|'error'|'info'|'warning',
     *   code?: string,
     *   log_level?: 'debug'|'info'|'warning'|'error',
     *   log_channel?: string,
     *   context?: array<string, mixed>,
     *   flags_bag?: string,
     *   set_flags?: array<string, mixed>
     * } $opt
     */
    public function assertClean(array $opt): bool
    {
        /** @var array<string, mixed> $form */
        $form     = $opt['form'];
        $redirect = $opt['redirect'];

        $flashType  = $opt['flash_type']  ?? 'error';
        $code       = $opt['code']        ?? ErrorCode::AUTH_TECHNICAL_ERROR;
        $logLevel   = $opt['log_level']   ?? 'warning';
        $logChannel = $opt['log_channel'] ?? 'auth';

        $contextBase = is_array($opt['context'] ?? null) ? $opt['context'] : [];

        try {
            $this->honeypot->assertClean($form);
            return true;
        } catch (SuspiciousSubmissionException $e) {
            $ctx = $contextBase + [
                'reason' => 'honeypot',
            ];

            $logCtx = $this->logNormalizer->normalize($ctx);

            $this->flash->add(
                $flashType,
                Logger::logCodeAndGetMessage($logChannel, $logLevel, $code, $logCtx)
            );

            $flagsBag = is_string($opt['flags_bag'] ?? null) && $opt['flags_bag'] !== ''
                ? $opt['flags_bag']
                : 'security_flags';

            if (isset($opt['set_flags'])) {
                $existing = $this->flash->take($flagsBag, []);
                $existing = is_array($existing) ? $existing : [];
                $this->flash->put($flagsBag, $existing + $opt['set_flags']);
            }

            Logger::getLogger('auth')->info('security_flags_set', [
                'guard' => 'honeypot',
                'flags' => $opt['set_flags'] ?? null,
            ]);

            $this->responder->redirect($redirect);
            return false;
        }
    }
}
