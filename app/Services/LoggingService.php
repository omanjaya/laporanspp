<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Auth;

class LoggingService
{
    private const LOG_LEVELS = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
        'critical' => 4,
    ];

    private array $context;
    private string $channel;

    public function __construct(string $channel = 'spp_rekon')
    {
        $this->channel = $channel;
        $this->context = $this->getBaseContext();
    }

    /**
     * Log debug message
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * Log info message
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Log warning message
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Log error message
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * Log critical message
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /**
     * Log API request
     */
    public function logApiRequest(string $endpoint, string $method, array $data = [], array $response = [], int $duration = 0): void
    {
        $context = [
            'type' => 'api_request',
            'endpoint' => $endpoint,
            'method' => $method,
            'request_data' => $this->sanitizeData($data),
            'response_data' => $this->sanitizeData($response),
            'duration_ms' => $duration,
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ];

        $level = $duration > 5000 ? 'warning' : 'info';

        $this->log($level, "API Request: {$method} {$endpoint}", $context);
    }

    /**
     * Log file import operation
     */
    public function logFileImport(string $fileName, int $fileSize, string $type, array $result): void
    {
        $context = [
            'type' => 'file_import',
            'file_name' => $fileName,
            'file_size_mb' => round($fileSize / 1024 / 1024, 2),
            'import_type' => $type,
            'result' => $result,
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ];

        $level = ($result['error_count'] ?? 0) > 0 ? 'warning' : 'info';

        $this->log($level, "File Import: {$fileName} ({$type})", $context);
    }

    /**
     * Log database operation
     */
    public function logDatabaseOperation(string $operation, string $table, array $data = [], float $duration = 0): void
    {
        $context = [
            'type' => 'database_operation',
            'operation' => $operation,
            'table' => $table,
            'data_count' => is_array($data) ? count($data) : 0,
            'duration_ms' => $duration,
        ];

        $level = $duration > 1000 ? 'warning' : 'debug';

        $this->log($level, "Database Operation: {$operation} on {$table}", $context);
    }

    /**
     * Log performance metrics
     */
    public function logPerformance(string $operation, array $metrics): void
    {
        $context = [
            'type' => 'performance',
            'operation' => $operation,
            'metrics' => $metrics,
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ];

        // Determine log level based on metrics
        $level = 'info';
        if (isset($metrics['duration_ms']) && $metrics['duration_ms'] > 5000) {
            $level = 'warning';
        }
        if (isset($metrics['memory_peak_mb']) && $metrics['memory_peak_mb'] > 256) {
            $level = 'warning';
        }

        $this->log($level, "Performance: {$operation}", $context);
    }

    /**
     * Log security events
     */
    public function logSecurity(string $event, array $context = []): void
    {
        $context = array_merge([
            'type' => 'security',
            'event' => $event,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ], $context);

        $this->log('warning', "Security Event: {$event}", $context);
    }

    /**
     * Log user activity
     */
    public function logUserActivity(string $action, array $context = []): void
    {
        $context = array_merge([
            'type' => 'user_activity',
            'action' => $action,
        ], $context);

        $this->log('info', "User Activity: {$action}", $context);
    }

    /**
     * Create context for batch operations
     */
    public function createBatchContext(string $batchId, string $operation): array
    {
        return [
            'batch_id' => $batchId,
            'operation' => $operation,
            'type' => 'batch_operation',
            'started_at' => now()->toISOString(),
        ];
    }

    /**
     * Update batch context with progress
     */
    public function updateBatchProgress(array &$context, int $processed, int $total): void
    {
        $context['processed'] = $processed;
        $context['total'] = $total;
        $context['progress_percent'] = $total > 0 ? round(($processed / $total) * 100, 2) : 0;
        $context['updated_at'] = now()->toISOString();

        $this->info('Batch Progress Update', $context);
    }

    /**
     * Complete batch operation
     */
    public function completeBatch(array &$context, array $result): void
    {
        $context['completed_at'] = now()->toISOString();
        $context['result'] = $result;
        $context['duration_seconds'] = now()->diffInSeconds($context['started_at']);

        $level = ($result['error_count'] ?? 0) > 0 ? 'warning' : 'info';

        $this->log($level, 'Batch Operation Completed', $context);
    }

    /**
     * Generic log method
     */
    private function log(string $level, string $message, array $context = []): void
    {
        $fullContext = array_merge($this->context, $context);
        $fullContext['timestamp'] = now()->toISOString();
        $fullContext['log_channel'] = $this->channel;

        // Add correlation ID for request tracking
        if (!isset($fullContext['correlation_id'])) {
            $fullContext['correlation_id'] = $this->getCorrelationId();
        }

        Log::channel($this->channel)->log($level, $message, $fullContext);

        // For critical errors, also log to default channel
        if ($level === 'critical') {
            Log::critical("[{$this->channel}] {$message}", $fullContext);
        }
    }

    /**
     * Get base context with user and request information
     */
    private function getBaseContext(): array
    {
        $context = [];

        if (Auth::check()) {
            $context['user'] = [
                'id' => Auth::id(),
                'email' => Auth::user()->email ?? null,
            ];
        }

        $context['request'] = [
            'method' => Request::method(),
            'url' => Request::fullUrl(),
            'ip' => Request::ip(),
        ];

        return $context;
    }

    /**
     * Get or create correlation ID for request tracking
     */
    private function getCorrelationId(): string
    {
        static $correlationId = null;

        if ($correlationId === null) {
            $correlationId = 'req_' . uniqid() . '_' . time();
        }

        return $correlationId;
    }

    /**
     * Sanitize sensitive data from logs
     */
    private function sanitizeData(array $data): array
    {
        $sensitiveKeys = [
            'password', 'password_confirmation', 'api_key', 'token',
            'secret', 'key', 'authorization', 'auth'
        ];

        return collect($data)->mapWithKeys(function ($value, $key) use ($sensitiveKeys) {
            if (is_string($key) && in_array(strtolower($key), $sensitiveKeys)) {
                return [$key => '[FILTERED]'];
            }

            if (is_array($value)) {
                return [$key => $this->sanitizeData($value)];
            }

            return [$key => $value];
        })->toArray();
    }
}