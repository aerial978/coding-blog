<?php

namespace Tests\Fixtures;

/**
 * Dummy controller used for unit testing.
 */
class DummyController
{
    /**
     * Mock index method.
     */
    public function index(): void
    {
        echo 'Méthode index exécutée';
    }

    /**
     * Mock greeting method.
     */
    public function hello(): void
    {
        echo 'Hello from dummy controller';
    }

    /**
     * Intentionally throws an exception.
     */
    public function error(): void
    {
        throw new \RuntimeException('Erreur volontaire');
    }
}
