<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException as LaravelValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        ImportException::class => 'error',
        DatabaseException::class => 'critical',
        FileProcessingException::class => 'error',
        AuthenticationException::class => 'warning',
        RateLimitException::class => 'warning',
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
        'api_key',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            $this->logException($e);
        });

        $this->renderable(function (Throwable $e, Request $request) {
            return $this->handleException($e, $request);
        });
    }

    /**
     * Log exception with structured data
     */
    private function logException(Throwable $e): void
    {
        $context = [
            'exception_class' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => collect($e->getTrace())->take(10)->toArray(),
            'timestamp' => now()->toISOString(),
        ];

        // Add user context if available
        if (auth()->check()) {
            $context['user'] = [
                'id' => auth()->id(),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ];
        }

        // Add request context
        $context['request'] = [
            'method' => request()->method(),
            'url' => request()->fullUrl(),
            'headers' => $this->sanitizeHeaders(request()->headers->all()),
        ];

        // Add custom exception context
        if ($e instanceof SppRekonException) {
            $context = array_merge($context, $e->getContext());
            $context['error_code'] = $e->getErrorCode();
            $context['http_status'] = $e->getHttpStatusCode();
            $context['user_message'] = $e->getUserMessage();
        }

        $logLevel = $this->determineLogLevel($e);

        Log::log($logLevel, 'SPP Rekon System Exception', $context);
    }

    /**
     * Determine log level based on exception type
     */
    private function determineLogLevel(Throwable $e): string
    {
        foreach ($this->levels as $exceptionClass => $level) {
            if ($e instanceof $exceptionClass) {
                return $level;
            }
        }

        return 'error';
    }

    /**
     * Handle exception and return appropriate response
     */
    private function handleException(Throwable $e, Request $request): JsonResponse|Response|null
    {
        // Handle API requests
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->handleApiException($e, $request);
        }

        // Handle web requests
        return $this->handleWebException($e, $request);
    }

    /**
     * Handle API exceptions
     */
    private function handleApiException(Throwable $e, Request $request): JsonResponse
    {
        // Custom SPP exceptions
        if ($e instanceof SppRekonException) {
            return response()->json($e->toArray(), $e->getHttpStatusCode());
        }

        // Laravel validation exceptions
        if ($e instanceof LaravelValidationException) {
            return response()->json([
                'success' => false,
                'error_code' => 'VAL-' . strtoupper(uniqid()),
                'message' => 'Data yang Anda masukkan tidak valid.',
                'validation_errors' => $e->errors(),
                'timestamp' => now()->toISOString(),
                'status_code' => 422
            ], 422);
        }

        // HTTP exceptions
        if ($e instanceof HttpException) {
            return response()->json([
                'success' => false,
                'error_code' => 'HTTP-' . $e->getStatusCode(),
                'message' => $this->getHttpErrorMessage($e->getStatusCode()),
                'timestamp' => now()->toISOString(),
                'status_code' => $e->getStatusCode()
            ], $e->getStatusCode());
        }

        // Generic server error
        return response()->json([
            'success' => false,
            'error_code' => 'SRV-' . strtoupper(uniqid()),
            'message' => 'Terjadi kesalahan pada server. Silakan coba beberapa saat lagi.',
            'timestamp' => now()->toISOString(),
            'status_code' => 500
        ], 500);
    }

    /**
     * Handle web exceptions (for Blade views)
     */
    private function handleWebException(Throwable $e, Request $request): Response|null
    {
        // Let Laravel handle web exceptions normally for development
        if (app()->environment('local', 'testing')) {
            return null;
        }

        // For production, show user-friendly error page
        $statusCode = $this->getStatusCode($e);

        return response()->view('errors.generic', [
            'message' => $this->getUserFriendlyMessage($e),
            'error_code' => $e instanceof SppRekonException ? $e->getErrorCode() : 'WEB-' . strtoupper(uniqid()),
            'status_code' => $statusCode
        ], $statusCode);
    }

    /**
     * Get HTTP status code from exception
     */
    private function getStatusCode(Throwable $e): int
    {
        if ($e instanceof SppRekonException) {
            return $e->getHttpStatusCode();
        }

        if ($e instanceof HttpException) {
            return $e->getStatusCode();
        }

        return 500;
    }

    /**
     * Get user-friendly message for web requests
     */
    private function getUserFriendlyMessage(Throwable $e): string
    {
        if ($e instanceof SppRekonException) {
            return $e->getUserMessage();
        }

        if ($e instanceof LaravelValidationException) {
            return 'Data yang Anda masukkan tidak valid. Periksa kembali input Anda.';
        }

        return 'Terjadi kesalahan pada sistem. Silakan coba beberapa saat lagi atau hubungi administrator.';
    }

    /**
     * Get HTTP error message based on status code
     */
    private function getHttpErrorMessage(int $statusCode): string
    {
        $messages = [
            400 => 'Permintaan tidak valid.',
            401 => 'Autentikasi diperlukan.',
            403 => 'Anda tidak memiliki izin untuk mengakses resource ini.',
            404 => 'Resource tidak ditemukan.',
            405 => 'Metode HTTP tidak diizinkan.',
            422 => 'Data yang Anda masukkan tidak valid.',
            429 => 'Terlalu banyak permintaan. Silakan coba lagi nanti.',
            500 => 'Terjadi kesalahan pada server.',
            503 => 'Layanan sedang tidak tersedia.',
        ];

        return $messages[$statusCode] ?? 'Terjadi kesalahan.';
    }

    /**
     * Sanitize headers for logging (remove sensitive data)
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = ['authorization', 'x-api-key', 'cookie', 'set-cookie'];

        return collect($headers)->mapWithKeys(function ($value, $key) use ($sensitiveHeaders) {
            if (in_array(strtolower($key), $sensitiveHeaders)) {
                return [$key => '[FILTERED]'];
            }

            return [$key => $value];
        })->toArray();
    }
}