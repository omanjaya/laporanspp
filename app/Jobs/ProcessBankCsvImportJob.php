<?php

namespace App\Jobs;

use App\Services\OptimizedBankCsvImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessBankCsvImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Job timeout in seconds (30 minutes)
     */
    public int $timeout = 1800;

    /**
     * Number of times the job may be attempted
     */
    public int $tries = 3;

    /**
     * Number of seconds to wait before retrying
     */
    public int $retryAfter = 60;

    /**
     * The uploaded file path
     */
    private string $filePath;

    /**
     * The original filename
     */
    private string $originalName;

    /**
     * User ID for tracking
     */
    private int $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $filePath, string $originalName, int $userId)
    {
        $this->filePath = $filePath;
        $this->originalName = $originalName;
        $this->userId = $userId;

        // Set queue based on file size
        $this->onQueue('csv-import');
    }

    /**
     * Execute the job.
     */
    public function handle(OptimizedBankCsvImportService $importService): void
    {
        try {
            Log::info('Starting Bank CSV import job', [
                'file_path' => $this->filePath,
                'original_name' => $this->originalName,
                'user_id' => $this->userId,
                'job_id' => $this->job->getJobId()
            ]);

            // Check if file still exists
            if (!Storage::disk('local')->exists($this->filePath)) {
                Log::error('CSV file not found', ['file_path' => $this->filePath]);
                throw new \Exception('File not found: ' . $this->filePath);
            }

            // Get file info
            $fileInfo = Storage::disk('local')->size($this->filePath);
            Log::info('Processing CSV file', [
                'file_size' => round($fileInfo / 1024 / 1024, 2) . ' MB'
            ]);

            // Create temporary file for processing
            $tempPath = sys_get_temp_dir() . '/' . uniqid('csv_import_', true) . '.csv';
            Storage::disk('local')->copy($this->filePath, $tempPath);

            // Process the import
            $result = $importService->importFromBankCsv(new \Illuminate\Http\UploadedFile(
                $tempPath,
                $this->originalName,
                'text/csv',
                null,
                true
            ));

            // Log successful completion
            Log::info('Bank CSV import completed successfully', [
                'result' => $result,
                'job_id' => $this->job->getJobId(),
                'user_id' => $this->userId
            ]);

            // Clean up temporary file
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            // Optionally clean up original uploaded file after successful processing
            Storage::disk('local')->delete($this->filePath);

            // Store results for later retrieval (could use cache or database)
            $cacheKey = 'import_result_' . $this->userId . '_' . $this->job->getJobId();
            cache()->put($cacheKey, $result, now()->addHours(24));

        } catch (\Exception $e) {
            Log::error('Bank CSV import job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file_path' => $this->filePath,
                'job_id' => $this->job->getJobId(),
                'user_id' => $this->userId
            ]);

            // Store error result
            $errorResult = [
                'success' => false,
                'message' => $e->getMessage(),
                'file_name' => $this->originalName,
                'completed_at' => now()->toISOString()
            ];

            $cacheKey = 'import_result_' . $this->userId . '_' . $this->job->getJobId();
            cache()->put($cacheKey, $errorResult, now()->addHours(24));

            // Re-throw the exception to trigger job retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Bank CSV import job permanently failed', [
            'error' => $exception->getMessage(),
            'file_path' => $this->filePath,
            'job_id' => $this->job->getJobId(),
            'user_id' => $this->userId
        ]);

        // Clean up uploaded file on permanent failure
        Storage::disk('local')->delete($this->filePath);

        // Store permanent failure result
        $failureResult = [
            'success' => false,
            'message' => 'Import failed after multiple attempts: ' . $exception->getMessage(),
            'file_name' => $this->originalName,
            'failed_at' => now()->toISOString(),
            'attempts' => $this->attempts()
        ];

        $cacheKey = 'import_result_' . $this->userId . '_' . $this->job->getJobId();
        cache()->put($cacheKey, $failureResult, now()->addDays(7));
    }
}