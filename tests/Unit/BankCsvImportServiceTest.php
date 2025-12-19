<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\BankCsvImportService;
use App\Models\RekonData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BankCsvImportServiceTest extends TestCase
{
    private BankCsvImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BankCsvImportService();
        Storage::fake('local');
    }

    /** @test */
    public function it_can_import_valid_bank_csv_file()
    {
        // Arrange
        $file = $this->createTestBankSpreadsheet([
            [
                'INSTANSI' => 'SMAN_1_DENPASAR',
                'NO. TAGIHAN' => '12345',
                'NAMA' => 'Test Student',
                'TAGIHAN' => '350000',
                'BIAYA ADM.' => '0',
                'TAGIHAN LAIN' => '0',
                'KET. TAGIHAN LAIN' => '',
                'ALAMAT' => 'Test Address',
                'KELAS' => 'XI',
                'JURUSAN' => 'MIPA1',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA MASYARAKAT' => '350000',
                'KETERANGAN' => '',
                'TANGGAL TRANSAKSI' => '01/01/2024 10:00',
                'STATUS BAYAR' => 'Terbayar',
                'KODE CABANG' => 'EB',
                'USER' => 'igate_pac',
                'STATUS REVERSAL' => '-',
                'NO. BUKTI' => '1234567890'
            ]
        ]);

        // Act
        $result = $this->service->importFromBankCsv($file);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['imported']);
        $this->assertEquals(0, $result['duplicates']);
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
    public function it_skip_rows_with_unpaid_status()
    {
        // Arrange
        $file = $this->createTestBankSpreadsheet([
            [
                'INSTANSI' => 'SMAN_1_DENPASAR',
                'NO. TAGIHAN' => '12345',
                'NAMA' => 'Test Student',
                'TAGIHAN' => '350000',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA MASYARAKAT' => '350000',
                'TANGGAL TRANSAKSI' => '01/01/2024 10:00',
                'STATUS BAYAR' => 'Belum Bayar', // Unpaid status
                'NO. BUKTI' => '1234567890'
            ]
        ]);

        // Act
        $result = $this->service->importFromBankCsv($file);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['imported']); // Should not import unpaid rows
        $this->assertDatabaseMissing('rekon_data', [
            'no_bukti' => '1234567890'
        ]);
    }

    /** @test */
    public function it_detects_and_counts_duplicate_records()
    {
        // Arrange - Create existing record
        RekonData::factory()->create([
            'no_bukti' => '1234567890',
            'sekolah' => 'SMAN_1_DENPASAR'
        ]);

        $file = $this->createTestBankSpreadsheet([
            [
                'INSTANSI' => 'SMAN_1_DENPASAR',
                'NO. TAGIHAN' => '12345',
                'NAMA' => 'Test Student',
                'TAGIHAN' => '350000',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA MASYARAKAT' => '350000',
                'TANGGAL TRANSAKSI' => '01/01/2024 10:00',
                'STATUS BAYAR' => 'Terbayar',
                'NO. BUKTI' => '1234567890' // Duplicate no_bukti
            ]
        ]);

        // Act
        $result = $this->service->importFromBankCsv($file);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['imported']);
        $this->assertEquals(1, $result['duplicates']);
    }

    /** @test */
    public function it_handles_missing_required_fields()
    {
        // Arrange
        $file = $this->createTestBankSpreadsheet([
            [
                'INSTANSI' => '', // Missing required field
                'NO. TAGIHAN' => '12345',
                'NAMA' => 'Test Student',
                'TAGIHAN' => '350000',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA MASYARAKAT' => '350000',
                'TANGGAL TRANSAKSI' => '01/01/2024 10:00',
                'STATUS BAYAR' => 'Terbayar',
                'NO. BUKTI' => '1234567890'
            ]
        ]);

        // Act
        $result = $this->service->importFromBankCsv($file);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['imported']); // Should not import rows with missing required fields
        $this->assertDatabaseMissing('rekon_data', [
            'no_bukti' => '1234567890'
        ]);
    }

    /** @test */
    public function it_handles_invalid_date_formats()
    {
        // Arrange
        $file = $this->createTestBankSpreadsheet([
            [
                'INSTANSI' => 'SMAN_1_DENPASAR',
                'NO. TAGIHAN' => '12345',
                'NAMA' => 'Test Student',
                'TAGIHAN' => '350000',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA MASYARAKAT' => '350000',
                'TANGGAL TRANSAKSI' => 'invalid-date-format',
                'STATUS BAYAR' => 'Terbayar',
                'NO. BUKTI' => '1234567890'
            ]
        ]);

        // Act
        $result = $this->service->importFromBankCsv($file);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['imported']); // Should still import, date will default to now()

        $record = RekonData::where('no_bukti', '1234567890')->first();
        $this->assertNotNull($record->tgl_tx);
    }

    /** @test */
    public function it_parses_number_fields_correctly()
    {
        // Arrange
        $file = $this->createTestBankSpreadsheet([
            [
                'INSTANSI' => 'SMAN_1_DENPASAR',
                'NO. TAGIHAN' => '12345',
                'NAMA' => 'Test Student',
                'TAGIHAN' => '350,000', // Number with comma
                'BIAYA ADM.' => '2,500', // Number with comma
                'TAGIHAN LAIN' => '5000', // Plain number
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA MASYARAKAT' => '350000',
                'TANGGAL TRANSAKSI' => '01/01/2024 10:00',
                'STATUS BAYAR' => 'Terbayar',
                'NO. BUKTI' => '1234567890'
            ]
        ]);

        // Act
        $result = $this->service->importFromBankCsv($file);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['imported']);

        $record = RekonData::where('no_bukti', '1234567890')->first();
        $this->assertEquals(350000, $record->jum_tagihan);
        $this->assertEquals(2500, $record->biaya_adm);
        $this->assertEquals(5000, $record->tagihan_lain);
    }

    /** @test */
    public function it_throws_exception_for_invalid_file_format()
    {
        // Arrange
        $file = UploadedFile::fake()->create('invalid_file.txt', 1000);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Error importing Bank CSV');

        $this->service->importFromBankCsv($file);
    }

    /** @test */
    public function it_handles_file_with_empty_rows()
    {
        // Arrange
        $file = $this->createTestBankSpreadsheet([
            [
                'INSTANSI' => 'SMAN_1_DENPASAR',
                'NO. TAGIHAN' => '12345',
                'NAMA' => 'Test Student 1',
                'TAGIHAN' => '350000',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA MASYARAKAT' => '350000',
                'TANGGAL TRANSAKSI' => '01/01/2024 10:00',
                'STATUS BAYAR' => 'Terbayar',
                'NO. BUKTI' => '1234567890'
            ],
            // Empty row
            [],
            [
                'INSTANSI' => 'SMAN_2_DENPASAR',
                'NO. TAGIHAN' => '12346',
                'NAMA' => 'Test Student 2',
                'TAGIHAN' => '350000',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA MASYARAKAT' => '350000',
                'TANGGAL TRANSAKSI' => '02/01/2024 10:00',
                'STATUS BAYAR' => 'Terbayar',
                'NO. BUKTI' => '1234567891'
            ]
        ]);

        // Act
        $result = $this->service->importFromBankCsv($file);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['imported']); // Should import 2 valid rows
        $this->assertEquals(1, $result['error_count']); // Should report error for empty row
    }

    /** @test */
    public function it_handles_various_column_name_formats()
    {
        // Arrange - Test different column name formats
        $file = $this->createTestBankSpreadsheet([
            [
                'Instansi' => 'SMAN_1_DENPASAR', // lowercase
                'No. Tagihan' => '12345', // with space and dot
                'Nama' => 'Test Student',
                'TAGIHAN' => '350000', // uppercase
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA MASYARAKAT' => '350000',
                'Tanggal Transaksi' => '01/01/2024 10:00', // with space
                'Status Bayar' => 'Terbayar', // with space
                'No. Bukti' => '1234567890'
            ]
        ]);

        // Act
        $result = $this->service->importFromBankCsv($file);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['imported']);
    }

    /**
     * Helper method to create test spreadsheet file
     */
    private function createTestBankSpreadsheet(array $data): UploadedFile
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

        $filename = 'test_bank_import_' . time() . '.xlsx';
        $filepath = storage_path('app/testing/' . $filename);

        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);

        return new UploadedFile($filepath, $filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}