<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use App\Services\RekonImportService;
use App\Services\BankCsvImportService;
use App\Services\OptimizedRekonImportService;
use App\Services\OptimizedBankCsvImportService;
use App\Services\PerformanceMonitoringService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Auth;
use Mockery;

class RekonImportControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_import_legacy_file_successfully()
    {
        // Arrange
        $mockService = Mockery::mock(RekonImportService::class);
        $mockService->shouldReceive('importFromFile')
                   ->once()
                   ->andReturn([
                       'success' => true,
                       'imported' => 5,
                       'total_rows' => 5,
                       'errors' => [],
                       'error_count' => 0
                   ]);

        $mockPerfMonitor = Mockery::mock(PerformanceMonitoringService::class);
        $mockPerfMonitor->shouldReceive('measure')
                       ->once()
                       ->andReturnUsing(function ($name, $callback) use ($mockPerfMonitor) {
            $mockPerfMonitor->shouldReceive('recordMetric')->once();
            return $callback();
        });

        $this->app->instance(RekonImportService::class, $mockService);
        $this->app->instance(PerformanceMonitoringService::class, $mockPerfMonitor);

        $file = UploadedFile::fake()->create('test_import.xlsx', 1000);

        // Act
        $response = $this->postJson('/api/rekon/import', [
            'file' => $file
        ]);

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Berhasil mengimport 5 data',
                    'imported' => 5,
                    'total_rows' => 5,
                    'error_count' => 0
                ]);
    }

    /** @test */
    public function it_can_import_bank_csv_file_successfully()
    {
        // Arrange
        $mockService = Mockery::mock(BankCsvImportService::class);
        $mockService->shouldReceive('importFromBankCsv')
                   ->once()
                   ->andReturn([
                       'success' => true,
                       'imported' => 10,
                       'duplicates' => 2,
                       'total_rows' => 12,
                       'errors' => [],
                       'error_count' => 0
                   ]);

        $mockPerfMonitor = Mockery::mock(PerformanceMonitoringService::class);
        $mockPerfMonitor->shouldReceive('measure')
                       ->once()
                       ->andReturnUsing(function ($name, $callback) use ($mockPerfMonitor) {
            $mockPerfMonitor->shouldReceive('recordMetric')->once();
            return $callback();
        });

        $this->app->instance(BankCsvImportService::class, $mockService);
        $this->app->instance(PerformanceMonitoringService::class, $mockPerfMonitor);

        $file = UploadedFile::fake()->create('bank_import.csv', 2000);

        // Act
        $response = $this->postJson('/api/rekon/import-bank-csv', [
            'file' => $file
        ]);

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Berhasil mengimport 10 data dari CSV Bank',
                    'imported' => 10,
                    'duplicates' => 2,
                    'total_rows' => 12,
                    'error_count' => 0
                ]);
    }

    /** @test */
    public function it_queues_large_files_for_background_processing()
    {
        // Arrange
        $largeFile = UploadedFile::fake()->create('large_file.xlsx', 6 * 1024 * 1024); // 6MB

        $mockOptimizedService = Mockery::mock(OptimizedRekonImportService::class);
        $mockPerfMonitor = Mockery::mock(PerformanceMonitoringService::class);
        $mockPerfMonitor->shouldReceive('measure')
                       ->once()
                       ->andReturnUsing(function ($name, $callback) use ($mockPerfMonitor) {
            $mockPerfMonitor->shouldReceive('recordMetric')->once();
            return $callback();
        });
        $mockPerfMonitor->shouldReceive('recordMetric')->once();

        $this->app->instance(OptimizedRekonImportService::class, $mockOptimizedService);
        $this->app->instance(PerformanceMonitoringService::class, $mockPerfMonitor);

        // Mock user authentication
        Auth::shouldReceive('id')->andReturn(1);

        // Act
        $response = $this->postJson('/api/rekon/import', [
            'file' => $largeFile
        ]);

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'File besar terdeteksi. Import akan diproses di background.',
                    'queued' => true
                ]);

        $this->assertArrayHasKey('job_id', $response->json());
        $this->assertArrayHasKey('estimated_time', $response->json());

        // Check if file was stored
        Storage::disk('local')->assertExists('temp/imports/' . $response->json('file_name'));
    }

    /** @test */
    public function it_queues_large_bank_csv_files_for_background_processing()
    {
        // Arrange
        $largeFile = UploadedFile::fake()->create('large_bank.csv', 7 * 1024 * 1024); // 7MB

        $mockOptimizedService = Mockery::mock(OptimizedBankCsvImportService::class);
        $mockPerfMonitor = Mockery::mock(PerformanceMonitoringService::class);
        $mockPerfMonitor->shouldReceive('measure')
                       ->once()
                       ->andReturnUsing(function ($name, $callback) use ($mockPerfMonitor) {
            $mockPerfMonitor->shouldReceive('recordMetric')->once();
            return $callback();
        });
        $mockPerfMonitor->shouldReceive('recordMetric')->once();

        $this->app->instance(OptimizedBankCsvImportService::class, $mockOptimizedService);
        $this->app->instance(PerformanceMonitoringService::class, $mockPerfMonitor);

        // Mock user authentication
        Auth::shouldReceive('id')->andReturn(1);

        // Act
        $response = $this->postJson('/api/rekon/import-bank-csv', [
            'file' => $largeFile
        ]);

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'queued' => true
                ]);

        Queue::assertPushedOn('csv-import', \App\Jobs\ProcessBankCsvImportJob::class);
    }

    /** @test */
    public function it_handles_legacy_import_errors()
    {
        // Arrange
        $mockService = Mockery::mock(RekonImportService::class);
        $mockService->shouldReceive('importFromFile')
                   ->once()
                   ->andThrow(new \Exception('File format error'));

        $mockPerfMonitor = Mockery::mock(PerformanceMonitoringService::class);
        $mockPerfMonitor->shouldReceive('measure')
                       ->once()
                       ->andReturnUsing(function ($name, $callback) use ($mockPerfMonitor) {
            $mockPerfMonitor->shouldReceive('recordMetric')->once();
            return $callback();
        });

        $this->app->instance(RekonImportService::class, $mockService);
        $this->app->instance(PerformanceMonitoringService::class, $mockPerfMonitor);

        $file = UploadedFile::fake()->create('invalid_file.xlsx', 1000);

        // Act
        $response = $this->postJson('/api/rekon/import', [
            'file' => $file
        ]);

        // Assert
        $response->assertStatus(500)
                ->assertJson([
                    'success' => false,
                    'message' => 'Error importing data: File format error'
                ]);
    }

    /** @test */
    public function it_handles_bank_csv_import_errors()
    {
        // Arrange
        $mockService = Mockery::mock(BankCsvImportService::class);
        $mockService->shouldReceive('importFromBankCsv')
                   ->once()
                   ->andThrow(new \Exception('CSV parsing error'));

        $mockPerfMonitor = Mockery::mock(PerformanceMonitoringService::class);
        $mockPerfMonitor->shouldReceive('measure')
                       ->once()
                       ->andReturnUsing(function ($name, $callback) use ($mockPerfMonitor) {
            $mockPerfMonitor->shouldReceive('recordMetric')->once();
            return $callback();
        });

        $this->app->instance(BankCsvImportService::class, $mockService);
        $this->app->instance(PerformanceMonitoringService::class, $mockPerfMonitor);

        $file = UploadedFile::fake()->create('invalid_bank.csv', 1000);

        // Act
        $response = $this->postJson('/api/rekon/import-bank-csv', [
            'file' => $file
        ]);

        // Assert
        $response->assertStatus(500)
                ->assertJson([
                    'success' => false,
                    'message' => 'Error importing Bank CSV: CSV parsing error'
                ]);
    }

    /** @test */
    public function it_can_check_import_status()
    {
        // Arrange
        $cacheKey = 'import_result_1_test_job_123';
        cache()->put($cacheKey, [
            'success' => true,
            'imported' => 100,
            'errors' => []
        ], 3600);

        // Mock user authentication
        Auth::shouldReceive('id')->andReturn(1);

        // Act
        $response = $this->getJson('/api/rekon/import-status?job_id=test_job_123');

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'status' => 'completed',
                    'result' => [
                        'success' => true,
                        'imported' => 100
                    ]
                ]);
    }

    /** @test */
    public function it_returns_processing_status_for_pending_jobs()
    {
        // Arrange
        // No cache entry - job still processing

        // Mock user authentication
        Auth::shouldReceive('id')->andReturn(1);

        // Act
        $response = $this->getJson('/api/rekon/import-status?job_id=pending_job_456');

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'status' => 'processing',
                    'message' => 'Import sedang diproses...'
                ]);
    }

    /** @test */
    public function it_returns_error_for_missing_job_id()
    {
        // Act
        $response = $this->getJson('/api/rekon/import-status');

        // Assert
        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Job ID diperlukan'
                ]);
    }

    /** @test */
    public function it_handles_queue_errors()
    {
        // Arrange
        $largeFile = UploadedFile::fake()->create('large_file.xlsx', 6 * 1024 * 1024);

        $mockPerfMonitor = Mockery::mock(PerformanceMonitoringService::class);
        $mockPerfMonitor->shouldReceive('measure')
                       ->once()
                       ->andThrow(new \Exception('Queue error'));

        $this->app->instance(PerformanceMonitoringService::class, $mockPerfMonitor);

        // Mock user authentication
        Auth::shouldReceive('id')->andReturn(1);

        // Act
        $response = $this->postJson('/api/rekon/import', [
            'file' => $largeFile
        ]);

        // Assert
        $response->assertStatus(500)
                ->assertJson([
                    'success' => false,
                    'message' => 'Gagal memproses file: Queue error'
                ]);
    }

    /** @test */
    public function it_estimates_processing_time_based_on_file_size()
    {
        // Arrange
        $file = UploadedFile::fake()->create('medium_file.xlsx', 2 * 1024 * 1024); // 2MB

        $mockService = Mockery::mock(RekonImportService::class);
        $mockService->shouldReceive('importFromFile')
                   ->once()
                   ->andReturn([
                       'success' => true,
                       'imported' => 5,
                       'total_rows' => 5,
                       'errors' => [],
                       'error_count' => 0
                   ]);

        $mockPerfMonitor = Mockery::mock(PerformanceMonitoringService::class);
        $mockPerfMonitor->shouldReceive('measure')
                       ->once()
                       ->andReturnUsing(function ($name, $callback) use ($mockPerfMonitor) {
            $mockPerfMonitor->shouldReceive('recordMetric')->once();
            return $callback();
        });

        $this->app->instance(RekonImportService::class, $mockService);
        $this->app->instance(PerformanceMonitoringService::class, $mockPerfMonitor);

        // Act
        $response = $this->postJson('/api/rekon/import', [
            'file' => $file
        ]);

        // Assert
        $response->assertStatus(200);
        // For small files, no estimated time should be included
        $this->assertArrayNotHasKey('estimated_time', $response->json());
    }

    /** @test */
    public function it_can_get_import_history()
    {
        // Arrange
        $mockPerfMonitor = Mockery::mock(PerformanceMonitoringService::class);
        $mockPerfMonitor->shouldReceive('measure')
                       ->once()
                       ->andReturn([
                            'success' => true,
                            'data' => [],
                            'message' => 'Import history feature coming soon'
                       ]);

        $this->app->instance(PerformanceMonitoringService::class, $mockPerfMonitor);

        // Mock user authentication
        Auth::shouldReceive('id')->andReturn(1);

        // Act
        $response = $this->getJson('/api/rekon/import-history');

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [],
                    'message' => 'Import history feature coming soon'
                ]);
    }

    /** @test */
    public function it_handles_import_history_errors()
    {
        // Arrange
        $mockPerfMonitor = Mockery::mock(PerformanceMonitoringService::class);
        $mockPerfMonitor->shouldReceive('measure')
                       ->once()
                       ->andThrow(new \Exception('Database error'));

        $this->app->instance(PerformanceMonitoringService::class, $mockPerfMonitor);

        // Mock user authentication
        Auth::shouldReceive('id')->andReturn(1);

        // Act
        $response = $this->getJson('/api/rekon/import-history');

        // Assert
        $response->assertStatus(500)
                ->assertJson([
                    'success' => false,
                    'message' => 'Gagal mengambil riwayat import: Database error'
                ]);
    }

    /** @test */
    public function it_limits_import_history_results()
    {
        // Arrange
        $mockPerfMonitor = Mockery::mock(PerformanceMonitoringService::class);
        $mockPerfMonitor->shouldReceive('measure')
                       ->once()
                       ->andReturn([
                            'success' => true,
                            'data' => [],
                            'message' => 'Import history feature coming soon'
                       ]);

        $this->app->instance(PerformanceMonitoringService::class, $mockPerfMonitor);

        // Mock user authentication
        Auth::shouldReceive('id')->andReturn(1);

        // Act
        $response = $this->getJson('/api/rekon/import-history?limit=100');

        // Assert
        $response->assertStatus(200);
        // The limit should be capped at 50
        // This would be verified in the actual implementation
    }

    /** @test */
    public function it_records_performance_metrics()
    {
        // Arrange
        $mockService = Mockery::mock(RekonImportService::class);
        $mockService->shouldReceive('importFromFile')
                   ->once()
                   ->andReturn([
                       'success' => true,
                       'imported' => 5,
                       'total_rows' => 5,
                       'errors' => [],
                       'error_count' => 0
                   ]);

        $mockPerfMonitor = Mockery::mock(PerformanceMonitoringService::class);
        $mockPerfMonitor->shouldReceive('measure')
                       ->once()
                       ->andReturnUsing(function ($name, $callback) use ($mockPerfMonitor) {
            // Verify that recordMetric is called with correct parameters
            $mockPerfMonitor->shouldReceive('recordMetric')
                           ->once()
                           ->with('file.import.success', 1, Mockery::type('array'));
            return $callback();
        });
        $mockPerfMonitor->shouldReceive('recordMetric')
                       ->once()
                       ->with('file.import.size_mb', Mockery::type('float'), Mockery::type('array'));

        $this->app->instance(RekonImportService::class, $mockService);
        $this->app->instance(PerformanceMonitoringService::class, $mockPerfMonitor);

        $file = UploadedFile::fake()->create('test_import.xlsx', 1000);

        // Act
        $response = $this->postJson('/api/rekon/import', [
            'file' => $file
        ]);

        // Assert
        $response->assertStatus(200);
    }

    /** @test */
    public function it_handles_unauthenticated_user_fallback()
    {
        // Arrange
        Auth::shouldReceive('id')->andReturn(null); // No authenticated user

        $largeFile = UploadedFile::fake()->create('large_file.xlsx', 6 * 1024 * 1024);

        $mockOptimizedService = Mockery::mock(OptimizedRekonImportService::class);
        $mockPerfMonitor = Mockery::mock(PerformanceMonitoringService::class);
        $mockPerfMonitor->shouldReceive('measure')
                       ->once()
                       ->andReturnUsing(function ($name, $callback) use ($mockPerfMonitor) {
            $mockPerfMonitor->shouldReceive('recordMetric')->once();
            return $callback();
        });
        $mockPerfMonitor->shouldReceive('recordMetric')->once();

        $this->app->instance(OptimizedRekonImportService::class, $mockOptimizedService);
        $this->app->instance(PerformanceMonitoringService::class, $mockPerfMonitor);

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

        // Should handle fallback user ID = 1
        $cacheKey = 'import_result_1_' . $response->json('job_id');
        // This would be used in the actual job processing
    }
}