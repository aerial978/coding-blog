<?php

namespace App\Service;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Service for importing CSV data into a database table.
 *
 * This class provides a reusable utility for reading a CSV file,
 * validating its structure, and inserting its rows into a specified
 * database table. The import runs inside a transaction to ensure
 * atomicity and to improve performance.
 *
 * It supports configurable delimiters and includes basic validation
 * for header consistency and row integrity.
 */
class CsvImportService
{
    /**
     * Constructor.
     *
     * Initializes the CSV import service with a PDO database connection.
     *
     * @param PDO $pdo
     *     The PDO instance used for database interactions.
     */
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Imports data from a CSV file into a given database table.
     *
     * The method:
     *  - Validates the file existence and structure.
     *  - Deletes existing table data (reset mode, optional).
     *  - Reads and normalizes the CSV header.
     *  - Executes a bulk insertion transaction for performance.
     *
     * @param string $csvFile
     *     Path to the CSV file to import.
     * @param string $tableName
     *     Target database table name.
     * @param string $delimiter
     *     Field delimiter used in the CSV file (default: ";").
     *
     * @return string
     *     Summary message containing the total number of inserted rows.
     *
     * @throws \RuntimeException
     *     If the file cannot be read, has an invalid header,
     *     or contains inconsistent row structures.
     * @throws PDOException
     *     If a database error occurs during insertion.
     * @throws \Throwable
     *     For any unexpected error during processing.
     */
    public function importCsv(string $csvFile, string $tableName, string $delimiter = ';'): string
    {
        if (!is_file($csvFile)) {
            throw new RuntimeException("CSV file not found: $csvFile");
        }

        // On nettoie la table (optionnel — à toi d’adapter)
        $this->pdo->exec("DELETE FROM `$tableName`");

        $handle = fopen($csvFile, 'r');
        if ($handle === false) {
            throw new RuntimeException("Unable to open CSV file: $csvFile");
        }

        $rowCount = 0;

        // Lecture de l’en-tête
        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false || count($header) === 0) {
            fclose($handle);
            throw new RuntimeException('Invalid or empty CSV header.');
        }

        // Trim & validation
        $header = array_map(static fn ($col) => trim((string) $col), $header);
        if (in_array('', $header, true)) {
            fclose($handle);
            throw new RuntimeException('The CSV header contains empty column names.');
        }

        // Préparation requête
        $colsQuoted   = implode(',', array_map(static fn ($col) => "`$col`", $header));
        $placeholders = implode(',', array_fill(0, count($header), '?'));
        $sql          = "INSERT INTO `$tableName` ($colsQuoted) VALUES ($placeholders)";
        $stmt         = $this->pdo->prepare($sql);

        // Transaction pour performance/atomicité
        $this->pdo->beginTransaction();

        try {
            while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                if (count($data) !== count($header)) {
                    throw new RuntimeException('Columns count mismatch between header and row.');
                }

                // Normalisation & transformations
                $data = $this->transformRow($data);

                $stmt->execute($data);
                $rowCount++;
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            fclose($handle);
            throw $e;
        }

        fclose($handle);

        return "Import finished for `$tableName`: $rowCount rows inserted.";
    }

    /**
     * Applies row-specific transformations before insertion.
     *
     * This method can be customized per table to perform data normalization,
     * format adjustments, or any field-level pre-processing.
     * The default implementation replaces empty strings with NULL values.
     *
     * @param array<int, string|null> $row
     *     The row data as parsed from the CSV file.
     *
     * @return array<int, string|null>
     *     The transformed row ready for insertion.
     */
    private function transformRow(array $row): array
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
