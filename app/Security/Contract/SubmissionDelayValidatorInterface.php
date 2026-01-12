<?php

declare(strict_types=1);

namespace App\Security\Contract;

use App\Security\Exception\SuspiciousSubmissionException;

interface SubmissionDelayValidatorInterface
{
    public function markFormStart(string $formId): void;

    /**
     * @throws SuspiciousSubmissionException
     */
    public function assertDelayPassed(string $formId, ?int $minSeconds = null, ?int $maxSeconds = null): void;
}
