<?php

function afficherArborescenceComplete(string $chemin, string $prefixe = '')
{
    if (!is_dir($chemin)) {
        echo "Chemin invalide : $chemin" . PHP_EOL;
        return;
    }

    $elements = scandir($chemin);
    foreach ($elements as $element) {
        if ($element === '.' || $element === '..') continue;

        $cheminComplet = $chemin . DIRECTORY_SEPARATOR . $element;
        echo $prefixe . '|-- ' . $element . PHP_EOL;

        if (is_dir($cheminComplet)) {
            afficherArborescenceComplete($cheminComplet, $prefixe . '|   ');
        }
    }
}

function afficherProjetsMetiers(string $racine, array $dossiersCibles)
{
    echo "Projet's tree structure : " . realpath($racine) . PHP_EOL . PHP_EOL;

    $elements = scandir($racine);
    foreach ($elements as $element) {
        if ($element === '.' || $element === '..') continue;
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

$dossiersAAfficher = ['app', 'assets', 'bin', 'data', 'Logs', 'public', 'resources', 'tests', 'var'];

$cheminDuProjet = __DIR__;

afficherProjetsMetiers($cheminDuProjet, $dossiersAAfficher);
