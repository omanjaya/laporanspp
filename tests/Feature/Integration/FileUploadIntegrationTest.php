<?php

namespace Tests\Feature\Integration;

use Tests\TestCase;
use App\Services\RekonImportService;
use App\Services\BankCsvImportService;
use App\Models\RekonData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FileUploadIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
    }

    /** @test */
    public function it_can_complete_full_legacy_import_workflow()
    {
        // Arrange
        $testData = $this->createTestExcelFile([
            [
                'SEKOLAH' => 'SMAN_1_DENPASAR',
                'ID_SISWA' => '001',
                'NAMA_SISWA' => 'Test Student 1',
                'ALAMAT' => 'Address 1',
                'KELAS' => 'XI',
                'JURUSAN' => 'MIPA1',
                'JUM_TAGIHAN' => '350000',
                'BIAYA_ADM' => '0',
                'TAGIHAN_LAIN' => '0',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA_MASYARAKAT' => '350000',
                'TGL_TX' => '01/01/2024 10:00',
                'STS_BAYAR' => '1',
                'NO_BUKTI' => '1234567890'
            ],
            [
                'SEKOLAH' => 'SMAN_1_DENPASAR',
                'ID_SISWA' => '002',
                'NAMA_SISWA' => 'Test Student 2',
                'ALAMAT' => 'Address 2',
                'KELAS' => 'XI',
                'JURUSAN' => 'MIPA2',
                'JUM_TAGIHAN' => '350000',
                'BIAYA_ADM' => '0',
                'TAGIHAN_LAIN' => '0',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA_MASYARAKAT' => '350000',
                'TGL_TX' => '02/01/2024 11:00',
                'STS_BAYAR' => '1',
                'NO_BUKTI' => '1234567891'
            ]
        ]);

        // Act
        $response = $this->postJson('/api/rekon/import', [
            'file' => $testData
        ]);

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'imported' => 2,
                    'total_rows' => 2,
                    'error_count' => 0
                ]);

        // Verify data was imported correctly
        $this->assertDatabaseCount('rekon_data', 2);
        $this->assertDatabaseHas('rekon_data', [
            'sekolah' => 'SMAN_1_DENPASAR',
            'id_siswa' => '001',
            'nama_siswa' => 'Test Student 1'
        ]);
        $this->assertDatabaseHas('rekon_data', [
            'sekolah' => 'SMAN_1_DENPASAR',
            'id_siswa' => '002',
            'nama_siswa' => 'Test Student 2'
        ]);
    }

    /** @test */
    public function it_can_complete_full_bank_csv_import_workflow()
    {
        // Arrange
        $testData = $this->createTestBankCsvFile([
            [
                'INSTANSI' => 'SMAN_1_DENPASAR',
                'NO. TAGIHAN' => '001',
                'NAMA' => 'Test Student 1',
                'TAGIHAN' => '350000',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA MASYARAKAT' => '350000',
                'TANGGAL TRANSAKSI' => '01/01/2024 10:00',
                'STATUS BAYAR' => 'Terbayar',
                'NO. BUKTI' => '1234567890'
            ],
            [
                'INSTANSI' => 'SMAN_1_DENPASAR',
                'NO. TAGIHAN' => '002',
                'NAMA' => 'Test Student 2',
                'TAGIHAN' => '350000',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA MASYARAKAT' => '350000',
                'TANGGAL TRANSAKSI' => '02/01/2024 11:00',
                'STATUS BAYAR' => 'Terbayar',
                'NO. BUKTI' => '1234567891'
            ],
            [
                'INSTANSI' => 'SMAN_1_DENPASAR',
                'NO. TAGIHAN' => '003',
                'NAMA' => 'Test Student 3',
                'TAGIHAN' => '350000',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA MASYARAKAT' => '350000',
                'TANGGAL TRANSAKSI' => '03/01/2024 12:00',
                'STATUS BAYAR' => 'Belum Bayar', // Should be skipped
                'NO. BUKTI' => '1234567892'
            ]
        ]);

        // Act
        $response = $this->postJson('/api/rekon/import-bank-csv', [
            'file' => $testData
        ]);

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'imported' => 2, // Only paid records
                    'duplicates' => 0,
                    'total_rows' => 3,
                    'error_count' => 0
                ]);

        // Verify only paid records were imported
        $this->assertDatabaseCount('rekon_data', 2);
        $this->assertDatabaseHas('rekon_data', [
            'sekolah' => 'SMAN_1_DENPASAR',
            'id_siswa' => '001',
            'sts_bayar' => 1
        ]);
        $this->assertDatabaseHas('rekon_data', [
            'sekolah' => 'SMAN_1_DENPASAR',
            'id_siswa' => '002',
            'sts_bayar' => 1
        ]);
        $this->assertDatabaseMissing('rekon_data', [
            'sekolah' => 'SMAN_1_DENPASAR',
            'id_siswa' => '003'
        ]);
    }

    /** @test */
    public function it_handles_duplicate_prevention_during_import()
    {
        // Arrange - Create existing record
        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'id_siswa' => '001',
            'tahun' => 2024,
            'bulan' => 1,
            'no_bukti' => '1234567890'
        ]);

        // Create test data with duplicate no_bukti
        $testData = $this->createTestBankCsvFile([
            [
                'INSTANSI' => 'SMAN_1_DENPASAR',
                'NO. TAGIHAN' => '001',
                'NAMA' => 'Test Student 1',
                'TAGIHAN' => '350000',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA MASYARAKAT' => '350000',
                'TANGGAL TRANSAKSI' => '01/01/2024 10:00',
                'STATUS BAYAR' => 'Terbayar',
                'NO. BUKTI' => '1234567890' // Duplicate
            ],
            [
                'INSTANSI' => 'SMAN_1_DENPASAR',
                'NO. TAGIHAN' => '002',
                'NAMA' => 'Test Student 2',
                'TAGIHAN' => '350000',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA MASYARAKAT' => '350000',
                'TANGGAL TRANSAKSI' => '02/01/2024 11:00',
                'STATUS BAYAR' => 'Terbayar',
                'NO. BUKTI' => '1234567891' // New
            ]
        ]);

        // Act
        $response = $this->postJson('/api/rekon/import-bank-csv', [
            'file' => $testData
        ]);

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'imported' => 1, // Only new record
                    'duplicates' => 1, // Duplicate was skipped
                    'total_rows' => 2
                ]);

        // Verify only one new record was added
        $this->assertDatabaseCount('rekon_data', 2);
        $this->assertDatabaseHas('rekon_data', ['no_bukti' => '1234567890']);
        $this->assertDatabaseHas('rekon_data', ['no_bukti' => '1234567891']);
    }

    /** @test */
    public function it_handles_large_file_upload_workflow()
    {
        // Arrange - Create large file (over 5MB threshold)
        $largeData = [];
        for ($i = 1; $i <= 1000; $i++) {
            $largeData[] = [
                'SEKOLAH' => 'SMAN_1_DENPASAR',
                'ID_SISWA' => str_pad($i, 5, '0', STR_PAD_LEFT),
                'NAMA_SISWA' => "Test Student {$i}",
                'ALAMAT' => "Address {$i}",
                'KELAS' => 'XI',
                'JURUSAN' => 'MIPA1',
                'JUM_TAGIHAN' => '350000',
                'BIAYA_ADM' => '0',
                'TAGIHAN_LAIN' => '0',
                'TAHUN' => '2024',
                'BULAN' => ($i % 12) + 1,
                'DANA_MASYARAKAT' => '350000',
                'TGL_TX' => "01/01/2024 10:{$i % 60}",
                'STS_BAYAR' => '1',
                'NO_BUKTI' => str_pad($i, 10, '0', STR_PAD_LEFT)
            ];
        }

        $largeFile = $this->createTestExcelFile($largeData);

        // Act
        $response = $this->postJson('/api/rekon/import', [
            'file' => $largeFile
        ]);

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'queued' => true
                ]);

        // Verify job was queued
        Queue::assertPushedOn('rekon-import', \App\Jobs\ProcessRekonImportJob::class);

        // Verify file was stored temporarily
        $this->assertTrue(Storage::disk('local')->exists('temp/imports'));
    }

    /** @test */
    public function it_handles_mixed_valid_invalid_data_in_import()
    {
        // Arrange
        $testData = $this->createTestExcelFile([
            [
                'SEKOLAH' => 'SMAN_1_DENPASAR',
                'ID_SISWA' => '001',
                'NAMA_SISWA' => 'Valid Student',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA_MASYARAKAT' => '350000'
            ],
            [
                'SEKOLAH' => '', // Invalid - missing required field
                'ID_SISWA' => '002',
                'NAMA_SISWA' => 'Invalid Student',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA_MASYARAKAT' => '350000'
            ],
            [
                'SEKOLAH' => 'SMAN_1_DENPASAR',
                'ID_SISWA' => '003',
                'NAMA_SISWA' => 'Another Valid Student',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA_MASYARAKAT' => '350000'
            ],
            [] // Empty row
        ]);

        // Act
        $response = $this->postJson('/api/rekon/import', [
            'file' => $testData
        ]);

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'imported' => 2, // Only valid rows
                    'total_rows' => 4,
                    'error_count' => 2 // Invalid row + empty row
                ]);

        // Verify only valid records were imported
        $this->assertDatabaseCount('rekon_data', 2);
        $this->assertDatabaseHas('rekon_data', ['nama_siswa' => 'Valid Student']);
        $this->assertDatabaseHas('rekon_data', ['nama_siswa' => 'Another Valid Student']);
        $this->assertDatabaseMissing('rekon_data', ['nama_siswa' => 'Invalid Student']);
    }

    /** @test */
    public function it_handles_various_date_formats_in_import()
    {
        // Arrange
        $testData = $this->createTestExcelFile([
            [
                'SEKOLAH' => 'SMAN_1_DENPASAR',
                'ID_SISWA' => '001',
                'NAMA_SISWA' => 'Student 1',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA_MASYARAKAT' => '350000',
                'TGL_TX' => '01/01/2024 10:30' // d/m/Y H:i format
            ],
            [
                'SEKOLAH' => 'SMAN_1_DENPASAR',
                'ID_SISWA' => '002',
                'NAMA_SISWA' => 'Student 2',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA_MASYARAKAT' => '350000',
                'TGL_TX' => '2024-01-02' // Y-m-d format
            ],
            [
                'SEKOLAH' => 'SMAN_1_DENPASAR',
                'ID_SISWA' => '003',
                'NAMA_SISWA' => 'Student 3',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA_MASYARAKAT' => '350000',
                'TGL_TX' => 'invalid-date' // Invalid format
            ]
        ]);

        // Act
        $response = $this->postJson('/api/rekon/import', [
            'file' => $testData
        ]);

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'imported' => 3 // All should import with default date for invalid one
                ]);

        // Verify all records were imported with proper date handling
        $this->assertDatabaseCount('rekon_data', 3);

        $records = RekonData::all();
        foreach ($records as $record) {
            $this->assertNotNull($record->tgl_tx);
            $this->assertInstanceOf(\Carbon\Carbon::class, $record->tgl_tx);
        }
    }

    /** @test */
    public function it_handles_payment_matching_after_import()
    {
        // Arrange - Import initial data
        $importData = $this->createTestExcelFile([
            [
                'SEKOLAH' => 'SMAN_1_DENPASAR',
                'ID_SISWA' => '001',
                'NAMA_SISWA' => 'Test Student',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA_MASYARAKAT' => '350000',
                'TGL_TX' => '01/01/2024 10:00',
                'STS_BAYAR' => '1'
            ]
        ]);

        $importResponse = $this->postJson('/api/rekon/import', [
            'file' => $importData
        ]);
        $importResponse->assertStatus(200);

        // Act - Search for the imported data
        $searchResponse = $this->getJson('/api/rekon/search?sekolah=SMAN_1_DENPASAR&tahun=2024&bulan=1');

        // Assert
        $searchResponse->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'summary' => [
                        'total_records' => 1,
                        'unique_students' => 1
                    ]
                ]);

        // Act - Get specific value
        $valueResponse = $this->getJson('/api/rekon/get-value?sekolah=SMAN_1_DENPASAR&tahun=2024&bulan=1&field=dana_masyarakat');

        // Assert
        $valueResponse->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'value' => 350000.0
                ]);
    }

    /** @test */
    public function it_handles_export_after_import_workflow()
    {
        // Arrange - Import data
        $testData = $this->createTestExcelFile([
            [
                'SEKOLAH' => 'SMAN_1_DENPASAR',
                'ID_SISWA' => '001',
                'NAMA_SISWA' => 'Test Student',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA_MASYARAKAT' => '350000'
            ]
        ]);

        $this->postJson('/api/rekon/import', [
            'file' => $testData
        ]);

        // Act - Export the data
        $exportResponse = $this->postJson('/api/rekon/export/excel', [
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1
        ]);

        // Assert
        $exportResponse->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'filename',
                    'download_url',
                    'total_records'
                ]);

        $this->assertEquals(1, $exportResponse->json('total_records'));
    }

    /** @test */
    public function it_handles_kelas_report_workflow_after_import()
    {
        // Arrange - Import class data
        $classData = [
            [
                'SEKOLAH' => 'SMAN_1_DENPASAR',
                'ID_SISWA' => '001',
                'NAMA_SISWA' => 'Student 1',
                'KELAS' => 'XI',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA_MASYARAKAT' => '350000',
                'TGL_TX' => '01/01/2024 10:00',
                'STS_BAYAR' => '1'
            ],
            [
                'SEKOLAH' => 'SMAN_1_DENPASAR',
                'ID_SISWA' => '002',
                'NAMA_SISWA' => 'Student 2',
                'KELAS' => 'XI',
                'TAHUN' => '2024',
                'BULAN' => '2',
                'DANA_MASYARAKAT' => '350000',
                'TGL_TX' => '01/02/2024 10:00',
                'STS_BAYAR' => '1'
            ]
        ];

        $importFile = $this->createTestExcelFile($classData);
        $this->postJson('/api/rekon/import', [
            'file' => $importFile
        ]);

        // Act - Get class report
        $reportResponse = $this->getJson('/api/rekon/laporan/kelas?sekolah=SMAN_1_DENPASAR&kelas=XI&angkatan=2024');

        // Assert
        $reportResponse->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'kelas' => 'XI',
                        'angkatan' => 2024,
                        'sekolah' => 'SMAN_1_DENPASAR',
                        'total_siswa' => 2
                    ]
                ]);

        $siswaData = $reportResponse->json('data.siswa');
        $this->assertCount(2, $siswaData);

        // Verify payment dates are included
        $student1 = collect($siswaData)->firstWhere('nis', '001');
        $this->assertArrayHasKey('pembayaran', $student1);
        $this->assertNotEmpty($student1['pembayaran']);
    }

    /** @test */
    public function it_handles_file_cleanup_after_failed_import()
    {
        // Arrange - Create invalid file
        $invalidFile = UploadedFile::fake()->create('invalid.txt', 1000);

        // Act
        $response = $this->postJson('/api/rekon/import', [
            'file' => $invalidFile
        ]);

        // Assert
        $response->assertStatus(422);

        // Verify no temporary files were left behind
        $tempFiles = Storage::disk('local')->allFiles('temp');
        $this->assertEmpty($tempFiles);
    }

    /** @test */
    public function it_handles_concurrent_imports()
    {
        // Arrange - Create multiple test files
        $file1 = $this->createTestExcelFile([[
            'SEKOLAH' => 'SMAN_1_DENPASAR',
            'ID_SISWA' => '001',
            'NAMA_SISWA' => 'Student 1',
            'TAHUN' => '2024',
            'BULAN' => '1',
            'DANA_MASYARAKAT' => '350000'
        ]]);

        $file2 = $this->createTestExcelFile([[
            'SEKOLAH' => 'SMAN_2_DENPASAR',
            'ID_SISWA' => '002',
            'NAMA_SISWA' => 'Student 2',
            'TAHUN' => '2024',
            'BULAN' => '1',
            'DANA_MASYARAKAT' => '350000'
        ]]);

        // Act - Import both files concurrently
        $response1 = $this->postJson('/api/rekon/import', ['file' => $file1]);
        $response2 = $this->postJson('/api/rekon/import', ['file' => $file2]);

        // Assert
        $response1->assertStatus(200);
        $response2->assertStatus(200);

        $this->assertDatabaseCount('rekon_data', 2);
        $this->assertDatabaseHas('rekon_data', ['sekolah' => 'SMAN_1_DENPASAR']);
        $this->assertDatabaseHas('rekon_data', ['sekolah' => 'SMAN_2_DENPASAR']);
    }

    /** @test */
    public function it_handles_import_with_special_characters()
    {
        // Arrange
        $testData = $this->createTestExcelFile([
            [
                'SEKOLAH' => 'SMAN_1_DENPASAR',
                'ID_SISWA' => '001',
                'NAMA_SISWA' => 'I Made Bagus Kadek Adi Wibawa',
                'ALAMAT' => 'Jl. Gajah Mada No. 123, Denpasar, Bali',
                'KETERANGAN' => 'Pembayaran SPP bulan Januari 2024',
                'TAHUN' => '2024',
                'BULAN' => '1',
                'DANA_MASYARAKAT' => '350000'
            ]
        ]);

        // Act
        $response = $this->postJson('/api/rekon/import', [
            'file' => $testData
        ]);

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'imported' => 1
                ]);

        // Verify data was imported correctly with special characters
        $this->assertDatabaseHas('rekon_data', [
            'nama_siswa' => 'I Made Bagus Kadek Adi Wibawa',
            'alamat' => 'Jl. Gajah Mada No. 123, Denpasar, Bali',
            'keterangan' => 'Pembayaran SPP bulan Januari 2024'
        ]);
    }

    /**
     * Helper method to create test Excel file
     */
    private function createTestExcelFile(array $data): UploadedFile
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

        $filename = 'test_import_' . time() . '.xlsx';
        $filepath = storage_path('app/testing/' . $filename);

        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);

        return new UploadedFile($filepath, $filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /**
     * Helper method to create test Bank CSV file
     */
    private function createTestBankCsvFile(array $data): UploadedFile
    {
        $filename = 'test_bank_' . time() . '.xlsx';
        $filepath = storage_path('app/testing/' . $filename);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        if (!empty($data)) {
            $headers = array_keys($data[0]);
            $sheet->fromArray([$headers]);

            $row = 2;
            foreach ($data as $rowData) {
                $sheet->fromArray([$rowData], null, 'A' . $row++);
            }
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);

        return new UploadedFile($filepath, $filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}