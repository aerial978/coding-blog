<?php

declare(strict_types=1);

namespace Tests\Double;

use App\Core\Contract\FlashInterface;

/**
 * Fake Flash qui :
 * - enregistre tous les messages dans $messages,
 * - stocke des payloads arbitraires dans $store,
 * - consumeMany() ne purge QUE les niveaux passés (success|error|warning|info),
 *   et laisse intactes les autres clés (ex: register_state, old, etc.).
 */
final class FlashFake implements FlashInterface
{
    /** @var list<array{0:string,1:string}> */
    public array $messages = [];

    /** @var array<string, mixed> */
    public array $store = [];

    public function add(string $type, string $message): void
    {
        $this->messages[] = [$type, $message];

        // Normaliser le "bucket" du type en list<string>
        $bucket = $this->store[$type] ?? [];
        $list   = [];

        if (is_array($bucket)) {
            foreach ($bucket as $v) {
                if (is_string($v)) {
                    $list[] = $v;
                }
            }
        }

        $list[]              = $message;
        $this->store[$type]  = $list;
    }

    public function put(string $key, mixed $value): void
    {
        $this->store[$key] = $value;
    }

    public function take(string $key, mixed $default = null): mixed
    {
        $val = $this->store[$key] ?? $default;
        unset($this->store[$key]);
        return $val;
    }

    /**
     * @param list<'success'|'error'|'warning'|'info'> $levels
     * @return array{
     *   success: list<string>,
     *   error:   list<string>,
     *   warning: list<string>,
     *   info:    list<string>
     * }
     */
    public function consumeMany(array $levels = ['success','error','warning','info']): array
    {
        $out = [
            'success' => [],
            'error'   => [],
            'warning' => [],
            'info'    => [],
        ];

        foreach ($levels as $lvl) {
            $vals = $this->store[$lvl] ?? [];
            $list = [];

            if (is_array($vals)) {
                foreach ($vals as $v) {
                    if (is_string($v)) {
                        $list[] = $v;
                    }
                }
            }

            $out[$lvl] = $list;
            unset($this->store[$lvl]); // on consomme seulement ces niveaux
        }

        return $out;
    }
}
