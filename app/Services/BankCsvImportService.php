<?php

namespace App\Services;

use App\Models\RekonData;
use App\Exceptions\ImportException;
use App\Exceptions\DatabaseException;
use App\Exceptions\FileProcessingException;
use Illuminate\Http\UploadedFile;

class BankCsvImportService
{
    private LoggingService $logger;

    public function __construct(LoggingService $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Import CSV dari Bank with enhanced error handling
     */
    public function importFromBankCsv(UploadedFile $file): array
    {
        $startTime = microtime(true);
        $imported = 0;
        $errors = [];
        $duplicates = 0;
        $batchId = 'bank_csv_' . uniqid();

        try {
            // Validate file
            $this->validateFile($file);

            $this->logger->info('Starting bank CSV import', [
                'batch_id' => $batchId,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
            ]);
            error_log("DEBUG: Starting bank CSV import: " . $file->getClientOriginalName());

            $this->logger->logFileImport(
                $file->getClientOriginalName(),
                $file->getSize(),
                'bank_csv',
                ['status' => 'started', 'batch_id' => $batchId]
            );

            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();

            // Header untuk format CSV Bank
            $headerRow = $worksheet->rangeToArray('A1:' . $highestColumn . '1', NULL, TRUE, FALSE)[0];
            $columnMapping = $this->mapBankColumns($headerRow);

            if (empty($columnMapping)) {
                throw new ImportException(
                    'Format CSV Bank tidak sesuai. Pastikan kolom: Instansi, No. Tagihan, Nama, Tagihan, Tanggal Transaksi, Status Bayar, Tahun, Bulan',
                    'Format file CSV Bank tidak valid. Pastikan file memiliki header yang sesuai.',
                    1,
                    null,
                    $file->getClientOriginalName()
                );
            }

            // Process data rows (start from row 2 to skip header)
            $batchContext = $this->logger->createBatchContext($batchId, 'bank_csv_import');
            $totalRows = $highestRow - 1;
            error_log("DEBUG: Processing $totalRows rows");

            for ($row = 2; $row <= $highestRow; $row++) {
                try {
                    error_log("DEBUG: Processing row $row");
                    $rowData = $worksheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, NULL, TRUE, FALSE)[0];
                    error_log("DEBUG: Row data: " . json_encode($rowData));
                    $data = $this->extractBankRowData($rowData, $columnMapping, $row);
                    error_log("DEBUG: Extracted data: " . ($data ? json_encode($data) : "NULL"));

                    if ($data) {
                        // Cek duplicate berdasarkan no_bukti
                        $existing = RekonData::where('no_bukti', $data['no_bukti'])->first();
                        if ($existing) {
                            $duplicates++;
                            error_log("DEBUG: Duplicate found for no_bukti: " . $data['no_bukti']);
                            continue;
                        }

                        // Hanya proses yang statusnya "Terbayar"
                        if (strtolower($data['status_bayar']) === 'terbayar') {
                            try {
                                RekonData::create($data);
                                $imported++;
                                error_log("DEBUG: Imported row $row successfully");
                            } catch (\Exception $dbError) {
                                error_log("DEBUG: Database error for row $row: " . $dbError->getMessage());
                                throw new DatabaseException(
                                    'Failed to insert RekonData record: ' . $dbError->getMessage(),
                                    "Gagal menyimpan data pada baris $row. Periksa format data.",
                                    'insert',
                                    'rekon_data',
                                    null,
                                    ['row_data' => $data]
                                );
                            }
                        } else {
                            error_log("DEBUG: Skipping row $row - status_bayar is not 'terbayar': " . $data['status_bayar']);
                        }
                    } else {
                        error_log("DEBUG: No data extracted for row $row");
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
                'duplicates' => $duplicates,
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
                'bank_csv',
                $result
            );

            $this->logger->logPerformance('bank_csv_import', [
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
                'Error importing Bank CSV: ' . $e->getMessage(),
                'Terjadi kesalahan saat memproses file CSV Bank. Pastikan file tidak rusak dan formatnya benar.',
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
     * Map kolom CSV Bank ke indeks
     */
    private function mapBankColumns($headerRow): array
    {
        $mapping = [];
        $expectedColumns = [
            'INSTANSI', 'NO._TAGIHAN', 'NAMA', 'TAGIHAN', 'BIAYA_ADM.', 'TAGIHAN_LAIN',
            'KET._TAGIHAN_LAIN', 'ALAMAT', 'KELAS', 'JURUSAN', 'TAHUN', 'BULAN',
            'DANA_MASYARAKAT', 'KETERANGAN', 'TANGGAL_TRANSAKSI', 'STATUS_BAYAR',
            'KODE_CABANG', 'USER', 'STATUS_REVERSAL', 'NO._BUKTI'
        ];

        $this->logger->debug('Starting bank column mapping', [
            'header_row' => $headerRow,
            'expected_columns' => $expectedColumns
        ]);
        error_log("DEBUG: Starting bank column mapping");
        error_log("DEBUG: Header row: " . json_encode($headerRow));

        foreach ($headerRow as $index => $columnName) {
            // Clean the column name properly
            $cleanName = strtoupper(trim($columnName));
            // Replace periods with spaces (to handle "No. Tagihan" -> "No Tagihan")
            $cleanName = str_replace('.', ' ', $cleanName);
            // Replace hyphens with spaces
            $cleanName = str_replace('-', ' ', $cleanName);
            // Replace multiple spaces with single space
            $cleanName = preg_replace('/\s+/', ' ', $cleanName);
            // Replace spaces with underscores
            $cleanName = str_replace(' ', '_', $cleanName);
            
            // Log the mapping for debugging
            error_log("DEBUG: Column $index: '$columnName' -> '$cleanName'");

            $this->logger->debug('Processing column', [
                'index' => $index,
                'original_name' => $columnName,
                'clean_name' => $cleanName
            ]);

            // Special handling for some column names
            if (strpos($cleanName, 'INSTANSI') !== false) {
                $mapping['INSTANSI'] = $index;
                $this->logger->debug("Mapped INSTANSI to index $index");
                error_log("DEBUG: Mapped INSTANSI to index $index");
            } elseif (strpos($cleanName, 'NO_TAGIHAN') !== false) {
                $mapping['NO._TAGIHAN'] = $index;
                $this->logger->debug("Mapped NO._TAGIHAN to index $index");
                error_log("DEBUG: Mapped NO._TAGIHAN to index $index");
            } elseif (strpos($cleanName, 'TANGGAL_TRANSAKSI') !== false) {
                $mapping['TANGGAL_TRANSAKSI'] = $index;
                $this->logger->debug("Mapped TANGGAL_TRANSAKSI to index $index");
                error_log("DEBUG: Mapped TANGGAL_TRANSAKSI to index $index");
            } elseif (strpos($cleanName, 'STATUS_BAYAR') !== false) {
                $mapping['STATUS_BAYAR'] = $index;
                $this->logger->debug("Mapped STATUS_BAYAR to index $index");
                error_log("DEBUG: Mapped STATUS_BAYAR to index $index");
            } elseif (in_array($cleanName, $expectedColumns)) {
                $mapping[$cleanName] = $index;
                $this->logger->debug("Mapped $cleanName to index $index");
                error_log("DEBUG: Mapped $cleanName to index $index");
            } else {
                $this->logger->debug("Column not mapped", [
                    'index' => $index,
                    'name' => $cleanName
                ]);
                error_log("DEBUG: Column not mapped: index=$index, name='$cleanName'");
            }
        }
        
        $this->logger->debug('Final column mapping', ['mapping' => $mapping]);
        error_log("DEBUG: Final column mapping: " . json_encode($mapping));

        return $mapping;
    }

    /**
     * Extract data dari baris CSV Bank with enhanced validation
     */
    private function extractBankRowData($rowData, $mapping, int $rowNumber): ?array
    {
        $requiredFields = ['sekolah', 'id_siswa', 'nama_siswa', 'tahun', 'bulan'];
        $data = [];

        try {
            // Map dan validasi field yang diperlukan
            $data = [
                'sekolah' => trim($rowData[$mapping['INSTANSI']] ?? ''),
                'id_siswa' => trim($rowData[$mapping['NO._TAGIHAN']] ?? ''),
                'nama_siswa' => trim($rowData[$mapping['NAMA']] ?? ''),
                'alamat' => trim($rowData[$mapping['ALAMAT']] ?? ''),
                'kelas' => trim($rowData[$mapping['KELAS']] ?? ''),
                'jurusan' => trim($rowData[$mapping['JURUSAN']] ?? ''),
                'jum_tagihan' => $this->parseNumber($rowData[$mapping['TAGIHAN']] ?? 0),
                'biaya_adm' => $this->parseNumber($rowData[$mapping['BIAYA_ADM.']] ?? 0),
                'tagihan_lain' => $this->parseNumber($rowData[$mapping['TAGIHAN_LAIN']] ?? 0),
                'ket_tagihan_lain' => trim($rowData[$mapping['KET._TAGIHAN_LAIN']] ?? ''),
                'keterangan' => trim($rowData[$mapping['KETERANGAN']] ?? ''),
                'tahun' => $this->parseYear($rowData[$mapping['TAHUN']] ?? date('Y'), $rowNumber),
                'bulan' => $this->parseMonth($rowData[$mapping['BULAN']] ?? date('n'), $rowNumber),
                'dana_masyarakat' => trim($rowData[$mapping['DANA_MASYARAKAT']] ?? ''),
                'tgl_tx' => $this->parseDate($rowData[$mapping['TANGGAL_TRANSAKSI']] ?? now(), $rowNumber),
                'tgl_tx_formatted' => trim($rowData[$mapping['TANGGAL_TRANSAKSI']] ?? ''),
                'status_bayar' => trim($rowData[$mapping['STATUS_BAYAR']] ?? ''),
                'kd_cab' => trim($rowData[$mapping['KODE_CABANG']] ?? ''),
                'kd_user' => trim($rowData[$mapping['USER']] ?? 'system'),
                'sts_reversal' => (strtolower(trim($rowData[$mapping['STATUS_REVERSAL']] ?? '')) === '-' ? 0 : 1),
                'no_bukti' => trim($rowData[$mapping['NO._BUKTI']] ?? ''),
                'sts_bayar' => (strtolower(trim($rowData[$mapping['STATUS_BAYAR']] ?? '')) === 'terbayar' ? 1 : 0)
            ];

            // Validasi field wajib
            $missingFields = [];
            foreach (['sekolah', 'id_siswa', 'nama_siswa'] as $field) {
                if (empty($data[$field])) {
                    $missingFields[] = $field;
                }
            }

            if (!empty($missingFields)) {
                throw new ImportException(
                    "Missing required fields: " . implode(', ', $missingFields),
                    "Data tidak lengkap pada baris ini. Pastikan kolom Sekolah, No. Tagihan, dan Nama terisi.",
                    $rowNumber,
                    implode(', ', $missingFields)
                );
            }

            // Validate no_bukti for uniqueness
            if (empty($data['no_bukti'])) {
                throw new ImportException(
                    "No. Bukti is empty",
                    "No. Bukti tidak boleh kosong. Ini diperlukan untuk mencegah duplikasi data.",
                    $rowNumber,
                    'no_bukti'
                );
            }

            // Validate year range
            $currentYear = (int) date('Y');
            if ($data['tahun'] < $currentYear - 5 || $data['tahun'] > $currentYear + 5) {
                throw new ImportException(
                    "Invalid year: {$data['tahun']}",
                    "Tahun tidak valid. Gunakan tahun antara " . ($currentYear - 5) . " dan " . ($currentYear + 5),
                    $rowNumber,
                    'tahun'
                );
            }

            // Validate month range
            if ($data['bulan'] < 1 || $data['bulan'] > 12) {
                throw new ImportException(
                    "Invalid month: {$data['bulan']}",
                    "Bulan tidak valid. Gunakan angka 1-12.",
                    $rowNumber,
                    'bulan'
                );
            }

            return $data;

        } catch (ImportException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Error extracting row data', [
                'row_number' => $rowNumber,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'raw_row_data' => $rowData,
                'column_mapping' => $mapping
            ]);

            throw new ImportException(
                "Error extracting row data: " . $e->getMessage(),
                "Format data pada baris ini tidak valid. Periksa kembali format setiap kolom.",
                $rowNumber,
                null,
                null,
                ['original_error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
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