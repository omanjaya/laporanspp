<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Add CORS headers
        $response = $next($request);

        // Get allowed origins from config
        $allowedOrigins = config('cors.allowed_origins', ['*']);
        
        // Determine the origin to use
        $origin = $request->headers->get('Origin');
        $allowedOrigin = '*'; // Default to allow all
        
        // Check if the origin is in our allowed list
        if ($origin && in_array($origin, $allowedOrigins)) {
            $allowedOrigin = $origin;
        }

        $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-API-KEY');

        // Handle preflight requests
        if ($request->isMethod('OPTIONS')) {
            return response('', 200)
                ->header('Access-Control-Allow-Origin', $allowedOrigin)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-API-KEY')
                ->header('Access-Control-Max-Age', '86400'); // Cache preflight for 1 day
        }

        return $response;
    }
}