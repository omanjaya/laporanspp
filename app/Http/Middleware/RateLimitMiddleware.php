<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Cache;
use App\Services\ApiKeyService;

class RateLimitMiddleware
{
    private $apiKeyService;

    public function __construct(ApiKeyService $apiKeyService)
    {
        $this->apiKeyService = $apiKeyService;
    }

    /**
     * Handle an incoming request with enhanced rate limiting.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip rate limiting for dashboard access and analytics (remove in production)
        if ($request->is('/dashboard') || $request->is('/') || $request->is('api/dashboard/analytics')) {
            return $next($request);
        }

        $apiKey = $request->header('X-API-KEY');
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();

        // Create secure identifier
        $identifier = $this->createSecureIdentifier($apiKey, $ipAddress);

        // Check for blacklisted IPs or suspicious patterns
        if ($this->isBlacklisted($ipAddress, $userAgent)) {
            $this->logSecurityEvent($request, 'blacklisted_access_attempt', [
                'ip' => $ipAddress,
                'user_agent' => $userAgent
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Access denied.',
                'error' => 'access_denied'
            ], 403);
        }

        // Define rate limits based on endpoint type and authentication
        $limits = $this->getRateLimits($request, $apiKey);

        foreach ($limits as $limit) {
            $key = "rate_limit:{$identifier}:{$limit['key']}";
            $current = Cache::get($key, 0);

            if ($current >= $limit['max']) {
                $this->logRateLimitExceeded($request, $limit, $identifier);

                // Add progressive backoff for repeated violations
                $penaltyKey = "rate_limit_penalty:{$identifier}";
                $penaltyCount = Cache::increment($penaltyKey, 1, now()->addHours(1));
                $penaltyMultiplier = min($penaltyCount, 5); // Max 5x penalty

                $retryAfter = $limit['seconds'] * $penaltyMultiplier;

                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please try again later.',
                    'error' => 'rate_limit_exceeded',
                    'retry_after' => $retryAfter,
                    'penalty_multiplier' => $penaltyMultiplier
                ], 429)->header('Retry-After', $retryAfter);
            }

            // Increment counter with expiration
            Cache::put($key, $current + 1, $limit['seconds']);
        }

        // Add rate limit headers for transparency
        $response = $next($request);
        $this->addRateLimitHeaders($response, $identifier, $limits);

        return $response;
    }

    /**
     * Create secure identifier for rate limiting
     */
    private function createSecureIdentifier(?string $apiKey, string $ipAddress): string
    {
        if ($apiKey && $this->apiKeyService->validateApiKey($apiKey)) {
            // Use hash of API key for privacy
            return 'api_key:' . hash('sha256', $apiKey);
        }

        // Use IP address for non-authenticated requests
        return 'ip:' . hash('sha256', $ipAddress);
    }

    /**
     * Check if IP or user agent is blacklisted
     */
    private function isBlacklisted(string $ipAddress, string $userAgent): bool
    {
        // Check against known malicious patterns
        $maliciousPatterns = [
            '/bot/i',
            '/crawler/i',
            '/scanner/i',
            '/curl/i',
            '/wget/i',
            '/python/i',
            '/java/i',
            '/go-http/i'
        ];

        // Block requests without proper user agent
        if (empty($userAgent) || strlen($userAgent) < 10) {
            return true;
        }

        // Check for suspicious user agents
        foreach ($maliciousPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }

        // Check against internal blacklist cache
        $blacklistKey = 'blacklist:ip:' . hash('sha256', $ipAddress);
        return Cache::has($blacklistKey);
    }

    /**
     * Get rate limits based on request type and authentication
     */
    private function getRateLimits(Request $request, ?string $apiKey): array
    {
        $isAuthenticated = $apiKey && $this->apiKeyService->validateApiKey($apiKey);

        if ($request->is('/api/rekon/import')) {
            return [
                [
                    'key' => 'import_minute',
                    'max' => $isAuthenticated ? 10 : 3,
                    'seconds' => 60
                ],
                [
                    'key' => 'import_hour',
                    'max' => $isAuthenticated ? 100 : 20,
                    'seconds' => 3600
                ],
            ];
        }

        if ($request->is('/api/rekon/export/*')) {
            return [
                [
                    'key' => 'export_minute',
                    'max' => $isAuthenticated ? 20 : 5,
                    'seconds' => 60
                ],
                [
                    'key' => 'export_hour',
                    'max' => $isAuthenticated ? 200 : 50,
                    'seconds' => 3600
                ],
            ];
        }

        if ($request->is('/api/rekon/search')) {
            return [
                [
                    'key' => 'search_minute',
                    'max' => $isAuthenticated ? 100 : 30,
                    'seconds' => 60
                ],
                [
                    'key' => 'search_hour',
                    'max' => $isAuthenticated ? 1000 : 300,
                    'seconds' => 3600
                ],
            ];
        }

        // Default rate limits
        return [
            [
                'key' => 'api_minute',
                'max' => $isAuthenticated ? 120 : 60,
                'seconds' => 60
            ],
            [
                'key' => 'api_hour',
                'max' => $isAuthenticated ? 2000 : 1000,
                'seconds' => 3600
            ],
        ];
    }

    /**
     * Add rate limit headers to response
     */
    private function addRateLimitHeaders($response, string $identifier, array $limits): void
    {
        $remaining = [];

        foreach ($limits as $limit) {
            $key = "rate_limit:{$identifier}:{$limit['key']}";
            $current = Cache::get($key, 0);
            $remaining[$limit['key']] = max(0, $limit['max'] - $current);
        }

        $response->headers->set('X-RateLimit-Limit', json_encode($limits));
        $response->headers->set('X-RateLimit-Remaining', json_encode($remaining));
    }

    /**
     * Log rate limit exceeded events
     */
    private function logRateLimitExceeded(Request $request, array $limit, string $identifier): void
    {
        \Log::warning('Rate limit exceeded', [
            'ip' => $request->ip(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'limit_key' => $limit['key'],
            'limit_max' => $limit['max'],
            'identifier' => $identifier,
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Log security events
     */
    private function logSecurityEvent(Request $request, string $event, array $context): void
    {
        \Log::warning("Security Event: {$event}", array_merge([
            'ip' => $request->ip(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'timestamp' => now()->toISOString()
        ], $context));
    }
}
