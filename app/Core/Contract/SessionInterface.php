<?php

declare(strict_types=1);

namespace App\Core\Contract;

interface SessionInterface
{
    public function set(string $key, mixed $value): void;

    public function get(string $key, mixed $default = null): mixed;

    public function remove(string $key): void;

    public function clear(): void;

    /**
     * Régénère l'ID de session et supprime l'ancienne session (recommandé après login).
     */
    public function regenerateAndDeleteOld(): void;

    /**
     * Régénère l'ID de session sans supprimer l'ancienne session (cas spécifiques).
     */
    public function regenerateKeepOld(): void;
}
