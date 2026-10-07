<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use RuntimeException;

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
        echo 'Index method executed';
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
        throw new RuntimeException('Intentional error');
    }

    public function dummy(): string
    {
        return 'dummy';
    }

    /**
     * Mock method accepting a route parameter.
     */
    public function show(string $id): void
    {
        echo 'User ID: ' . $id;
    }
}
