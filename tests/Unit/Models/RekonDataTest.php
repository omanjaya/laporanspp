<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\RekonData;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RekonDataTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_rekon_data()
    {
        // Arrange
        $data = [
            'sekolah' => 'SMAN_1_DENPASAR',
            'id_siswa' => '12345',
            'nama_siswa' => 'Test Student',
            'alamat' => 'Test Address',
            'kelas' => 'XI',
            'jurusan' => 'MIPA1',
            'jum_tagihan' => 350000,
            'biaya_adm' => 0,
            'tagihan_lain' => 0,
            'tahun' => 2024,
            'bulan' => 1,
            'dana_masyarakat' => '350000',
            'tgl_tx' => now(),
            'tgl_tx_formatted' => '01/01/2024 10:00',
            'sts_bayar' => 1,
            'kd_cab' => 'EB',
            'kd_user' => 'system',
            'sts_reversal' => 0,
            'no_bukti' => '1234567890'
        ];

        // Act
        $record = RekonData::create($data);

        // Assert
        $this->assertInstanceOf(RekonData::class, $record);
        $this->assertDatabaseHas('rekon_data', [
            'sekolah' => 'SMAN_1_DENPASAR',
            'id_siswa' => '12345',
            'nama_siswa' => 'Test Student'
        ]);
    }

    /** @test */
    public function it_uses_correct_table_name()
    {
        // Arrange
        $record = RekonData::factory()->create();

        // Act & Assert
        $this->assertEquals('rekon_data', $record->getTable());
    }

    /** @test */
    public function it_has_fillable_attributes()
    {
        // Arrange
        $record = new RekonData();

        // Act & Assert
        $expectedFillable = [
            'sekolah', 'id_siswa', 'nama_siswa', 'alamat', 'kelas', 'jurusan',
            'jum_tagihan', 'biaya_adm', 'tagihan_lain', 'ket_tagihan_lain', 'keterangan',
            'tahun', 'bulan', 'dana_masyarakat',
            'tgl_tx', 'tgl_tx_formatted', 'sts_bayar', 'kd_cab', 'kd_user', 'sts_reversal', 'no_bukti'
        ];

        $this->assertEquals($expectedFillable, $record->getFillable());
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        // Arrange
        $record = RekonData::factory()->create([
            'jum_tagihan' => '350000',
            'biaya_adm' => '2500',
            'tagihan_lain' => '5000',
            'tahun' => '2024',
            'bulan' => '1',
            'sts_bayar' => '1',
            'sts_reversal' => '0'
        ]);

        // Act
        $freshRecord = $record->fresh();

        // Assert
        $this->assertIsInt($freshRecord->jum_tagihan);
        $this->assertIsInt($freshRecord->biaya_adm);
        $this->assertIsInt($freshRecord->tagihan_lain);
        $this->assertIsInt($freshRecord->tahun);
        $this->assertIsInt($freshRecord->bulan);
        $this->assertIsInt($freshRecord->sts_bayar);
        $this->assertIsInt($freshRecord->sts_reversal);
        $this->assertInstanceOf(\Carbon\Carbon::class, $freshRecord->tgl_tx);
    }

    /** @test */
    public function it_can_use_by_kriteria_scope()
    {
        // Arrange
        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1
        ]);
        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 2
        ]);
        RekonData::factory()->create([
            'sekolah' => 'SMAN_2_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1
        ]);

        // Act
        $results = RekonData::byKriteria('SMAN_1_DENPASAR', 2024, 1)->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals('SMAN_1_DENPASAR', $results->first()->sekolah);
        $this->assertEquals(2024, $results->first()->tahun);
        $this->assertEquals(1, $results->first()->bulan);
    }

    /** @test */
    public function it_can_get_dana_masyarakat_static_method()
    {
        // Arrange
        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1,
            'dana_masyarakat' => '350000'
        ]);

        // Act
        $danaMasyarakat = RekonData::getDanaMasyarakat('SMAN_1_DENPASAR', 2024, 1);

        // Assert
        $this->assertEquals('350000', $danaMasyarakat);
    }

    /** @test */
    public function it_returns_dash_for_nonexistent_dana_masyarakat()
    {
        // Arrange - No matching record

        // Act
        $danaMasyarakat = RekonData::getDanaMasyarakat('NONEXISTENT', 2024, 1);

        // Assert
        $this->assertEquals('-', $danaMasyarakat);
    }

    /** @test */
    public function it_handles_unique_no_bukti_constraint()
    {
        // Arrange
        $existingRecord = RekonData::factory()->create(['no_bukti' => '1234567890']);

        // Act & Assert
        $this->expectException(\Illuminate\Database\QueryException::class);
        RekonData::factory()->create(['no_bukti' => '1234567890']);
    }

    /** @test */
    public function it_validates_required_fields()
    {
        // Arrange & Act
        $record = new RekonData();

        // Assert - Test that these fields are in fillable (required for creation)
        $fillable = $record->getFillable();
        $this->assertContains('sekolah', $fillable);
        $this->assertContains('id_siswa', $fillable);
        $this->assertContains('nama_siswa', $fillable);
        $this->assertContains('tahun', $fillable);
        $this->assertContains('bulan', $fillable);
    }

    /** @test */
    public function it_handles_null_values_in_optional_fields()
    {
        // Arrange
        $data = [
            'sekolah' => 'SMAN_1_DENPASAR',
            'id_siswa' => '12345',
            'nama_siswa' => 'Test Student',
            'tahun' => 2024,
            'bulan' => 1,
            'alamat' => null,
            'kelas' => null,
            'jurusan' => null,
            'keterangan' => null,
            'ket_tagihan_lain' => null,
            'dana_masyarakat' => ''
        ];

        // Act
        $record = RekonData::create($data);

        // Assert
        $this->assertInstanceOf(RekonData::class, $record);
        $this->assertNull($record->alamat);
        $this->assertNull($record->kelas);
        $this->assertNull($record->jurusan);
        $this->assertNull($record->keterangan);
        $this->assertNull($record->ket_tagihan_lain);
        $this->assertEquals('', $record->dana_masyarakat);
    }

    /** @test */
    public function it_handles_default_values()
    {
        // Arrange
        $data = [
            'sekolah' => 'SMAN_1_DENPASAR',
            'id_siswa' => '12345',
            'nama_siswa' => 'Test Student',
            'tahun' => 2024,
            'bulan' => 1,
            'dana_masyarakat' => '350000',
            'tgl_tx' => now()
            // Other fields will use defaults
        ];

        // Act
        $record = RekonData::create($data);

        // Assert
        $this->assertEquals(0, $record->biaya_adm);
        $this->assertEquals(0, $record->tagihan_lain);
        $this->assertEquals(0, $record->sts_reversal);
    }

    /** @test */
    public function it_handles_large_integer_values()
    {
        // Arrange
        $largeValue = 2147483647; // Max int value

        // Act
        $record = RekonData::factory()->create([
            'jum_tagihan' => $largeValue,
            'biaya_adm' => $largeValue,
            'tagihan_lain' => $largeValue
        ]);

        // Assert
        $freshRecord = $record->fresh();
        $this->assertEquals($largeValue, $freshRecord->jum_tagihan);
        $this->assertEquals($largeValue, $freshRecord->biaya_adm);
        $this->assertEquals($largeValue, $freshRecord->tagihan_lain);
    }

    /** @test */
    public function it_handles_datetime_formats()
    {
        // Arrange
        $specificDate = '2024-01-15 10:30:00';

        // Act
        $record = RekonData::factory()->create([
            'tgl_tx' => $specificDate
        ]);

        // Assert
        $freshRecord = $record->fresh();
        $this->assertEquals('2024-01-15 10:30:00', $freshRecord->tgl_tx->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_handles_year_and_month_ranges()
    {
        // Arrange & Act
        $record = RekonData::factory()->create([
            'tahun' => 2000, // Minimum reasonable year
            'bulan' => 1     // Minimum month
        ]);

        // Assert
        $freshRecord = $record->fresh();
        $this->assertEquals(2000, $freshRecord->tahun);
        $this->assertEquals(1, $freshRecord->bulan);

        // Test maximum values
        $record2 = RekonData::factory()->create([
            'tahun' => 2100, // Maximum reasonable year
            'bulan' => 12    // Maximum month
        ]);

        $freshRecord2 = $record2->fresh();
        $this->assertEquals(2100, $freshRecord2->tahun);
        $this->assertEquals(12, $freshRecord2->bulan);
    }

    /** @test */
    public function it_handles_string_length_limits()
    {
        // Arrange
        $longString = str_repeat('A', 255); // Test reasonable string length

        // Act
        $record = RekonData::factory()->create([
            'sekolah' => $longString,
            'nama_siswa' => $longString,
            'alamat' => $longString,
            'keterangan' => $longString,
            'dana_masyarakat' => $longString
        ]);

        // Assert
        $freshRecord = $record->fresh();
        $this->assertEquals($longString, $freshRecord->sekolah);
        $this->assertEquals($longString, $freshRecord->nama_siswa);
        $this->assertEquals($longString, $freshRecord->alamat);
        $this->assertEquals($longString, $freshRecord->keterangan);
        $this->assertEquals($longString, $freshRecord->dana_masyarakat);
    }

    /** @test */
    public function it_queries_by_multiple_criteria_efficiently()
    {
        // Arrange
        $records = RekonData::factory()->count(100)->create();
        $targetRecord = $records->random();

        // Act
        $foundRecord = RekonData::byKriteria(
            $targetRecord->sekolah,
            $targetRecord->tahun,
            $targetRecord->bulan
        )->first();

        // Assert
        $this->assertNotNull($foundRecord);
        $this->assertEquals($targetRecord->id, $foundRecord->id);
    }

    /** @test */
    public function it_handles_created_at_and_updated_at()
    {
        // Arrange
        $beforeCreation = now();

        // Act
        $record = RekonData::factory()->create();

        // Assert
        $this->assertNotNull($record->created_at);
        $this->assertNotNull($record->updated_at);
        $this->assertGreaterThanOrEqual($beforeCreation, $record->created_at);
        $this->assertEquals($record->created_at, $record->updated_at);

        // Test updated_at changes on update
        sleep(1);
        $record->update(['keterangan' => 'Updated']);
        $this->assertGreaterThan($record->created_at, $record->updated_at);
    }

    /** @test */
    public function it_handles_bulk_operations()
    {
        // Arrange
        $data = RekonData::factory()->count(10)->make()->toArray();

        // Act
        RekonData::insert($data);

        // Assert
        $this->assertDatabaseCount('rekon_data', 10);
    }

    /** @test */
    public function it_handles_relationships_if_any()
    {
        // Arrange & Act
        $record = RekonData::factory()->create();

        // Assert - Test that relationships exist (even if empty)
        $this->assertTrue(method_exists($record, 'created_at'));
        $this->assertTrue(method_exists($record, 'updated_at'));
    }

    /** @test */
    public function it_handles_soft_deletes_if_enabled()
    {
        // Arrange
        $record = RekonData::factory()->create();

        // Act & Assert
        // Test that model doesn't use soft deletes by default
        $this->assertFalse(method_exists($record, 'trashed'));
        $this->assertFalse($record->usesSoftDeletes());
    }

    /** @test */
    public function it_has_proper_json_serialization()
    {
        // Arrange
        $record = RekonData::factory()->create();

        // Act
        $json = $record->toJson();

        // Assert
        $this->assertJson($json);
        $array = json_decode($json, true);
        $this->assertArrayHasKey('sekolah', $array);
        $this->assertArrayHasKey('id_siswa', $array);
        $this->assertArrayHasKey('nama_siswa', $array);
    }
}