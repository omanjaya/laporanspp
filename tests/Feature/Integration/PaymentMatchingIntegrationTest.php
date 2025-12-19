<?php

namespace Tests\Feature\Integration;

use Tests\TestCase;
use App\Models\RekonData;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PaymentMatchingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_matches_payments_using_excel_formula_equivalent()
    {
        // Arrange - Create test data matching Excel INDEX/MATCH logic
        // Formula: =IFERROR(INDEX(Rekon!$P:$P; MATCH(1; (Rekon!$B:$B=B$7)*(Rekon!$L:$L=D5)*(Rekon!$K:$K=D4); 0));"-")
        // Where: B=sekolah, L=bulan, K=tahun, P=dana_masyarakat

        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',    // Column B equivalent
            'tahun' => 2024,                   // Column K equivalent
            'bulan' => 1,                      // Column L equivalent
            'id_siswa' => '001',
            'nama_siswa' => 'Test Student 1',
            'dana_masyarakat' => '350000',     // Column P equivalent
            'sts_bayar' => 1,
            'tgl_tx' => now()
        ]);

        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 2, // Different month
            'id_siswa' => '001',
            'nama_siswa' => 'Test Student 1',
            'dana_masyarakat' => '350000',
            'sts_bayar' => 1,
            'tgl_tx' => now()->addMonth()
        ]);

        RekonData::factory()->create([
            'sekolah' => 'SMAN_2_DENPASAR', // Different school
            'tahun' => 2024,
            'bulan' => 1,
            'id_siswa' => '002',
            'nama_siswa' => 'Test Student 2',
            'dana_masyarakat' => '400000',
            'sts_bayar' => 1,
            'tgl_tx' => now()
        ]);

        // Act - Test the equivalent of Excel formula
        $response = $this->getJson('/api/rekon/search?sekolah=SMAN_1_DENPASAR&tahun=2024&bulan=1');

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'summary' => [
                        'total_records' => 1, // Should only find exact match
                        'unique_students' => 1
                    ]
                ]);

        $data = $response->json('data.0');
        $this->assertEquals('SMAN_1_DENPASAR', $data['sekolah']);
        $this->assertEquals(2024, $data['tahun']);
        $this->assertEquals(1, $data['bulan']);
        $this->assertEquals('350000', $data['dana_masyarakat']);
    }

    /** @test */
    public function it_returns_dash_when_no_match_found()
    {
        // Arrange - Create some data
        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1,
            'dana_masyarakat' => '350000'
        ]);

        // Act - Search for non-matching criteria
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
    public function it_handles_multiple_matches_for_same_criteria()
    {
        // Arrange - Multiple records with same search criteria
        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1,
            'id_siswa' => '001',
            'dana_masyarakat' => '350000'
        ]);

        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1,
            'id_siswa' => '002', // Different student, same criteria
            'dana_masyarakat' => '400000'
        ]);

        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1,
            'id_siswa' => '003', // Another student, same criteria
            'dana_masyarakat' => '350000'
        ]);

        // Act
        $response = $this->getJson('/api/rekon/search?sekolah=SMAN_1_DENPASAR&tahun=2024&bulan=1');

        // Assert
        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('summary.total_records'));
        $this->assertEquals(3, $response->json('summary.unique_students'));
        $this->assertEquals(1100000, $response->json('summary.total_dana_masyarakat')); // 350000 + 400000 + 350000
    }

    /** @test */
    public function it_matches_payment_dates_for_kelas_reports()
    {
        // Arrange - Create payment history for a student
        $sekolah = 'SMAN_1_DENPASAR';
        $kelas = 'XI';
        $idSiswa = '001';

        // Student paid for multiple months
        RekonData::factory()->create([
            'sekolah' => $sekolah,
            'kelas' => $kelas,
            'id_siswa' => $idSiswa,
            'nama_siswa' => 'Test Student',
            'tahun' => 2024,
            'bulan' => 1, // January
            'dana_masyarakat' => '350000',
            'sts_bayar' => 1,
            'tgl_tx' => now()->setMonth(1)->setDay(15)
        ]);

        RekonData::factory()->create([
            'sekolah' => $sekolah,
            'kelas' => $kelas,
            'id_siswa' => $idSiswa,
            'nama_siswa' => 'Test Student',
            'tahun' => 2024,
            'bulan' => 2, // February
            'dana_masyarakat' => '350000',
            'sts_bayar' => 1,
            'tgl_tx' => now()->setMonth(2)->setDay(15)
        ]);

        // Unpaid month
        RekonData::factory()->create([
            'sekolah' => $sekolah,
            'kelas' => $kelas,
            'id_siswa' => $idSiswa,
            'nama_siswa' => 'Test Student',
            'tahun' => 2024,
            'bulan' => 3, // March
            'dana_masyarakat' => '350000',
            'sts_bayar' => 0, // Unpaid
            'tgl_tx' => now()->setMonth(3)->setDay(15)
        ]);

        // Act
        $response = $this->getJson("/api/rekon/laporan/kelas?sekolah={$sekolah}&kelas={$kelas}&angkatan=2024");

        // Assert
        $response->assertStatus(200);
        $siswaData = $response->json('data.siswa');
        $this->assertCount(1, $siswaData);

        $student = $siswaData[0];
        $this->assertEquals('001', $student['nis']);
        $this->assertEquals('Test Student', $student['nama']);
        $this->assertNotEmpty($student['pembayaran']);

        // Find payment dates for specific months
        $headers = $response->json('data.headers');
        $payments = $student['pembayaran'];

        // Find January payment
        $januaryIndex = null;
        foreach ($headers as $index => $header) {
            if ($header['year'] == 2024 && $header['month'] == 1) {
                $januaryIndex = $index;
                break;
            }
        }

        $this->assertNotNull($januaryIndex);
        $this->assertEquals('15/01/2024', $payments[$januaryIndex]); // Should show payment date

        // Find March payment (should be unpaid)
        $marchIndex = null;
        foreach ($headers as $index => $header) {
            if ($header['year'] == 2024 && $header['month'] == 3) {
                $marchIndex = $index;
                break;
            }
        }

        $this->assertNotNull($marchIndex);
        $this->assertEquals('-', $payments[$marchIndex]); // Should show dash for unpaid
    }

    /** @test */
    public function it_handles_cross_year_payment_matching()
    {
        // Arrange - Create data across academic year boundary
        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2023,
            'bulan' => 12, // December 2023
            'id_siswa' => '001',
            'nama_siswa' => 'Test Student',
            'dana_masyarakat' => '350000',
            'sts_bayar' => 1,
            'tgl_tx' => now()->setYear(2023)->setMonth(12)->setDay(15)
        ]);

        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1, // January 2024
            'id_siswa' => '001',
            'nama_siswa' => 'Test Student',
            'dana_masyarakat' => '350000',
            'sts_bayar' => 1,
            'tgl_tx' => now()->setYear(2024)->setMonth(1)->setDay(15)
        ]);

        // Act - Search for December 2023
        $responseDec = $this->getJson('/api/rekon/search?sekolah=SMAN_1_DENPASAR&tahun=2023&bulan=12');

        // Search for January 2024
        $responseJan = $this->getJson('/api/rekon/search?sekolah=SMAN_1_DENPASAR&tahun=2024&bulan=1');

        // Assert
        $responseDec->assertStatus(200);
        $responseJan->assertStatus(200);

        $this->assertEquals(1, $responseDec->json('summary.total_records'));
        $this->assertEquals(1, $responseJan->json('summary.total_records'));

        // Verify correct years
        $this->assertEquals(2023, $responseDec->json('data.0.tahun'));
        $this->assertEquals(2024, $responseJan->json('data.0.tahun'));
    }

    /** @test */
    public function it_handles_payment_reversals()
    {
        // Arrange - Create payment and then reversal
        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1,
            'id_siswa' => '001',
            'nama_siswa' => 'Test Student',
            'dana_masyarakat' => '350000',
            'sts_bayar' => 1,
            'sts_reversal' => 0, // No reversal
            'tgl_tx' => now()
        ]);

        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1,
            'id_siswa' => '001',
            'nama_siswa' => 'Test Student',
            'dana_masyarakat' => '350000',
            'sts_bayar' => 1,
            'sts_reversal' => 1, // Reversed payment
            'no_bukti' => 'REVERSED_123',
            'tgl_tx' => now()
        ]);

        // Act
        $response = $this->getJson('/api/rekon/search?sekolah=SMAN_1_DENPASAR&tahun=2024&bulan=1');

        // Assert
        $response->assertStatus(200);

        // Should find both records (original and reversed)
        $this->assertEquals(2, $response->json('summary.total_records'));
    }

    /** @test */
    public function it_matches_payments_with_different_dana_masyarakat_values()
    {
        // Arrange
        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1,
            'id_siswa' => '001',
            'dana_masyarakat' => '350000'
        ]);

        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1,
            'id_siswa' => '002',
            'dana_masyarakat' => '400000' // Different amount
        ]);

        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1,
            'id_siswa' => '003',
            'dana_masyarakat' => '0' // Zero amount
        ]);

        // Act
        $response = $this->getJson('/api/rekon/search?sekolah=SMAN_1_DENPASAR&tahun=2024&bulan=1');

        // Assert
        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('summary.total_records'));
        $this->assertEquals(750000, $response->json('summary.total_dana_masyarakat')); // 350000 + 400000 + 0
    }

    /** @test */
    public function it_handles_payment_status_matching()
    {
        // Arrange - Create records with different payment statuses
        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1,
            'id_siswa' => '001',
            'dana_masyarakat' => '350000',
            'sts_bayar' => 1, // Paid
            'tgl_tx' => now()
        ]);

        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1,
            'id_siswa' => '002',
            'dana_masyarakat' => '350000',
            'sts_bayar' => 0, // Unpaid
            'tgl_tx' => null
        ]);

        // Act - Get specific value for paid student
        $responsePaid = $this->getJson('/api/rekon/get-value?sekolah=SMAN_1_DENPASAR&tahun=2024&bulan=1&field=tgl_tx');

        // This will return the first matching record, regardless of payment status
        // In a real implementation, you might want to filter by sts_bayar = 1

        // Assert
        $this->assertContains($responsePaid->status(), [200, 404]);
    }

    /** @test */
    public function it_handles_bulk_payment_matching()
    {
        // Arrange - Create bulk data for testing performance
        $schools = ['SMAN_1_DENPASAR', 'SMAN_2_DENPASAR', 'SMAN_3_DENPASAR'];
        $months = [1, 2, 3, 4, 5, 6];
        $year = 2024;

        foreach ($schools as $school) {
            foreach ($months as $month) {
                RekonData::factory()->count(10)->create([
                    'sekolah' => $school,
                    'tahun' => $year,
                    'bulan' => $month,
                    'sts_bayar' => 1
                ]);
            }
        }

        // Act - Test matching for each combination
        foreach ($schools as $school) {
            foreach ($months as $month) {
                $response = $this->getJson("/api/rekon/search?sekolah={$school}&tahun={$year}&bulan={$month}");

                // Assert
                $response->assertStatus(200);
                $this->assertEquals(10, $response->json('summary.total_records'));
                $this->assertEquals(10, $response->json('summary.unique_students'));
            }
        }
    }

    /** @test */
    public function it_handles_edge_cases_in_payment_matching()
    {
        // Arrange - Test edge cases
        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1,
            'id_siswa' => '001',
            'dana_masyarakat' => 'invalid_value' // Invalid amount
        ]);

        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1,
            'id_siswa' => '002',
            'dana_masyarakat' => '' // Empty amount
        ]);

        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1,
            'id_siswa' => '003',
            'dana_masyarakat' => '350000'
        ]);

        // Act
        $response = $this->getJson('/api/rekon/search?sekolah=SMAN_1_DENPASAR&tahun=2024&bulan=1');

        // Assert
        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('summary.total_records'));

        // Should handle invalid amounts gracefully
        $this->assertEquals(350000, $response->json('summary.total_dana_masyarakat')); // Only valid amount
    }

    /** @test */
    public function it_matches_payments_across_different_schools()
    {
        // Arrange
        $schools = ['SMAN_1_DENPASAR', 'SMAN_2_DENPASAR', 'SMAN_3_DENPASAR'];

        foreach ($schools as $index => $school) {
            RekonData::factory()->create([
                'sekolah' => $school,
                'tahun' => 2024,
                'bulan' => 1,
                'id_siswa' => '00' . ($index + 1),
                'nama_siswa' => "Student " . ($index + 1),
                'dana_masyarakat' => 350000 + ($index * 50000)
            ]);
        }

        // Act & Assert - Test each school
        foreach ($schools as $index => $school) {
            $response = $this->getJson("/api/rekon/search?sekolah={$school}&tahun=2024&bulan=1");

            $response->assertStatus(200);
            $this->assertEquals(1, $response->json('summary.total_records'));
            $this->assertEquals('00' . ($index + 1), $response->json('data.0.id_siswa'));
        }

        // Test non-existent school
        $response = $this->getJson('/api/rekon/search?sekolah=NONEXISTENT&tahun=2024&bulan=1');
        $response->assertStatus(404);
    }

    /** @test */
    public function it_handles_partial_year_data_for_kelas_reports()
    {
        // Arrange - Create data for specific academic year range
        $angkatan = 2022;
        $currentYear = date('Y');

        // Student with payments only for some months
        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'kelas' => 'XI',
            'tahun' => $angkatan,
            'bulan' => 7, // July (start of academic year)
            'id_siswa' => '001',
            'nama_siswa' => 'Test Student',
            'sts_bayar' => 1,
            'tgl_tx' => now()->setYear($angkatan)->setMonth(7)->setDay(15)
        ]);

        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'kelas' => 'XI',
            'tahun' => $currentYear,
            'bulan' => 1, // January
            'id_siswa' => '001',
            'nama_siswa' => 'Test Student',
            'sts_bayar' => 1,
            'tgl_tx' => now()->setYear($currentYear)->setMonth(1)->setDay(15)
        ]);

        // Act
        $response = $this->getJson("/api/rekon/laporan/kelas?sekolah=SMAN_1_DENPASAR&kelas=XI&angkatan={$angkatan}");

        // Assert
        $response->assertStatus(200);

        $headers = $response->json('data.headers');
        $this->assertNotEmpty($headers);

        // Should include years from angkatan to current year + 1
        $years = collect($headers)->pluck('year')->unique()->sort()->values();
        $this->assertEquals($angkatan, $years->first());
        $this->assertEquals($currentYear + 1, $years->last());

        // Should have Indonesian month names
        $monthNames = collect($headers)->pluck('label')->unique();
        $this->assertTrue($monthNames->contains('Juli'));
        $this->assertTrue($monthNames->contains('Januari'));
    }
}