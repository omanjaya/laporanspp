<?php

namespace App\Services;

use App\Models\RekonData;
use App\Exceptions\FileProcessingException;
use App\Exceptions\DatabaseException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;

class RekonExportService
{
    private LoggingService $logger;

    public function __construct(LoggingService $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Export data ke Excel with enhanced error handling
     */
    public function exportToExcel(array $filters = []): array
    {
        $startTime = microtime(true);
        $exportId = 'excel_export_' . uniqid();

        try {
            $this->logger->info('Starting Excel export', [
                'export_id' => $exportId,
                'filters' => $this->sanitizeFilters($filters)
            ]);

            // Validate filters
            $this->validateFilters($filters);

            // Query data with error handling
            try {
                $query = RekonData::orderBy('created_at', 'desc');

                // Apply filters
                if (!empty($filters['sekolah'])) {
                    $query->where('sekolah', $filters['sekolah']);
                }
                if (!empty($filters['tahun'])) {
                    $query->where('tahun', $filters['tahun']);
                }
                if (!empty($filters['bulan'])) {
                    $query->where('bulan', $filters['bulan']);
                }

                $data = $query->get();

                if ($data->isEmpty()) {
                    throw new FileProcessingException(
                        'No data found for the specified filters',
                        'Tidak ada data yang ditemukan untuk filter yang dipilih.',
                        null,
                        null,
                        null,
                        null,
                        ['filters' => $filters]
                    );
                }

                $this->logger->info('Data retrieved for export', [
                    'export_id' => $exportId,
                    'record_count' => $data->count(),
                    'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
                ]);

            } catch (\Illuminate\Database\QueryException $e) {
                throw new DatabaseException(
                    'Database query failed: ' . $e->getMessage(),
                    'Gagal mengambil data dari database. Silakan coba lagi atau hubungi administrator.',
                    'select',
                    'rekon_data',
                    $e->getSql() ?? null,
                    ['filters' => $filters, 'original_error' => $e->getMessage()]
                );
            }

            // Create new Spreadsheet object with error handling
            try {
                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Laporan Rekon SPP');

                // Set headers
                $headers = [
                    'No', 'Sekolah', 'ID Siswa', 'Nama Siswa', 'Alamat', 'Kelas', 'Jurusan',
                    'Jumlah Tagihan', 'Biaya Admin', 'Tagihan Lain', 'Ket. Tagihan Lain', 'Keterangan',
                    'Tahun', 'Bulan', 'Dana Masyarakat', 'Tanggal Transaksi', 'Status Bayar', 'Kode Cabang',
                    'Kode User', 'Status Reversal', 'No. Bukti'
                ];

                $sheet->fromArray([$headers]);

                // Add data with progress logging
                $row = 2;
                $processed = 0;
                $total = $data->count();

                foreach ($data as $index => $item) {
                    try {
                        $sheet->fromArray([
                            $index + 1,
                            $item->sekolah,
                            $item->id_siswa,
                            $item->nama_siswa,
                            $item->alamat,
                            $item->kelas,
                            $item->jurusan,
                            $item->jum_tagihan,
                            $item->biaya_adm,
                            $item->tagihan_lain,
                            $item->ket_tagihan_lain,
                            $item->keterangan,
                            $item->tahun,
                            $item->bulan,
                            $item->dana_masyarakat,
                            $item->tgl_tx_formatted,
                            $item->sts_bayar,
                            $item->kd_cab,
                            $item->kd_user,
                            $item->sts_reversal,
                            $item->no_bukti
                        ], null, 'A' . $row++);

                        $processed++;

                        // Log progress every 1000 rows
                        if ($processed % 1000 === 0) {
                            $this->logger->info('Export progress', [
                                'export_id' => $exportId,
                                'processed' => $processed,
                                'total' => $total,
                                'progress_percent' => round(($processed / $total) * 100, 2)
                            ]);
                        }

                    } catch (SpreadsheetException $e) {
                        throw new FileProcessingException(
                            'Spreadsheet error at row ' . $row . ': ' . $e->getMessage(),
                            "Terjadi kesalahan saat menulis data ke file Excel pada baris {$row}.",
                            null,
                            null,
                            null,
                            null,
                            ['row' => $row, 'original_error' => $e->getMessage()]
                        );
                    }
                }

                // Auto-size columns
                foreach (range('A', 'T') as $columnID) {
                    $sheet->getColumnDimension($columnID)->setAutoSize(true);
                }

                $this->logger->info('Spreadsheet created successfully', [
                    'export_id' => $exportId,
                    'total_rows' => $total,
                    'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
                ]);

            } catch (SpreadsheetException $e) {
                throw new FileProcessingException(
                    'Spreadsheet creation failed: ' . $e->getMessage(),
                    'Gagal membuat file Excel. Silakan coba lagi dengan data yang lebih sedikit.',
                    null,
                    null,
                    null,
                    null,
                    ['original_error' => $e->getMessage()]
                );
            }

            // Create file with directory and permission handling
            $filename = 'laporan_rekon_spp_' . date('Y-m-d_H-i-s') . '.xlsx';
            $tempPath = storage_path('app/public/exports/' . $filename);

            try {
                $directory = dirname($tempPath);
                if (!is_dir($directory)) {
                    if (!mkdir($directory, 0755, true)) {
                        throw new FileProcessingException(
                            "Failed to create directory: {$directory}",
                            'Gagal membuat folder untuk menyimpan file. Periksa izin akses folder.',
                            null,
                            null,
                            null,
                            $directory
                        );
                    }
                }

                if (!is_writable($directory)) {
                    throw new FileProcessingException(
                        "Directory not writable: {$directory}",
                        'Folder tidak dapat ditulis. Periksa izin akses folder.',
                        null,
                        null,
                        null,
                        $directory
                    );
                }

                $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                $writer->save($tempPath);

                if (!file_exists($tempPath)) {
                    throw new FileProcessingException(
                        "File was not created: {$tempPath}",
                        'Gagal menyimpan file Excel. Periksa ruang penyimpanan yang tersedia.',
                        null,
                        null,
                        null,
                        $tempPath
                    );
                }

                $duration = (microtime(true) - $startTime) * 1000;
                $result = [
                    'success' => true,
                    'filename' => $filename,
                    'download_url' => '/exports/' . $filename,
                    'total_records' => $data->count(),
                    'file_size_mb' => round(filesize($tempPath) / 1024 / 1024, 2),
                    'duration_ms' => round($duration, 2),
                    'export_id' => $exportId
                ];

                $this->logger->logPerformance('excel_export', [
                    'duration_ms' => $duration,
                    'records_processed' => $total,
                    'records_per_second' => round($total / ($duration / 1000), 2),
                    'file_size_mb' => $result['file_size_mb'],
                    'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
                ]);

                $this->logger->info('Excel export completed successfully', [
                    'export_id' => $exportId,
                    'result' => $result
                ]);

                return $result;

            } catch (\Exception $e) {
                throw new FileProcessingException(
                    'File creation failed: ' . $e->getMessage(),
                    'Gagal menyimpan file Excel. Periksa ruang penyimpanan dan izin akses folder.',
                    $filename,
                    null,
                    null,
                    $tempPath,
                    ['original_error' => $e->getMessage()]
                );
            }

        } catch (FileProcessingException | DatabaseException $e) {
            $this->logger->error('Excel export failed', [
                'export_id' => $exportId,
                'error' => $e->getMessage(),
                'error_code' => $e->getErrorCode() ?? null,
                'context' => $e->getContext() ?? []
            ]);

            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in Excel export', [
                'export_id' => $exportId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new FileProcessingException(
                'Error creating Excel file: ' . $e->getMessage(),
                'Terjadi kesalahan tidak terduga saat membuat file Excel. Silakan coba lagi.',
                null,
                null,
                null,
                null,
                ['original_error' => $e->getMessage()]
            );
        }
    }

    /**
     * Validate export filters
     */
    private function validateFilters(array $filters): void
    {
        if (isset($filters['tahun'])) {
            $year = (int) $filters['tahun'];
            $currentYear = (int) date('Y');
            if ($year < 2000 || $year > $currentYear + 5) {
                throw new FileProcessingException(
                    "Invalid year filter: {$year}",
                    "Filter tahun tidak valid. Gunakan tahun antara 2000 dan " . ($currentYear + 5),
                    null,
                    null,
                    null,
                    null,
                    ['filter_type' => 'tahun', 'value' => $filters['tahun']]
                );
            }
        }

        if (isset($filters['bulan'])) {
            $month = (int) $filters['bulan'];
            if ($month < 1 || $month > 12) {
                throw new FileProcessingException(
                    "Invalid month filter: {$month}",
                    "Filter bulan tidak valid. Gunakan angka 1-12.",
                    null,
                    null,
                    null,
                    null,
                    ['filter_type' => 'bulan', 'value' => $filters['bulan']]
                );
            }
        }

        if (isset($filters['sekolah']) && !is_string($filters['sekolah'])) {
            throw new FileProcessingException(
                "Invalid sekolah filter type",
                "Filter sekolah harus berupa teks.",
                null,
                null,
                null,
                null,
                ['filter_type' => 'sekolah', 'value' => $filters['sekolah']]
            );
        }
    }

    /**
     * Sanitize filters for logging
     */
    private function sanitizeFilters(array $filters): array
    {
        return collect($filters)->mapWithKeys(function ($value, $key) {
            // Truncate long values for logging
            if (is_string($value) && strlen($value) > 100) {
                return [$key => substr($value, 0, 100) . '...'];
            }
            return [$key => $value];
        })->toArray();
    }

    /**
     * Export data ke CSV
     */
    public function exportToCSV(array $filters = []): array
    {
        try {
            $query = RekonData::orderBy('created_at', 'desc');

            // Apply filters
            if (!empty($filters['sekolah'])) {
                $query->where('sekolah', $filters['sekolah']);
            }
            if (!empty($filters['tahun'])) {
                $query->where('tahun', $filters['tahun']);
            }
            if (!empty($filters['bulan'])) {
                $query->where('bulan', $filters['bulan']);
            }

            $data = $query->get();

            // Create CSV content
            $filename = 'laporan_rekon_spp_' . date('Y-m-d_H-i-s') . '.csv';
            $tempPath = storage_path('app/public/exports/' . $filename);

            if (!is_dir(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }

            $file = fopen($tempPath, 'w');

            // Add headers
            fputcsv($file, [
                'No', 'Sekolah', 'ID Siswa', 'Nama Siswa', 'Alamat', 'Kelas', 'Jurusan',
                'Jumlah Tagihan', 'Biaya Admin', 'Tagihan Lain', 'Ket. Tagihan Lain', 'Keterangan',
                'Tahun', 'Bulan', 'Dana Masyarakat', 'Tanggal Transaksi', 'Status Bayar', 'Kode Cabang',
                'Kode User', 'Status Reversal', 'No. Bukti'
            ]);

            // Add data
            foreach ($data as $index => $item) {
                fputcsv($file, [
                    $index + 1,
                    $item->sekolah,
                    $item->id_siswa,
                    $item->nama_siswa,
                    $item->alamat,
                    $item->kelas,
                    $item->jurusan,
                    $item->jum_tagihan,
                    $item->biaya_adm,
                    $item->tagihan_lain,
                    $item->ket_tagihan_lain,
                    $item->keterangan,
                    $item->tahun,
                    $item->bulan,
                    $item->dana_masyarakat,
                    $item->tgl_tx_formatted,
                    $item->sts_bayar,
                    $item->kd_cab,
                    $item->kd_user,
                    $item->sts_reversal,
                    $item->no_bukti
                ]);
            }

            fclose($file);

            return [
                'success' => true,
                'filename' => $filename,
                'download_url' => '/exports/' . $filename,
                'total_records' => $data->count()
            ];

        } catch (\Exception $e) {
            throw new \Exception('Error creating CSV file: ' . $e->getMessage());
        }
    }

    /**
     * Export laporan kelas ke Excel
     */
    public function exportLaporanKelas(string $sekolah, string $kelas, int $angkatan): array
    {
        try {
            // Get laporan data
            $siswaList = RekonData::where('sekolah', $sekolah)
                                 ->where('kelas', $kelas)
                                 ->where('tahun', '>=', $angkatan)
                                 ->select('id_siswa', 'nama_siswa', 'kelas')
                                 ->distinct()
                                 ->orderBy('id_siswa')
                                 ->get();

            if ($siswaList->isEmpty()) {
                throw new \Exception('Data siswa untuk kelas tersebut tidak ditemukan');
            }

            // Generate headers
            $currentYear = date('Y');
            $startYear = $angkatan;
            $endYear = $currentYear + 1;
            $months = ['7', '8', '9', '10', '11', '12', '1', '2', '3', '4', '5', '6'];
            $headers = [];

            foreach ($months as $month) {
                for ($year = $startYear; $year <= $endYear; $year++) {
                    $headers[] = [
                        'year' => $year,
                        'month' => $month,
                        'label' => $this->getMonthName($month)
                    ];
                }
            }

            // Create spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Laporan Kelas ' . $kelas);

            // Set header info
            $sheet->setCellValue('A1', 'Kelas : ' . $kelas);
            $sheet->setCellValue('A2', 'Angkatan : ' . $angkatan);

            // Build headers
            $headerRow = ['No', 'NIS', 'Nama'];
            foreach ($headers as $header) {
                $headerRow[] = $header['label'] . ' ' . $header['year'];
            }

            // Place headers starting from row 4
            $sheet->fromArray([$headerRow], null, 'A4');

            // Add data
            $row = 5;
            foreach ($siswaList as $index => $siswa) {
                $rowData = [
                    $index + 1,
                    $siswa->id_siswa,
                    $siswa->nama_siswa
                ];

                // Add payment data for each month
                foreach ($headers as $header) {
                    $tanggalBayar = $this->getTanggalPembayaran($sekolah, $siswa->id_siswa, $header['year'], $header['month']);
                    $rowData[] = $tanggalBayar;
                }

                $sheet->fromArray([$rowData], null, 'A' . $row++);
            }

            // Auto-size columns
            foreach (range('A', 'Z') as $columnID) {
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }

            // Styling
            $sheet->getStyle('A4:' . $sheet->getHighestColumn() . '4')->getFont()->setBold(true);
            $sheet->getStyle('A1:A2')->getFont()->setBold(true);

            // Create file
            $filename = 'Laporan_Kelas_' . str_replace('.', '_', $kelas) . '_Angkatan_' . $angkatan . '_' . date('Y-m-d_H-i-s') . '.xlsx';
            $tempPath = storage_path('app/public/exports/' . $filename);

            if (!is_dir(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($tempPath);

            return [
                'success' => true,
                'filename' => $filename,
                'download_url' => '/exports/' . $filename,
                'total_records' => $siswaList->count()
            ];

        } catch (\Exception $e) {
            throw new \Exception('Error creating Excel file: ' . $e->getMessage());
        }
    }

    /**
     * Get tanggal pembayaran untuk NIS, bulan, dan tahun tertentu
     */
    private function getTanggalPembayaran($sekolah, $nis, $tahun, $bulan): string
    {
        $data = RekonData::where('sekolah', $sekolah)
                        ->where('id_siswa', $nis)
                        ->where('tahun', $tahun)
                        ->where('bulan', $bulan)
                        ->where('sts_bayar', 1)
                        ->first();

        if ($data && $data->tgl_tx) {
            return $data->tgl_tx->format('d/m/Y');
        }

        return '-';
    }

    /**
     * Get nama bulan dalam Bahasa Indonesia
     */
    private function getMonthName($month): string
    {
        $months = [
            '1' => 'Januari', '2' => 'Februari', '3' => 'Maret',
            '4' => 'April', '5' => 'Mei', '6' => 'Juni',
            '7' => 'Juli', '8' => 'Agustus', '9' => 'September',
            '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
        ];

        return $months[$month] ?? $month;
    }
}