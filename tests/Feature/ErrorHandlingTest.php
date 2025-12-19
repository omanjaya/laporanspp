<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\RekonData;
use App\Services\LoggingService;
use App\Exceptions\ImportException;
use App\Exceptions\FileProcessingException;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * Test custom exception handling
     */
    public function test_custom_exception_handling(): void
    {
        $exception = new ImportException(
            'Test error message',
            'Pesan error untuk user',
            1,
            'test_column',
            'test_file.csv'
        );

        $this->assertEquals('Test error message', $exception->getMessage());
        $this->assertEquals('Pesan error untuk user', $exception->getUserMessage());
        $this->assertEquals(1, $exception->getRowNumber());
        $this->assertEquals('test_column', $exception->getColumn());
        $this->assertEquals('test_file.csv', $exception->getFileName());
        $this->assertArrayHasKey('row_number', $exception->getContext());
        $this->assertArrayHasKey('column', $exception->getContext());
        $this->assertArrayHasKey('file_name', $exception->getContext());
    }

    /**
     * Test API error response format
     */
    public function test_api_error_response_format(): void
    {
        // Test invalid API key
        $response = $this->postJson('/api/rekon/import', [], [
            'X-API-KEY' => 'invalid-key'
        ]);

        $response->assertStatus(401)
                 ->assertJsonStructure([
                     'success',
                     'error_code',
                     'message',
                     'timestamp',
                     'request_id'
                 ]);

        $this->assertFalse($response->json('success'));
        $this->assertNotEmpty($response->json('error_code'));
        $this->assertNotEmpty($response->json('message'));
    }

    /**
     * Test validation error handling
     */
    public function test_validation_error_handling(): void
    {
        $response = $this->postJson('/api/rekon/import', [], [
            'X-API-KEY' => 'spp-rekon-2024-secret-key'
        ]);

        $response->assertStatus(422)
                 ->assertJsonStructure([
                     'success',
                     'error_code',
                     'message',
                     'validation_errors',
                     'timestamp',
                     'request_id'
                 ]);

        $this->assertFalse($response->json('success'));
        $this->assertArrayHasKey('file', $response->json('validation_errors'));
    }

    /**
     * Test file processing error handling
     */
    public function test_file_processing_error_handling(): void
    {
        // Create a file that's too large
        $largeFile = UploadedFile::fake()->create('large_file.csv', 100 * 1024); // 100MB

        $response = $this->postJson('/api/rekon/import', [
            'file' => $largeFile
        ], [
            'X-API-KEY' => 'spp-rekon-2024-secret-key'
        ]);

        $response->assertStatus(400)
                 ->assertJsonStructure([
                     'success',
                     'error_code',
                     'message',
                     'timestamp',
                     'request_id'
                 ]);

        $this->assertFalse($response->json('success'));
        $this->assertStringContains('ukuran file', strtolower($response->json('message')));
    }

    /**
     * Test invalid file format error
     */
    public function test_invalid_file_format_error(): void
    {
        $invalidFile = UploadedFile::fake()->create('invalid_file.txt', 100);

        $response = $this->postJson('/api/rekon/import', [
            'file' => $invalidFile
        ], [
            'X-API-KEY' => 'spp-rekon-2024-secret-key'
        ]);

        $response->assertStatus(400)
                 ->assertJsonStructure([
                     'success',
                     'error_code',
                     'message',
                     'timestamp',
                     'request_id'
                 ]);

        $this->assertFalse($response->json('success'));
        $this->assertStringContains('format file', strtolower($response->json('message')));
    }

    /**
     * Test logging service functionality
     */
    public function test_logging_service(): void
    {
        $logger = new LoggingService();

        // Test different log levels
        $logger->info('Test info message', ['test' => 'data']);
        $logger->warning('Test warning message', ['test' => 'data']);
        $logger->error('Test error message', ['test' => 'data']);

        // Test specialized logging methods
        $logger->logApiRequest('/test', 'GET', [], ['result' => 'success'], 100);
        $logger->logFileImport('test.csv', 1024, 'test_type', ['status' => 'success']);
        $logger->logDatabaseOperation('select', 'test_table', [], 50);
        $logger->logPerformance('test_operation', ['duration_ms' => 100]);
        $logger->logSecurity('test_security_event', ['test' => 'data']);

        // These should not throw exceptions
        $this->assertTrue(true);
    }

    /**
     * Test frontend error logging
     */
    public function test_frontend_error_logging(): void
    {
        $errorData = [
            'error_type' => 'JavaScript Error',
            'message' => 'Test error message',
            'url' => 'http://localhost/test',
            'user_agent' => 'Test Browser',
            'filename' => 'test.js',
            'lineno' => 123,
            'colno' => 45,
            'stack' => 'Test stack trace',
            'context' => ['test' => 'data']
        ];

        $response = $this->postJson('/api/log/error', $errorData, [
            'X-API-KEY' => 'spp-rekon-2024-secret-key'
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Error logged successfully'
                 ]);
    }

    /**
     * Test error statistics endpoint
     */
    public function test_error_statistics_endpoint(): void
    {
        $response = $this->getJson('/api/errors/stats', [
            'X-API-KEY' => 'spp-rekon-2024-secret-key'
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'total_errors_today',
                         'most_common_errors',
                         'errors_by_type',
                         'recent_errors'
                     ]
                 ]);

        $this->assertTrue($response->json('success'));
    }

    /**
     * Test service layer error handling
     */
    public function test_service_layer_error_handling(): void
    {
        $logger = new LoggingService();
        $service = new \App\Services\BankCsvImportService($logger);

        // Test with invalid file
        $invalidFile = UploadedFile::fake()->create('invalid.txt', 100);

        $this->expectException(FileProcessingException::class);
        $service->importFromBankCsv($invalidFile);
    }

    /**
     * Test error context preservation
     */
    public function test_error_context_preservation(): void
    {
        $exception = new ImportException(
            'Test message',
            'User message',
            1,
            'test_column',
            'test_file.csv',
            ['additional_context' => 'test_data']
        );

        $array = $exception->toArray();

        $this->assertArrayHasKey('error_code', $array);
        $this->assertArrayHasKey('message', $array);
        $this->assertArrayHasKey('context', $array);
        $this->assertArrayHasKey('timestamp', $array);
        $this->assertArrayHasKey('status_code', $array);
        $this->assertEquals(400, $array['status_code']);
    }

    /**
     * Test error code generation uniqueness
     */
    public function test_error_code_uniqueness(): void
    {
        $exception1 = new ImportException('Test 1', 'User 1');
        $exception2 = new ImportException('Test 2', 'User 2');

        $this->assertNotEquals($exception1->getErrorCode(), $exception2->getErrorCode());
        $this->assertStringStartsWith('IMP-', $exception1->getErrorCode());
        $this->assertStringStartsWith('IMP-', $exception2->getErrorCode());
    }
}