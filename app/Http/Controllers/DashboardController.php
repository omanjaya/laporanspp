<?php

namespace App\Http\Controllers;

use App\Models\RekonData;
use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Cache TTL in seconds (15 minutes)
     */
    private const CACHE_TTL = 900;

    /**
     * Get dashboard analytics data with caching and optimized queries
     */
    public function getAnalytics(): JsonResponse
    {
        try {
            $cacheKey = 'dashboard_analytics_' . date('Y-m-d-H'); // Cache per hour

            $analyticsData = Cache::remember($cacheKey, self::CACHE_TTL, function () {
                // Optimized summary data using database aggregates
                $summary = $this->getOptimizedSummary();

                // Optimized monthly data using database aggregation
                $monthlyData = $this->getOptimizedMonthlyData();

                // Optimized school data using database aggregation
                $schoolData = $this->getOptimizedSchoolData();

                return [
                    'summary' => $summary,
                    'monthly_data' => $monthlyData,
                    'school_data' => $schoolData,
                    'cached_at' => now()->toISOString()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $analyticsData
            ]);

        } catch (\Exception $e) {
            // Log error for monitoring
            \Log::error('Dashboard analytics error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get optimized summary data using database aggregates
     */
    private function getOptimizedSummary(): array
    {
        // Single query for all summary statistics
        $summary = DB::table('rekon_data')
            ->selectRaw('
                COUNT(*) as total_transactions,
                COUNT(DISTINCT id_siswa) as total_siswa,
                SUM(CASE WHEN dana_masyarakat GLOB "[0-9]*" AND dana_masyarakat NOT GLOB "*[^0-9]*" THEN CAST(dana_masyarakat AS INTEGER) ELSE 0 END) as total_dana
            ')
            ->first();

        $totalSchools = Cache::remember('active_schools_count', self::CACHE_TTL, function () {
            return School::where('is_active', true)->count();
        });

        return [
            'total_transactions' => (int) $summary->total_transactions,
            'total_dana' => (int) $summary->total_dana,
            'total_siswa' => (int) $summary->total_siswa,
            'total_schools' => $totalSchools
        ];
    }

    /**
     * Get optimized monthly data using database aggregation
     */
    private function getOptimizedMonthlyData(): array
    {
        return DB::table('rekon_data')
            ->selectRaw('
                tahun,
                bulan,
                COUNT(*) as total,
                SUM(CASE WHEN dana_masyarakat GLOB "[0-9]*" AND dana_masyarakat NOT GLOB "*[^0-9]*" THEN CAST(dana_masyarakat AS INTEGER) ELSE 0 END) as dana
            ')
            ->where('tahun', '>=', date('Y') - 2)
            ->groupBy('tahun', 'bulan')
            ->orderBy('tahun')
            ->orderBy('bulan')
            ->get()
            ->map(function ($item) {
                return [
                    'tahun' => (int) $item->tahun,
                    'bulan' => (int) $item->bulan,
                    'total' => (int) $item->total,
                    'dana' => (int) $item->dana
                ];
            })
            ->toArray();
    }

    /**
     * Get optimized school data using database aggregation
     */
    private function getOptimizedSchoolData(): array
    {
        return DB::table('rekon_data')
            ->selectRaw('
                sekolah,
                COUNT(*) as total,
                COUNT(DISTINCT id_siswa) as siswa,
                SUM(CASE WHEN dana_masyarakat GLOB "[0-9]*" AND dana_masyarakat NOT GLOB "*[^0-9]*" AND dana_masyarakat != "" THEN CAST(dana_masyarakat AS INTEGER) ELSE 0 END) as dana
            ')
            ->groupBy('sekolah')
            ->orderByDesc('total')
            ->get()
            ->map(function ($item) {
                return [
                    'sekolah' => e($item->sekolah),
                    'total' => (int) $item->total,
                    'dana' => (int) $item->dana,
                    'siswa' => (int) $item->siswa
                ];
            })
            ->toArray();
    }

    /**
     * Get list of active schools
     */
    public function getSchools(): JsonResponse
    {
        try {
            $schools = School::getActive();

            return response()->json([
                'success' => true,
                'data' => $schools
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
}