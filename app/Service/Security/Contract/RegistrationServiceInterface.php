<?php

declare(strict_types=1);

namespace App\Service\Security\Contract;

interface RegistrationServiceInterface
{
    /** @param array<string,mixed> $form
     *  @return array<string,mixed>
     */
    public function register(array $form): array;
}
