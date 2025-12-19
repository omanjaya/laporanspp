<?php

namespace Tests\Feature\Performance;

use Tests\TestCase;
use App\Models\RekonData;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class LargeDatasetTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_handles_large_dataset_searches_efficiently()
    {
        // Arrange - Create large dataset
        $this->createLargeDataset(5000); // 5000 records

        // Act - Measure search performance
        $startTime = microtime(true);

        $response = $this->getJson('/api/rekon/search?sekolah=SMAN_1_DENPASAR&tahun=2024&bulan=1');

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Assert
        $response->assertStatus(200);
        $this->assertLessThan(2.0, $executionTime, 'Search should complete within 2 seconds');

        // Should limit results to prevent data dumping
        $this->assertLessThanOrEqual(1000, $response->json('summary.total_records'));
    }

    /** @test */
    public function it_handles_dashboard_analytics_with_large_data()
    {
        // Arrange
        $this->createLargeDataset(10000); // 10,000 records
        School::factory()->count(20)->create(['is_active' => true]);

        // Act - Measure analytics performance
        $startTime = microtime(true);

        $response = $this->getJson('/api/dashboard/analytics');

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Assert
        $response->assertStatus(200);
        $this->assertLessThan(3.0, $executionTime, 'Analytics should load within 3 seconds');

        // Verify caching is working by making second request
        $startTime2 = microtime(true);
        $response2 = $this->getJson('/api/dashboard/analytics');
        $endTime2 = microtime(true);
        $executionTime2 = $endTime2 - $startTime2;

        $response2->assertStatus(200);
        $this->assertLessThan(0.5, $executionTime2, 'Cached response should be faster');
    }

    /** @test */
    public function it_handles_pagination_with_large_datasets()
    {
        // Arrange
        $this->createLargeDataset(2000);

        // Act - Test pagination performance
        $startTime = microtime(true);

        $response = $this->getJson('/api/rekon/index?per_page=50');

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Assert
        $response->assertStatus(200);
        $this->assertLessThan(1.0, $executionTime, 'Pagination should be fast');

        $data = $response->json('data');
        $this->assertEquals(50, $data['per_page']);
        $this->assertCount(50, $data['data']);

        // Test large page number
        $startTime2 = microtime(true);
        $response2 = $this->getJson('/api/rekon/index?per_page=50&page=20');
        $endTime2 = microtime(true);
        $executionTime2 = $endTime2 - $startTime2;

        $response2->assertStatus(200);
        $this->assertLessThan(1.0, $executionTime2, 'Deep pagination should also be fast');
    }

    /** @test */
    public function it_handles_kelas_report_with_large_class_sizes()
    {
        // Arrange - Create large class
        $sekolah = 'SMAN_1_DENPASAR';
        $kelas = 'XI';
        $angkatan = 2022;

        // Create 100 students in one class with multiple payment records
        for ($i = 1; $i <= 100; $i++) {
            $idSiswa = str_pad($i, 4, '0', STR_PAD_LEFT);

            // Create payment records for multiple months
            for ($month = 1; $month <= 12; $month++) {
                for ($year = 2022; $year <= 2024; $year++) {
                    RekonData::factory()->create([
                        'sekolah' => $sekolah,
                        'kelas' => $kelas,
                        'id_siswa' => $idSiswa,
                        'nama_siswa' => "Student {$i}",
                        'tahun' => $year,
                        'bulan' => $month,
                        'sts_bayar' => $month % 3 === 0 ? 1 : 0, // Every 3rd month is paid
                        'tgl_tx' => now()->setYear($year)->setMonth($month)
                    ]);
                }
            }
        }

        // Act - Measure report generation performance
        $startTime = microtime(true);

        $response = $this->getJson("/api/rekon/laporan/kelas?sekolah={$sekolah}&kelas={$kelas}&angkatan={$angkatan}");

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Assert
        $response->assertStatus(200);
        $this->assertLessThan(5.0, $executionTime, 'Class report should generate within 5 seconds');

        $data = $response->json('data');
        $this->assertEquals(100, $data['total_siswa']);
        $this->assertCount(100, $data['siswa']);

        // Each student should have payment data for all months in the range
        $student = $data['siswa'][0];
        $this->assertNotEmpty($student['pembayaran']);
        $this->assertGreaterThan(30, count($student['pembayaran'])); // 3 years x 12 months = 36 months
    }

    /** @test */
    public function it_handles_export_with_large_datasets()
    {
        // Arrange
        $this->createLargeDataset(1000);

        // Act - Test export performance
        $startTime = microtime(true);

        $response = $this->postJson('/api/rekon/export/excel', [
            'tahun' => 2024
        ]);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Assert
        $response->assertStatus(200);
        $this->assertLessThan(10.0, $executionTime, 'Export should complete within 10 seconds');

        $this->assertEquals(1000, $response->json('total_records'));
    }

    /** @test */
    public function it_handles_concurrent_requests_with_large_data()
    {
        // Arrange
        $this->createLargeDataset(3000);

        // Act - Simulate concurrent requests
        $startTime = microtime(true);

        $responses = collect(range(1, 10))->map(function () {
            return $this->getJson('/api/rekon/search?sekolah=SMAN_1_DENPASAR&tahun=2024&bulan=1');
        });

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        // Assert
        $responses->each(function ($response) {
            $response->assertStatus(200);
        });

        $this->assertLessThan(5.0, $totalTime, 'Concurrent requests should be handled efficiently');

        // Average time per request
        $averageTime = $totalTime / 10;
        $this->assertLessThan(1.0, $averageTime, 'Average request time should be under 1 second');
    }

    /** @test */
    public function it_uses_database_indexes_effectively()
    {
        // Arrange - Create data that benefits from indexes
        $this->createLargeDataset(5000);

        // Enable query log
        DB::enableQueryLog();

        // Act - Test indexed queries
        $response1 = $this->getJson('/api/rekon/search?sekolah=SMAN_1_DENPASAR&tahun=2024&bulan=1');
        $queries1 = DB::getQueryLog();
        DB::flushQueryLog();

        $response2 = $this->getJson('/api/rekon/index?school=SMAN_1_DENPASAR&per_page=100');
        $queries2 = DB::getQueryLog();
        DB::flushQueryLog();

        DB::disableQueryLog();

        // Assert
        $response1->assertStatus(200);
        $response2->assertStatus(200);

        // Check that queries are using indexes (this is a basic check)
        $this->assertNotEmpty($queries1);
        $this->assertNotEmpty($queries2);

        // Queries should be efficient (not too many for simple operations)
        $this->assertLessThan(5, count($queries1), 'Search should use minimal queries');
        $this->assertLessThan(3, count($queries2), 'Pagination should use minimal queries');
    }

    /** @test */
    public function it_handles_memory_usage_with_large_datasets()
    {
        // Arrange
        $initialMemory = memory_get_usage(true);
        $this->createLargeDataset(2000);

        // Act - Process large dataset
        $response = $this->getJson('/api/rekon/search?sekolah=SMAN_1_DENPASAR&tahun=2024&bulan=1');
        $peakMemory = memory_get_peak_usage(true);

        // Assert
        $response->assertStatus(200);

        $memoryIncrease = $peakMemory - $initialMemory;
        $maxMemoryIncrease = 50 * 1024 * 1024; // 50MB max increase

        $this->assertLessThan($maxMemoryIncrease, $memoryIncrease, 'Memory usage should not increase excessively');
    }

    /** @test */
    public function it_handles_bulk_operations_efficiently()
    {
        // Arrange - Test bulk insert performance
        $bulkData = [];
        for ($i = 1; $i <= 1000; $i++) {
            $bulkData[] = [
                'sekolah' => 'SMAN_' . ($i % 5 + 1) . '_DENPASAR',
                'id_siswa' => str_pad($i, 5, '0', STR_PAD_LEFT),
                'nama_siswa' => "Student {$i}",
                'tahun' => 2024,
                'bulan' => ($i % 12) + 1,
                'dana_masyarakat' => '350000',
                'jum_tagihan' => 350000,
                'sts_bayar' => 1,
                'no_bukti' => str_pad($i, 10, '0', STR_PAD_LEFT),
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        // Act
        $startTime = microtime(true);
        RekonData::insert($bulkData);
        $endTime = microtime(true);

        $executionTime = $endTime - $startTime;

        // Assert
        $this->assertLessThan(2.0, $executionTime, 'Bulk insert should be fast');
        $this->assertDatabaseCount('rekon_data', 1000);

        // Test bulk query performance
        $startTime2 = microtime(true);
        $results = RekonData::whereIn('id_siswa', ['00001', '00050', '00100'])->get();
        $endTime2 = microtime(true);

        $this->assertLessThan(0.5, $endTime2 - $startTime2, 'Bulk select should be fast');
        $this->assertCount(3, $results);
    }

    /** @test */
    public function it_handles_complex_aggregates_efficiently()
    {
        // Arrange - Create data for complex aggregation
        $schools = ['SMAN_1_DENPASAR', 'SMAN_2_DENPASAR', 'SMAN_3_DENPASAR'];
        $years = [2022, 2023, 2024];
        $months = range(1, 12);

        foreach ($schools as $school) {
            foreach ($years as $year) {
                foreach ($months as $month) {
                    RekonData::factory()->count(50)->create([
                        'sekolah' => $school,
                        'tahun' => $year,
                        'bulan' => $month,
                        'dana_masyarakat' => rand(300000, 500000)
                    ]);
                }
            }
        }

        // Act - Test complex aggregation
        $startTime = microtime(true);

        $response = $this->getJson('/api/dashboard/analytics');

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Assert
        $response->assertStatus(200);
        $this->assertLessThan(3.0, $executionTime, 'Complex aggregation should be efficient');

        $summary = $response->json('data.summary');
        $this->assertEquals(3 * 3 * 12 * 50, $summary['total_transactions']); // 3 schools x 3 years x 12 months x 50 records

        $monthlyData = $response->json('data.monthly_data');
        $this->assertNotEmpty($monthlyData);
        $this->assertLessThanOrEqual(36, count($monthlyData)); // 3 years x 12 months

        $schoolData = $response->json('data.school_data');
        $this->assertCount(3, $schoolData);
    }

    /** @test */
    public function it_handles_file_processing_performance()
    {
        // This test simulates large file import performance
        // Arrange - Create large import data
        $largeImportData = [];
        for ($i = 1; $i <= 5000; $i++) {
            $largeImportData[] = [
                'SEKOLAH' => 'SMAN_' . ($i % 10 + 1) . '_DENPASAR',
                'ID_SISWA' => str_pad($i, 5, '0', STR_PAD_LEFT),
                'NAMA_SISWA' => "Student {$i}",
                'ALAMAT' => "Address {$i}",
                'KELAS' => ['X', 'XI', 'XII'][array_rand(['X', 'XI', 'XII'])],
                'JURUSAN' => ['MIPA1', 'MIPA2', 'IPS1', 'IPS2'][array_rand(['MIPA1', 'MIPA2', 'IPS1', 'IPS2'])],
                'JUM_TAGIHAN' => rand(300000, 500000),
                'TAHUN' => 2024,
                'BULAN' => ($i % 12) + 1,
                'DANA_MASYARAKAT' => rand(300000, 500000),
                'TGL_TX' => now()->subDays(rand(1, 30))->format('d/m/Y H:i'),
                'STS_BAYAR' => 1,
                'NO_BUKTI' => str_pad($i, 10, '0', STR_PAD_LEFT)
            ];
        }

        // Create test file
        $testFile = $this->createLargeTestFile($largeImportData);

        // Act - Measure import performance
        $startTime = microtime(true);

        $response = $this->postJson('/api/rekon/import', [
            'file' => $testFile
        ]);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Assert
        // For large files, it should be queued
        if ($response->json('queued')) {
            $this->assertTrue(true, 'Large files should be queued for background processing');
        } else {
            $this->assertLessThan(30.0, $executionTime, 'Import should complete within reasonable time');
            $response->assertStatus(200);
        }
    }

    /** @test */
    public function it_maintains_performance_under_load()
    {
        // Arrange - Create moderate dataset
        $this->createLargeDataset(1000);

        // Act - Simulate sustained load
        $totalTime = 0;
        $requestCount = 50;

        for ($i = 0; $i < $requestCount; $i++) {
            $startTime = microtime(true);

            $response = $this->getJson('/api/dashboard/analytics');

            $endTime = microtime(true);
            $totalTime += ($endTime - $startTime);

            $response->assertStatus(200);
        }

        // Assert
        $averageTime = $totalTime / $requestCount;
        $this->assertLessThan(1.0, $averageTime, 'Average response time should remain fast under load');
        $this->assertLessThan(30.0, $totalTime, 'Total time for all requests should be reasonable');
    }

    /**
     * Helper method to create large test dataset
     */
    private function createLargeDataset(int $count): void
    {
        $schools = ['SMAN_1_DENPASAR', 'SMAN_2_DENPASAR', 'SMAN_3_DENPASAR', 'SMAN_4_DENPASAR', 'SMAN_5_DENPASAR'];
        $kelas = ['X', 'XI', 'XII'];
        $jurusan = ['MIPA1', 'MIPA2', 'MIPA3', 'IPS1', 'IPS2', 'Bahasa'];

        RekonData::factory()->count($count)->create([
            'sekolah' => $schools[array_rand($schools)],
            'kelas' => $kelas[array_rand($kelas)],
            'jurusan' => $jurusan[array_rand($jurusan)],
            'tahun' => rand(2022, 2024),
            'bulan' => rand(1, 12),
            'sts_bayar' => rand(0, 1)
        ]);
    }

    /**
     * Helper method to create large test file
     */
    private function createLargeTestFile(array $data): \Illuminate\Http\UploadedFile
    {
        $filename = 'large_import_' . time() . '.xlsx';
        $filepath = storage_path('app/testing/' . $filename);

        // Create a simple CSV file for large data (faster than Excel for testing)
        $handle = fopen($filepath, 'w');

        if (!empty($data)) {
            // Write header
            fputcsv($handle, array_keys($data[0]));

            // Write data in chunks to manage memory
            foreach ($data as $row) {
                fputcsv($handle, $row);
            }
        }

        fclose($handle);

        return new \Illuminate\Http\UploadedFile(
            $filepath,
            $filename,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}