<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Performance Queue Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file contains settings for optimizing queue performance
    | in the SPP reconciliation system.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'database'),

    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
            'after_commit' => false,
            // Performance settings
            'batch_size' => 100,
            'max_tries' => 3,
            'memory_limit' => '512M',
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for' => null,
            'after_commit' => false,
            // Performance settings
            'batch_size' => 200,
            'max_tries' => 3,
            'memory_limit' => '256M',
        ],

        // High-performance queues for different job types
        'csv-import' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => 'csv-import',
            'retry_after' => 1800, // 30 minutes
            'block_for' => 10,
            'after_commit' => true,
            'batch_size' => 50,
            'max_tries' => 2,
            'memory_limit' => '1G',
        ],

        'rekon-import' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => 'rekon-import',
            'retry_after' => 1800, // 30 minutes
            'block_for' => 10,
            'after_commit' => true,
            'batch_size' => 25,
            'max_tries' => 2,
            'memory_limit' => '1G',
        ],

        'analytics' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => 'analytics',
            'retry_after' => 300, // 5 minutes
            'block_for' => 5,
            'after_commit' => false,
            'batch_size' => 500,
            'max_tries' => 1,
            'memory_limit' => '128M',
        ],

        'reports' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => 'reports',
            'retry_after' => 600, // 10 minutes
            'block_for' => 5,
            'after_commit' => true,
            'batch_size' => 100,
            'max_tries' => 2,
            'memory_limit' => '256M',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control which database and table are used to store the jobs that
    | have failed. You may change them to any database / table you wish.
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    |
    | Settings for queue performance monitoring and optimization
    |
    */

    'monitoring' => [
        'enabled' => env('QUEUE_MONITORING_ENABLED', true),
        'memory_threshold' => env('QUEUE_MEMORY_THRESHOLD', '512M'),
        'execution_time_threshold' => env('QUEUE_EXECUTION_THRESHOLD', 300), // seconds
        'batch_size_threshold' => env('QUEUE_BATCH_THRESHOLD', 100),
        'log_slow_jobs' => env('QUEUE_LOG_SLOW_JOBS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Default settings for queue workers to optimize performance
    |
    */

    'workers' => [
        'default' => [
            'connection' => env('QUEUE_CONNECTION', 'redis'),
            'queue' => ['default', 'analytics'],
            'delay' => env('QUEUE_DELAY', 0),
            'memory' => env('QUEUE_MEMORY_LIMIT', 256),
            'timeout' => env('QUEUE_TIMEOUT', 60),
            'sleep' => env('QUEUE_SLEEP', 3),
            'max_tries' => env('QUEUE_MAX_TRIES', 3),
            'force' => env('QUEUE_FORCE', false),
            'stop_when_empty' => env('QUEUE_STOP_WHEN_EMPTY', false),
            'max_jobs' => env('QUEUE_MAX_JOBS', 0),
            'max_time' => env('QUEUE_MAX_TIME', 0),
        ],

        'high_memory' => [
            'connection' => 'redis',
            'queue' => ['csv-import', 'rekon-import'],
            'delay' => 0,
            'memory' => 1024, // 1GB
            'timeout' => 1800, // 30 minutes
            'sleep' => 5,
            'max_tries' => 2,
            'force' => false,
            'stop_when_empty' => false,
            'max_jobs' => 1, // Process one job at a time for large files
            'max_time' => 3600, // 1 hour max
        ],

        'analytics' => [
            'connection' => 'redis',
            'queue' => ['analytics', 'reports'],
            'delay' => 0,
            'memory' => 128,
            'timeout' => 300,
            'sleep' => 1,
            'max_tries' => 1,
            'force' => false,
            'stop_when_empty' => false,
            'max_jobs' => 100,
            'max_time' => 600,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Batching Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for job batching to improve throughput
    |
    */

    'batching' => [
        'enabled' => env('QUEUE_BATCHING_ENABLED', true),
        'batch_size' => env('QUEUE_BATCH_SIZE', 100),
        'release_delay' => env('QUEUE_BATCH_RELEASE_DELAY', 5),
        'max_batch_time' => env('QUEUE_MAX_BATCH_TIME', 300), // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limiting configuration for queue processing
    |
    */

    'rate_limiting' => [
        'enabled' => env('QUEUE_RATE_LIMITING_ENABLED', false),
        'max_attempts' => env('QUEUE_RATE_LIMIT_ATTEMPTS', 60),
        'decay_minutes' => env('QUEUE_RATE_LIMIT_DECAY', 1),
    ],
];