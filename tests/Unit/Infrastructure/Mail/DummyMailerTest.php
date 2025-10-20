<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Mail;

use App\Core\Logger;
use App\Infrastructure\Mail\DummyMailer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DummyMailerTest extends TestCase
{
    private string $root;
    private string $tplDir;
    private string $tplFile;
    private string $logsDir;
    protected function setUp(): void
    {
        $this->root   = dirname(__DIR__, 4);
        // racine du projet
        $this->tplDir  = $this->root . '/resources/mail/templates';
        $this->logsDir = $this->root . '/Logs/mail';
        if (!is_dir($this->tplDir)) {
            mkdir($this->tplDir, 0777, true);
        }

        $this->tplFile = $this->tplDir . '/_dummy_test_template.html';
        file_put_contents($this->tplFile, '<h1>Hello {username}</h1><p>Click: {link}</p>');
    }

    protected function tearDown(): void
    {
        // Nettoyage template temporaire
        if (is_file($this->tplFile)) {
            @unlink($this->tplFile);
        }

        // Nettoyage des logs du canal "mail"
        if (is_dir($this->logsDir)) {
            $files = glob($this->logsDir . '/*.log');
            if (is_array($files)) {
                foreach ($files as $f) {
                    @unlink($f);
                }
            }
            @rmdir($this->logsDir);
        }

        // Reset du cache d'instances du Logger (au cas où)
        Logger::reset();
        parent::tearDown();
    }

    #[Test]
    public function send_retourne_true_et_genere_un_log(): void
    {
        $mailer = new DummyMailer('no-reply@example.test', 'Coding Blog');
        $ok     = $mailer->send('to@example.test', 'Jane', 'Sujet de test', basename($this->tplFile), ['username' => 'Jane', 'link' => 'https://example.test/x']);
        self::assertTrue($ok);
        // Le canal "mail" doit exister et avoir (au moins) un fichier de log.
        self::assertDirectoryExists($this->logsDir);
        $logs = glob($this->logsDir . '/*.log') ?: [];
        self::assertNotEmpty($logs, 'Aucun fichier de log créé pour le canal mail.');
    }

    #[Test]
    public function send_lance_exception_si_template_introuvable(): void
    {
        $mailer = new DummyMailer('no-reply@example.test', 'Coding Blog');
        $this->expectException(\RuntimeException::class);
        $mailer->send('to@example.test', 'Jane', 'Sujet', '_template_inexistant.html', ['username' => 'Jane']);
    }
}
