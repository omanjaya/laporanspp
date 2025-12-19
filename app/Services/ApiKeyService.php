<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ApiKeyService
{
    /**
     * Generate a new secure API key
     */
    public function generateApiKey(): string
    {
        return 'spp-rekon-' . Str::random(32) . '-' . date('Y');
    }

    /**
     * Validate API key against environment and cache
     */
    public function validateApiKey(string $apiKey): bool
    {
        // Get valid API keys from environment (comma-separated)
        $validKeys = $this->getValidApiKeys();

        if (empty($validKeys)) {
            return false;
        }

        // Check against allowed keys
        if (!in_array($apiKey, $validKeys)) {
            return false;
        }

        // Track API key usage for security monitoring
        $this->trackApiKeyUsage($apiKey);

        return true;
    }

    /**
     * Get all valid API keys from environment
     */
    private function getValidApiKeys(): array
    {
        $apiKeyEnv = env('API_KEYS');

        if (empty($apiKeyEnv)) {
            return [];
        }

        return array_map('trim', explode(',', $apiKeyEnv));
    }

    /**
     * Track API key usage for security monitoring
     */
    private function trackApiKeyUsage(string $apiKey): void
    {
        $keyHash = hash('sha256', $apiKey);
        $cacheKey = "api_key_usage:{$keyHash}:" . date('Y-m-d-H');

        Cache::increment($cacheKey, 1, [
            'expires_at' => now()->addHours(25)
        ]);
    }

    /**
     * Check if API key usage is suspicious
     */
    public function isApiKeyUsageSuspicious(string $apiKey): bool
    {
        $keyHash = hash('sha256', $apiKey);
        $currentHour = date('Y-m-d-H');
        $cacheKey = "api_key_usage:{$keyHash}:{$currentHour}";

        $usage = Cache::get($cacheKey, 0);

        // Flag if more than 1000 requests per hour
        return $usage > 1000;
    }

    /**
     * Rotate API keys (generate new set)
     */
    public function rotateApiKeys(int $count = 1): array
    {
        $newKeys = [];

        for ($i = 0; $i < $count; $i++) {
            $newKeys[] = $this->generateApiKey();
        }

        return $newKeys;
    }

    /**
     * Get API key usage statistics
     */
    public function getApiKeyStats(string $apiKey): array
    {
        $keyHash = hash('sha256', $apiKey);
        $stats = [];

        // Get last 24 hours of usage
        for ($i = 0; $i < 24; $i++) {
            $hour = date('Y-m-d-H', strtotime("-{$i} hours"));
            $cacheKey = "api_key_usage:{$keyHash}:{$hour}";

            $stats[$hour] = Cache::get($cacheKey, 0);
        }

        return [
            'total_requests_24h' => array_sum($stats),
            'hourly_breakdown' => array_reverse($stats, true),
            'is_suspicious' => $this->isApiKeyUsageSuspicious($apiKey)
        ];
    }
}