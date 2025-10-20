<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\ErrorCode;
use App\Core\Logger;
use App\Core\MessageManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LoggerExtraTest extends TestCase
{
    /** @var list<string> */
    private array $channels = [];
    protected function tearDown(): void
    {
        // nettoyage des répertoires de logs créés par ces tests
        $root = dirname(__DIR__, 3) . '/Logs';
        foreach ($this->channels as $ch) {
            $dir = $root . '/' . $ch;
            if (is_dir($dir)) {
                $files = glob($dir . '/*.log');
                if (is_array($files)) {
                    foreach ($files as $f) {
                        @unlink($f);
                    }
                }
                @rmdir($dir);
            }
        }
        Logger::reset();
        parent::tearDown();
    }

    private function newChannel(string $prefix): string
    {
        $ch               = $prefix . '_' . uniqid();
        $this->channels[] = $ch;
        return $ch;
    }

    #[Test]
    public function reset_reinitialise_les_instances(): void
    {
        $channel = $this->newChannel('reset');
        $first   = Logger::getLogger($channel);
        $firstId = spl_object_id($first);
        Logger::reset();
        $second   = Logger::getLogger($channel);
        $secondId = spl_object_id($second);
        self::assertNotSame($firstId, $secondId, 'reset() doit bien vider le cache d’instances.');
    }

    #[Test]
    public function logCodeAndGetMessage_utilise_le_niveau_demande(): void
    {
        $channel = $this->newChannel('level_ok');
        $msg     = Logger::logCodeAndGetMessage($channel, 'warning', ErrorCode::AUTH_TECHNICAL_ERROR, ['k' => 'v']);
        self::assertSame(MessageManager::get(ErrorCode::AUTH_TECHNICAL_ERROR), $msg);
        self::assertDirectoryExists(dirname(__DIR__, 3) . '/Logs/' . $channel);
    }

    #[Test]
    public function logCodeAndGetMessage_fallback_sur_info_quand_niveau_inconnu(): void
    {
        $channel = $this->newChannel('fallback');
        $msg     = Logger::logCodeAndGetMessage($channel, 'niveau_inconnu', ErrorCode::AUTH_TECHNICAL_ERROR);
        self::assertSame(MessageManager::get(ErrorCode::AUTH_TECHNICAL_ERROR), $msg);
        self::assertDirectoryExists(dirname(__DIR__, 3) . '/Logs/' . $channel);
    }
}
