<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Services\ApiKeyService;

class ImportFileRequest extends FormRequest
{
    private $apiKeyService;

    public function __construct(ApiKeyService $apiKeyService)
    {
        $this->apiKeyService = $apiKeyService;
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $apiKey = $this->header('X-API-KEY');

        // Check session authentication first
        if (session()->has('authenticated')) {
            return true;
        }

        // Validate API key using the secure service
        if ($apiKey && $this->apiKeyService->validateApiKey($apiKey)) {
            return true;
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:10240', // 10MB max
                'mimes:csv,xlsx,xls',
                // Custom validation to ensure file is not executable
                function ($attribute, $value, $fail) {
                    if ($value && $value->isValid()) {
                        $extension = strtolower($value->getClientOriginalExtension());
                        $allowedExtensions = ['csv', 'xlsx', 'xls'];

                        if (!in_array($extension, $allowedExtensions)) {
                            $fail('The file type is not allowed. Only CSV, XLS, and XLSX files are permitted.');
                        }

                        // Additional security check for file content
                        $content = file_get_contents($value->getPathname());
                        if ($this->containsSuspiciousContent($content)) {
                            $fail('The file contains suspicious content and cannot be processed.');
                        }
                    }
                }
            ]
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please select a file to upload.',
            'file.max' => 'The file size must not exceed 10MB.',
            'file.mimes' => 'The file must be a CSV, XLS, or XLSX file.',
        ];
    }

    /**
     * Check for suspicious file content
     */
    private function containsSuspiciousContent(string $content): bool
    {
        $suspiciousPatterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
            '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload\s*=/i',
            '/onerror\s*=/i',
            '/eval\s*\(/i',
            '/document\.write/i',
            '/window\.location/i',
            '/\.php\s*$/i',
            '/\.exe\s*$/i',
            '/\.bat\s*$/i',
            '/\.cmd\s*$/i',
            '/\.sh\s*$/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }
}
