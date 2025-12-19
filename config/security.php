<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains security settings for the SPP application.
    | Configure these settings according to your security requirements.
    |
    */

    'api_keys' => [
        /*
        |--------------------------------------------------------------------------
        | API Key Configuration
        |--------------------------------------------------------------------------
        |
        | Settings for API key management and validation.
        |
        */
        'rotation_days' => env('API_KEY_ROTATION_DAYS', 90), // Rotate keys every 90 days
        'max_keys_per_user' => env('MAX_KEYS_PER_USER', 5),
        'usage_tracking' => env('API_KEY_USAGE_TRACKING', true),
        'suspicious_threshold' => env('SUSPICIOUS_USAGE_THRESHOLD', 1000), // requests per hour
    ],

    'rate_limiting' => [
        /*
        |--------------------------------------------------------------------------
        | Rate Limiting Configuration
        |--------------------------------------------------------------------------
        |
        | Configure rate limiting for different types of requests.
        |
        */
        'authenticated' => [
            'per_minute' => 120,
            'per_hour' => 2000,
            'per_day' => 10000,
        ],
        'unauthenticated' => [
            'per_minute' => 60,
            'per_hour' => 1000,
            'per_day' => 5000,
        ],
        'import' => [
            'per_minute' => 10,
            'per_hour' => 100,
            'per_day' => 500,
        ],
        'export' => [
            'per_minute' => 20,
            'per_hour' => 200,
            'per_day' => 1000,
        ],
    ],

    'upload' => [
        /*
        |--------------------------------------------------------------------------
        | File Upload Security
        |--------------------------------------------------------------------------
        |
        | Security settings for file uploads.
        |
        */
        'max_file_size' => env('MAX_UPLOAD_SIZE', 10240), // KB (10MB)
        'allowed_extensions' => ['csv', 'xlsx', 'xls'],
        'scan_for_malware' => env('SCAN_UPLOADS', true),
        'quarantine_suspicious' => env('QUARANTINE_UPLOADS', true),
    ],

    'session' => [
        /*
        |--------------------------------------------------------------------------
        | Session Security
        |--------------------------------------------------------------------------
        |
        | Security settings for user sessions.
        |
        */
        'lifetime' => env('SESSION_LIFETIME', 120), // minutes
        'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),
        'secure' => env('SESSION_SECURE', true), // HTTPS only
        'http_only' => env('SESSION_HTTP_ONLY', true),
        'same_site' => env('SESSION_SAME_SITE', 'strict'),
        'regenerate_id' => true,
        'max_concurrent_sessions' => env('MAX_CONCURRENT_SESSIONS', 3),
    ],

    'headers' => [
        /*
        |--------------------------------------------------------------------------
        | Security Headers
        |--------------------------------------------------------------------------
        |
        | Configure security headers sent with responses.
        |
        */
        'x_frame_options' => 'DENY',
        'x_content_type_options' => 'nosniff',
        'x_xss_protection' => '1; mode=block',
        'strict_transport_security' => 'max-age=31536000; includeSubDomains; preload',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'content_security_policy' => [
            'default-src' => "'self'",
            'script-src' => "'self' 'unsafe-inline'",
            'style-src' => "'self' 'unsafe-inline'",
            'img-src' => "'self' data: https:",
            'font-src' => "'self'",
            'connect-src' => "'self'",
            'frame-ancestors' => "'none'",
            'base-uri' => "'self'",
            'form-action' => "'self'",
        ],
    ],

    'logging' => [
        /*
        |--------------------------------------------------------------------------
        | Security Logging
        |--------------------------------------------------------------------------
        |
        | Configure security event logging.
        |
        */
        'log_failed_attempts' => env('LOG_FAILED_ATTEMPTS', true),
        'log_rate_limit_violations' => env('LOG_RATE_LIMIT_VIOLATIONS', true),
        'log_suspicious_activity' => env('LOG_SUSPICIOUS_ACTIVITY', true),
        'retention_days' => env('SECURITY_LOG_RETENTION_DAYS', 90),
    ],

    'password' => [
        /*
        |--------------------------------------------------------------------------
        | Password Policy
        |--------------------------------------------------------------------------
        |
        | Password security requirements.
        |
        */
        'min_length' => env('PASSWORD_MIN_LENGTH', 8),
        'require_uppercase' => env('PASSWORD_REQUIRE_UPPERCASE', true),
        'require_lowercase' => env('PASSWORD_REQUIRE_LOWERCASE', true),
        'require_numbers' => env('PASSWORD_REQUIRE_NUMBERS', true),
        'require_special_chars' => env('PASSWORD_REQUIRE_SPECIAL', true),
        'max_age_days' => env('PASSWORD_MAX_AGE_DAYS', 90),
    ],

    'encryption' => [
        /*
        |--------------------------------------------------------------------------
        | Encryption Settings
        |--------------------------------------------------------------------------
        |
        | Configure encryption settings for sensitive data.
        |
        */
        'algorithm' => 'AES-256-GCM',
        'key_rotation_days' => env('ENCRYPTION_KEY_ROTATION_DAYS', 365),
    ],

    'monitoring' => [
        /*
        |--------------------------------------------------------------------------
        | Security Monitoring
        |--------------------------------------------------------------------------
        |
        | Configure automated security monitoring.
        |
        */
        'enable_monitoring' => env('ENABLE_SECURITY_MONITORING', true),
        'alert_on_suspicious_activity' => env('ALERT_ON_SUSPICIOUS_ACTIVITY', true),
        'auto_block_suspicious_ips' => env('AUTO_BLOCK_SUSPICIOUS_IPS', false),
        'block_duration_minutes' => env('BLOCK_DURATION_MINUTES', 60),
    ],
];