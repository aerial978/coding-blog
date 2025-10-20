<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\CsvImportService;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de CsvImportService.
 */
final class CsvImportServiceTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];
    protected function tearDown(): void
    {
        // Nettoyage des fichiers temporaires éventuels
        foreach ($this->tempFiles as $f) {
            if (\is_file($f)) {
                @\unlink($f);
            }
        }

        // S'assure que le wrapper custom n'est plus enregistré
        if (\in_array('fail', \stream_get_wrappers(), true)) {
            @\stream_wrapper_unregister('fail');
        }

        parent::tearDown();
    }

    private function makeTempCsv(string $content): string
    {
        $file = \tempnam(\sys_get_temp_dir(), 'csv_') . '.csv';
        \file_put_contents($file, $content);
        $this->tempFiles[] = $file;
        return $file;
    }

    private function makePdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    // ─────────────────────────────────────────────────────────────────────
    // SUCCÈS : import simple
    // ─────────────────────────────────────────────────────────────────────

    #[Test]
    public function importCsv_insere_les_lignes_et_retourne_un_message(): void
    {
        $pdo = $this->makePdo();
        $pdo->exec('CREATE TABLE people (name TEXT, email TEXT)');
        $csv = $this->makeTempCsv("name;email\nAlice;alice@test.tld\nBob;bob@test.tld\n");
        $svc = new CsvImportService($pdo);
        $msg = $svc->importCsv($csv, 'people');
        $this->assertStringContainsString('Import finished for `people`: 2 rows inserted.', $msg);
        $stmt = $pdo->query('SELECT COUNT(*) FROM people');
        self::assertNotFalse($stmt);
        $count = (int) $stmt->fetchColumn();
        self::assertSame(2, $count);
    }

    // ─────────────────────────────────────────────────────────────────────
    // COUVERTURE DE transformRow() : chaînes vides → NULL
    // ─────────────────────────────────────────────────────────────────────

    #[Test]
    public function importCsv_convertit_les_chaines_vides_en_null(): void
    {
        $pdo = $this->makePdo();
        $pdo->exec('CREATE TABLE people (name TEXT, email TEXT, city TEXT)');
        // Champ email vide (entre deux ;) => fgetcsv() renvoie '' → doit devenir NULL
        $csv = $this->makeTempCsv("name;email;city\nJohn;;Paris\n");
        $svc = new CsvImportService($pdo);
        $msg = $svc->importCsv($csv, 'people');
        $this->assertStringContainsString('Import finished for `people`: 1 rows inserted.', $msg);
        // comptage
        $stmt = $pdo->query('SELECT COUNT(*) FROM people');
        self::assertNotFalse($stmt);
        $count = (int) $stmt->fetchColumn();
        self::assertSame(1, $count);

        // sélection de la ligne insérée
        $stmtRow = $pdo->query('SELECT name, email, city FROM people');
        self::assertNotFalse($stmtRow);
        $row = $stmtRow->fetch(PDO::FETCH_ASSOC);

        // ici, on “rassure” PHPStan : $row est bien un array associatif
        self::assertIsArray($row);
        /** @var array{name:string, email:null|string, city:string} $row */

        self::assertSame('John', $row['name']);
        self::assertSame('Paris', $row['city']);
        self::assertNull($row['email'], 'Le champ vide doit être inséré en tant que NULL.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // ERREURS : fichier manquant
    // ─────────────────────────────────────────────────────────────────────

    #[Test]
    public function importCsv_lance_exception_si_fichier_absent(): void
    {
        $pdo = $this->makePdo();
        $svc = new CsvImportService($pdo);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CSV file not found:');
        $svc->importCsv('/path/does/not/exist.csv', 't');
    }

    // ─────────────────────────────────────────────────────────────────────
    // ERREURS : header invalide / colonnes vides
    // ─────────────────────────────────────────────────────────────────────

    #[Test]
    public function importCsv_lance_exception_si_header_vide(): void
    {
        $pdo = $this->makePdo();
        $pdo->exec('CREATE TABLE t (a TEXT)');
        // Fichier **totalement vide** pour déclencher "Invalid or empty CSV header."
        $csv = $this->makeTempCsv('');
        $svc = new CsvImportService($pdo);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid or empty CSV header.');
        $svc->importCsv($csv, 't');
    }

    #[Test]
    public function importCsv_lance_exception_si_header_contient_colonne_vide(): void
    {
        $pdo = $this->makePdo();
        $pdo->exec('CREATE TABLE t (a TEXT)');
        // Une colonne vide après trim → "The CSV header contains empty column names."
        $csv = $this->makeTempCsv("a; \n1;2\n");
        $svc = new CsvImportService($pdo);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The CSV header contains empty column names.');
        $svc->importCsv($csv, 't');
    }

    // ─────────────────────────────────────────────────────────────────────
    // ERREURS : mismatch colonnes (ligne ≠ header)
    // ─────────────────────────────────────────────────────────────────────

    #[Test]
    public function importCsv_lance_exception_si_nb_colonnes_ne_correspond_pas(): void
    {
        $pdo = $this->makePdo();
        $pdo->exec('CREATE TABLE t (a TEXT, b TEXT)');
        $csv = $this->makeTempCsv("a;b\n1\n");
        $svc = new CsvImportService($pdo);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Columns count mismatch');
        $svc->importCsv($csv, 't');
    }

    // ─────────────────────────────────────────────────────────────────────
    // ERREURS : fopen échoue alors que is_file() est vrai
    // ─────────────────────────────────────────────────────────────────────

    #[Test]
    public function importCsv_lance_exception_si_fopen_echoue_meme_si_is_file_est_vrai(): void
    {
        if (!\in_array('fail', \stream_get_wrappers(), true)) {
            \stream_wrapper_register('fail', FailOpenStream::class);
        }

        $pdo = $this->makePdo();
        $pdo->exec('CREATE TABLE t (a TEXT)');
        $svc = new CsvImportService($pdo);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to open CSV file: fail://unreadable.csv');

        \set_error_handler(static function () {
            return true;
        });
        // masque le warning fopen
        try {
            $svc->importCsv('fail://unreadable.csv', 't');
        } finally {
            \restore_error_handler();
            // ← remet l'ancien handler (évite "risky test")
        }
    }
}

/**
 * Stream wrapper utilisé pour simuler un fopen() qui échoue
 * tout en laissant is_file() (url_stat) croire que le fichier existe.
 */
final class FailOpenStream
{
    /** @var resource|null requis par l’API des stream wrappers */
    public $context = null;

    /**
     * Simule un fichier « existant » (is_file() → true) via url_stat.
     * Retour minimal avec mode S_IFREG.
     *
     * @param string $path
     * @param int    $flags
     * @return array<int|string, mixed>   // ← plus de “|false” car on ne renvoie jamais false ici
     */
    public function url_stat(string $path, int $flags): array
    {
        // 0100000 (S_IFREG) | 0644
        return ['mode' => 0100000 | 0644, 'size' => 0, 'mtime' => \time()];
    }

    /**
     * Échec d’ouverture systématique → fopen() retournera false.
     */
    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        return false;
    }
}
