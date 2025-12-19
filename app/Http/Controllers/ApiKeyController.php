<?php

namespace App\Http\Controllers;

use App\Services\ApiKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiKeyController extends Controller
{
    private $apiKeyService;

    public function __construct(ApiKeyService $apiKeyService)
    {
        $this->apiKeyService = $apiKeyService;
    }

    /**
     * Generate new API keys (admin only)
     */
    public function generateKeys(Request $request): JsonResponse
    {
        // This should be protected by proper authentication/authorization
        // For now, we'll add a simple admin check
        if (!$this->isAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        try {
            $count = min($request->get('count', 1), 10); // Max 10 keys at once
            $newKeys = $this->apiKeyService->rotateApiKeys($count);

            return response()->json([
                'success' => true,
                'message' => "Generated {$count} new API keys",
                'keys' => $newKeys,
                'instructions' => 'Add these keys to your API_KEYS environment variable as comma-separated values.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate API keys: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get API key usage statistics (admin only)
     */
    public function getKeyStats(Request $request): JsonResponse
    {
        if (!$this->isAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $apiKey = $request->get('api_key');

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'API key is required.'
            ], 400);
        }

        try {
            $stats = $this->apiKeyService->getApiKeyStats($apiKey);

            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get API key stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate an API key
     */
    public function validateKey(Request $request): JsonResponse
    {
        $apiKey = $request->get('api_key');

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'API key is required.',
                'valid' => false
            ], 400);
        }

        try {
            $isValid = $this->apiKeyService->validateApiKey($apiKey);
            $isSuspicious = $this->apiKeyService->isApiKeyUsageSuspicious($apiKey);

            return response()->json([
                'success' => true,
                'valid' => $isValid,
                'suspicious' => $isSuspicious,
                'message' => $isValid ? 'API key is valid' : 'API key is invalid'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . $e->getMessage(),
                'valid' => false
            ], 500);
        }
    }

    /**
     * Simple admin check (in production, use proper authentication)
     */
    private function isAdmin(Request $request): bool
    {
        // For demo purposes, check for session or specific header
        // In production, use proper authentication/authorization
        return session()->has('admin_authenticated') ||
               $request->header('X-Admin-Key') === env('ADMIN_KEY');
    }
}