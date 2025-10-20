<?php

declare(strict_types=1);

/**
 * Fakes Mailjet classes used by the SUT.
 * Elles sont déclarées *avant* l'import du SUT et ne sortent pas du namespace \Mailjet,
 * donc aucun impact sur le reste des tests (on ne fait pas d'appel réel à l'API).
 */

namespace Mailjet {
    final class Resources
    {
        /** @var array<string,mixed> */
        public static array $Email = ['resource' => 'email', 'version' => 'v3.1'];
        // le contenu exact n’a pas d’importance pour le test, seul le type compte
    }

    final class Client
    {
        /** @var array<string,mixed> */
        public static array $behavior = [
            'mode'   => 'success',
            'status' => 200,
            'reason' => 'OK',
        ];

        /** @param array<string,mixed> $opts */
        public function __construct(
            private string $k,
            private string $s,
            private bool $secure,
            private array $opts = [],
        ) {
        }

        private function touch(): void
        {
            $ver    = $this->opts['version'] ?? null;
            $verStr = \is_string($ver) ? $ver : '';
            $sink   = $this->k . $this->s . ($this->secure ? '1' : '0') . $verStr;
            if ($sink === "\0") {
                /* no-op */
            }
        }

        /**
         * Signature alignée avec l’usage réel : la ressource Mailjet est un array.
         * @param array<string,mixed> $resource  (non utilisé par le double)
         * @param array<string,mixed> $payload
         */
        public function post(array $resource, array $payload): object
        {
            $this->touch();

            $b = self::$behavior;
            if (($b['mode'] ?? '') === 'throw') {
                throw new \RuntimeException('boom');
            }

            $status = \is_int($b['status'] ?? null) ? $b['status'] : 200;
            $reason = \is_string($b['reason'] ?? null) ? $b['reason'] : 'OK';

            $success = (($b['mode'] ?? '') !== 'http_error');

            /** @var array<string,mixed> $data */
            $data = [
                'Messages' => [[
                    'Status' => (($b['mode'] ?? '') === 'functional_error') ? 'error' : 'success',
                    'Errors' => (($b['mode'] ?? '') === 'functional_error') ? [['ErrorMessage' => 'X']] : null,
                ]],
            ];

            return new \Tests\Unit\Infrastructure\Mail\FakeMailjetResponse(
                $success,
                $status,
                $reason,
                ['payload' => $payload],
                $data
            );
        }
    }
}

namespace Tests\Unit\Infrastructure\Mail {

    use App\Core\Logger;
    use App\Infrastructure\Mail\MailjetMailer;
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;

    final class FakeMailjetResponse
    {
        /**
         * @param array<string,mixed> $data
         */
        public function __construct(private bool $success, private int $status, private string $reason, private mixed $body, private array $data)
        {
        }

        public function success(): bool
        {
            return $this->success;
        }
        public function getStatus(): int
        {
            return $this->status;
        }
        public function getReasonPhrase(): string
        {
            return $this->reason;
        }
        public function getBody(): mixed
        {
            return $this->body;
        }

        /** @return array<string,mixed> */
        public function getData(): array
        {
            return $this->data;
        }
    }

    final class MailjetMailerTest extends TestCase
    {
        private string $root;
        private string $tplDir;
        private string $tplFile;
        protected function setUp(): void
        {
            // tests/Unit/Infrastructure/Mail -> projet
            $this->root   = \dirname(__DIR__, 4);
            $this->tplDir = $this->root . '/resources/mail/templates';
            if (!is_dir($this->tplDir)) {
                mkdir($this->tplDir, 0777, true);
            }

            $this->tplFile = $this->tplDir . '/_mailjet_test_template.html';
            file_put_contents($this->tplFile, '<h1>Hello {username}</h1><p>{link}</p>');
            \Mailjet\Client::$behavior = ['mode' => 'success', 'status' => 200, 'reason' => 'OK'];
        }

        protected function tearDown(): void
        {
            if (is_file($this->tplFile)) {
                @unlink($this->tplFile);
            }

            // Nettoyage de base pour les logs mail si présents
            $mailLogDir = $this->root . '/Logs/mail';
            if (is_dir($mailLogDir)) {
                foreach (glob($mailLogDir . '/*.log') ?: [] as $f) {
                    @unlink($f);
                }
                @rmdir($mailLogDir);
            }

            Logger::reset();
            parent::tearDown();
        }

        private function makeMailer(): MailjetMailer
        {
            return new MailjetMailer(
                'key_xxx',
                'secret_xxx',
                'no-reply@example.test',
                'Coding Blog'
            );
        }

        #[Test]
        public function send_retourne_true_quand_mailjet_repond_success(): void
        {
            \Mailjet\Client::$behavior = ['mode' => 'success', 'status' => 200, 'reason' => 'OK'];

            $ok = $this->makeMailer()->send(
                'to@example.test',
                'Jane',
                'Sujet',
                \basename($this->tplFile),
                ['username' => 'Jane', 'link' => 'https://example.test/x']
            );
            self::assertTrue($ok);
        }

        #[Test]
        public function send_retourne_false_si_template_manquant(): void
        {
            // Fichier supprimé → le SUT doit retourner false (et logger une erreur)
            @unlink($this->tplFile);

            $ok = $this->makeMailer()->send(
                'to@example.test',
                'Jane',
                'Sujet',
                \basename($this->tplFile),
                ['username' => 'Jane']
            );
            self::assertFalse($ok);
        }

        #[Test]
        public function send_retourne_false_en_cas_d_http_error(): void
        {
            \Mailjet\Client::$behavior = ['mode' => 'http_error', 'status' => 500, 'reason' => 'Server Error'];

            $ok = $this->makeMailer()->send(
                'to@example.test',
                'Jane',
                'Sujet',
                \basename($this->tplFile),
                ['username' => 'Jane']
            );
            self::assertFalse($ok);
        }

        #[Test]
        public function send_retourne_false_en_cas_d_erreur_fonctionnelle(): void
        {
            \Mailjet\Client::$behavior = ['mode' => 'functional_error', 'status' => 200, 'reason' => 'OK'];

            $ok = $this->makeMailer()->send(
                'to@example.test',
                'Jane',
                'Sujet',
                \basename($this->tplFile),
                ['username' => 'Jane']
            );
            self::assertFalse($ok);
        }

        #[Test]
        public function send_retourne_false_en_cas_exception_client(): void
        {
            \Mailjet\Client::$behavior = ['mode' => 'throw'];

            $ok = $this->makeMailer()->send(
                'to@example.test',
                'Jane',
                'Sujet',
                \basename($this->tplFile),
                ['username' => 'Jane']
            );
            self::assertFalse($ok);
        }
    }

}
