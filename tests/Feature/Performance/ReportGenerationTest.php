<?php

namespace Tests\Feature\Performance;

use Tests\TestCase;
use App\Models\RekonData;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class ReportGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** @test */
    public function it_generates_excel_reports_efficiently()
    {
        // Arrange - Create test data
        $this->createReportTestData(2000);

        // Act - Measure export performance
        $startTime = microtime(true);

        $response = $this->postJson('/api/rekon/export/excel');

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Assert
        $response->assertStatus(200);
        $this->assertLessThan(15.0, $executionTime, 'Excel export should complete within 15 seconds');

        $this->assertEquals(2000, $response->json('total_records'));
        $this->assertStringContains('.xlsx', $response->json('filename'));

        // Verify file was created
        $filePath = 'public/exports/' . $response->json('filename');
        Storage::assertExists($filePath);
    }

    /** @test */
    public function it_generates_csv_reports_efficiently()
    {
        // Arrange - Create test data
        $this->createReportTestData(2000);

        // Act - Measure CSV export performance
        $startTime = microtime(true);

        $response = $this->postJson('/api/rekon/export/csv');

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Assert
        $response->assertStatus(200);
        $this->assertLessThan(10.0, $executionTime, 'CSV export should be faster than Excel');

        $this->assertEquals(2000, $response->json('total_records'));
        $this->assertStringContains('.csv', $response->json('filename'));

        // Verify file was created
        $filePath = 'public/exports/' . $response->json('filename');
        Storage::assertExists($filePath);
    }

    /** @test */
    public function it_generates_filtered_reports_efficiently()
    {
        // Arrange - Create data across multiple years and schools
        $this->createMultiYearMultiSchoolData();

        // Act - Test filtered export performance
        $startTime = microtime(true);

        $response = $this->postJson('/api/rekon/export/excel', [
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1
        ]);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Assert
        $response->assertStatus(200);
        $this->assertLessThan(5.0, $executionTime, 'Filtered export should be faster');

        // Should have fewer records due to filtering
        $this->assertLessThan(1000, $response->json('total_records'));
    }

    /** @test */
    public function it_generates_kelas_reports_with_large_data()
    {
        // Arrange - Create large class data
        $this->createLargeClassData();

        // Act - Measure class report generation performance
        $startTime = microtime(true);

        $response = $this->getJson('/api/rekon/laporan/kelas?sekolah=SMAN_1_DENPASAR&kelas=XI&angkatan=2022');

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Assert
        $response->assertStatus(200);
        $this->assertLessThan(8.0, $executionTime, 'Large class report should generate within 8 seconds');

        $data = $response->json('data');
        $this->assertEquals(200, $data['total_siswa']); // Large class
        $this->assertCount(200, $data['siswa']);

        // Each student should have payment data for multiple months
        $student = $data['siswa'][0];
        $this->assertNotEmpty($student['pembayaran']);
        $this->assertGreaterThan(30, count($student['pembayaran'])); // Multiple years of data
    }

    /** @test */
    public function it_exports_kelas_reports_to_excel_efficiently()
    {
        // Arrange - Create large class data
        $this->createLargeClassData();

        // Act - Measure Excel class report export
        $startTime = microtime(true);

        $response = $this->postJson('/api/rekon/export/laporan-kelas', [
            'sekolah' => 'SMAN_1_DENPASAR',
            'kelas' => 'XI',
            'angkatan' => 2022
        ]);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Assert
        $response->assertStatus(200);
        $this->assertLessThan(20.0, $executionTime, 'Large Excel class report should generate within 20 seconds');

        $this->assertEquals(200, $response->json('total_records'));
        $this->assertStringContains('Laporan_Kelas_XI', $response->json('filename'));

        // Verify file was created
        $filePath = 'public/exports/' . $response->json('filename');
        Storage::assertExists($filePath);
    }

    /** @test */
    public function it_handles_concurrent_report_generation()
    {
        // Arrange - Create test data
        $this->createReportTestData(1000);

        // Act - Generate multiple reports concurrently
        $startTime = microtime(true);

        $responses = collect([
            $this->postJson('/api/rekon/export/excel'),
            $this->postJson('/api/rekon/export/csv'),
            $this->postJson('/api/rekon/export/excel', ['tahun' => 2024]),
            $this->postJson('/api/rekon/export/csv', ['sekolah' => 'SMAN_1_DENPASAR'])
        ]);

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        // Assert
        $responses->each(function ($response) {
            $response->assertStatus(200);
        });

        $this->assertLessThan(25.0, $totalTime, 'Concurrent report generation should be efficient');

        // Verify all files were created
        $responses->each(function ($response) {
            $filePath = 'public/exports/' . $response->json('filename');
            Storage::assertExists($filePath);
        });
    }

    /** @test */
    public function it_handles_report_generation_memory_usage()
    {
        // Arrange - Create large dataset
        $this->createReportTestData(3000);

        $initialMemory = memory_get_usage(true);

        // Act - Generate large report
        $response = $this->postJson('/api/rekon/export/excel');

        $peakMemory = memory_get_peak_usage(true);

        // Assert
        $response->assertStatus(200);
        $this->assertEquals(3000, $response->json('total_records'));

        $memoryIncrease = $peakMemory - $initialMemory;
        $maxMemoryIncrease = 100 * 1024 * 1024; // 100MB max increase

        $this->assertLessThan($maxMemoryIncrease, $memoryIncrease, 'Report generation should not use excessive memory');
    }

    /** @test */
    public function it_generates_reports_with_various_data_types()
    {
        // Arrange - Create data with various characteristics
        $this->createVariedTestData();

        // Act
        $response = $this->postJson('/api/rekon/export/excel');

        // Assert
        $response->assertStatus(200);

        // Verify file was created and contains data
        $filePath = 'public/exports/' . $response->json('filename');
        Storage::assertExists($filePath);

        // File should have reasonable size (not too large for the data amount)
        $fileSize = Storage::size($filePath);
        $this->assertGreaterThan(1000, $fileSize); // At least 1KB
        $this->assertLessThan(10 * 1024 * 1024, $fileSize); // Less than 10MB for test data
    }

    /** @test */
    public function it_handles_empty_data_reports()
    {
        // Arrange - No data in database

        // Act
        $response = $this->postJson('/api/rekon/export/excel');

        // Assert
        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('total_records'));

        // Should still create file with headers
        $filePath = 'public/exports/' . $response->json('filename');
        Storage::assertExists($filePath);

        // File should exist but be small (headers only)
        $fileSize = Storage::size($filePath);
        $this->assertGreaterThan(0, $fileSize);
        $this->assertLessThan(50 * 1024, $fileSize); // Less than 50KB
    }

    /** @test */
    public function it_generates_yearly_summary_reports()
    {
        // Arrange - Create multi-year data
        $this->createMultiYearData();

        // Act - Test yearly data aggregation
        $response = $this->getJson('/api/dashboard/analytics');

        // Assert
        $response->assertStatus(200);

        $monthlyData = $response->json('data.monthly_data');
        $this->assertNotEmpty($monthlyData);

        // Should have data for multiple years
        $years = collect($monthlyData)->pluck('tahun')->unique();
        $this->assertGreaterThan(1, $years->count());

        // Verify aggregation is correct
        $totalTransactions = collect($monthlyData)->sum('total');
        $expectedTotal = 3 * 12 * 50; // 3 years x 12 months x 50 records per month
        $this->assertEquals($expectedTotal, $totalTransactions);
    }

    /** @test */
    public function it_generates_school_comparison_reports()
    {
        // Arrange - Create data for multiple schools
        $this->createMultiSchoolComparisonData();

        // Act
        $response = $this->getJson('/api/dashboard/analytics');

        // Assert
        $response->assertStatus(200);

        $schoolData = $response->json('data.school_data');
        $this->assertCount(5, $schoolData);

        // Should be ordered by total (descending)
        $totals = collect($schoolData)->pluck('total');
        $sortedTotals = $totals->sortDesc()->values();
        $this->assertEquals($sortedTotals->toArray(), $totals->toArray());

        // Verify aggregation metrics
        foreach ($schoolData as $school) {
            $this->assertArrayHasKey('sekolah', $school);
            $this->assertArrayHasKey('total', $school);
            $this->assertArrayHasKey('dana', $school);
            $this->assertArrayHasKey('siswa', $school);
        }
    }

    /** @test */
    public function it_handles_report_generation_with_filters()
    {
        // Arrange - Create filtered test data
        $this->createFilteredTestData();

        // Act & Assert - Test various filter combinations
        $testCases = [
            ['tahun' => 2024],
            ['sekolah' => 'SMAN_1_DENPASAR'],
            ['bulan' => 6],
            ['tahun' => 2024, 'bulan' => 6],
            ['sekolah' => 'SMAN_1_DENPASAR', 'tahun' => 2024],
            ['sekolah' => 'SMAN_1_DENPASAR', 'tahun' => 2024, 'bulan' => 6]
        ];

        foreach ($testCases as $filters) {
            $startTime = microtime(true);

            $response = $this->postJson('/api/rekon/export/excel', $filters);

            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;

            $response->assertStatus(200);
            $this->assertLessThan(5.0, $executionTime, "Filtered export should be fast for: " . json_encode($filters));

            // Should have fewer records due to filtering
            $this->assertLessThan(1000, $response->json('total_records'));
        }
    }

    /** @test */
    public function it_handles_report_file_cleanup()
    {
        // Arrange - Create test data
        $this->createReportTestData(100);

        // Act - Generate multiple reports
        $responses = collect(range(1, 5))->map(function () {
            return $this->postJson('/api/rekon/export/excel');
        });

        // Assert - All files should be created
        $responses->each(function ($response) {
            $response->assertStatus(200);
            $filePath = 'public/exports/' . $response->json('filename');
            Storage::assertExists($filePath);
        });

        // Files should have unique names
        $filenames = $responses->map(function ($response) {
            return $response->json('filename');
        });
        $uniqueFilenames = $filenames->unique();
        $this->assertEquals($filenames->count(), $uniqueFilenames->count());
    }

    /**
     * Helper method to create test data for reports
     */
    private function createReportTestData(int $count): void
    {
        RekonData::factory()->count($count)->create([
            'tahun' => 2024,
            'bulan' => rand(1, 12),
            'sts_bayar' => 1
        ]);
    }

    /**
     * Helper method to create multi-year, multi-school data
     */
    private function createMultiYearMultiSchoolData(): void
    {
        $schools = ['SMAN_1_DENPASAR', 'SMAN_2_DENPASAR', 'SMAN_3_DENPASAR'];
        $years = [2022, 2023, 2024];
        $months = range(1, 12);

        foreach ($schools as $school) {
            foreach ($years as $year) {
                foreach ($months as $month) {
                    RekonData::factory()->count(10)->create([
                        'sekolah' => $school,
                        'tahun' => $year,
                        'bulan' => $month,
                        'sts_bayar' => 1
                    ]);
                }
            }
        }
    }

    /**
     * Helper method to create large class data
     */
    private function createLargeClassData(): void
    {
        $sekolah = 'SMAN_1_DENPASAR';
        $kelas = 'XI';
        $angkatan = 2022;

        // Create 200 students with payment history over 3 years
        for ($i = 1; $i <= 200; $i++) {
            $idSiswa = str_pad($i, 4, '0', STR_PAD_LEFT);

            // Create payment records for multiple years
            for ($year = $angkatan; $year <= 2024; $year++) {
                for ($month = 1; $month <= 12; $month++) {
                    if ($year === $angkatan && $month < 7) continue; // Start from July of angkatan year

                    RekonData::factory()->create([
                        'sekolah' => $sekolah,
                        'kelas' => $kelas,
                        'id_siswa' => $idSiswa,
                        'nama_siswa' => "Student {$i}",
                        'tahun' => $year,
                        'bulan' => $month,
                        'sts_bayar' => (rand(1, 100) > 30) ? 1 : 0, // 70% payment rate
                        'dana_masyarakat' => rand(300000, 500000),
                        'tgl_tx' => now()->setYear($year)->setMonth($month)->setDay(rand(1, 28))
                    ]);
                }
            }
        }
    }

    /**
     * Helper method to create varied test data
     */
    private function createVariedTestData(): void
    {
        RekonData::factory()->count(1000)->create([
            'dana_masyarakat' => function () {
                return rand(1, 10) > 2 ? rand(300000, 500000) : 'invalid_value';
            },
            'sts_bayar' => rand(0, 1),
            'sts_reversal' => rand(0, 1),
            'keterangan' => function () {
                return rand(1, 10) > 7 ? 'Special note ' . rand(1, 100) : null;
            }
        ]);
    }

    /**
     * Helper method to create multi-year data
     */
    private function createMultiYearData(): void
    {
        for ($year = 2022; $year <= 2024; $year++) {
            for ($month = 1; $month <= 12; $month++) {
                RekonData::factory()->count(50)->create([
                    'tahun' => $year,
                    'bulan' => $month,
                    'sts_bayar' => 1,
                    'dana_masyarakat' => rand(300000, 500000)
                ]);
            }
        }
    }

    /**
     * Helper method to create multi-school comparison data
     */
    private function createMultiSchoolComparisonData(): void
    {
        $schools = ['SMAN_1_DENPASAR', 'SMAN_2_DENPASAR', 'SMAN_3_DENPASAR', 'SMAN_4_DENPASAR', 'SMAN_5_DENPASAR'];

        foreach ($schools as $index => $school) {
            // Create varying amounts of data per school
            $recordCount = 100 + ($index * 50); // 100, 150, 200, 250, 300 records

            RekonData::factory()->count($recordCount)->create([
                'sekolah' => $school,
                'tahun' => 2024,
                'bulan' => rand(1, 12),
                'sts_bayar' => 1,
                'dana_masyarakat' => rand(300000, 500000)
            ]);
        }
    }

    /**
     * Helper method to create filtered test data
     */
    private function createFilteredTestData(): void
    {
        $schools = ['SMAN_1_DENPASAR', 'SMAN_2_DENPASAR', 'SMAN_3_DENPASAR'];
        $years = [2023, 2024];

        foreach ($schools as $school) {
            foreach ($years as $year) {
                RekonData::factory()->count(50)->create([
                    'sekolah' => $school,
                    'tahun' => $year,
                    'bulan' => rand(1, 12),
                    'sts_bayar' => 1
                ]);
            }
        }
    }
}