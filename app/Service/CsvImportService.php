<?php

namespace App\Service;

use PDO;
use PDOException;

class CsvImportService
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return string Message de fin avec stats
     */
    public function importCsv(string $csvFile, string $tableName, string $delimiter = ';'): string
    {
        if (!is_file($csvFile)) {
            throw new \RuntimeException("CSV file not found: $csvFile");
        }

        // On nettoie la table (optionnel — à toi d’adapter)
        $this->pdo->exec("DELETE FROM `$tableName`");

        $handle = fopen($csvFile, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open CSV file: $csvFile");
        }

        $rowCount = 0;

        // Lecture de l’en-tête
        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false || count($header) === 0) {
            fclose($handle);
            throw new \RuntimeException('Invalid or empty CSV header.');
        }

        // Trim & validation
        $header = array_map(static fn($col) => trim((string) $col), $header);
        if (in_array('', $header, true)) {
            fclose($handle);
            throw new \RuntimeException('The CSV header contains empty column names.');
        }

        // Préparation requête
        $colsQuoted   = implode(',', array_map(static fn($c) => "`$c`", $header));
        $placeholders = implode(',', array_fill(0, count($header), '?'));
        $sql          = "INSERT INTO `$tableName` ($colsQuoted) VALUES ($placeholders)";
        $stmt         = $this->pdo->prepare($sql);

        // Transaction pour performance/atomicité
        $this->pdo->beginTransaction();

        try {
            while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                if (count($data) !== count($header)) {
                    throw new \RuntimeException('Columns count mismatch between header and row.');
                }

                // Normalisation & transformations
                $data = $this->transformRow($data, $tableName, $header);

                $stmt->execute($data);
                $rowCount++;
            }

            $this->pdo->commit();
        } catch (PDOException|\Throwable $e) {
            $this->pdo->rollBack();
            fclose($handle);
            throw $e;
        }

        fclose($handle);

        return "Import finished for `$tableName`: $rowCount rows inserted.";
    }

    /**
     * Transformations spécifiques par table.
     *
     * @param array<int, string|null> $row
     * @param string[]                 $header
     * @return array<int, string|null>
     */
    private function transformRow(array $row, string $tableName, array $header): array
    {
        // Remplacer les chaînes vides par NULL
        foreach ($row as $i => $value) {
            if ($value === '') {
                $row[$i] = null;
            }
        }

        return $row;
    }
}
