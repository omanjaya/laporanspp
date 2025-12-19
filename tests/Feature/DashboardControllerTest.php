<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use App\Models\RekonData;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_get_dashboard_analytics()
    {
        // Arrange
        School::factory()->count(3)->create(['is_active' => true]);
        RekonData::factory()->count(10)->create([
            'tahun' => 2024,
            'bulan' => 1,
            'dana_masyarakat' => '350000'
        ]);
        RekonData::factory()->count(5)->create([
            'tahun' => 2023,
            'bulan' => 12,
            'dana_masyarakat' => '400000'
        ]);

        // Act
        $response = $this->getJson('/api/dashboard/analytics');

        // Assert
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'summary' => [
                            'total_transactions',
                            'total_dana',
                            'total_siswa',
                            'total_schools'
                        ],
                        'monthly_data' => [
                            '*' => [
                                'tahun',
                                'bulan',
                                'total',
                                'dana'
                            ]
                        ],
                        'school_data' => [
                            '*' => [
                                'sekolah',
                                'total',
                                'dana',
                                'siswa'
                            ]
                        ],
                        'cached_at'
                    ]
                ]);

        $response->assertJson([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_transactions' => 15,
                    'total_schools' => 3
                ]
            ]
        ]);
    }

    /** @test */
    public function it_caches_analytics_data()
    {
        // Arrange
        RekonData::factory()->count(5)->create();
        Cache::flush();

        // Act
        $response1 = $this->getJson('/api/dashboard/analytics');
        $response2 = $this->getJson('/api/dashboard/analytics');

        // Assert
        $response1->assertStatus(200);
        $response2->assertStatus(200);

        // Cached data should have same timestamp
        $data1 = $response1->json('data.cached_at');
        $data2 = $response2->json('data.cached_at');
        $this->assertEquals($data1, $data2);
    }

    /** @test */
    public function it_returns_empty_analytics_when_no_data_exists()
    {
        // Arrange - No data in database

        // Act
        $response = $this->getJson('/api/dashboard/analytics');

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'summary' => [
                            'total_transactions' => 0,
                            'total_dana' => 0,
                            'total_siswa' => 0,
                            'total_schools' => 0
                        ]
                    ]
                ]);
    }

    /** @test */
    public function it_handles_database_errors_gracefully()
    {
        // Arrange - Mock a database error
        $this->withoutExceptionHandling();

        // Simulate database connection issue
        config(['database.default' => 'nonexistent']);

        // Act
        $response = $this->getJson('/api/dashboard/analytics');

        // Assert
        $response->assertStatus(500)
                ->assertJson([
                    'success' => false,
                    'message' => 'Terjadi kesalahan:'
                ]);
    }

    /** @test */
    public function it_calculates_monthly_data_correctly()
    {
        // Arrange
        RekonData::factory()->count(3)->create([
            'tahun' => 2024,
            'bulan' => 1,
            'dana_masyarakat' => '100000'
        ]);
        RekonData::factory()->count(2)->create([
            'tahun' => 2024,
            'bulan' => 2,
            'dana_masyarakat' => '200000'
        ]);
        RekonData::factory()->count(1)->create([
            'tahun' => 2022, // Should not be included (older than 1 year)
            'bulan' => 12,
            'dana_masyarakat' => '300000'
        ]);

        // Act
        $response = $this->getJson('/api/dashboard/analytics');

        // Assert
        $response->assertStatus(200);

        $monthlyData = $response->json('data.monthly_data');
        $this->assertCount(2, $monthlyData); // Only 2024 data

        $januaryData = collect($monthlyData)->firstWhere('bulan', 1);
        $this->assertEquals(3, $januaryData['total']);
        $this->assertEquals(300000, $januaryData['dana']); // 3 * 100000

        $februaryData = collect($monthlyData)->firstWhere('bulan', 2);
        $this->assertEquals(2, $februaryData['total']);
        $this->assertEquals(400000, $februaryData['dana']); // 2 * 200000
    }

    /** @test */
    public function it_groups_school_data_correctly()
    {
        // Arrange
        RekonData::factory()->count(3)->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'id_siswa' => '1001',
            'dana_masyarakat' => '350000'
        ]);
        RekonData::factory()->count(2)->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'id_siswa' => '1002', // Different student
            'dana_masyarakat' => '350000'
        ]);
        RekonData::factory()->count(1)->create([
            'sekolah' => 'SMAN_2_DENPASAR',
            'id_siswa' => '2001',
            'dana_masyarakat' => '400000'
        ]);

        // Act
        $response = $this->getJson('/api/dashboard/analytics');

        // Assert
        $response->assertStatus(200);

        $schoolData = $response->json('data.school_data');
        $this->assertCount(2, $schoolData);

        $sman1Data = collect($schoolData)->firstWhere('sekolah', 'SMAN_1_DENPASAR');
        $this->assertEquals(5, $sman1Data['total']); // Total records
        $this->assertEquals(2, $sman1Data['siswa']); // Unique students
        $this->assertEquals(1750000, $sman1Data['dana']); // 5 * 350000

        $sman2Data = collect($schoolData)->firstWhere('sekolah', 'SMAN_2_DENPASAR');
        $this->assertEquals(1, $sman2Data['total']);
        $this->assertEquals(1, $sman2Data['siswa']);
        $this->assertEquals(400000, $sman2Data['dana']);
    }

    /** @test */
    public function it_handles_invalid_dana_masyarakat_values()
    {
        // Arrange
        RekonData::factory()->create([
            'dana_masyarakat' => 'invalid_value',
            'jum_tagihan' => '350000'
        ]);
        RekonData::factory()->create([
            'dana_masyarakat' => '',
            'jum_tagihan' => 'invalid_value'
        ]);
        RekonData::factory()->create([
            'dana_masyarakat' => '400000',
            'jum_tagihan' => '300000'
        ]);

        // Act
        $response = $this->getJson('/api/dashboard/analytics');

        // Assert
        $response->assertStatus(200);

        $summary = $response->json('data.summary');
        $this->assertEquals(400000, $summary['total_dana']); // Only valid numeric value
    }

    /** @test */
    public function it_can_get_active_schools()
    {
        // Arrange
        School::factory()->create(['name' => 'SMAN_1_DENPASAR', 'is_active' => true]);
        School::factory()->create(['name' => 'SMAN_2_DENPASAR', 'is_active' => true]);
        School::factory()->create(['name' => 'SMAN_3_DENPASAR', 'is_active' => false]); // Inactive

        // Act
        $response = $this->getJson('/api/dashboard/schools');

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ]);

        $schools = $response->json('data');
        $this->assertCount(2, $schools);
        $this->assertContains('SMAN_1_DENPASAR', collect($schools)->pluck('name'));
        $this->assertContains('SMAN_2_DENPASAR', collect($schools)->pluck('name'));
        $this->assertNotContains('SMAN_3_DENPASAR', collect($schools)->pluck('name'));
    }

    /** @test */
    public function it_returns_empty_schools_when_none_are_active()
    {
        // Arrange
        School::factory()->create(['is_active' => false]);
        School::factory()->create(['is_active' => false]);

        // Act
        $response = $this->getJson('/api/dashboard/schools');

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => []
                ]);
    }

    /** @test */
    public function it_handles_schools_endpoint_error()
    {
        // Arrange - Mock database error
        config(['database.default' => 'nonexistent']);

        // Act
        $response = $this->getJson('/api/dashboard/schools');

        // Assert
        $response->assertStatus(500)
                ->assertJson([
                    'success' => false,
                    'message' => 'Terjadi kesalahan:'
                ]);
    }

    /** @test */
    public function it_sanitizes_school_names_in_output()
    {
        // Arrange
        School::factory()->create([
            'name' => '<script>alert("xss")</script>SMAN_1',
            'display_name' => 'SMA <b>Negeri</b> 1',
            'is_active' => true
        ]);

        // Act
        $response = $this->getJson('/api/dashboard/analytics');

        // Assert
        $response->assertStatus(200);

        $schoolData = $response->json('data.school_data');
        $schoolName = collect($schoolData)->firstWhere('sekolah', 'SMAN_1')['sekolah'];

        // School name should be sanitized
        $this->assertStringNotContainsString('<script>', $schoolName);
        $this->assertStringNotContainsString('<b>', $schoolName);
    }

    /** @test */
    public function it_calculates_unique_student_count_correctly()
    {
        // Arrange
        RekonData::factory()->count(5)->create([
            'id_siswa' => '1001', // Same student, different months
            'tahun' => 2024,
            'bulan' => [1, 2, 3, 4, 5][array_rand([1, 2, 3, 4, 5])]
        ]);
        RekonData::factory()->count(3)->create([
            'id_siswa' => '1002', // Another student
            'tahun' => 2024,
            'bulan' => [1, 2, 3][array_rand([1, 2, 3])]
        ]);

        // Act
        $response = $this->getJson('/api/dashboard/analytics');

        // Assert
        $response->assertStatus(200);

        $summary = $response->json('data.summary');
        $this->assertEquals(8, $summary['total_transactions']); // Total records
        $this->assertEquals(2, $summary['total_siswa']); // Unique students
    }

    /** @test */
    public function it_limits_monthly_data_to_recent_years()
    {
        // Arrange
        $currentYear = date('Y');
        $twoYearsAgo = $currentYear - 2;

        RekonData::factory()->count(2)->create(['tahun' => $currentYear]);
        RekonData::factory()->count(2)->create(['tahun' => $currentYear - 1]);
        RekonData::factory()->count(2)->create(['tahun' => $twoYearsAgo]); // Should not be included

        // Act
        $response = $this->getJson('/api/dashboard/analytics');

        // Assert
        $response->assertStatus(200);

        $monthlyData = $response->json('data.monthly_data');
        $years = collect($monthlyData)->pluck('tahun')->unique();

        $this->assertNotContains($twoYearsAgo, $years);
        $this->assertContains($currentYear, $years);
        $this->assertContains($currentYear - 1, $years);
    }

    /** @test */
    public function it_caches_school_count_separately()
    {
        // Arrange
        Cache::flush();
        School::factory()->count(5)->create(['is_active' => true]);

        // Act
        $response1 = $this->getJson('/api/dashboard/analytics');

        // Check cache exists
        $this->assertTrue(Cache::has('active_schools_count'));

        // Act again
        $response2 = $this->getJson('/api/dashboard/analytics');

        // Assert
        $response1->assertStatus(200);
        $response2->assertStatus(200);

        $response1->assertJsonPath('data.summary.total_schools', 5);
        $response2->assertJsonPath('data.summary.total_schools', 5);
    }
}