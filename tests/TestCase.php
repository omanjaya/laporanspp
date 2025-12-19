<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase, WithFaker;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Configure testing environment
        config(['app.env' => 'testing']);
        config(['cache.default' => 'array']); // Use array cache for testing
        config(['queue.default' => 'sync']); // Use sync queue for testing
    }

    /**
     * Create a test CSV file for bank import testing
     */
    protected function createTestBankCsv(string $filename, array $data): string
    {
        $path = storage_path('app/testing/' . $filename);
        $this->ensureDirectoryExists(dirname($path));

        $handle = fopen($path, 'w');

        if (!empty($data)) {
            // Write header if not present
            if (!isset($data[0]['header'])) {
                fputcsv($handle, array_keys($data[0]));
            }

            foreach ($data as $row) {
                fputcsv($handle, $row);
            }
        }

        fclose($handle);
        return $path;
    }

    /**
     * Ensure directory exists
     */
    protected function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Clean up test files
     */
    protected function cleanupTestFiles(string $directory = 'testing'): void
    {
        $path = storage_path('app/' . $directory);
        if (is_dir($path)) {
            $files = glob($path . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Tear down the test environment
     */
    protected function tearDown(): void
    {
        $this->cleanupTestFiles();
        parent::tearDown();
    }
}
