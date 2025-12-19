<?php

namespace App\Services;

use App\Models\RekonData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class OptimizedRekonImportService
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
     * Import data dari Excel/CSV file with chunking and memory optimization
     */
    public function importFromFile(UploadedFile $file): array
    {
        // Validate file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \Exception('File terlalu besar. Maksimal 50MB.');
        }

        $imported = 0;
        $errors = [];
        $chunksProcessed = 0;

        try {
            // Use PhpSpreadsheet with memory optimization
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file);
            $reader->setReadDataOnly(true); // Skip formatting for performance

            // Configure chunking for large files
            $spreadsheet = $reader->load($file);
            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();

            // Read header row
            $headerRow = $worksheet->rangeToArray('A1:' . $highestColumn . '1', NULL, TRUE, FALSE)[0];
            $columnMapping = $this->mapColumns($headerRow);

            if (empty($columnMapping)) {
                throw new \Exception('Format file tidak sesuai. Pastikan kolom: SEKOLAH, ID_SISWA, NAMA_SISWA, KELAS, JURUSAN, TAHUN, BULAN, DANA_MASYARAKAT');
            }

            // Process data in chunks to manage memory
            for ($startRow = 2; $startRow <= $highestRow; $startRow += self::CHUNK_SIZE) {
                $endRow = min($startRow + self::CHUNK_SIZE - 1, $highestRow);
                $chunkData = $this->processChunk($worksheet, $startRow, $endRow, $highestColumn, $columnMapping);

                // Batch insert chunk data
                if (!empty($chunkData['data'])) {
                    $this->batchInsert($chunkData['data']);
                    $imported += count($chunkData['data']);
                }

                // Merge errors
                $errors = array_merge($errors, $chunkData['errors']);
                $chunksProcessed++;

                // Clear memory periodically
                if ($chunksProcessed % 10 === 0) {
                    gc_collect_cycles();
                }
            }

            // Clean up spreadsheet object
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return [
                'success' => true,
                'imported' => $imported,
                'total_rows' => $highestRow - 1,
                'chunks_processed' => $chunksProcessed,
                'errors' => $errors,
                'error_count' => count($errors),
                'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB'
            ];

        } catch (\Exception $e) {
            throw new \Exception('Error importing data: ' . $e->getMessage());
        }
    }

    /**
     * Process a chunk of rows for memory efficiency
     */
    private function processChunk($worksheet, int $startRow, int $endRow, string $highestColumn, array $columnMapping): array
    {
        $batchData = [];
        $errors = [];

        // Read chunk data
        $chunkRows = $worksheet->rangeToArray("A{$startRow}:{$highestColumn}{$endRow}", NULL, TRUE, FALSE);

        foreach ($chunkRows as $index => $rowData) {
            try {
                $actualRow = $startRow + $index;
                $data = $this->extractRowData($rowData, $columnMapping);

                if ($data) {
                    $batchData[] = $data;
                }
            } catch (\Exception $e) {
                $actualRow = $startRow + $index;
                $errors[] = "Baris $actualRow: " . $e->getMessage();
            }
        }

        return [
            'data' => $batchData,
            'errors' => $errors
        ];
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
     * Extract data from row using column mapping
     */
    private function extractRowData($rowData, $mapping): ?array
    {
        $requiredFields = ['sekolah', 'id_siswa', 'nama_siswa', 'tahun', 'bulan'];

        // Check required fields
        foreach ($requiredFields as $field) {
            $columnIndex = $mapping[strtoupper($field)] ?? null;
            if ($columnIndex === null || empty($rowData[$columnIndex])) {
                return null; // Skip row if required field is missing
            }
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
            'tahun' => (int) $rowData[$mapping['TAHUN']],
            'bulan' => (int) $rowData[$mapping['BULAN']],
            'dana_masyarakat' => trim($rowData[$mapping['DANA_MASYARAKAT']] ?? ''),
            'tgl_tx' => $this->parseDate($rowData[$mapping['TGL_TX']] ?? now()),
            'tgl_tx_formatted' => trim($rowData[$mapping['TGL_TX']] ?? ''),
            'sts_bayar' => (int) ($rowData[$mapping['STS_BAYAR']] ?? 1),
            'kd_cab' => trim($rowData[$mapping['KD_CAB']] ?? ''),
            'kd_user' => trim($rowData[$mapping['KD_USER']] ?? 'system'),
            'sts_reversal' => (int) ($rowData[$mapping['STS_REVERSAL']] ?? 0),
            'no_bukti' => trim($rowData[$mapping['NO_BUKTI']] ?? ''),
            'created_at' => now(),
            'updated_at' => now()
        ];
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
            $formats = ['d/m/Y H:i', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d'];

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