<?php

declare(strict_types=1);

function afficherArborescenceComplete(string $chemin, string $prefixe = ''): void
{
    if (!is_dir($chemin)) {
        echo "Chemin invalide : $chemin" . PHP_EOL;
        return;
    }

    $elements = scandir($chemin);
    if ($elements === false) {
        echo "Impossible de lire le dossier : $chemin" . PHP_EOL;
        return;
    }

    foreach ($elements as $element) {
        if ($element === '.' || $element === '..') {
            continue;
        }

        $cheminComplet = $chemin . DIRECTORY_SEPARATOR . $element;
        echo $prefixe . '|-- ' . $element . PHP_EOL;

        if (is_dir($cheminComplet)) {
            afficherArborescenceComplete($cheminComplet, $prefixe . '|   ');
        }
    }
}

function afficherProjetsMetiers(string $racine, array $dossiersCibles): void
{
    afficherEnteteProjet($racine);

    $elements = lireElementsDossier($racine);
    if ($elements === null) {
        return;
    }

    afficherFichiersRacine($racine, $elements);
    afficherDossiersCibles($racine, $dossiersCibles);
}

function afficherEnteteProjet(string $racine): void
{
    $racineReelle = realpath($racine) ?: $racine;
    echo "Projet's tree structure : " . $racineReelle . PHP_EOL . PHP_EOL;
}

/**
 * @return array<int,string>|null
 */
function lireElementsDossier(string $racine): ?array
{
    $elements = scandir($racine);

    if ($elements === false) {
        echo "Impossible de lire le dossier : $racine" . PHP_EOL;
        return null;
    }

    /** @var array<int,string> $elements */
    return $elements;
}

/**
 * @param array<int,string> $elements
 */
function afficherFichiersRacine(string $racine, array $elements): void
{
    foreach ($elements as $element) {
        if (estEntreeSpeciale($element)) {
            continue;
        }

        $cheminComplet = $racine . DIRECTORY_SEPARATOR . $element;

        if (is_file($cheminComplet)) {
            echo '|-- ' . $element . PHP_EOL;
        }
    }
}

/**
 * @param array<int,string> $dossiersCibles
 */
function afficherDossiersCibles(string $racine, array $dossiersCibles): void
{
    foreach ($dossiersCibles as $dossier) {
        $cheminComplet = $racine . DIRECTORY_SEPARATOR . $dossier;

        if (!is_dir($cheminComplet)) {
            continue;
        }

        echo '|-- ' . $dossier . PHP_EOL;
        afficherArborescenceComplete($cheminComplet, '|   ');
    }
}

function estEntreeSpeciale(string $element): bool
{
    return $element === '.' || $element === '..';
}

function main(): void
{
    $dossiersAAfficher = ['app', 'assets', 'bin', 'data', 'Logs', 'public', 'resources', 'tests', 'var'];
    $cheminDuProjet    = dirname(__DIR__);

    afficherProjetsMetiers($cheminDuProjet, $dossiersAAfficher);
}
