<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\RekonImportService;
use App\Models\RekonData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RekonImportServiceTest extends TestCase
{
    private RekonImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RekonImportService();
        Storage::fake('local');
    }

    /** @test */
    public function it_can_import_valid_excel_file()
    {
        // Arrange
        $file = $this->createTestRekonSpreadsheet([
            [
                'SEKOLAH' => 'SMAN_1_DENPASAR',
                'ID_SISWA' => '12345',
                'NAMA_SISWA' => 'Test Student',
                'ALAMAT' => 'Test Address',
                'KELAS' => 'XI',
                'JURUSAN' => 'MIPA1',
                'JUM_TAGIHAN' => '350000',
                'BIAYA_ADM' => '0',
                'TAGIHAN_LAIN' => '0',
                'KET_TAGIHAN_LAIN' => '',
                'KETERANGAN' => '',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA_MASYARAKAT' => '350000',
                'TGL_TX' => '01/01/2024 10:00',
                'STS_BAYAR' => '1',
                'KD_CAB' => 'EB',
                'KD_USER' => 'system',
                'STS_REVERSAL' => '0',
                'NO_BUKTI' => '1234567890'
            ]
        ]);

        // Act
        $result = $this->service->importFromFile($file);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['imported']);
        $this->assertEquals(0, $result['error_count']);

        $this->assertDatabaseHas('rekon_data', [
            'sekolah' => 'SMAN_1_DENPASAR',
            'id_siswa' => '12345',
            'nama_siswa' => 'Test Student',
            'tahun' => 2024,
            'bulan' => 1,
            'no_bukti' => '1234567890'
        ]);
    }

    /** @test */
    public function it_skips_rows_with_missing_required_fields()
    {
        // Arrange
        $file = $this->createTestRekonSpreadsheet([
            [
                'SEKOLAH' => '', // Missing required field
                'ID_SISWA' => '12345',
                'NAMA_SISWA' => 'Test Student',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA_MASYARAKAT' => '350000'
            ]
        ]);

        // Act
        $result = $this->service->importFromFile($file);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['imported']);
        $this->assertDatabaseMissing('rekon_data', [
            'id_siswa' => '12345'
        ]);
    }

    /** @test */
    public function it_handles_multiple_valid_rows()
    {
        // Arrange
        $file = $this->createTestRekonSpreadsheet([
            [
                'SEKOLAH' => 'SMAN_1_DENPASAR',
                'ID_SISWA' => '12345',
                'NAMA_SISWA' => 'Test Student 1',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA_MASYARAKAT' => '350000'
            ],
            [
                'SEKOLAH' => 'SMAN_2_DENPASAR',
                'ID_SISWA' => '12346',
                'NAMA_SISWA' => 'Test Student 2',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA_MASYARAKAT' => '400000'
            ],
            [
                'SEKOLAH' => 'SMAN_1_DENPASAR',
                'ID_SISWA' => '12347',
                'NAMA_SISWA' => 'Test Student 3',
                'TAHUN' => '2024',
                'BULAN' => '2',
                'DANA_MASYARAKAT' => '350000'
            ]
        ]);

        // Act
        $result = $this->service->importFromFile($file);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['imported']);
        $this->assertEquals(0, $result['error_count']);

        $this->assertDatabaseCount('rekon_data', 3);
    }

    /** @test */
    public function it_parses_date_fields_with_various_formats()
    {
        // Arrange
        $file = $this->createTestRekonSpreadsheet([
            [
                'SEKOLAH' => 'SMAN_1_DENPASAR',
                'ID_SISWA' => '12345',
                'NAMA_SISWA' => 'Test Student',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA_MASYARAKAT' => '350000',
                'TGL_TX' => '01/01/2024 10:30' // d/m/Y H:i format
            ],
            [
                'SEKOLAH' => 'SMAN_1_DENPASAR',
                'ID_SISWA' => '12346',
                'NAMA_SISWA' => 'Test Student 2',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA_MASYARAKAT' => '350000',
                'TGL_TX' => '2024-01-02' // Y-m-d format
            ]
        ]);

        // Act
        $result = $this->service->importFromFile($file);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['imported']);

        $record1 = RekonData::where('id_siswa', '12345')->first();
        $record2 = RekonData::where('id_siswa', '12346')->first();

        $this->assertNotNull($record1->tgl_tx);
        $this->assertNotNull($record2->tgl_tx);
    }

    /** @test */
    public function it_handles_numeric_fields_correctly()
    {
        // Arrange
        $file = $this->createTestRekonSpreadsheet([
            [
                'SEKOLAH' => 'SMAN_1_DENPASAR',
                'ID_SISWA' => '12345',
                'NAMA_SISWA' => 'Test Student',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA_MASYARAKAT' => '350000',
                'JUM_TAGIHAN' => '350,000', // With comma
                'BIAYA_ADM' => '2.500', // With dot
                'TAGIHAN_LAIN' => '5000', // Plain number
                'STS_BAYAR' => '1',
                'STS_REVERSAL' => '0'
            ]
        ]);

        // Act
        $result = $this->service->importFromFile($file);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['imported']);

        $record = RekonData::first();
        $this->assertEquals(350000, $record->jum_tagihan);
        $this->assertEquals(2500, $record->biaya_adm);
        $this->assertEquals(5000, $record->tagihan_lain);
        $this->assertEquals(1, $record->sts_bayar);
        $this->assertEquals(0, $record->sts_reversal);
    }

    /** @test */
    public function it_handles_empty_optional_fields()
    {
        // Arrange
        $file = $this->createTestRekonSpreadsheet([
            [
                'SEKOLAH' => 'SMAN_1_DENPASAR',
                'ID_SISWA' => '12345',
                'NAMA_SISWA' => 'Test Student',
                'ALAMAT' => '', // Empty optional field
                'KELAS' => '', // Empty optional field
                'JURUSAN' => '', // Empty optional field
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA_MASYARAKAT' => '350000'
            ]
        ]);

        // Act
        $result = $this->service->importFromFile($file);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['imported']);

        $record = RekonData::first();
        $this->assertEquals('', $record->alamat);
        $this->assertEquals('', $record->kelas);
        $this->assertEquals('', $record->jurusan);
    }

    /** @test */
    public function it_throws_exception_for_invalid_file_format()
    {
        // Arrange
        $file = UploadedFile::fake()->create('invalid_file.txt', 1000);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Error importing data');

        $this->service->importFromFile($file);
    }

    /** @test */
    public function it_handles_mixed_valid_and_invalid_rows()
    {
        // Arrange
        $file = $this->createTestRekonSpreadsheet([
            [
                'SEKOLAH' => 'SMAN_1_DENPASAR',
                'ID_SISWA' => '12345',
                'NAMA_SISWA' => 'Test Student 1',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA_MASYARAKAT' => '350000'
            ],
            [
                'SEKOLAH' => '', // Invalid row - missing required field
                'ID_SISWA' => '12346',
                'NAMA_SISWA' => 'Test Student 2',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA_MASYARAKAT' => '350000'
            ],
            [
                'SEKOLAH' => 'SMAN_2_DENPASAR',
                'ID_SISWA' => '12347',
                'NAMA_SISWA' => 'Test Student 3',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA_MASYARAKAT' => '350000'
            ]
        ]);

        // Act
        $result = $this->service->importFromFile($file);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['imported']); // Only valid rows imported
        $this->assertEquals(1, $result['error_count']); // One error for invalid row
    }

    /** @test */
    public function it_handles_whitespace_in_fields()
    {
        // Arrange
        $file = $this->createTestRekonSpreadsheet([
            [
                'SEKOLAH' => ' SMAN_1_DENPASAR ', // With whitespace
                'ID_SISWA' => ' 12345 ', // With whitespace
                'NAMA_SISWA' => ' Test Student ', // With whitespace
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA_MASYARAKAT' => ' 350000 ' // With whitespace
            ]
        ]);

        // Act
        $result = $this->service->importFromFile($file);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['imported']);

        $record = RekonData::first();
        $this->assertEquals('SMAN_1_DENPASAR', $record->sekolah);
        $this->assertEquals('12345', $record->id_siswa);
        $this->assertEquals('Test Student', $record->nama_siswa);
        $this->assertEquals('350000', $record->dana_masyarakat);
    }

    /** @test */
    public function it_handles_default_values_for_missing_fields()
    {
        // Arrange
        $file = $this->createTestRekonSpreadsheet([
            [
                'SEKOLAH' => 'SMAN_1_DENPASAR',
                'ID_SISWA' => '12345',
                'NAMA_SISWA' => 'Test Student',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA_MASYARAKAT' => '350000'
                // Other fields are missing, should use defaults
            ]
        ]);

        // Act
        $result = $this->service->importFromFile($file);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['imported']);

        $record = RekonData::first();
        $this->assertEquals('', $record->alamat);
        $this->assertEquals('', $record->kelas);
        $this->assertEquals('', $record->jurusan);
        $this->assertEquals(0, $record->jum_tagihan);
        $this->assertEquals(0, $record->biaya_adm);
        $this->assertEquals(0, $record->tagihan_lain);
        $this->assertEquals(1, $record->sts_bayar); // Default value
        $this->assertEquals('system', $record->kd_user); // Default value
        $this->assertEquals(0, $record->sts_reversal); // Default value
    }

    /**
     * Helper method to create test spreadsheet file
     */
    private function createTestRekonSpreadsheet(array $data): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        if (!empty($data)) {
            $headers = array_keys($data[0]);
            $sheet->fromArray([$headers]);

            $row = 2;
            foreach ($data as $rowData) {
                if (!empty($rowData)) {
                    $sheet->fromArray([$rowData], null, 'A' . $row++);
                }
            }
        }

        $filename = 'test_rekon_import_' . time() . '.xlsx';
        $filepath = storage_path('app/testing/' . $filename);

        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);

        return new UploadedFile($filepath, $filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}