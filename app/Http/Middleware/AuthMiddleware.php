<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\ApiKeyService;

class AuthMiddleware
{
    private $apiKeyService;

    public function __construct(ApiKeyService $apiKeyService)
    {
        $this->apiKeyService = $apiKeyService;
    }

    /**
     * Handle an incoming request with enhanced security.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get API key from header
        $apiKey = $request->header('X-API-KEY');

        // Allow dashboard access without auth for demo (remove in production)
        if ($request->is('/dashboard') || $request->is('/')) {
            return $next($request);
        }

        // For API endpoints, require API key or session
        if ($request->is('/api/*')) {
            
            // Check session authentication first
            if (session()->has('authenticated')) {
                // Add security headers
                $response = $next($request);
                return $this->addSecurityHeaders($response);
            }

            // Validate API key
            if (!$apiKey || !$this->apiKeyService->validateApiKey($apiKey)) {
                $this->logSecurityEvent($request, 'invalid_api_key', [
                    'provided_key' => $apiKey ? substr($apiKey, 0, 8) . '...' : 'none',
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Invalid or missing API key.',
                    'error' => 'authentication_failed'
                ], 401);
            }

            // Check for suspicious usage
            if ($this->apiKeyService->isApiKeyUsageSuspicious($apiKey)) {
                $this->logSecurityEvent($request, 'suspicious_api_usage', [
                    'api_key_prefix' => substr($apiKey, 0, 8),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'API key usage temporarily blocked due to suspicious activity.',
                    'error' => 'rate_limit_exceeded'
                ], 429);
            }

            // Add API key to request for use in controllers
            $request->merge(['api_key_validated' => true]);
        }

        $response = $next($request);
        return $this->addSecurityHeaders($response);
    }

    /**
     * Add security headers to response
     */
    private function addSecurityHeaders($response): Response
    {
        // Build CSP header based on environment
        $cspHeader = "default-src 'self';";
        $cspHeader .= " script-src 'self' 'unsafe-inline'";
        $cspHeader .= env('APP_ENV') === 'local' ? " 'unsafe-eval' http://localhost:3000 ws://localhost:3000" : "";
        $cspHeader .= "; style-src 'self' 'unsafe-inline'";
        $cspHeader .= env('APP_ENV') === 'local' ? " http://localhost:3000" : "";
        $cspHeader .= "; font-src 'self'";
        $cspHeader .= env('APP_ENV') === 'local' ? " data: http://localhost:3000" : " data:";
        $cspHeader .= "; img-src 'self' data:";
        $cspHeader .= "; connect-src 'self'";
        $cspHeader .= env('APP_ENV') === 'local' ? " http://localhost:3000 ws://localhost:3000" : "";
        $cspHeader .= ";";

        return $response
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('X-Frame-Options', 'DENY')
            ->header('X-XSS-Protection', '1; mode=block')
            ->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
            ->header('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->header('Content-Security-Policy', $cspHeader);
    }

    /**
     * Log security events for monitoring
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
