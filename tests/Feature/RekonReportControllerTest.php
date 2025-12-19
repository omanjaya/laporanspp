<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use App\Services\RekonExportService;
use App\Models\RekonData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class RekonReportControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** @test */
    public function it_can_get_laporan_kelas()
    {
        // Arrange
        $sekolah = 'SMAN_1_DENPASAR';
        $kelas = 'XI';
        $angkatan = 2022;

        RekonData::factory()->create([
            'sekolah' => $sekolah,
            'kelas' => $kelas,
            'id_siswa' => '001',
            'nama_siswa' => 'Student 1',
            'tahun' => 2024,
            'bulan' => 1,
            'tgl_tx' => now(),
            'sts_bayar' => 1
        ]);

        RekonData::factory()->create([
            'sekolah' => $sekolah,
            'kelas' => $kelas,
            'id_siswa' => '002',
            'nama_siswa' => 'Student 2',
            'tahun' => 2024,
            'bulan' => 2,
            'tgl_tx' => now(),
            'sts_bayar' => 1
        ]);

        // Act
        $response = $this->getJson("/api/rekon/laporan/kelas?sekolah={$sekolah}&kelas={$kelas}&angkatan={$angkatan}");

        // Assert
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'kelas',
                        'angkatan',
                        'sekolah',
                        'headers' => [
                            '*' => [
                                'year',
                                'month',
                                'label'
                            ]
                        ],
                        'siswa' => [
                            '*' => [
                                'no',
                                'nis',
                                'nama',
                                'pembayaran' => [
                                    '*' => []
                                ]
                            ]
                        ],
                        'total_siswa'
                    ]
                ]);

        $data = $response->json('data');
        $this->assertEquals($kelas, $data['kelas']);
        $this->assertEquals($angkatan, $data['angkatan']);
        $this->assertEquals($sekolah, $data['sekolah']);
        $this->assertEquals(2, $data['total_siswa']);
        $this->assertCount(2, $data['siswa']);
    }

    /** @test */
    public function it_returns_404_when_no_kelas_data_found()
    {
        // Arrange
        $sekolah = 'SMAN_1_DENPASAR';
        $kelas = 'XI';
        $angkatan = 2022;

        // Create data for different class
        RekonData::factory()->create(['kelas' => 'XII']);

        // Act
        $response = $this->getJson("/api/rekon/laporan/kelas?sekolah={$sekolah}&kelas={$kelas}&angkatan={$angkatan}");

        // Assert
        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Data siswa untuk kelas tersebut tidak ditemukan'
                ]);
    }

    /** @test */
    public function it_validates_laporan_kelas_parameters()
    {
        // Act
        $response = $this->getJson('/api/rekon/laporan/kelas');

        // Assert
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['sekolah', 'kelas', 'angkatan']);
    }

    /** @test */
    public function it_validates_angkatan_parameter_range()
    {
        // Arrange
        $sekolah = 'SMAN_1_DENPASAR';
        $kelas = 'XI';

        // Act
        $response = $this->getJson("/api/rekon/laporan/kelas?sekolah={$sekolah}&kelas={$kelas}&angkatan=1999");

        // Assert
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['angkatan']);
    }

    /** @test */
    public function it_formats_tanggal_pembayaran_correctly()
    {
        // Arrange
        $sekolah = 'SMAN_1_DENPASAR';
        $kelas = 'XI';
        $angkatan = 2022;

        $tanggalBayar = now()->subDays(10);

        RekonData::factory()->create([
            'sekolah' => $sekolah,
            'kelas' => $kelas,
            'id_siswa' => '001',
            'nama_siswa' => 'Test Student',
            'tahun' => 2024,
            'bulan' => 1,
            'tgl_tx' => $tanggalBayar,
            'sts_bayar' => 1
        ]);

        // Act
        $response = $this->getJson("/api/rekon/laporan/kelas?sekolah={$sekolah}&kelas={$kelas}&angkatan={$angkatan}");

        // Assert
        $response->assertStatus(200);

        $siswa = $response->json('data.siswa.0');
        $this->assertEquals('001', $siswa['nis']);
        $this->assertEquals('Test Student', $siswa['nama']);

        // Find the payment for January 2024
        $januaryPayment = null;
        foreach ($siswa['pembayaran'] as $index => $payment) {
            $headers = $response->json('data.headers');
            if ($headers[$index]['year'] == 2024 && $headers[$index]['month'] == 1) {
                $januaryPayment = $payment;
                break;
            }
        }

        $this->assertNotNull($januaryPayment);
        $this->assertEquals($tanggalBayar->format('d/m/Y'), $januaryPayment);
    }

    /** @test */
    public function it_shows_dash_for_unpaid_months()
    {
        // Arrange
        $sekolah = 'SMAN_1_DENPASAR';
        $kelas = 'XI';
        $angkatan = 2022;

        RekonData::factory()->create([
            'sekolah' => $sekolah,
            'kelas' => $kelas,
            'id_siswa' => '001',
            'nama_siswa' => 'Test Student',
            'tahun' => 2024,
            'bulan' => 1,
            'sts_bayar' => 0 // Unpaid
        ]);

        // Act
        $response = $this->getJson("/api/rekon/laporan/kelas?sekolah={$sekolah}&kelas={$kelas}&angkatan={$angkatan}");

        // Assert
        $response->assertStatus(200);

        $siswa = $response->json('data.siswa.0');

        // Find the payment for January 2024 (should show '-')
        $januaryPayment = null;
        foreach ($siswa['pembayaran'] as $index => $payment) {
            $headers = $response->json('data.headers');
            if ($headers[$index]['year'] == 2024 && $headers[$index]['month'] == 1) {
                $januaryPayment = $payment;
                break;
            }
        }

        $this->assertEquals('-', $januaryPayment);
    }

    /** @test */
    public function it_generates_correct_headers_for_kelas_report()
    {
        // Arrange
        $sekolah = 'SMAN_1_DENPASAR';
        $kelas = 'XI';
        $angkatan = 2022;
        $currentYear = date('Y');

        RekonData::factory()->create([
            'sekolah' => $sekolah,
            'kelas' => $kelas,
            'tahun' => $angkatan
        ]);

        // Act
        $response = $this->getJson("/api/rekon/laporan/kelas?sekolah={$sekolah}&kelas={$kelas}&angkatan={$angkatan}");

        // Assert
        $response->assertStatus(200);

        $headers = $response->json('data.headers');

        // Should generate headers for multiple years
        $years = collect($headers)->pluck('year')->unique();
        $this->assertTrue($years->contains($angkatan));
        $this->assertTrue($years->contains($currentYear));
        $this->assertTrue($years->contains($currentYear + 1));

        // Should have Indonesian month names
        $months = collect($headers)->pluck('label')->unique();
        $this->assertTrue($months->contains('Juli'));
        $this->assertTrue($months->contains('Agustus'));
        $this->assertTrue($months->contains('Januari'));
    }

    /** @test */
    public function it_filters_by_angkatan_correctly()
    {
        // Arrange
        $sekolah = 'SMAN_1_DENPASAR';
        $kelas = 'XI';
        $angkatan = 2022;

        // Student from angkatan 2022
        RekonData::factory()->create([
            'sekolah' => $sekolah,
            'kelas' => $kelas,
            'id_siswa' => '001',
            'tahun' => 2022
        ]);

        // Student from angkatan 2021 (should not be included)
        RekonData::factory()->create([
            'sekolah' => $sekolah,
            'kelas' => $kelas,
            'id_siswa' => '002',
            'tahun' => 2021
        ]);

        // Act
        $response = $this->getJson("/api/rekon/laporan/kelas?sekolah={$sekolah}&kelas={$kelas}&angkatan={$angkatan}");

        // Assert
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(1, $data['total_siswa']);
        $this->assertEquals('001', $data['siswa'][0]['nis']);
    }

    /** @test */
    public function it_can_export_laporan_kelas_to_excel()
    {
        // Arrange
        $mockService = $this->mock(RekonExportService::class);
        $mockService->shouldReceive('exportLaporanKelas')
                    ->once()
                    ->with('SMAN_1_DENPASAR', 'XI', 2022)
                    ->andReturn([
                        'success' => true,
                        'filename' => 'Laporan_Kelas_XI_Angkatan_2022_test.xlsx',
                        'download_url' => '/exports/Laporan_Kelas_XI_Angkatan_2022_test.xlsx',
                        'total_records' => 10
                    ]);

        // Act
        $response = $this->postJson('/api/rekon/export/laporan-kelas', [
            'sekolah' => 'SMAN_1_DENPASAR',
            'kelas' => 'XI',
            'angkatan' => 2022
        ]);

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'File Excel laporan kelas berhasil dibuat',
                    'filename' => 'Laporan_Kelas_XI_Angkatan_2022_test.xlsx',
                    'download_url' => '/exports/Laporan_Kelas_XI_Angkatan_2022_test.xlsx',
                    'total_records' => 10
                ]);
    }

    /** @test */
    public function it_handles_export_laporan_kelas_error()
    {
        // Arrange
        $mockService = $this->mock(RekonExportService::class);
        $mockService->shouldReceive('exportLaporanKelas')
                    ->once()
                    ->andThrow(new \Exception('Export failed'));

        // Act
        $response = $this->postJson('/api/rekon/export/laporan-kelas', [
            'sekolah' => 'SMAN_1_DENPASAR',
            'kelas' => 'XI',
            'angkatan' => 2022
        ]);

        // Assert
        $response->assertStatus(500)
                ->assertJson([
                    'success' => false,
                    'message' => 'Error creating Excel file: Export failed'
                ]);
    }

    /** @test */
    public function it_validates_export_laporan_kelas_parameters()
    {
        // Act
        $response = $this->postJson('/api/rekon/export/laporan-kelas');

        // Assert
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['sekolah', 'kelas', 'angkatan']);
    }

    /** @test */
    public function it_can_export_data_to_excel()
    {
        // Arrange
        $mockService = $this->mock(RekonExportService::class);
        $mockService->shouldReceive('exportToExcel')
                    ->once()
                    ->with(['sekolah' => 'SMAN_1_DENPASAR', 'tahun' => 2024, 'bulan' => 1])
                    ->andReturn([
                        'success' => true,
                        'filename' => 'laporan_rekon_spp_test.xlsx',
                        'download_url' => '/exports/laporan_rekon_spp_test.xlsx',
                        'total_records' => 50
                    ]);

        // Act
        $response = $this->postJson('/api/rekon/export/excel', [
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1
        ]);

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'File Excel berhasil dibuat',
                    'filename' => 'laporan_rekon_spp_test.xlsx',
                    'download_url' => '/exports/laporan_rekon_spp_test.xlsx',
                    'total_records' => 50
                ]);
    }

    /** @test */
    public function it_can_export_data_to_csv()
    {
        // Arrange
        $mockService = $this->mock(RekonExportService::class);
        $mockService->shouldReceive('exportToCSV')
                    ->once()
                    ->with(['sekolah' => 'SMAN_1_DENPASAR'])
                    ->andReturn([
                        'success' => true,
                        'filename' => 'laporan_rekon_spp_test.csv',
                        'download_url' => '/exports/laporan_rekon_spp_test.csv',
                        'total_records' => 25
                    ]);

        // Act
        $response = $this->postJson('/api/rekon/export/csv', [
            'sekolah' => 'SMAN_1_DENPASAR'
        ]);

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'File CSV berhasil dibuat',
                    'filename' => 'laporan_rekon_spp_test.csv',
                    'download_url' => '/exports/laporan_rekon_spp_test.csv',
                    'total_records' => 25
                ]);
    }

    /** @test */
    public function it_handles_export_excel_error()
    {
        // Arrange
        $mockService = $this->mock(RekonExportService::class);
        $mockService->shouldReceive('exportToExcel')
                    ->once()
                    ->andThrow(new \Exception('Excel creation failed'));

        // Act
        $response = $this->postJson('/api/rekon/export/excel');

        // Assert
        $response->assertStatus(500)
                ->assertJson([
                    'success' => false,
                    'message' => 'Error creating Excel file: Excel creation failed'
                ]);
    }

    /** @test */
    public function it_handles_export_csv_error()
    {
        // Arrange
        $mockService = $this->mock(RekonExportService::class);
        $mockService->shouldReceive('exportToCSV')
                    ->once()
                    ->andThrow(new \Exception('CSV creation failed'));

        // Act
        $response = $this->postJson('/api/rekon/export/csv');

        // Assert
        $response->assertStatus(500)
                ->assertJson([
                    'success' => false,
                    'message' => 'Error creating CSV file: CSV creation failed'
                ]);
    }

    /** @test */
    public function it_handles_unique_students_in_kelas_report()
    {
        // Arrange
        $sekolah = 'SMAN_1_DENPASAR';
        $kelas = 'XI';
        $angkatan = 2022;

        // Same student, different months
        RekonData::factory()->create([
            'sekolah' => $sekolah,
            'kelas' => $kelas,
            'id_siswa' => '001',
            'nama_siswa' => 'Student 1',
            'tahun' => 2024,
            'bulan' => 1,
            'sts_bayar' => 1
        ]);

        RekonData::factory()->create([
            'sekolah' => $sekolah,
            'kelas' => $kelas,
            'id_siswa' => '001', // Same student
            'nama_siswa' => 'Student 1',
            'tahun' => 2024,
            'bulan' => 2,
            'sts_bayar' => 1
        ]);

        RekonData::factory()->create([
            'sekolah' => $sekolah,
            'kelas' => $kelas,
            'id_siswa' => '002',
            'nama_siswa' => 'Student 2',
            'tahun' => 2024,
            'bulan' => 1,
            'sts_bayar' => 1
        ]);

        // Act
        $response = $this->getJson("/api/rekon/laporan/kelas?sekolah={$sekolah}&kelas={$kelas}&angkatan={$angkatan}");

        // Assert
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(2, $data['total_siswa']); // Should be 2 unique students

        $studentIds = collect($data['siswa'])->pluck('nis');
        $this->assertContains('001', $studentIds);
        $this->assertContains('002', $studentIds);
    }

    /** @test */
    public function it_orders_students_by_id_siswa()
    {
        // Arrange
        $sekolah = 'SMAN_1_DENPASAR';
        $kelas = 'XI';
        $angkatan = 2022;

        RekonData::factory()->create([
            'sekolah' => $sekolah,
            'kelas' => $kelas,
            'id_siswa' => '003',
            'nama_siswa' => 'Student C',
            'tahun' => $angkatan
        ]);

        RekonData::factory()->create([
            'sekolah' => $sekolah,
            'kelas' => $kelas,
            'id_siswa' => '001',
            'nama_siswa' => 'Student A',
            'tahun' => $angkatan
        ]);

        RekonData::factory()->create([
            'sekolah' => $sekolah,
            'kelas' => $kelas,
            'id_siswa' => '002',
            'nama_siswa' => 'Student B',
            'tahun' => $angkatan
        ]);

        // Act
        $response = $this->getJson("/api/rekon/laporan/kelas?sekolah={$sekolah}&kelas={$kelas}&angkatan={$angkatan}");

        // Assert
        $response->assertStatus(200);

        $siswa = $response->json('data.siswa');
        $this->assertEquals('001', $siswa[0]['nis']); // First
        $this->assertEquals('002', $siswa[1]['nis']); // Second
        $this->assertEquals('003', $siswa[2]['nis']); // Third
    }

    /** @test */
    public function it_handles_laporan_kelas_general_error()
    {
        // Arrange - Mock database error
        config(['database.default' => 'nonexistent']);

        // Act
        $response = $this->getJson('/api/rekon/laporan/kelas?sekolah=SMAN_1_DENPASAR&kelas=XI&angkatan=2022');

        // Assert
        $response->assertStatus(500)
                ->assertJson([
                    'success' => false,
                    'message' => 'Terjadi kesalahan:'
                ]);
    }

    /** @test */
    public function it_generates_correct_academic_year_range()
    {
        // Arrange
        $sekolah = 'SMAN_1_DENPASAR';
        $kelas = 'XI';
        $angkatan = 2022; // Start year
        $currentYear = date('Y');

        RekonData::factory()->create([
            'sekolah' => $sekolah,
            'kelas' => $kelas,
            'tahun' => $angkatan
        ]);

        // Act
        $response = $this->getJson("/api/rekon/laporan/kelas?sekolah={$sekolah}&kelas={$kelas}&angkatan={$angkatan}");

        // Assert
        $response->assertStatus(200);

        $headers = $response->json('data.headers');
        $years = collect($headers)->pluck('year')->unique()->sort()->values();

        // Should start from angkatan year and go to current year + 1
        $this->assertEquals($angkatan, $years->first());
        $this->assertEquals($currentYear + 1, $years->last());
    }
}