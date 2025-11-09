<?php

namespace App\Service;

use PDO;
use PDOException;
use RuntimeException;
use InvalidArgumentException;

/**
 * Service for importing CSV data into a database table.
 */
class CsvImportService
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Imports data from a CSV file into a given database table.
     *
     * @throws RuntimeException
     * @throws PDOException
     * @throws \Throwable
     */
    public function importCsv(string $csvFile, string $tableName, string $delimiter = ';'): string
    {
        $this->assertFileExists($csvFile);
        $this->clearTable($tableName);

        $handle = $this->openCsvFile($csvFile);
        $header = $this->readHeader($handle, $delimiter);

        $stmt = $this->prepareInsertStatement($tableName, $header);

        $this->pdo->beginTransaction();
        $rowCount = 0;

        try {
            $rowCount = $this->processRows($handle, $delimiter, $header, $stmt);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            fclose($handle);
            throw $e;
        }

        fclose($handle);
        return "Import finished for `$tableName`: $rowCount rows inserted.";
    }

    // ───────────────────────────
    // 🔹 Small specialized helpers
    // ───────────────────────────

    private function assertFileExists(string $csvFile): void
    {
        if (!is_file($csvFile)) {
            throw new RuntimeException("CSV file not found: $csvFile");
        }
    }

    private function clearTable(string $tableName): void
    {
        $this->pdo->exec("DELETE FROM `$tableName`");
    }

    /** @return resource */
    private function openCsvFile(string $csvFile)
    {
        $handle = fopen($csvFile, 'r');
        if ($handle === false) {
            throw new RuntimeException("Unable to open CSV file: $csvFile");
        }
        return $handle;
    }

    /** 
     * @param resource $handle
     * @return non-empty-list<string>
     */
    private function readHeader($handle, string $delimiter): array
    {
        if (!\is_resource($handle)) {
            throw new InvalidArgumentException('CSV handle must be a resource');
        }

        $header = \fgetcsv($handle, 0, $delimiter);
        if ($header === false || $header === []) {
            throw new RuntimeException('Invalid or empty CSV header.');
        }

        /** @var non-empty-list<string> $header */
        $header = \array_map(
            static fn($col): string => \trim((string) $col),
            $header
        );

        if (\in_array('', $header, true)) {
            throw new RuntimeException('The CSV header contains empty column names.');
        }

        return $header;
    }

    /** @param string[] $header */
    private function prepareInsertStatement(string $tableName, array $header): \PDOStatement
    {
        $colsQuoted   = implode(',', array_map(static fn ($col) => "`$col`", $header));
        $placeholders = implode(',', array_fill(0, count($header), '?'));
        $sql          = "INSERT INTO `$tableName` ($colsQuoted) VALUES ($placeholders)";
        return $this->pdo->prepare($sql);
    }

    /**
     * @param resource $handle
     * @param string[] $header
     */
    private function processRows($handle, string $delimiter, array $header, \PDOStatement $stmt): int
    {
        $rowCount = 0;
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($data) !== count($header)) {
                throw new RuntimeException('Columns count mismatch between header and row.');
            }

            $stmt->execute($this->transformRow($data));
            $rowCount++;
        }
        return $rowCount;
    }

    /**
     * @param  list<string|null> $row
     * @return list<string|null>
     */
    private function transformRow(array $row): array
    {
        foreach ($row as $i => $value) {
            if ($value === '') {
                $row[$i] = null;
            }
        }
        
        /** @var list<string|null> $row */
        return $row;
    }
}
