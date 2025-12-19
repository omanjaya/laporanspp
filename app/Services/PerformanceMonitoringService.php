<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PerformanceMonitoringService
{
    /**
     * Cache performance metrics
     */
    private const CACHE_KEY_PREFIX = 'performance_metrics_';
    private const CACHE_DURATION = 3600; // 1 hour

    /**
     * Record application performance metrics
     */
    public function recordMetric(string $metric, $value, array $tags = []): void
    {
        try {
            $timestamp = now();
            $metrics = $this->getMetrics();

            $metrics[] = [
                'metric' => $metric,
                'value' => $value,
                'timestamp' => $timestamp->toISOString(),
                'tags' => $tags,
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true)
            ];

            // Keep only last 1000 metrics to prevent memory issues
            if (count($metrics) > 1000) {
                $metrics = array_slice($metrics, -1000);
            }

            $this->storeMetrics($metrics);

            // Log critical metrics
            $this->logCriticalMetrics($metric, $value, $tags);

        } catch (\Exception $e) {
            Log::error('Failed to record performance metric', [
                'metric' => $metric,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Measure execution time of a callable
     */
    public function measure(string $name, callable $callback, array $tags = [])
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        try {
            $result = $callback();
            $success = true;
            $error = null;
        } catch (\Exception $e) {
            $success = false;
            $error = $e->getMessage();
            throw $e;
        } finally {
            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);

            $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
            $memoryUsed = $endMemory - $startMemory;

            $this->recordMetric($name . '.execution_time', $executionTime, array_merge($tags, [
                'success' => $success,
                'memory_used' => $memoryUsed
            ]));

            if (!$success) {
                $this->recordMetric($name . '.error', 1, array_merge($tags, [
                    'error' => $error
                ]));
            }
        }

        return $result;
    }

    /**
     * Record database query performance
     */
    public function recordQueryPerformance(float $executionTime, string $query, array $bindings = []): void
    {
        // Only log slow queries (>100ms)
        if ($executionTime > 100) {
            $this->recordMetric('database.slow_query', $executionTime, [
                'query_hash' => md5($query),
                'query_type' => $this->getQueryType($query),
                'binding_count' => count($bindings)
            ]);

            Log::warning('Slow query detected', [
                'execution_time' => $executionTime,
                'query' => $query,
                'bindings' => $bindings
            ]);
        }

        $this->recordMetric('database.query_time', $executionTime, [
            'query_type' => $this->getQueryType($query)
        ]);
    }

    /**
     * Get system performance metrics
     */
    public function getSystemMetrics(): array
    {
        return [
            'memory_usage' => [
                'current' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
            ],
            'database' => $this->getDatabaseMetrics(),
            'cache' => $this->getCacheMetrics(),
            'php' => [
                'version' => PHP_VERSION,
                'max_execution_time' => ini_get('max_execution_time'),
                'memory_limit' => ini_get('memory_limit'),
                'upload_max_filesize' => ini_get('upload_max_filesize')
            ]
        ];
    }

    /**
     * Get database performance metrics
     */
    private function getDatabaseMetrics(): array
    {
        try {
            $connection = DB::connection();
            $pdo = $connection->getPdo();

            return [
                'connection_name' => $connection->getName(),
                'driver' => $connection->getDriverName(),
                'version' => $pdo ? $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION) : 'Unknown',
                'queries_today' => $this->getQueryCountToday(),
                'slow_queries_today' => $this->getSlowQueryCountToday()
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get database metrics', ['error' => $e->getMessage()]);
            return ['error' => 'Failed to retrieve database metrics'];
        }
    }

    /**
     * Get cache metrics
     */
    private function getCacheMetrics(): array
    {
        try {
            $cache = Cache::getStore();
            $cacheClass = get_class($cache);

            return [
                'driver' => config('cache.default'),
                'class' => $cacheClass,
                'test_hit' => Cache::add('performance_test', 'test', 60) ? 'miss' : 'hit',
                'test_value' => Cache::get('performance_test')
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get cache metrics', ['error' => $e->getMessage()]);
            return ['error' => 'Failed to retrieve cache metrics'];
        }
    }

    /**
     * Get query count for today
     */
    private function getQueryCountToday(): int
    {
        try {
            // This would need to be implemented based on your database
            // For MySQL, you could query the information_schema
            return DB::table('performance_log')
                ->whereDate('created_at', today())
                ->where('metric', 'like', 'database.%')
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get slow query count for today
     */
    private function getSlowQueryCountToday(): int
    {
        try {
            return DB::table('performance_log')
                ->whereDate('created_at', today())
                ->where('metric', 'database.slow_query')
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get query type from SQL query
     */
    private function getQueryType(string $query): string
    {
        $query = strtoupper(trim($query));

        if (strpos($query, 'SELECT') === 0) return 'SELECT';
        if (strpos($query, 'INSERT') === 0) return 'INSERT';
        if (strpos($query, 'UPDATE') === 0) return 'UPDATE';
        if (strpos($query, 'DELETE') === 0) return 'DELETE';
        if (strpos($query, 'CREATE') === 0) return 'CREATE';
        if (strpos($query, 'ALTER') === 0) return 'ALTER';
        if (strpos($query, 'DROP') === 0) return 'DROP';

        return 'OTHER';
    }

    /**
     * Get stored metrics
     */
    private function getMetrics(): array
    {
        return Cache::get(self::CACHE_KEY_PREFIX . 'metrics', []);
    }

    /**
     * Store metrics
     */
    private function storeMetrics(array $metrics): void
    {
        Cache::put(self::CACHE_KEY_PREFIX . 'metrics', $metrics, self::CACHE_DURATION);
    }

    /**
     * Log critical metrics for monitoring
     */
    private function logCriticalMetrics(string $metric, $value, array $tags): void
    {
        $criticalMetrics = [
            'database.slow_query',
            'memory.high_usage',
            'api.error_rate',
            'file.import.error'
        ];

        if (in_array($metric, $criticalMetrics) ||
            (is_numeric($value) && $value > 1000)) { // High threshold for alerts

            Log::critical('Critical performance metric', [
                'metric' => $metric,
                'value' => $value,
                'tags' => $tags,
                'timestamp' => now()->toISOString()
            ]);
        }
    }

    /**
     * Get performance report
     */
    public function getPerformanceReport(string $period = '24h'): array
    {
        try {
            $metrics = $this->getMetrics();
            $now = now();

            $filterDate = match($period) {
                '1h' => $now->subHour(),
                '24h' => $now->subDay(),
                '7d' => $now->subWeek(),
                default => $now->subDay()
            };

            $filteredMetrics = array_filter($metrics, function($metric) use ($filterDate) {
                return Carbon::parse($metric['timestamp'])->gte($filterDate);
            });

            return [
                'period' => $period,
                'total_metrics' => count($filteredMetrics),
                'summary' => $this->summarizeMetrics($filteredMetrics),
                'system_metrics' => $this->getSystemMetrics(),
                'alerts' => $this->getPerformanceAlerts($filteredMetrics)
            ];

        } catch (\Exception $e) {
            Log::error('Failed to generate performance report', ['error' => $e->getMessage()]);
            return ['error' => 'Failed to generate performance report'];
        }
    }

    /**
     * Summarize metrics
     */
    private function summarizeMetrics(array $metrics): array
    {
        $summary = [];
        $groupedMetrics = [];

        // Group metrics by name
        foreach ($metrics as $metric) {
            $name = $metric['metric'];
            if (!isset($groupedMetrics[$name])) {
                $groupedMetrics[$name] = [];
            }
            $groupedMetrics[$name][] = $metric['value'];
        }

        // Calculate statistics for each metric group
        foreach ($groupedMetrics as $name => $values) {
            if (empty($values)) continue;

            $numericValues = array_filter($values, 'is_numeric');

            if (!empty($numericValues)) {
                $summary[$name] = [
                    'count' => count($numericValues),
                    'avg' => round(array_sum($numericValues) / count($numericValues), 2),
                    'min' => min($numericValues),
                    'max' => max($numericValues),
                    'latest' => end($numericValues)
                ];
            }
        }

        return $summary;
    }

    /**
     * Get performance alerts
     */
    private function getPerformanceAlerts(array $metrics): array
    {
        $alerts = [];

        // Check for performance issues
        $slowQueries = array_filter($metrics, function($metric) {
            return $metric['metric'] === 'database.slow_query' && $metric['value'] > 1000;
        });

        if (count($slowQueries) > 5) {
            $alerts[] = [
                'type' => 'warning',
                'message' => 'High number of slow queries detected',
                'count' => count($slowQueries)
            ];
        }

        // Check memory usage
        $highMemoryUsage = array_filter($metrics, function($metric) {
            return isset($metric['memory_usage']) && $metric['memory_usage'] > 128 * 1024 * 1024; // 128MB
        });

        if (count($highMemoryUsage) > 3) {
            $alerts[] = [
                'type' => 'warning',
                'message' => 'High memory usage detected',
                'count' => count($highMemoryUsage)
            ];
        }

        return $alerts;
    }

    /**
     * Cleanup old metrics
     */
    public function cleanupOldMetrics(int $daysToKeep = 7): void
    {
        try {
            $metrics = $this->getMetrics();
            $cutoffDate = now()->subDays($daysToKeep);

            $filteredMetrics = array_filter($metrics, function($metric) use ($cutoffDate) {
                return Carbon::parse($metric['timestamp'])->gte($cutoffDate);
            });

            $this->storeMetrics(array_values($filteredMetrics));

            Log::info('Performance metrics cleanup completed', [
                'removed_count' => count($metrics) - count($filteredMetrics),
                'remaining_count' => count($filteredMetrics)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to cleanup performance metrics', ['error' => $e->getMessage()]);
        }
    }
}