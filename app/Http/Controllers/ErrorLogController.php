<?php

namespace App\Http\Controllers;

use App\Services\LoggingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ErrorLogController extends Controller
{
    private LoggingService $logger;

    public function __construct(LoggingService $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Log frontend errors
     */
    public function logFrontendError(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'error_type' => 'required|string',
                'message' => 'nullable|string',
                'url' => 'nullable|string',
                'user_agent' => 'nullable|string',
                'filename' => 'nullable|string',
                'lineno' => 'nullable|integer',
                'colno' => 'nullable|integer',
                'stack' => 'nullable|string',
                'reason' => 'nullable|string',
                'context' => 'nullable|array'
            ]);

            $this->logger->warning('Frontend Error', [
                'error_type' => $data['error_type'],
                'message' => $data['message'] ?? 'Unknown error',
                'url' => $data['url'] ?? 'Unknown URL',
                'user_agent' => $data['user_agent'] ?? 'Unknown UA',
                'filename' => $data['filename'] ?? null,
                'line_number' => $data['lineno'] ?? null,
                'column_number' => $data['colno'] ?? null,
                'stack_trace' => $data['stack'] ?? null,
                'reason' => $data['reason'] ?? null,
                'context' => $data['context'] ?? [],
                'ip_address' => $request->ip(),
                'timestamp' => now()->toISOString()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Error logged successfully'
            ]);

        } catch (\Exception $e) {
            // Don't throw errors from error logging to prevent infinite loops
            return response()->json([
                'success' => false,
                'message' => 'Failed to log error'
            ], 500);
        }
    }

    /**
     * Get error statistics
     */
    public function getErrorStats(Request $request): JsonResponse
    {
        try {
            // This would typically query a database table that stores error statistics
            // For now, return a placeholder response
            return response()->json([
                'success' => true,
                'data' => [
                    'total_errors_today' => 0,
                    'most_common_errors' => [],
                    'errors_by_type' => [],
                    'recent_errors' => []
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get error statistics'
            ], 500);
        }
    }
}