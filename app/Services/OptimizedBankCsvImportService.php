<?php

namespace App\Services;

use App\Models\RekonData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OptimizedBankCsvImportService
{
    /**
     * Chunk size for processing large files
     */
    private const CHUNK_SIZE = 1000;

    /**
     * Maximum file size (50MB)
     */
    private const MAX_FILE_SIZE = 50 * 1024 * 1024;

    /**
     * Import CSV from Bank with chunking and memory optimization
     */
    public function importFromBankCsv(UploadedFile $file): array
    {
        // Validate file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \Exception('File terlalu besar. Maksimal 50MB.');
        }

        $imported = 0;
        $errors = [];
        $duplicates = 0;
        $chunksProcessed = 0;

        try {
            $filePath = $file->getRealPath();

            // Read file in chunks to manage memory
            $this->processFileInChunks($filePath, function ($chunkData, $headerRow) use (&$imported, &$errors, &$duplicates, &$chunksProcessed) {
                $columnMapping = $this->mapBankColumns($headerRow);

                if (empty($columnMapping)) {
                    throw new \Exception('Format CSV Bank tidak sesuai. Pastikan kolom: Instansi, No. Tagihan, Nama, Tagihan, Tanggal Transaksi, Status Bayar, Tahun, Bulan');
                }

                // Process chunk data with batch insert
                $batchData = [];
                $batchNoBukti = []; // Track no_bukti for duplicate checking in this batch

                foreach ($chunkData as $rowIndex => $rowData) {
                    try {
                        $data = $this->extractBankRowData($rowData, $columnMapping);

                        if ($data) {
                            // Check for duplicates in batch first
                            if (in_array($data['no_bukti'], $batchNoBukti)) {
                                $duplicates++;
                                continue;
                            }

                            // Check database for existing record (more efficient with index)
                            $existing = RekonData::where('no_bukti', $data['no_bukti'])->first();
                            if ($existing) {
                                $duplicates++;
                                continue;
                            }

                            // Only process paid status
                            if (strtolower($data['status_bayar']) === 'terbayar') {
                                $batchData[] = $data;
                                $batchNoBukti[] = $data['no_bukti'];
                            }
                        }
                    } catch (\Exception $e) {
                        $actualRow = $rowIndex + 2; // Account for header and 0-index
                        $errors[] = "Baris $actualRow: " . $e->getMessage();
                    }
                }

                // Batch insert for performance
                if (!empty($batchData)) {
                    $this->batchInsert($batchData);
                    $imported += count($batchData);
                }

                $chunksProcessed++;
            });

            // Clear any remaining data
            gc_collect_cycles();

            return [
                'success' => true,
                'imported' => $imported,
                'duplicates' => $duplicates,
                'chunks_processed' => $chunksProcessed,
                'errors' => $errors,
                'error_count' => count($errors),
                'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB'
            ];

        } catch (\Exception $e) {
            throw new \Exception('Error importing Bank CSV: ' . $e->getMessage());
        }
    }

    /**
     * Process file in chunks to manage memory
     */
    private function processFileInChunks(string $filePath, callable $processor): void
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception('Cannot open file for reading');
        }

        try {
            $headerRow = fgetcsv($handle, 0, ',');
            if ($headerRow === false) {
                throw new \Exception('Cannot read header row');
            }

            $chunk = [];
            $rowIndex = 0;

            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                $chunk[] = $row;
                $rowIndex++;

                // Process chunk when it reaches the defined size
                if (count($chunk) >= self::CHUNK_SIZE) {
                    $processor($chunk, $headerRow);
                    $chunk = []; // Clear chunk to free memory
                }
            }

            // Process remaining rows
            if (!empty($chunk)) {
                $processor($chunk, $headerRow);
            }

        } finally {
            fclose($handle);
        }
    }

    /**
     * Batch insert data for better performance
     */
    private function batchInsert(array $data): void
    {
        // Use chunks within batch to avoid single massive insert
        $batchChunks = array_chunk($data, 500);

        foreach ($batchChunks as $batchChunk) {
            DB::table('rekon_data')->insert($batchChunk);
        }
    }

    /**
     * Map kolom CSV Bank ke indeks
     */
    private function mapBankColumns($headerRow): array
    {
        $mapping = [];
        $expectedColumns = [
            'INSTANSI', 'NO. TAGIHAN', 'NAMA', 'TAGIHAN', 'BIAYA ADM.', 'TAGIHAN LAIN',
            'KET. TAGIHAN LAIN', 'ALAMAT', 'KELAS', 'JURUSAN', 'TAHUN', 'BULAN',
            'DANA MASYARAKAT', 'KETERANGAN', 'TANGGAL TRANSAKSI', 'STATUS BAYAR',
            'KODE CABANG', 'USER', 'STATUS REVERSAL', 'NO. BUKTI'
        ];

        foreach ($headerRow as $index => $columnName) {
            $cleanName = strtoupper(trim(str_replace([' ', '.', '-'], '_', $columnName)));

            // Special handling for some column names
            if (strpos($cleanName, 'INSTANSI') !== false) {
                $mapping['INSTANSI'] = $index;
            } elseif (strpos($cleanName, 'NO_TAGIHAN') !== false || strpos($cleanName, 'NO._TAGIHAN') !== false) {
                $mapping['NO._TAGIHAN'] = $index;
            } elseif (strpos($cleanName, 'TANGGAL_TRANSAKSI') !== false) {
                $mapping['TANGGAL_TRANSAKSI'] = $index;
            } elseif (strpos($cleanName, 'STATUS_BAYAR') !== false || strpos($cleanName, 'STATUS_BAYAR') !== false) {
                $mapping['STATUS_BAYAR'] = $index;
            } elseif (in_array($cleanName, $expectedColumns)) {
                $mapping[$cleanName] = $index;
            }
        }

        return $mapping;
    }

    /**
     * Extract data dari baris CSV Bank
     */
    private function extractBankRowData($rowData, $mapping): ?array
    {
        $requiredFields = ['sekolah', 'id_siswa', 'nama_siswa', 'tahun', 'bulan'];
        $data = [];

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
            'tahun' => (int) ($rowData[$mapping['TAHUN']] ?? date('Y')),
            'bulan' => (int) ($rowData[$mapping['BULAN']] ?? date('n')),
            'dana_masyarakat' => trim($rowData[$mapping['DANA_MASYARAKAT']] ?? ''),
            'tgl_tx' => $this->parseDate($rowData[$mapping['TANGGAL_TRANSAKSI']] ?? now()),
            'tgl_tx_formatted' => trim($rowData[$mapping['TANGGAL_TRANSAKSI']] ?? ''),
            'status_bayar' => trim($rowData[$mapping['STATUS_BAYAR']] ?? ''),
            'kd_cab' => trim($rowData[$mapping['KODE_CABANG']] ?? ''),
            'kd_user' => trim($rowData[$mapping['USER']] ?? 'system'),
            'sts_reversal' => (strtolower(trim($rowData[$mapping['STATUS_REVERSAL']] ?? '')) === '-' ? 0 : 1),
            'no_bukti' => trim($rowData[$mapping['NO._BUKTI']] ?? ''),
            'sts_bayar' => (strtolower(trim($rowData[$mapping['STATUS_BAYAR']] ?? '')) === 'terbayar' ? 1 : 0),
            'created_at' => now(),
            'updated_at' => now()
        ];

        // Validasi field wajib
        foreach (['sekolah', 'id_siswa', 'nama_siswa'] as $field) {
            if (empty($data[$field])) {
                return null;
            }
        }

        return $data;
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
     * Parse date from string
     */
    private function parseDate($value)
    {
        if (empty($value)) {
            return now();
        }

        try {
            $formats = ['d/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d'];

            foreach ($formats as $format) {
                $date = \DateTime::createFromFormat($format, $value);
                if ($date) {
                    return $date;
                }
            }

            return now();
        } catch (\Exception $e) {
            return now();
        }
    }
}