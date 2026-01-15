<?php

declare(strict_types=1);

/**
 * Affiche l’arborescence complète d’un dossier.
 */
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

/**
 * Affiche la structure "métier" d’un projet (fichiers racine + dossiers ciblés).
 *
 * @param array<int,string> $dossiersCibles
 */
function afficherProjetsMetiers(string $racine, array $dossiersCibles): void
{
    $racineReelle = realpath($racine) ?: $racine;
    echo "Projet's tree structure : " . $racineReelle . PHP_EOL . PHP_EOL;

    $elements = scandir($racine);
    if ($elements === false) {
        echo "Impossible de lire le dossier : $racine" . PHP_EOL;
        return;
    }

    foreach ($elements as $element) {
        if ($element === '.' || $element === '..') {
            continue;
        }

        $cheminComplet = $racine . DIRECTORY_SEPARATOR . $element;

        if (is_file($cheminComplet)) {
            echo '|-- ' . $element . PHP_EOL;
        }
    }

    foreach ($dossiersCibles as $dossier) {
        $cheminComplet = $racine . DIRECTORY_SEPARATOR . $dossier;

        if (is_dir($cheminComplet)) {
            echo '|-- ' . $dossier . PHP_EOL;
            afficherArborescenceComplete($cheminComplet, '|   ');
        }
    }
}

/**
 * Point d’entrée CLI (évite les effets de bord lors d’un include/require).
 */
function main(): void
{
    $dossiersAAfficher = ['app', 'assets', 'bin', 'data', 'Logs', 'public', 'resources', 'tests', 'var'];
    $cheminDuProjet    = __DIR__;

    afficherProjetsMetiers($cheminDuProjet, $dossiersAAfficher);
}

// Exécute uniquement si le fichier est lancé directement (pas inclus).
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    main();
}
