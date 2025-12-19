<?php

namespace App\Jobs;

use App\Services\OptimizedRekonImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessRekonImportJob implements ShouldQueue
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
        $this->userId = $this->userId;

        // Set queue based on file size
        $this->onQueue('rekon-import');
    }

    /**
     * Execute the job.
     */
    public function handle(OptimizedRekonImportService $importService): void
    {
        try {
            Log::info('Starting Rekon import job', [
                'file_path' => $this->filePath,
                'original_name' => $this->originalName,
                'user_id' => $this->userId,
                'job_id' => $this->job->getJobId()
            ]);

            // Check if file still exists
            if (!Storage::disk('local')->exists($this->filePath)) {
                Log::error('Rekon file not found', ['file_path' => $this->filePath]);
                throw new \Exception('File not found: ' . $this->filePath);
            }

            // Get file info
            $fileInfo = Storage::disk('local')->size($this->filePath);
            Log::info('Processing Rekon file', [
                'file_size' => round($fileInfo / 1024 / 1024, 2) . ' MB'
            ]);

            // Create temporary file for processing
            $tempPath = sys_get_temp_dir() . '/' . uniqid('rekon_import_', true) . '.xlsx';
            Storage::disk('local')->copy($this->filePath, $tempPath);

            // Process the import
            $result = $importService->importFromFile(new \Illuminate\Http\UploadedFile(
                $tempPath,
                $this->originalName,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            ));

            // Log successful completion
            Log::info('Rekon import completed successfully', [
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

            // Store results for later retrieval
            $cacheKey = 'import_result_' . $this->userId . '_' . $this->job->getJobId();
            cache()->put($cacheKey, $result, now()->addHours(24));

        } catch (\Exception $e) {
            Log::error('Rekon import job failed', [
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

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Rekon import job permanently failed', [
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