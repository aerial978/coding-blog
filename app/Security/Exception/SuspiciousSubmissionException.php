<?php

declare(strict_types=1);

namespace App\Security\Exception;

final class SuspiciousSubmissionException extends \RuntimeException
{
    /** @var string */
    private string $reason;

    /** @var array<string,mixed> */
    private array $context;

    /**
     * @param string $reason  Code interne (ex. 'min_delay_not_met', 'max_delay_exceeded', 'honeypot')
     * @param array<string,mixed> $context  Infos complémentaires (form, elapsed, min, max, etc.)
     */
    public function __construct(string $reason, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($reason, 0, $previous);

        $this->reason  = $reason;
        $this->context = $context;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    /**
     * @return array<string,mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
