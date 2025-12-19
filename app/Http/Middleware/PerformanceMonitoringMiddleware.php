<?php

namespace App\Http\Middleware;

use App\Services\PerformanceMonitoringService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PerformanceMonitoringMiddleware
{
    /**
     * Performance monitoring service instance
     */
    private PerformanceMonitoringService $monitor;

    /**
     * Constructor
     */
    public function __construct(PerformanceMonitoringService $monitor)
    {
        $this->monitor = $monitor;
    }

    /**
     * Handle an incoming request and monitor performance
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Generate unique request ID for tracking
        $requestId = uniqid('req_', true);
        $request->attributes->set('request_id', $requestId);

        try {
            $response = $next($request);

            // Calculate performance metrics
            $executionTime = (microtime(true) - $startTime) * 1000; // Convert to ms
            $memoryUsed = memory_get_usage(true) - $startMemory;

            // Record performance metrics
            $this->recordRequestMetrics($request, $response, $executionTime, $memoryUsed, $requestId);

            // Add performance headers
            $this->addPerformanceHeaders($response, $executionTime, $memoryUsed, $requestId);

            return $response;

        } catch (\Exception $e) {
            // Calculate metrics even for failed requests
            $executionTime = (microtime(true) - $startTime) * 1000;
            $memoryUsed = memory_get_usage(true) - $startMemory;

            $this->recordErrorMetrics($request, $e, $executionTime, $memoryUsed, $requestId);

            throw $e;
        }
    }

    /**
     * Record request performance metrics
     */
    private function recordRequestMetrics(
        Request $request,
        Response $response,
        float $executionTime,
        int $memoryUsed,
        string $requestId
    ): void {
        $tags = [
            'method' => $request->method(),
            'route' => $this->getRouteName($request),
            'status_code' => $response->getStatusCode(),
            'user_agent' => $this->getUserAgentType($request),
            'request_id' => $requestId
        ];

        // Record main metrics
        $this->monitor->recordMetric('request.execution_time', $executionTime, $tags);
        $this->monitor->recordMetric('request.memory_used', $memoryUsed, $tags);

        // Record slow requests
        if ($executionTime > 1000) { // > 1 second
            $this->monitor->recordMetric('request.slow_request', $executionTime, $tags);
        }

        // Record API specific metrics
        if ($request->is('api/*')) {
            $this->recordApiMetrics($request, $response, $executionTime, $tags);
        }

        // Record database intensive endpoints
        $dbIntensiveRoutes = [
            'dashboard.analytics',
            'rekon.search',
            'rekon.report'
        ];

        if (in_array($tags['route'], $dbIntensiveRoutes)) {
            $this->monitor->recordMetric('database_intensive.execution_time', $executionTime, $tags);
        }
    }

    /**
     * Record API-specific metrics
     */
    private function recordApiMetrics(
        Request $request,
        Response $response,
        float $executionTime,
        array $baseTags
    ): void {
        $apiTags = array_merge($baseTags, [
            'endpoint' => $request->path(),
            'response_size' => strlen($response->getContent())
        ]);

        // Record API response time
        $this->monitor->recordMetric('api.response_time', $executionTime, $apiTags);

        // Record API errors
        if ($response->getStatusCode() >= 400) {
            $this->monitor->recordMetric('api.error', 1, $apiTags);
        }

        // Record file upload metrics
        if ($request->hasFile('file') || $request->hasFile('bank_file')) {
            $fileSize = $request->hasFile('file')
                ? $request->file('file')->getSize()
                : $request->file('bank_file')->getSize();

            $uploadTags = array_merge($apiTags, [
                'file_size' => $fileSize,
                'file_type' => $request->hasFile('file') ? 'regular' : 'bank_csv'
            ]);

            $this->monitor->recordMetric('file.upload_time', $executionTime, $uploadTags);
        }
    }

    /**
     * Record error metrics
     */
    private function recordErrorMetrics(
        Request $request,
        \Exception $exception,
        float $executionTime,
        int $memoryUsed,
        string $requestId
    ): void {
        $tags = [
            'method' => $request->method(),
            'route' => $this->getRouteName($request),
            'exception_type' => get_class($exception),
            'exception_code' => $exception->getCode(),
            'request_id' => $requestId
        ];

        $this->monitor->recordMetric('request.error', 1, $tags);
        $this->monitor->recordMetric('request.error_execution_time', $executionTime, $tags);

        // Log error for debugging
        Log::error('Request error recorded', [
            'request_id' => $requestId,
            'route' => $tags['route'],
            'exception' => $exception->getMessage(),
            'execution_time' => $executionTime,
            'memory_used' => $memoryUsed
        ]);
    }

    /**
     * Add performance headers to response
     */
    private function addPerformanceHeaders(
        Response $response,
        float $executionTime,
        int $memoryUsed,
        string $requestId
    ): void {
        $response->headers->set('X-Request-ID', $requestId);
        $response->headers->set('X-Execution-Time', round($executionTime, 2) . 'ms');
        $response->headers->set('X-Memory-Used', round($memoryUsed / 1024 / 1024, 2) . 'MB');
        $response->headers->set('X-Peak-Memory', round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB');
    }

    /**
     * Get route name from request
     */
    private function getRouteName(Request $request): string
    {
        try {
            $route = $request->route();
            if ($route && $route->getName()) {
                return $route->getName();
            }
        } catch (\Exception $e) {
            // Route name not available
        }

        return $request->path();
    }

    /**
     * Get simplified user agent type
     */
    private function getUserAgentType(Request $request): string
    {
        $userAgent = $request->userAgent();

        if (strpos($userAgent, 'Mozilla') !== false) {
            if (strpos($userAgent, 'Chrome') !== false) return 'chrome';
            if (strpos($userAgent, 'Firefox') !== false) return 'firefox';
            if (strpos($userAgent, 'Safari') !== false) return 'safari';
            return 'browser';
        }

        if (strpos($userAgent, 'curl') !== false) return 'curl';
        if (strpos($userAgent, 'Postman') !== false) return 'postman';
        if (strpos($userAgent, 'axios') !== false) return 'axios';

        return 'other';
    }

    /**
     * Determine if performance monitoring should be skipped for this request
     */
    private function shouldSkipMonitoring(Request $request): bool
    {
        // Skip monitoring for health checks and static assets
        $skipPaths = [
            'health',
            'status',
            'ping',
            'metrics'
        ];

        foreach ($skipPaths as $path) {
            if ($request->is($path) || str_contains($request->path(), $path)) {
                return true;
            }
        }

        // Skip if request is from internal monitoring
        if ($request->header('User-Agent') && str_contains($request->header('User-Agent'), 'monitoring')) {
            return true;
        }

        return false;
    }
}