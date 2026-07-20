<?php

declare(strict_types=1);

namespace App\Security;

final class DisposableChecker
{
    /** @var array<string,bool> */
    private array $map = [];

    /**
     * @param array<int,string> $domains
     */
    public function __construct(array $domains)
    {
        foreach ($domains as $domain) {
            $domain = trim($domain);
            if ($domain === '') {
                continue;
            }
            $normalized             = mb_strtolower($domain);
            $this->map[$normalized] = true;
        }
    }

    public function isDisposable(string $email): bool
    {
        // On récupère le domaine après le @
        $pos = mb_strrpos($email, '@');
        if ($pos === false) {
            // Adresse invalide (mais ce cas est normalement géré ailleurs)
            return false;
        }

        $domain = mb_substr($email, $pos + 1);
        $domain = mb_strtolower(trim($domain));

        // On teste le domaine tel quel
        if (isset($this->map[$domain])) {
            return true;
        }

        // Optionnel : gérer des sous-domaines type "xxx.yopmail.com"
        // on peut remonter jusqu'au TLD
        $parts = explode('.', $domain);
        if (count($parts) > 2) {
            $base = implode('.', array_slice($parts, -2)); // ex: foo.bar.yopmail.com → yopmail.com
            if (isset($this->map[$base])) {
                return true;
            }
        }

        return false;
    }
}
