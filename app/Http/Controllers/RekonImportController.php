<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportFileRequest;
use App\Services\RekonImportService;
use App\Services\BankCsvImportService;
use App\Services\OptimizedRekonImportService;
use App\Services\OptimizedBankCsvImportService;
use App\Services\PerformanceMonitoringService;
use App\Services\LoggingService;
use App\Jobs\ProcessRekonImportJob;
use App\Jobs\ProcessBankCsvImportJob;
use App\Exceptions\ImportException;
use App\Exceptions\FileProcessingException;
use App\Exceptions\DatabaseException;
use App\Exceptions\ValidationException;
use App\Exceptions\AuthenticationException;
use App\Exceptions\RateLimitException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException as LaravelValidationException;

class RekonImportController extends Controller
{
    private $rekonImportService;
    private $bankCsvImportService;
    private $optimizedRekonImportService;
    private $optimizedBankCsvImportService;
    private $performanceMonitor;
    private $logger;

    public function __construct(
        RekonImportService $rekonImportService,
        BankCsvImportService $bankCsvImportService,
        OptimizedRekonImportService $optimizedRekonImportService,
        OptimizedBankCsvImportService $optimizedBankCsvImportService,
        PerformanceMonitoringService $performanceMonitor,
        LoggingService $logger
    ) {
        $this->rekonImportService = $rekonImportService;
        $this->bankCsvImportService = $bankCsvImportService;
        $this->optimizedRekonImportService = $optimizedRekonImportService;
        $this->optimizedBankCsvImportService = $optimizedBankCsvImportService;
        $this->performanceMonitor = $performanceMonitor;
        $this->logger = $logger;
    }

    /**
     * Import data dari CSV/Excel (Legacy) with enhanced error handling
     */
    public function import(ImportFileRequest $request): JsonResponse
    {
        $startTime = microtime(true);
        $requestId = 'req_import_' . uniqid();

        try {
            $this->logger->info('Starting legacy import request', [
                'request_id' => $requestId,
                'user_id' => Auth::id(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return $this->performanceMonitor->measure('rekon.import.legacy', function () use ($request, $requestId, $startTime) {
                try {
                    // Validate API key
                    if (!$this->validateApiKey($request)) {
                        throw new AuthenticationException(
                            'Invalid API key',
                            'API key tidak valid. Pastikan API key yang digunakan benar.',
                            $request->header('X-API-KEY'),
                            $request->userAgent(),
                            $request->ip()
                        );
                    }

                    $file = $request->file('file');
                    if (!$file) {
                        throw new ValidationException(
                            $request->validator ?? $this->createValidator($request),
                            'File tidak ditemukan. Pastikan Anda mengunggah file.'
                        );
                    }

                    $fileSize = $file->getSize();

                    // Record file size metrics
                    $this->performanceMonitor->recordMetric('file.import.size_mb', $fileSize / 1024 / 1024, [
                        'type' => 'legacy',
                        'extension' => $file->getClientOriginalExtension()
                    ]);

                    $this->logger->info('File validation passed', [
                        'request_id' => $requestId,
                        'file_name' => $file->getClientOriginalName(),
                        'file_size_mb' => round($fileSize / 1024 / 1024, 2),
                        'file_type' => $file->getClientOriginalExtension()
                    ]);

                    // Use optimized service for large files (>5MB)
                    if ($fileSize > 5 * 1024 * 1024) {
                        return $this->handleLargeFileImport($file, 'legacy', $requestId);
                    }

                    $result = $this->rekonImportService->importFromFile($file);

                    // Record success metrics
                    $this->performanceMonitor->recordMetric('file.import.success', 1, [
                        'type' => 'legacy',
                        'rows_processed' => $result['imported'],
                        'errors' => $result['error_count'],
                        'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
                    ]);

                    $response = [
                        'success' => true,
                        'message' => "Berhasil mengimport {$result['imported']} data",
                        'imported' => $result['imported'],
                        'total_rows' => $result['total_rows'],
                        'errors' => $result['errors'],
                        'error_count' => $result['error_count'],
                        'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                        'request_id' => $requestId
                    ];

                    $this->logger->info('Legacy import completed successfully', [
                        'request_id' => $requestId,
                        'result' => $result
                    ]);

                    return response()->json($response);

                } catch (AuthenticationException $e) {
                    return $this->handleAuthenticationException($e, $requestId);
                } catch (ValidationException | LaravelValidationException $e) {
                    return $this->handleValidationException($e, $requestId);
                } catch (ImportException | FileProcessingException | DatabaseException $e) {
                    return $this->handleCustomException($e, $requestId);
                }
            });

        } catch (\Exception $e) {
            return $this->handleGenericException($e, $requestId, 'legacy import');
        }
    }

    /**
     * Import CSV dari Bank with performance monitoring and optimization
     */
    public function importBankCsv(ImportFileRequest $request): JsonResponse
    {
        return $this->performanceMonitor->measure('rekon.import.bank_csv', function () use ($request) {
            try {
                $file = $request->file('file');
                $fileSize = $file->getSize();

                // Record file size metrics
                $this->performanceMonitor->recordMetric('file.import.size_mb', $fileSize / 1024 / 1024, [
                    'type' => 'bank_csv',
                    'extension' => $file->getClientOriginalExtension()
                ]);

                // Use optimized service for large files (>5MB)
                if ($fileSize > 5 * 1024 * 1024) {
                    return $this->handleLargeFileImport($file, 'bank_csv');
                }

                $result = $this->bankCsvImportService->importFromBankCsv($file);

                // Record success metrics
                $this->performanceMonitor->recordMetric('file.import.success', 1, [
                    'type' => 'bank_csv',
                    'rows_processed' => $result['imported'],
                    'duplicates' => $result['duplicates'],
                    'errors' => $result['error_count']
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "Berhasil mengimport {$result['imported']} data dari CSV Bank",
                    'imported' => $result['imported'],
                    'duplicates' => $result['duplicates'],
                    'total_rows' => $result['total_rows'],
                    'errors' => $result['errors'],
                    'error_count' => $result['error_count']
                ]);

            } catch (\Exception $e) {
                // Record error metrics
                $this->performanceMonitor->recordMetric('file.import.error', 1, [
                    'type' => 'bank_csv',
                    'error' => $e->getMessage()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error importing Bank CSV: ' . $e->getMessage()
                ], 500);
            }
        });
    }

    /**
     * Handle large file imports with background processing
     */
    private function handleLargeFileImport($file, string $type, string $requestId): JsonResponse
    {
        try {
            $userId = Auth::id() ?: 1; // Fallback to user ID 1 if not authenticated
            $originalName = $file->getClientOriginalName();
            $filename = uniqid('import_', true) . '.' . $file->getClientOriginalExtension();

            // Store file temporarily
            $filePath = $file->storeAs('temp/imports', $filename, 'local');

            // Queue the import job
            if ($type === 'bank_csv') {
                $job = new ProcessBankCsvImportJob($filePath, $originalName, $userId);
                $jobId = $job->getJobId();
                dispatch($job->onQueue('csv-import'));
            } else {
                $job = new ProcessRekonImportJob($filePath, $originalName, $userId);
                $jobId = $job->getJobId();
                dispatch($job->onQueue('rekon-import'));
            }

            // Record queue metrics
            $this->performanceMonitor->recordMetric('file.import.queued', 1, [
                'type' => $type,
                'job_id' => $jobId,
                'file_size_mb' => $file->getSize() / 1024 / 1024
            ]);

            $this->logger->info('Large file queued for processing', [
                'request_id' => $requestId,
                'job_id' => $jobId,
                'file_name' => $originalName,
                'file_size_mb' => round($file->getSize() / 1024 / 1024, 2),
                'type' => $type
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File besar terdeteksi. Import akan diproses di background.',
                'queued' => true,
                'job_id' => $jobId,
                'file_name' => $originalName,
                'estimated_time' => $this->estimateProcessingTime($file->getSize(), $type),
                'request_id' => $requestId
            ]);

        } catch (\Exception $e) {
            $this->performanceMonitor->recordMetric('file.import.queue_error', 1, [
                'type' => $type,
                'error' => $e->getMessage()
            ]);

            $this->logger->error('Failed to queue large file import', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'file_name' => $file->getClientOriginalName(),
                'type' => $type
            ]);

            throw new FileProcessingException(
                'Failed to queue import job: ' . $e->getMessage(),
                'Gagal memproses file besar. Silakan coba lagi atau hubungi administrator.',
                $file->getClientOriginalName(),
                $file->getSize(),
                $file->getClientOriginalExtension(),
                $file->getRealPath(),
                ['type' => $type, 'original_error' => $e->getMessage()]
            );
        }
    }

    /**
     * Validate API key
     */
    private function validateApiKey(Request $request): bool
    {
        $apiKey = $request->header('X-API-KEY');
        $expectedKey = 'spp-rekon-2024-secret-key';

        if (!$apiKey || $apiKey !== $expectedKey) {
            $this->logger->logSecurity('Invalid API key attempt', [
                'provided_key' => $apiKey ? substr($apiKey, 0, 8) . '***' : null,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'endpoint' => $request->path()
            ]);

            return false;
        }

        return true;
    }

    /**
     * Handle authentication exceptions
     */
    private function handleAuthenticationException(AuthenticationException $e, string $requestId): JsonResponse
    {
        $this->logger->warning('Authentication failed', [
            'request_id' => $requestId,
            'error_code' => $e->getErrorCode(),
            'context' => $e->getContext()
        ]);

        return response()->json([
            'success' => false,
            'error_code' => $e->getErrorCode(),
            'message' => $e->getUserMessage(),
            'timestamp' => now()->toISOString(),
            'request_id' => $requestId
        ], $e->getHttpStatusCode());
    }

    /**
     * Handle validation exceptions
     */
    private function handleValidationException($e, string $requestId): JsonResponse
    {
        $errors = [];
        if ($e instanceof LaravelValidationException) {
            $errors = $e->errors();
        } elseif ($e instanceof ValidationException) {
            $errors = $e->getErrors();
        }

        $this->logger->warning('Validation failed', [
            'request_id' => $requestId,
            'errors' => $errors,
            'error_code' => $e->getErrorCode() ?? 'VAL-' . strtoupper(uniqid())
        ]);

        return response()->json([
            'success' => false,
            'error_code' => $e->getErrorCode() ?? 'VAL-' . strtoupper(uniqid()),
            'message' => $e->getUserMessage() ?? 'Data yang Anda masukkan tidak valid.',
            'validation_errors' => $errors,
            'timestamp' => now()->toISOString(),
            'request_id' => $requestId
        ], 422);
    }

    /**
     * Handle custom exceptions
     */
    private function handleCustomException($e, string $requestId): JsonResponse
    {
        $this->logger->error('Custom exception occurred', [
            'request_id' => $requestId,
            'error_code' => $e->getErrorCode(),
            'context' => $e->getContext(),
            'message' => $e->getMessage()
        ]);

        return response()->json(array_merge($e->toArray(), [
            'request_id' => $requestId
        ]), $e->getHttpStatusCode());
    }

    /**
     * Handle generic exceptions
     */
    private function handleGenericException(\Exception $e, string $requestId, string $operation): JsonResponse
    {
        $this->logger->error('Unexpected error in ' . $operation, [
            'request_id' => $requestId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'operation' => $operation
        ]);

        return response()->json([
            'success' => false,
            'error_code' => 'SRV-' . strtoupper(uniqid()),
            'message' => 'Terjadi kesalahan pada server. Silakan coba beberapa saat lagi.',
            'timestamp' => now()->toISOString(),
            'request_id' => $requestId
        ], 500);
    }

    /**
     * Create validator for missing file
     */
    private function createValidator(Request $request)
    {
        return \Illuminate\Support\Facades\Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,xlsx,xls|max:51200'
        ], [
            'file.required' => 'File harus diunggah.',
            'file.file' => 'File tidak valid.',
            'file.mimes' => 'Format file harus CSV, XLSX, atau XLS.',
            'file.max' => 'Ukuran file maksimal 50MB.'
        ]);
    }

    /**
     * Check import job status
     */
    public function checkImportStatus(Request $request): JsonResponse
    {
        $jobId = $request->get('job_id');
        $userId = Auth::id() ?: 1;

        if (!$jobId) {
            return response()->json([
                'success' => false,
                'message' => 'Job ID diperlukan'
            ], 400);
        }

        $cacheKey = 'import_result_' . $userId . '_' . $jobId;
        $result = cache()->get($cacheKey);

        if (!$result) {
            return response()->json([
                'success' => true,
                'status' => 'processing',
                'message' => 'Import sedang diproses...'
            ]);
        }

        return response()->json([
            'success' => true,
            'status' => isset($result['success']) && $result['success'] ? 'completed' : 'failed',
            'result' => $result
        ]);
    }

    /**
     * Estimate processing time based on file size
     */
    private function estimateProcessingTime(int $fileSize, string $type): string
    {
        // Base processing rates (rows per second)
        $baseRates = [
            'legacy' => 100,   // ~100 rows/second
            'bank_csv' => 200  // ~200 rows/second (CSV is faster)
        ];

        $baseRate = $baseRates[$type] ?? 100;
        $estimatedRows = $fileSize / 500; // Average 500 bytes per row
        $estimatedSeconds = $estimatedRows / $baseRate;

        // Add buffer time for database operations
        $estimatedSeconds *= 1.5;

        if ($estimatedSeconds < 60) {
            return "~" . round($estimatedSeconds) . " detik";
        } elseif ($estimatedSeconds < 3600) {
            return "~" . round($estimatedSeconds / 60) . " menit";
        } else {
            return "~" . round($estimatedSeconds / 3600, 1) . " jam";
        }
    }

    /**
     * Get import history (if needed for monitoring)
     */
    public function getImportHistory(Request $request): JsonResponse
    {
        return $this->performanceMonitor->measure('rekon.import.history', function () use ($request) {
            try {
                $userId = Auth::id() ?: 1;
                $limit = min($request->get('limit', 10), 50); // Max 50 records

                // This would typically query a database table that tracks import history
                // For now, return a placeholder response
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'Import history feature coming soon'
                ]);

            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengambil riwayat import: ' . $e->getMessage()
                ], 500);
            }
        });
    }
}