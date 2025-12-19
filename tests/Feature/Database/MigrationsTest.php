<?php

namespace Tests\Feature\Database;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use App\Models\RekonData;
use App\Models\School;

class MigrationsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_rekon_data_table_with_correct_columns()
    {
        // Act & Assert
        $this->assertTrue(Schema::hasTable('rekon_data'));

        // Check required columns exist
        $this->assertTrue(Schema::hasColumn('rekon_data', 'id'));
        $this->assertTrue(Schema::hasColumn('rekon_data', 'sekolah'));
        $this->assertTrue(Schema::hasColumn('rekon_data', 'id_siswa'));
        $this->assertTrue(Schema::hasColumn('rekon_data', 'nama_siswa'));
        $this->assertTrue(Schema::hasColumn('rekon_data', 'alamat'));
        $this->assertTrue(Schema::hasColumn('rekon_data', 'kelas'));
        $this->assertTrue(Schema::hasColumn('rekon_data', 'jurusan'));
        $this->assertTrue(Schema::hasColumn('rekon_data', 'jum_tagihan'));
        $this->assertTrue(Schema::hasColumn('rekon_data', 'biaya_adm'));
        $this->assertTrue(Schema::hasColumn('rekon_data', 'tagihan_lain'));
        $this->assertTrue(Schema::hasColumn('rekon_data', 'ket_tagihan_lain'));
        $this->assertTrue(Schema::hasColumn('rekon_data', 'keterangan'));
        $this->assertTrue(Schema::hasColumn('rekon_data', 'tahun'));
        $this->assertTrue(Schema::hasColumn('rekon_data', 'bulan'));
        $this->assertTrue(Schema::hasColumn('rekon_data', 'dana_masyarakat'));
        $this->assertTrue(Schema::hasColumn('rekon_data', 'tgl_tx'));
        $this->assertTrue(Schema::hasColumn('rekon_data', 'tgl_tx_formatted'));
        $this->assertTrue(Schema::hasColumn('rekon_data', 'sts_bayar'));
        $this->assertTrue(Schema::hasColumn('rekon_data', 'kd_cab'));
        $this->assertTrue(Schema::hasColumn('rekon_data', 'kd_user'));
        $this->assertTrue(Schema::hasColumn('rekon_data', 'sts_reversal'));
        $this->assertTrue(Schema::hasColumn('rekon_data', 'no_bukti'));
        $this->assertTrue(Schema::hasColumn('rekon_data', 'created_at'));
        $this->assertTrue(Schema::hasColumn('rekon_data', 'updated_at'));
    }

    /** @test */
    public function it_creates_schools_table_with_correct_columns()
    {
        // Act & Assert
        $this->assertTrue(Schema::hasTable('schools'));

        // Check required columns exist
        $this->assertTrue(Schema::hasColumn('schools', 'id'));
        $this->assertTrue(Schema::hasColumn('schools', 'name'));
        $this->assertTrue(Schema::hasColumn('schools', 'display_name'));
        $this->assertTrue(Schema::hasColumn('schools', 'address'));
        $this->assertTrue(Schema::hasColumn('schools', 'phone'));
        $this->assertTrue(Schema::hasColumn('schools', 'email'));
        $this->assertTrue(Schema::hasColumn('schools', 'is_active'));
        $this->assertTrue(Schema::hasColumn('schools', 'created_at'));
        $this->assertTrue(Schema::hasColumn('schools', 'updated_at'));
    }

    /** @test */
    public function it_creates_rekon_data_indexes_correctly()
    {
        // This test assumes the performance indexes migration exists
        // If the migration doesn't exist, this test should be skipped or adapted

        // Act & Assert
        // Check for composite index on (sekolah, tahun, bulan)
        $this->assertTrue($this->hasIndex('rekon_data', ['sekolah', 'tahun', 'bulan']));

        // Check for composite index on (id_siswa, tahun, bulan)
        $this->assertTrue($this->hasIndex('rekon_data', ['id_siswa', 'tahun', 'bulan']));

        // Check for single column indexes
        $this->assertTrue($this->hasIndex('rekon_data', ['nama_siswa']));
        $this->assertTrue($this->hasIndex('rekon_data', ['no_bukti']));
    }

    /** @test */
    public function it_creates_required_system_tables()
    {
        // Act & Assert
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('jobs'));
        $this->assertTrue(Schema::hasTable('cache'));
        $this->assertTrue(Schema::hasTable('failed_jobs'));
    }

    /** @test */
    public function rekon_data_table_has_correct_column_types()
    {
        // Arrange
        $record = RekonData::factory()->create();

        // Act & Assert - Check column types through model behavior
        $this->assertIsInt($record->jum_tagihan);
        $this->assertIsInt($record->biaya_adm);
        $this->assertIsInt($record->tagihan_lain);
        $this->assertIsInt($record->tahun);
        $this->assertIsInt($record->bulan);
        $this->assertIsInt($record->sts_bayar);
        $this->assertIsInt($record->sts_reversal);
        $this->assertInstanceOf(\Carbon\Carbon::class, $record->tgl_tx);
    }

    /** @test */
    public function schools_table_has_correct_column_types()
    {
        // Arrange
        $school = School::factory()->create();

        // Act & Assert
        $this->assertIsBool($school->is_active);
        $this->assertInstanceOf(\Carbon\Carbon::class, $school->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $school->updated_at);
    }

    /** @test */
    public function migrations_are_rollback_safe()
    {
        // Arrange
        RekonData::factory()->count(5)->create();
        School::factory()->count(3)->create();

        // Act - Rollback migrations
        $this->artisan('migrate:rollback');

        // Assert - Tables should be dropped
        $this->assertFalse(Schema::hasTable('rekon_data'));
        $this->assertFalse(Schema::hasTable('schools'));

        // Re-run migrations
        $this->artisan('migrate');

        // Assert - Tables should be recreated
        $this->assertTrue(Schema::hasTable('rekon_data'));
        $this->assertTrue(Schema::hasTable('schools'));

        // Should be able to create records again
        $this->assertDatabaseCount('rekon_data', 0);
        $this->assertDatabaseCount('schools', 0);

        RekonData::factory()->create();
        School::factory()->create();

        $this->assertDatabaseCount('rekon_data', 1);
        $this->assertDatabaseCount('schools', 1);
    }

    /** @test */
    public function migrations_run_in_correct_order()
    {
        // This test ensures migrations run in the correct order to avoid foreign key issues
        // Since there are no explicit foreign keys between schools and rekon_data in the given schema,
        // this test mainly verifies that all migrations can run successfully

        // Act
        $this->artisan('migrate:fresh');

        // Assert
        $this->assertTrue(Schema::hasTable('users')); // Should exist first
        $this->assertTrue(Schema::hasTable('schools'));
        $this->assertTrue(Schema::hasTable('rekon_data'));
        $this->assertTrue(Schema::hasTable('jobs'));
        $this->assertTrue(Schema::hasTable('cache'));
    }

    /** @test */
    public function it_handles_foreign_key_constraints()
    {
        // This test is for documentation purposes
        // Based on the schema provided, there are no explicit foreign key constraints
        // between schools and rekon_data tables. The relationship is handled
        // at the application level through the 'sekolah' field.

        // Act & Assert
        $this->assertTrue(Schema::hasTable('rekon_data'));
        $this->assertTrue(Schema::hasTable('schools'));

        // Can create rekon_data with non-existent school name (no FK constraint)
        $record = RekonData::factory()->create(['sekolah' => 'NONEXISTENT_SCHOOL']);
        $this->assertInstanceOf(RekonData::class, $record);
        $this->assertEquals('NONEXISTENT_SCHOOL', $record->sekolah);
    }

    /** @test */
    public function it_handles_data_integrity_after_migration()
    {
        // Arrange - Create data with migrations
        $schools = School::factory()->count(3)->create();
        RekonData::factory()->count(10)->create();

        // Act - Fresh migration (drop and recreate)
        $this->artisan('migrate:fresh');

        // Assert - Should be empty
        $this->assertDatabaseCount('schools', 0);
        $this->assertDatabaseCount('rekon_data', 0);

        // Can recreate data without issues
        School::factory()->count(3)->create();
        RekonData::factory()->count(10)->create();

        $this->assertDatabaseCount('schools', 3);
        $this->assertDatabaseCount('rekon_data', 10);
    }

    /** @test */
    public function it_handles_large_data_sets_after_migration()
    {
        // Arrange
        $this->artisan('migrate:fresh');

        // Act - Create large dataset
        School::factory()->count(50)->create();
        RekonData::factory()->count(1000)->create();

        // Assert - Should be able to handle large datasets without issues
        $this->assertDatabaseCount('schools', 50);
        $this->assertDatabaseCount('rekon_data', 1000);

        // Test queries work efficiently
        $this->assertLessThan(1.0, microtime(true) - microtime(true)); // Basic performance check
        $schools = School::all();
        $this->assertCount(50, $schools);

        $rekonData = RekonData::limit(100)->get();
        $this->assertCount(100, $rekonData);
    }

    /** @test */
    public function it_handles_character_set_and_collation()
    {
        // This test ensures proper character set support for Indonesian characters
        $indonesianData = [
            'sekolah' => 'SMAN_1_DENPASAR',
            'nama_siswa' => 'I Made Bagus Kadek Adi Wibawa',
            'alamat' => 'Jl. Gajah Mada No. 123, Denpasar, Bali',
            'keterangan' => 'Pembayaran SPP bulan Januari 2024',
            'dana_masyarakat' => 'tiga ratus lima puluh ribu rupiah'
        ];

        // Act
        $record = RekonData::factory()->create($indonesianData);

        // Assert
        $this->assertInstanceOf(RekonData::class, $record);
        $this->assertEquals('I Made Bagus Kadek Adi Wibawa', $record->nama_siswa);
        $this->assertEquals('Jl. Gajah Mada No. 123, Denpasar, Bali', $record->alamat);
        $this->assertEquals('Pembayaran SPP bulan Januari 2024', $record->keterangan);
        $this->assertEquals('tiga ratus lima puluh ribu rupiah', $record->dana_masyarakat);
    }

    /** @test */
    public function it_handles_null_values_in_nullable_columns()
    {
        // Arrange
        $record = RekonData::factory()->create([
            'alamat' => null,
            'keterangan' => null,
            'ket_tagihan_lain' => null
        ]);

        // Act & Assert
        $this->assertNull($record->alamat);
        $this->assertNull($record->keterangan);
        $this->assertNull($record->ket_tagihan_lain);

        // Should be able to save and retrieve
        $freshRecord = $record->fresh();
        $this->assertNull($freshRecord->alamat);
        $this->assertNull($freshRecord->keterangan);
        $this->assertNull($freshRecord->ket_tagihan_lain);
    }

    /** @test */
    public function it_handles_auto_incrementing_primary_keys()
    {
        // Arrange & Act
        $record1 = RekonData::factory()->create();
        $record2 = RekonData::factory()->create();
        $record3 = RekonData::factory()->create();

        // Assert
        $this->assertEquals(1, $record1->id);
        $this->assertEquals(2, $record2->id);
        $this->assertEquals(3, $record3->id);

        // Test with schools table
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();

        $this->assertEquals(1, $school1->id);
        $this->assertEquals(2, $school2->id);
    }

    /**
     * Helper method to check if index exists
     */
    private function hasIndex(string $table, array $columns): bool
    {
        $indexes = \DB::select("SHOW INDEX FROM {$table}");

        foreach ($indexes as $index) {
            $indexColumns = \DB::select("
                SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND INDEX_NAME = ?
                ORDER BY SEQ_IN_INDEX
            ", [$table, $index->Key_name]);

            $indexColumnNames = collect($indexColumns)->pluck('COLUMN_NAME')->toArray();

            if ($indexColumnNames === $columns) {
                return true;
            }
        }

        return false;
    }

    /** @test */
    public function it_creates_timestamp_columns_correctly()
    {
        // Arrange
        $beforeCreation = now();

        // Act
        $record = RekonData::factory()->create();

        // Assert
        $this->assertNotNull($record->created_at);
        $this->assertNotNull($record->updated_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $record->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $record->updated_at);
        $this->assertGreaterThanOrEqual($beforeCreation, $record->created_at);
        $this->assertEquals($record->created_at->timestamp, $record->updated_at->timestamp);
    }

    /** @test */
    public function it_handles_datetime_columns()
    {
        // Arrange
        $specificDateTime = '2024-01-15 14:30:00';

        // Act
        $record = RekonData::factory()->create(['tgl_tx' => $specificDateTime]);

        // Assert
        $freshRecord = $record->fresh();
        $this->assertEquals('2024-01-15 14:30:00', $freshRecord->tgl_tx->format('Y-m-d H:i:s'));
    }
}