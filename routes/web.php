<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RekonController;
use App\Http\Controllers\RekonSearchController;
use App\Http\Controllers\RekonImportController;
use App\Http\Controllers\RekonReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\ErrorLogController;

// Authentication Routes
Route::prefix('api/auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/status', [AuthController::class, 'status']);
});

// API Key Management Routes (admin only)
Route::prefix('api/admin')->middleware(['throttle:10,1'])->group(function () {
    Route::post('/api-keys/generate', [ApiKeyController::class, 'generateKeys']);
    Route::get('/api-keys/stats', [ApiKeyController::class, 'getKeyStats']);
    Route::post('/api-keys/validate', [ApiKeyController::class, 'validateKey']);
});

// API Routes (protected by auth middleware)
Route::prefix('api')->group(function () {
    // Search routes
    Route::get('/rekon/search', [RekonSearchController::class, 'search']);
    Route::get('/rekon/get-value', [RekonSearchController::class, 'getValue']);
    Route::get('/rekon', [RekonSearchController::class, 'index']);

    // Import routes
    Route::post('/rekon/import', [RekonImportController::class, 'import']);
    Route::post('/rekon/import-bank', [RekonImportController::class, 'importBankCsv']);
    Route::get('/rekon/import/status', [RekonImportController::class, 'checkImportStatus']);
    Route::get('/rekon/import/history', [RekonImportController::class, 'getImportHistory']);

    // Report routes
    Route::get('/rekon/laporan-kelas', [RekonReportController::class, 'getLaporanKelas']);
    Route::get('/rekon/laporan-kelas/export', [RekonReportController::class, 'exportLaporanKelas']);
    Route::get('/rekon/export/excel', [RekonReportController::class, 'exportExcel']);
    Route::get('/rekon/export/csv', [RekonReportController::class, 'exportCSV']);

    // Dashboard routes
    Route::get('/dashboard/analytics', [DashboardController::class, 'getAnalytics']);

    // Error logging routes
    Route::post('/log/error', [ErrorLogController::class, 'logFrontendError']);
    Route::get('/errors/stats', [ErrorLogController::class, 'getErrorStats']);
});

// Dashboard analytics route
Route::get('/api/dashboard/analytics', [DashboardController::class, 'getAnalytics']);

// Health check endpoint
Route::get('/api/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0'
    ]);
})->name('api.health');

// Web Routes
Route::get('/', function () {
    return redirect('/dashboard');
})->name('home');

Route::get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');

// Fallback for API routes (return 404 instead of 500 for missing endpoints)
Route::fallback(function () {
    if (request()->is('api/*')) {
        return response()->json([
            'success' => false,
            'message' => 'API endpoint not found',
            'error' => 'not_found'
        ], 404);
    }

    return response()->json([
        'success' => false,
        'message' => 'Page not found',
        'error' => 'not_found'
    ], 404);
});
