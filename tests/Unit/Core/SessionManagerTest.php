<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\SessionManager;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SessionManagerTest extends TestCase
{
    /** @var array<string,mixed>|null */
    private ?array $sessionBackup = null;
    protected function setUp(): void
    {
        // Sauvegarde propre de l'état global
        if (isset($_SESSION)) {
            /** @var array<string,mixed> $sess */
            $sess                = $_SESSION;
            $this->sessionBackup = $sess;
        } else {
            $this->sessionBackup = null;
        }

        if (!isset($_SESSION)) {
            /** @var array<string,mixed> */ // (optionnel, pour phpstan)
            $_SESSION = [];
        }
    }

    protected function tearDown(): void
    {
        // Restaure l'état global pour ne rien perturber
        if ($this->sessionBackup === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->sessionBackup;
        }
    }

    #[Test]
    public function has_returns_true_when_key_exists_and_false_otherwise(): void
    {
        $sm       = new SessionManager();
        $_SESSION = ['foo' => 'bar'];
        self::assertTrue($sm->has('foo'));
        self::assertFalse($sm->has('nope'));
    }

    #[Test]
    public function all_returns_entire_session_array(): void
    {
        $sm       = new SessionManager();
        $_SESSION = ['a' => 1, 'b' => 'x'];
        self::assertSame(['a' => 1, 'b' => 'x'], $sm->all());
    }

    #[Test]
    public function remove_deletes_only_the_given_key(): void
    {
        $sm       = new SessionManager();
        $_SESSION = ['a' => 1, 'b' => 2];
        $sm->remove('a');
        self::assertArrayNotHasKey('a', $_SESSION);
        self::assertSame(2, $_SESSION['b']);
    }

    #[Test]
    public function clear_resets_session_to_empty_array(): void
    {
        $sm       = new SessionManager();
        $_SESSION = ['a' => 1, 'b' => 2];
        $sm->clear();
        self::assertSame([], $_SESSION);
    }

    /**
     * On isole ce test pour pouvoir démarrer une session et régénérer l’ID
     * sans interférer avec les autres tests.
     */
    #[Test]
    #[RunInSeparateProcess]
    public function regenerate_changes_session_id(): void
    {
        // Démarre une session propre dans ce sous-processus
        @session_start();
        $sm     = new SessionManager();
        $before = session_id();
        // On peut appeler sans argument (par défaut true) ; pour être zen sur Windows,
        // on met false afin d’éviter d’éventuels soucis de fichiers de session.
        $sm->regenerateKeepOld();
        $after = session_id();
        self::assertNotSame('', $before);
        self::assertNotSame('', $after);
        self::assertNotSame($before, $after, 'Le session_id devrait changer après regenerateKeepOld().');
    }
}
