<?php

namespace App\Http\Controllers;

use App\Services\RekonExportService;
use App\Models\RekonData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RekonReportController extends Controller
{
    private $exportService;

    public function __construct(RekonExportService $exportService)
    {
        $this->exportService = $exportService;
    }

    /**
     * Get laporan per kelas dengan tanggal pembayaran
     */
    public function getLaporanKelas(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'sekolah' => 'required|string',
                'kelas' => 'required|string',
                'angkatan' => 'required|integer|min:2000|max:2100'
            ]);

            $sekolah = $request->get('sekolah');
            $kelas = $request->get('kelas');
            $angkatan = $request->get('angkatan');

            // Ambil semua siswa di kelas tersebut
            $siswaList = RekonData::where('sekolah', $sekolah)
                                 ->where('kelas', $kelas)
                                 ->where('tahun', '>=', $angkatan)
                                 ->select('id_siswa', 'nama_siswa', 'kelas')
                                 ->distinct()
                                 ->orderBy('id_siswa')
                                 ->get();

            if ($siswaList->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data siswa untuk kelas tersebut tidak ditemukan'
                ], 404);
            }

            // Generate range tahun-bulan
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

            // Build data laporan
            $laporanData = [];
            foreach ($siswaList as $index => $siswa) {
                $row = [
                    'no' => $index + 1,
                    'nis' => $siswa->id_siswa,
                    'nama' => $siswa->nama_siswa,
                    'pembayaran' => []
                ];

                // Cek pembayaran untuk setiap bulan
                foreach ($headers as $header) {
                    $tanggalBayar = $this->getTanggalPembayaran(
                        $sekolah,
                        $siswa->id_siswa,
                        $header['year'],
                        $header['month']
                    );
                    $row['pembayaran'][] = $tanggalBayar;
                }

                $laporanData[] = $row;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'kelas' => $kelas,
                    'angkatan' => $angkatan,
                    'sekolah' => $sekolah,
                    'headers' => $headers,
                    'siswa' => $laporanData,
                    'total_siswa' => count($laporanData)
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export laporan kelas ke Excel
     */
    public function exportLaporanKelas(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'sekolah' => 'required|string',
                'kelas' => 'required|string',
                'angkatan' => 'required|integer|min:2000|max:2100'
            ]);

            $sekolah = $request->get('sekolah');
            $kelas = $request->get('kelas');
            $angkatan = $request->get('angkatan');

            $result = $this->exportService->exportLaporanKelas($sekolah, $kelas, $angkatan);

            return response()->json([
                'success' => true,
                'message' => 'File Excel laporan kelas berhasil dibuat',
                'download_url' => $result['download_url'],
                'filename' => $result['filename'],
                'total_records' => $result['total_records']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating Excel file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export data to Excel
     */
    public function exportExcel(Request $request): JsonResponse
    {
        try {
            $filters = [
                'sekolah' => $request->get('sekolah'),
                'tahun' => $request->get('tahun'),
                'bulan' => $request->get('bulan')
            ];

            $result = $this->exportService->exportToExcel($filters);

            return response()->json([
                'success' => true,
                'message' => 'File Excel berhasil dibuat',
                'download_url' => $result['download_url'],
                'filename' => $result['filename'],
                'total_records' => $result['total_records']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating Excel file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export data to CSV
     */
    public function exportCSV(Request $request): JsonResponse
    {
        try {
            $filters = [
                'sekolah' => $request->get('sekolah'),
                'tahun' => $request->get('tahun'),
                'bulan' => $request->get('bulan')
            ];

            $result = $this->exportService->exportToCSV($filters);

            return response()->json([
                'success' => true,
                'message' => 'File CSV berhasil dibuat',
                'download_url' => $result['download_url'],
                'filename' => $result['filename'],
                'total_records' => $result['total_records']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating CSV file: ' . $e->getMessage()
            ], 500);
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