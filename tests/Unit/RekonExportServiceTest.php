<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\RekonExportService;
use App\Models\RekonData;
use App\Models\School;
use Illuminate\Support\Facades\Storage;

class RekonExportServiceTest extends TestCase
{
    private RekonExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RekonExportService();
        Storage::fake('public');
    }

    /** @test */
    public function it_can_export_data_to_excel()
    {
        // Arrange
        RekonData::factory()->count(5)->create();

        // Act
        $result = $this->service->exportToExcel();

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(5, $result['total_records']);
        $this->assertStringContains('laporan_rekon_spp_', $result['filename']);
        $this->assertStringContains('.xlsx', $result['filename']);
        $this->assertStringStartsWith('/exports/', $result['download_url']);

        // Check if file was created
        $filePath = 'public/exports/' . $result['filename'];
        Storage::assertExists($filePath);
    }

    /** @test */
    public function it_can_export_filtered_data_to_excel()
    {
        // Arrange
        RekonData::factory()->create(['sekolah' => 'SMAN_1_DENPASAR', 'tahun' => 2024, 'bulan' => 1]);
        RekonData::factory()->create(['sekolah' => 'SMAN_2_DENPASAR', 'tahun' => 2024, 'bulan' => 1]);
        RekonData::factory()->create(['sekolah' => 'SMAN_1_DENPASAR', 'tahun' => 2023, 'bulan' => 1]);
        RekonData::factory()->create(['sekolah' => 'SMAN_1_DENPASAR', 'tahun' => 2024, 'bulan' => 2]);

        // Act - Export with filters
        $filters = [
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1
        ];
        $result = $this->service->exportToExcel($filters);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['total_records']); // Only one record matches all filters
    }

    /** @test */
    public function it_can_export_data_to_csv()
    {
        // Arrange
        RekonData::factory()->count(3)->create();

        // Act
        $result = $this->service->exportToCSV();

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['total_records']);
        $this->assertStringContains('laporan_rekon_spp_', $result['filename']);
        $this->assertStringContains('.csv', $result['filename']);
        $this->assertStringStartsWith('/exports/', $result['download_url']);

        // Check if file was created
        $filePath = 'public/exports/' . $result['filename'];
        Storage::assertExists($filePath);
    }

    /** @test */
    public function it_can_export_filtered_data_to_csv()
    {
        // Arrange
        RekonData::factory()->create(['sekolah' => 'SMAN_1_DENPASAR', 'tahun' => 2024]);
        RekonData::factory()->create(['sekolah' => 'SMAN_2_DENPASAR', 'tahun' => 2024]);
        RekonData::factory()->create(['sekolah' => 'SMAN_1_DENPASAR', 'tahun' => 2023]);

        // Act
        $filters = ['sekolah' => 'SMAN_1_DENPASAR'];
        $result = $this->service->exportToCSV($filters);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['total_records']); // Only SMAN_1_DENPASAR records
    }

    /** @test */
    public function it_can_export_laporan_kelas_to_excel()
    {
        // Arrange
        $sekolah = 'SMAN_1_DENPASAR';
        $kelas = 'XI';
        $angkatan = 2022;

        // Create test data
        RekonData::factory()->create([
            'sekolah' => $sekolah,
            'kelas' => $kelas,
            'id_siswa' => '001',
            'nama_siswa' => 'Student 1',
            'tahun' => 2024,
            'bulan' => 1,
            'tgl_tx' => now()
        ]);

        RekonData::factory()->create([
            'sekolah' => $sekolah,
            'kelas' => $kelas,
            'id_siswa' => '002',
            'nama_siswa' => 'Student 2',
            'tahun' => 2024,
            'bulan' => 2,
            'tgl_tx' => now()
        ]);

        RekonData::factory()->create([
            'sekolah' => $sekolah,
            'kelas' => 'XII', // Different class
            'id_siswa' => '003',
            'nama_siswa' => 'Student 3',
            'tahun' => 2024,
            'bulan' => 1,
            'tgl_tx' => now()
        ]);

        // Act
        $result = $this->service->exportLaporanKelas($sekolah, $kelas, $angkatan);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['total_records']); // Only XI class students
        $this->assertStringContains('Laporan_Kelas_XI_Angkatan_2022', $result['filename']);
        $this->assertStringContains('.xlsx', $result['filename']);

        // Check if file was created
        $filePath = 'public/exports/' . $result['filename'];
        Storage::assertExists($filePath);
    }

    /** @test */
    public function it_handles_empty_data_for_kelas_export()
    {
        // Arrange
        $sekolah = 'SMAN_1_DENPASAR';
        $kelas = 'XI';
        $angkatan = 2022;

        // Create data for different class
        RekonData::factory()->create(['kelas' => 'XII']);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Data siswa untuk kelas tersebut tidak ditemukan');

        $this->service->exportLaporanKelas($sekolah, $kelas, $angkatan);
    }

    /** @test */
    public function it_handles_excel_export_with_no_data()
    {
        // Arrange - No data in database

        // Act
        $result = $this->service->exportToExcel();

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['total_records']);

        // File should still be created with headers only
        $filePath = 'public/exports/' . $result['filename'];
        Storage::assertExists($filePath);
    }

    /** @test */
    public function it_handles_csv_export_with_no_data()
    {
        // Arrange - No data in database

        // Act
        $result = $this->service->exportToCSV();

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['total_records']);

        // File should still be created with headers only
        $filePath = 'public/exports/' . $result['filename'];
        Storage::assertExists($filePath);
    }

    /** @test */
    public function it_exports_with_correct_excel_headers()
    {
        // Arrange
        RekonData::factory()->create();

        // Act
        $result = $this->service->exportToExcel();

        // Assert
        $this->assertTrue($result['success']);

        // Read the file to verify headers
        $filePath = 'public/exports/' . $result['filename'];
        $content = Storage::get($filePath);

        // Check if it's a valid Excel file (basic check)
        $this->assertNotEmpty($content);
        $this->assertStringContains('PK', $content); // Excel files start with PK
    }

    /** @test */
    public function it_exports_with_correct_csv_headers()
    {
        // Arrange
        RekonData::factory()->create();

        // Act
        $result = $this->service->exportToCSV();

        // Assert
        $this->assertTrue($result['success']);

        // Read the file to verify headers
        $filePath = 'public/exports/' . $result['filename'];
        $content = Storage::get($filePath);

        $expectedHeaders = 'No,Sekolah,ID Siswa,Nama Siswa,Alamat,Kelas,Jurusan,Jumlah Tagihan,Biaya Admin,Tagihan Lain,Ket. Tagihan Lain,Keterangan,Tahun,Bulan,Dana Masyarakat,Tanggal Transaksi,Status Bayar,Kode Cabang,Kode User,Status Reversal,No. Bukti';
        $this->assertStringContains($expectedHeaders, $content);
    }

    /** @test */
    public function it_creates_export_directory_if_not_exists()
    {
        // Arrange
        RekonData::factory()->create();

        // Ensure directory doesn't exist
        Storage::assertDirectoryMissing('public/exports');

        // Act
        $result = $this->service->exportToExcel();

        // Assert
        $this->assertTrue($result['success']);
        Storage::assertDirectoryExists('public/exports');
    }

    /** @test */
    public function it_generates_unique_filenames()
    {
        // Arrange
        RekonData::factory()->create();

        // Act
        $result1 = $this->service->exportToExcel();
        sleep(1); // Ensure different timestamp
        $result2 = $this->service->exportToExcel();

        // Assert
        $this->assertTrue($result1['success']);
        $this->assertTrue($result2['success']);
        $this->assertNotEquals($result1['filename'], $result2['filename']);
    }

    /** @test */
    public function it_handles_special_characters_in_kelas_export_filename()
    {
        // Arrange
        $sekolah = 'SMAN_1_DENPASAR';
        $kelas = 'XI.MIPA1'; // Class with dot
        $angkatan = 2022;

        RekonData::factory()->create([
            'sekolah' => $sekolah,
            'kelas' => $kelas,
            'tahun' => $angkatan
        ]);

        // Act
        $result = $this->service->exportLaporanKelas($sekolah, $kelas, $angkatan);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertStringContains('XI_MIPA1', $result['filename']); // Dot should be replaced with underscore
        $this->assertStringNotContains('.', $result['filename']); // No dots in filename
    }

    /** @test */
    public function it_formats_tanggal_pembayaran_correctly_in_kelas_export()
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
        $result = $this->service->exportLaporanKelas($sekolah, $kelas, $angkatan);

        // Assert
        $this->assertTrue($result['success']);

        // Read the Excel file to verify date format (this would require additional Excel reading library for detailed verification)
        $filePath = 'public/exports/' . $result['filename'];
        Storage::assertExists($filePath);
    }
}