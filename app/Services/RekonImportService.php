<?php

namespace App\Services;

use App\Models\RekonData;
use App\Exceptions\ImportException;
use App\Exceptions\DatabaseException;
use App\Exceptions\FileProcessingException;
use Illuminate\Http\UploadedFile;

class RekonImportService
{
    private LoggingService $logger;

    public function __construct(LoggingService $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Import data dari Excel/CSV file with enhanced error handling
     */
    public function importFromFile(UploadedFile $file): array
    {
        $startTime = microtime(true);
        $imported = 0;
        $errors = [];
        $batchId = 'legacy_import_' . uniqid();

        try {
            // Validate file
            $this->validateFile($file);

            $this->logger->info('Starting legacy file import', [
                'batch_id' => $batchId,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
            ]);

            $this->logger->logFileImport(
                $file->getClientOriginalName(),
                $file->getSize(),
                'legacy',
                ['status' => 'started', 'batch_id' => $batchId]
            );

            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();

            // Assume header is in row 1
            $headerRow = $worksheet->rangeToArray('A1:' . $highestColumn . '1', NULL, TRUE, FALSE)[0];
            $columnMapping = $this->mapColumns($headerRow);

            if (empty($columnMapping)) {
                throw new ImportException(
                    'Format file tidak sesuai. Pastikan kolom: SEKOLAH, ID_SISWA, NAMA_SISWA, KELAS, JURUSAN, TAHUN, BULAN, DANA_MASYARAKAT',
                    'Format file tidak valid. Pastikan file memiliki header yang sesuai dengan format SPP Rekon.',
                    1,
                    null,
                    $file->getClientOriginalName()
                );
            }

            // Process data rows (start from row 2 to skip header)
            $batchContext = $this->logger->createBatchContext($batchId, 'legacy_import');
            $totalRows = $highestRow - 1;

            for ($row = 2; $row <= $highestRow; $row++) {
                try {
                    $rowData = $worksheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, NULL, TRUE, FALSE)[0];
                    $data = $this->extractRowData($rowData, $columnMapping, $row);

                    if ($data) {
                        try {
                            RekonData::create($data);
                            $imported++;
                        } catch (\Exception $dbError) {
                            throw new DatabaseException(
                                'Failed to insert RekonData record: ' . $dbError->getMessage(),
                                "Gagal menyimpan data pada baris $row. Periksa format data.",
                                'insert',
                                'rekon_data',
                                null,
                                ['row_data' => $data]
                            );
                        }
                    }

                    // Log progress every 100 rows
                    if ($row % 100 === 0) {
                        $this->logger->updateBatchProgress($batchContext, $row - 1, $totalRows);
                    }

                } catch (ImportException $e) {
                    $errors[] = "Baris $row: " . $e->getUserMessage();
                    $this->logger->warning('Row processing failed', [
                        'batch_id' => $batchId,
                        'row' => $row,
                        'error' => $e->getMessage(),
                        'user_message' => $e->getUserMessage()
                    ]);
                } catch (\Exception $e) {
                    $errors[] = "Baris $row: " . $e->getMessage();
                    $this->logger->error('Unexpected error processing row', [
                        'batch_id' => $batchId,
                        'row' => $row,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            $duration = (microtime(true) - $startTime) * 1000;
            $result = [
                'success' => true,
                'imported' => $imported,
                'total_rows' => $totalRows,
                'errors' => $errors,
                'error_count' => count($errors),
                'duration_ms' => round($duration, 2),
                'batch_id' => $batchId
            ];

            $this->logger->completeBatch($batchContext, $result);
            $this->logger->logFileImport(
                $file->getClientOriginalName(),
                $file->getSize(),
                'legacy',
                $result
            );

            $this->logger->logPerformance('legacy_import', [
                'duration_ms' => $duration,
                'rows_processed' => $totalRows,
                'rows_per_second' => round($totalRows / ($duration / 1000), 2),
                'success_rate' => round(($imported / $totalRows) * 100, 2),
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
            ]);

            return $result;

        } catch (ImportException | DatabaseException | FileProcessingException $e) {
            $this->logger->error('Import failed', [
                'batch_id' => $batchId,
                'error' => $e->getMessage(),
                'error_code' => $e->getErrorCode() ?? null,
                'context' => $e->getContext() ?? []
            ]);

            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected import error', [
                'batch_id' => $batchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new FileProcessingException(
                'Error importing data: ' . $e->getMessage(),
                'Terjadi kesalahan saat memproses file. Pastikan file tidak rusak dan formatnya benar.',
                $file->getClientOriginalName(),
                $file->getSize(),
                $file->getMimeType(),
                $file->getRealPath(),
                ['original_error' => $e->getMessage()]
            );
        }
    }

    /**
     * Validate uploaded file
     */
    private function validateFile(UploadedFile $file): void
    {
        // Check file size (max 50MB)
        $maxSize = 50 * 1024 * 1024; // 50MB
        if ($file->getSize() > $maxSize) {
            throw new FileProcessingException(
                "File size {$file->getSize()} exceeds maximum allowed size of {$maxSize}",
                "Ukuran file terlalu besar. Maksimal ukuran file adalah 50MB.",
                $file->getClientOriginalName(),
                $file->getSize(),
                $file->getMimeType()
            );
        }

        // Check file extension
        $allowedExtensions = ['csv', 'xlsx', 'xls'];
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, $allowedExtensions)) {
            throw new FileProcessingException(
                "File extension '{$extension}' is not allowed",
                "Format file tidak didukung. Gunakan file CSV, XLSX, atau XLS.",
                $file->getClientOriginalName(),
                $file->getSize(),
                $file->getMimeType()
            );
        }

        // Check if file is readable
        if (!$file->isReadable()) {
            throw new FileProcessingException(
                "File is not readable",
                "File tidak dapat dibaca. Pastikan file tidak rusak.",
                $file->getClientOriginalName(),
                $file->getSize(),
                $file->getMimeType()
            );
        }
    }

    /**
     * Map column names to indices
     */
    private function mapColumns($headerRow): array
    {
        $mapping = [];
        $expectedColumns = [
            'SEKOLAH', 'ID_SISWA', 'NAMA_SISWA', 'ALAMAT', 'KELAS', 'JURUSAN',
            'JUM_TAGIHAN', 'BIAYA_ADM', 'TAGIHAN_LAIN', 'KET_TAGIHAN_LAIN', 'KETERANGAN',
            'TAHUN', 'BULAN', 'DANA_MASYARAKAT', 'TGL_TX', 'STS_BAYAR', 'KD_CAB',
            'KD_USER', 'STS_REVERSAL', 'NO_BUKTI'
        ];

        foreach ($headerRow as $index => $columnName) {
            $cleanName = strtoupper(trim(str_replace([' ', '.', '-'], '_', $columnName)));

            if (in_array($cleanName, $expectedColumns)) {
                $mapping[$cleanName] = $index;
            }
        }

        return $mapping;
    }

    /**
     * Extract data from row using column mapping with enhanced validation
     */
    private function extractRowData($rowData, $mapping, int $rowNumber): ?array
    {
        $requiredFields = ['sekolah', 'id_siswa', 'nama_siswa', 'tahun', 'bulan'];
        $data = [];

        try {
            // Validate required fields first
            $missingFields = [];
            foreach ($requiredFields as $field) {
                $columnIndex = $mapping[strtoupper($field)] ?? null;
                if ($columnIndex === null || empty($rowData[$columnIndex])) {
                    $missingFields[] = $field;
                }
            }

            if (!empty($missingFields)) {
                throw new ImportException(
                    "Missing required fields: " . implode(', ', $missingFields),
                    "Data tidak lengkap pada baris ini. Pastikan kolom wajib terisi.",
                    $rowNumber,
                    implode(', ', $missingFields)
                );
            }

            return [
                'sekolah' => trim($rowData[$mapping['SEKOLAH']] ?? ''),
                'id_siswa' => trim($rowData[$mapping['ID_SISWA']] ?? ''),
                'nama_siswa' => trim($rowData[$mapping['NAMA_SISWA']] ?? ''),
                'alamat' => trim($rowData[$mapping['ALAMAT']] ?? ''),
                'kelas' => trim($rowData[$mapping['KELAS']] ?? ''),
                'jurusan' => trim($rowData[$mapping['JURUSAN']] ?? ''),
                'jum_tagihan' => $this->parseNumber($rowData[$mapping['JUM_TAGIHAN']] ?? 0),
                'biaya_adm' => $this->parseNumber($rowData[$mapping['BIAYA_ADM']] ?? 0),
                'tagihan_lain' => $this->parseNumber($rowData[$mapping['TAGIHAN_LAIN']] ?? 0),
                'ket_tagihan_lain' => trim($rowData[$mapping['KET_TAGIHAN_LAIN']] ?? ''),
                'keterangan' => trim($rowData[$mapping['KETERANGAN']] ?? ''),
                'tahun' => $this->parseYear($rowData[$mapping['TAHUN']] ?? date('Y'), $rowNumber),
                'bulan' => $this->parseMonth($rowData[$mapping['BULAN']] ?? date('n'), $rowNumber),
                'dana_masyarakat' => trim($rowData[$mapping['DANA_MASYARAKAT']] ?? ''),
                'tgl_tx' => $this->parseDate($rowData[$mapping['TGL_TX']] ?? now(), $rowNumber),
                'tgl_tx_formatted' => trim($rowData[$mapping['TGL_TX']] ?? ''),
                'sts_bayar' => (int) ($rowData[$mapping['STS_BAYAR']] ?? 1),
                'kd_cab' => trim($rowData[$mapping['KD_CAB']] ?? ''),
                'kd_user' => trim($rowData[$mapping['KD_USER']] ?? 'system'),
                'sts_reversal' => (int) ($rowData[$mapping['STS_REVERSAL']] ?? 0),
                'no_bukti' => trim($rowData[$mapping['NO_BUKTI']] ?? ''),
            ];

        } catch (ImportException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ImportException(
                "Error extracting row data: " . $e->getMessage(),
                "Format data pada baris ini tidak valid. Periksa kembali format setiap kolom.",
                $rowNumber,
                null,
                null,
                ['original_error' => $e->getMessage()]
            );
        }
    }

    /**
     * Parse and validate year
     */
    private function parseYear($value, int $rowNumber): int
    {
        $year = (int) $value;

        if ($year < 2000 || $year > 2100) {
            throw new ImportException(
                "Invalid year value: {$value}",
                "Tahun tidak valid. Gunakan format tahun 4 digit (contoh: 2024).",
                $rowNumber,
                'tahun'
            );
        }

        return $year;
    }

    /**
     * Parse and validate month
     */
    private function parseMonth($value, int $rowNumber): int
    {
        $month = (int) $value;

        if ($month < 1 || $month > 12) {
            throw new ImportException(
                "Invalid month value: {$value}",
                "Bulan tidak valid. Gunakan angka 1-12.",
                $rowNumber,
                'bulan'
            );
        }

        return $month;
    }

    /**
     * Parse number from string
     */
    private function parseNumber($value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        $cleaned = preg_replace('/[^0-9]/', '', $value);
        return (int) $cleaned;
    }

    /**
     * Parse date from string with enhanced validation
     */
    private function parseDate($value, int $rowNumber)
    {
        if (empty($value)) {
            return now();
        }

        try {
            $formats = ['d/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d', 'd/m/y', 'd-M-Y'];

            foreach ($formats as $format) {
                $date = \DateTime::createFromFormat($format, $value);
                if ($date) {
                    // Validate date is reasonable (not too far in future or past)
                    $year = (int) $date->format('Y');
                    $currentYear = (int) date('Y');

                    if ($year < $currentYear - 10 || $year > $currentYear + 2) {
                        throw new ImportException(
                            "Date year out of range: {$year}",
                            "Tanggal tidak valid. Tahun harus antara " . ($currentYear - 10) . " dan " . ($currentYear + 2),
                            $rowNumber,
                            'tgl_tx'
                        );
                    }

                    return $date;
                }
            }

            // Try to parse as natural language
            $date = new \DateTime($value);
            if ($date) {
                return $date;
            }

            throw new ImportException(
                "Unable to parse date: {$value}",
                "Format tanggal tidak valid. Gunakan format: DD/MM/YYYY atau YYYY-MM-DD.",
                $rowNumber,
                'tgl_tx'
            );

        } catch (ImportException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ImportException(
                "Error parsing date: " . $e->getMessage(),
                "Format tanggal tidak valid. Gunakan format: DD/MM/YYYY atau YYYY-MM-DD.",
                $rowNumber,
                'tgl_tx',
                null,
                ['original_value' => $value]
            );
        }
    }
}