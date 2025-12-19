<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use App\Models\RekonData;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RekonSearchControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_search_data_with_valid_parameters()
    {
        // Arrange
        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1,
            'id_siswa' => '1001',
            'nama_siswa' => 'Test Student',
            'dana_masyarakat' => '350000'
        ]);

        // Act
        $response = $this->getJson('/api/rekon/search?sekolah=SMAN_1_DENPASAR&tahun=2024&bulan=1');

        // Assert
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        '*' => [
                            'id',
                            'sekolah',
                            'id_siswa',
                            'nama_siswa',
                            'tahun',
                            'bulan',
                            'dana_masyarakat',
                            'jum_tagihan',
                            'no_bukti',
                            'created_at'
                        ]
                    ],
                    'summary' => [
                        'total_records',
                        'total_dana_masyarakat',
                        'unique_students',
                        'query_params' => [
                            'sekolah',
                            'tahun',
                            'bulan'
                        ]
                    ]
                ]);

        $response->assertJson([
            'success' => true,
            'message' => 'Data ditemukan',
            'summary' => [
                'total_records' => 1,
                'unique_students' => 1,
                'query_params' => [
                    'sekolah' => 'SMAN_1_DENPASAR',
                    'tahun' => 2024,
                    'bulan' => 1
                ]
            ]
        ]);
    }

    /** @test */
    public function it_returns_404_when_no_data_found()
    {
        // Act
        $response = $this->getJson('/api/rekon/search?sekolah=NONEXISTENT&tahun=2024&bulan=1');

        // Assert
        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Data tidak ditemukan',
                    'data' => '-'
                ]);
    }

    /** @test */
    public function it_sanitizes_output_data()
    {
        // Arrange
        RekonData::factory()->create([
            'sekolah' => '<script>alert("xss")</script>SMAN_1_DENPASAR',
            'id_siswa' => '<b>1001</b>',
            'nama_siswa' => '<img src=x onerror=alert("xss")>Test Student',
            'tahun' => 2024,
            'bulan' => 1,
            'dana_masyarakat' => '350000',
            'no_bukti' => '<script>1234567890</script>'
        ]);

        // Act
        $response = $this->getJson('/api/rekon/search?sekolah=SMAN_1_DENPASAR&tahun=2024&bulan=1');

        // Assert
        $response->assertStatus(200);

        $data = $response->json('data.0');
        $this->assertStringNotContainsString('<script>', $data['sekolah']);
        $this->assertStringNotContainsString('<b>', $data['id_siswa']);
        $this->assertStringNotContainsString('<img', $data['nama_siswa']);
        $this->assertStringNotContainsString('<script>', $data['no_bukti']);
    }

    /** @test */
    public function it_limits_search_results_to_1000_records()
    {
        // Arrange
        RekonData::factory()->count(1500)->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1
        ]);

        // Act
        $response = $this->getJson('/api/rekon/search?sekolah=SMAN_1_DENPASAR&tahun=2024&bulan=1');

        // Assert
        $response->assertStatus(200);

        $summary = $response->json('summary');
        $this->assertLessThanOrEqual(1000, $summary['total_records']);
    }

    /** @test */
    public function it_calculates_summary_correctly()
    {
        // Arrange
        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1,
            'id_siswa' => '1001',
            'dana_masyarakat' => '350000',
            'jum_tagihan' => '350000'
        ]);
        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1,
            'id_siswa' => '1002',
            'dana_masyarakat' => '400000',
            'jum_tagihan' => '400000'
        ]);
        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1,
            'id_siswa' => '1001', // Duplicate student
            'dana_masyarakat' => '350000',
            'jum_tagihan' => '350000'
        ]);

        // Act
        $response = $this->getJson('/api/rekon/search?sekolah=SMAN_1_DENPASAR&tahun=2024&bulan=1');

        // Assert
        $response->assertStatus(200);

        $summary = $response->json('summary');
        $this->assertEquals(3, $summary['total_records']);
        $this->assertEquals(1100000, $summary['total_dana_masyarakat']); // 350000 + 400000 + 350000
        $this->assertEquals(2, $summary['unique_students']); // Only 2 unique students
    }

    /** @test */
    public function it_handles_invalid_dana_masyarakat_values_in_summary()
    {
        // Arrange
        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1,
            'dana_masyarakat' => 'invalid_value'
        ]);
        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1,
            'dana_masyarakat' => '350000'
        ]);

        // Act
        $response = $this->getJson('/api/rekon/search?sekolah=SMAN_1_DENPASAR&tahun=2024&bulan=1');

        // Assert
        $response->assertStatus(200);

        $summary = $response->json('summary');
        $this->assertEquals(350000, $summary['total_dana_masyarakat']); // Only valid numeric value
    }

    /** @test */
    public function it_can_get_specific_value()
    {
        // Arrange
        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1,
            'dana_masyarakat' => '350000'
        ]);

        // Act
        $response = $this->getJson('/api/rekon/get-value?sekolah=SMAN_1_DENPASAR&tahun=2024&bulan=1&field=dana_masyarakat');

        // Assert
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'value',
                    'field'
                ]);

        $response->assertJson([
            'success' => true,
            'message' => 'Data ditemukan',
            'value' => 350000.0,
            'field' => 'dana_masyarakat'
        ]);
    }

    /** @test */
    public function it_returns_404_when_get_value_not_found()
    {
        // Act
        $response = $this->getJson('/api/rekon/get-value?sekolah=NONEXISTENT&tahun=2024&bulan=1&field=dana_masyarakat');

        // Assert
        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Data tidak ditemukan',
                    'value' => '-'
                ]);
    }

    /** @test */
    public function it_sanitizes_string_fields_in_get_value()
    {
        // Arrange
        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1,
            'nama_siswa' => '<script>alert("xss")</script>Test Student'
        ]);

        // Act
        $response = $this->getJson('/api/rekon/get-value?sekolah=SMAN_1_DENPASAR&tahun=2024&bulan=1&field=nama_siswa');

        // Assert
        $response->assertStatus(200);

        $value = $response->json('value');
        $this->assertStringNotContainsString('<script>', $value);
    }

    /** @test */
    public function it_can_get_paginated_index_data()
    {
        // Arrange
        RekonData::factory()->count(25)->create();

        // Act
        $response = $this->getJson('/api/rekon/index?per_page=10');

        // Assert
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'sekolah',
                                'id_siswa',
                                'nama_siswa',
                                'tahun',
                                'bulan',
                                'dana_masyarakat',
                                'jum_tagihan',
                                'no_bukti',
                                'created_at'
                            ]
                        ],
                        'current_page',
                        'last_page',
                        'per_page',
                        'total'
                    ],
                    'meta' => [
                        'per_page',
                        'school_filter'
                    ]
                ]);

        $data = $response->json('data');
        $this->assertEquals(10, $data['per_page']);
        $this->assertCount(10, $data['data']);
    }

    /** @test */
    public function it_limits_per_page_to_maximum_100()
    {
        // Arrange
        RekonData::factory()->count(150)->create();

        // Act
        $response = $this->getJson('/api/rekon/index?per_page=200');

        // Assert
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(100, $data['per_page']); // Should be limited to 100
    }

    /** @test */
    public function it_can_filter_index_by_school()
    {
        // Arrange
        RekonData::factory()->count(10)->create(['sekolah' => 'SMAN_1_DENPASAR']);
        RekonData::factory()->count(5)->create(['sekolah' => 'SMAN_2_DENPASAR']);

        // Act
        $response = $this->getJson('/api/rekon/index?school=SMAN_1');

        // Assert
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(10, $data['total']);
        $this->assertEquals('SMAN_1', $response->json('meta.school_filter'));
    }

    /** @test */
    public function it_validates_school_filter_parameter()
    {
        // Act
        $response = $this->getJson('/api/rekon/index?school=<script>alert("xss")</script>');

        // Assert
        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'message' => 'Invalid school filter parameter.',
                    'error_code' => 'invalid_filter'
                ]);
    }

    /** @test */
    public function it_sanitizes_school_filter_in_output()
    {
        // Arrange
        RekonData::factory()->create(['sekolah' => 'SMAN_1_DENPASAR']);

        // Act
        $response = $this->getJson('/api/rekon/index?school=SMAN_1');

        // Assert
        $response->assertStatus(200);

        $schoolFilter = $response->json('meta.school_filter');
        $this->assertEquals('SMAN_1', $schoolFilter);
    }

    /** @test */
    public function it_handles_search_errors_gracefully()
    {
        // Arrange - Mock database error
        config(['database.default' => 'nonexistent']);

        // Act
        $response = $this->getJson('/api/rekon/search?sekolah=SMAN_1_DENPASAR&tahun=2024&bulan=1');

        // Assert
        $response->assertStatus(500)
                ->assertJson([
                    'success' => false,
                    'message' => 'Internal server error. Please try again later.',
                    'error_code' => 'search_failed'
                ]);
    }

    /** @test */
    public function it_handles_get_value_errors_gracefully()
    {
        // Arrange - Mock database error
        config(['database.default' => 'nonexistent']);

        // Act
        $response = $this->getJson('/api/rekon/get-value?sekolah=SMAN_1_DENPASAR&tahun=2024&bulan=1&field=dana_masyarakat');

        // Assert
        $response->assertStatus(500)
                ->assertJson([
                    'success' => false,
                    'message' => 'Internal server error. Please try again later.',
                    'error_code' => 'get_value_failed'
                ]);
    }

    /** @test */
    public function it_handles_index_errors_gracefully()
    {
        // Arrange - Mock database error
        config(['database.default' => 'nonexistent']);

        // Act
        $response = $this->getJson('/api/rekon/index');

        // Assert
        $response->assertStatus(500)
                ->assertJson([
                    'success' => false,
                    'message' => 'Internal server error. Please try again later.',
                    'error_code' => 'index_failed'
                ]);
    }

    /** @test */
    public function it_casts_numeric_fields_correctly()
    {
        // Arrange
        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1,
            'dana_masyarakat' => '350000',
            'jum_tagihan' => '350000'
        ]);

        // Act
        $response = $this->getJson('/api/rekon/search?sekolah=SMAN_1_DENPASAR&tahun=2024&bulan=1');

        // Assert
        $response->assertStatus(200);

        $data = $response->json('data.0');
        $this->assertIsInt($data['tahun']);
        $this->assertIsInt($data['bulan']);
        $this->assertIsFloat($data['dana_masyarakat']);
        $this->assertIsFloat($data['jum_tagihan']);
    }

    /** @test */
    public function it_returns_data_in_descending_order()
    {
        // Arrange
        $record1 = RekonData::factory()->create(['created_at' => now()->subDays(2)]);
        $record2 = RekonData::factory()->create(['created_at' => now()->subDays(1)]);
        $record3 = RekonData::factory()->create(['created_at' => now()]);

        // Act
        $response = $this->getJson('/api/rekon/index');

        // Assert
        $response->assertStatus(200);

        $data = $response->json('data.data');
        $this->assertEquals($record3->id, $data[0]['id']); // Most recent first
        $this->assertEquals($record2->id, $data[1]['id']);
        $this->assertEquals($record1->id, $data[2]['id']);
    }

    /** @test */
    public function it_includes_created_at_in_iso_format()
    {
        // Arrange
        $record = RekonData::factory()->create();

        // Act
        $response = $this->getJson('/api/rekon/search?sekolah=' . $record->sekolah . '&tahun=' . $record->tahun . '&bulan=' . $record->bulan);

        // Assert
        $response->assertStatus(200);

        $createdAt = $response->json('data.0.created_at');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/', $createdAt);
    }
}