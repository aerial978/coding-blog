<?php

declare(strict_types=1);

namespace App\Security\Contract;

use App\Security\Exception\SuspiciousSubmissionException;

interface HoneypotValidatorInterface
{
    /**
     * @param array<string,mixed> $postData
     * @throws SuspiciousSubmissionException
     */
    public function assertClean(array $postData): void;

    public function fieldName(): string;
}
