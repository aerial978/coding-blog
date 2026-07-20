<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\EnvLoader;
use App\Core\Database;
use App\Service\CsvImportService;

// 1) Charger l’env
EnvLoader::load(__DIR__ . '/../');

// 2) Récupérer un PDO du projet actuel
$pdo = (new Database())->getConnection();

// 3) Définir les CSV à importer
$csvFiles = [
    dirname(__DIR__) . '/data/user.csv' => 'user',
];

$importer = new CsvImportService($pdo);

foreach ($csvFiles as $csvFile => $table) {
    // (Optionnel) FK OFF le temps de l’import — utile si relations
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    echo "Importing $csvFile into `$table` ..." . PHP_EOL;

    try {
        $message = $importer->importCsv($csvFile, $table, ';');
        echo $message . PHP_EOL;
    } catch (Throwable $e) {
        // Log minimal en CLI
        fwrite(STDERR, "Error importing $csvFile: {$e->getMessage()}" . PHP_EOL);
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}

echo 'Done.' . PHP_EOL;
