<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class SecurityAudit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security:audit
                            {--fix : Attempt to fix common security issues}
                            {--detailed : Show detailed audit results}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform security audit on the SPP application';

    /**
     * Issues found during audit
     */
    private $issues = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔒 Starting Security Audit for SPP System');
        $this->newLine();

        $this->auditEnvironmentVariables();
        $this->auditFilePermissions();
        $this->auditCodeForSecrets();
        $this->auditRoutes();
        $this->auditDependencies();
        $this->auditConfiguration();

        $this->displayResults();

        if ($this->option('fix')) {
            $this->attemptFixes();
        }

        $this->newLine();
        $this->info('✅ Security audit completed!');

        return 0;
    }

    /**
     * Audit environment variables
     */
    private function auditEnvironmentVariables(): void
    {
        $this->info('📋 Auditing environment variables...');

        $envPath = base_path('.env');
        if (!File::exists($envPath)) {
            $this->issues[] = [
                'type' => 'critical',
                'category' => 'environment',
                'message' => '.env file not found',
                'solution' => 'Create .env file from .env.example'
            ];
            return;
        }

        $envContent = File::get($envPath);

        // Check for default values
        $defaultPatterns = [
            '/APP_KEY=base64:/' => 'Default APP_KEY detected',
            '/API_KEY=spp-rekon-2024-secret-key/' => 'Default API key detected',
            '/ADMIN_KEY=secure-admin-key-2024-change-this-in-production/' => 'Default admin key detected',
            '/DB_PASSWORD=/' => 'Empty database password',
            '/APP_DEBUG=true/' => 'Debug mode enabled in production'
        ];

        foreach ($defaultPatterns as $pattern => $message) {
            if (preg_match($pattern, $envContent)) {
                $this->issues[] = [
                    'type' => 'critical',
                    'category' => 'environment',
                    'message' => $message,
                    'solution' => 'Change the default value in .env file'
                ];
            }
        }

        // Check for required security variables
        $requiredVars = ['API_KEYS', 'APP_KEY', 'APP_ENV'];
        foreach ($requiredVars as $var) {
            if (!preg_match("/^{$var}=/m", $envContent)) {
                $this->issues[] = [
                    'type' => 'high',
                    'category' => 'environment',
                    'message' => "Missing environment variable: {$var}",
                    'solution' => "Add {$var} to .env file"
                ];
            }
        }
    }

    /**
     * Audit file permissions
     */
    private function auditFilePermissions(): void
    {
        $this->info('📁 Auditing file permissions...');

        $criticalPaths = [
            '.env' => 'should be 600 or 640',
            'storage' => 'should be writable by web server',
            'bootstrap/cache' => 'should be writable by web server'
        ];

        foreach ($criticalPaths as $path => $expected) {
            $fullPath = base_path($path);
            if (File::exists($fullPath)) {
                $permissions = substr(sprintf('%o', fileperms($fullPath)), -4);
                if ($permissions === '0777' || $permissions === '0755') {
                    $this->issues[] = [
                        'type' => 'high',
                        'category' => 'permissions',
                        'message' => "Unsafe permissions on {$path}: {$permissions}",
                        'solution' => "Change permissions ({$expected})"
                    ];
                }
            }
        }
    }

    /**
     * Audit code for hardcoded secrets
     */
    private function auditCodeForSecrets(): void
    {
        $this->info('🔍 Scanning for hardcoded secrets...');

        $patterns = [
            '/spp-rekon-2024-secret-key/' => 'Hardcoded API key',
            '/sk_live_[a-zA-Z0-9]+/' => 'Hardcoded Stripe key',
            '/password\s*=\s*["\'][^"\']+["\']/' => 'Hardcoded password',
            '/api_key\s*=\s*["\'][^"\']+["\']/' => 'Hardcoded API key'
        ];

        $scanDirectories = ['app', 'config', 'routes', 'public/js'];

        foreach ($scanDirectories as $dir) {
            $fullPath = base_path($dir);
            if (File::exists($fullPath)) {
                $files = File::allFiles($fullPath);

                foreach ($files as $file) {
                    if ($file->getExtension() === 'php' || $file->getExtension() === 'js') {
                        $content = File::get($file->getPathname());

                        foreach ($patterns as $pattern => $description) {
                            if (preg_match($pattern, $content)) {
                                $this->issues[] = [
                                    'type' => 'critical',
                                    'category' => 'secrets',
                                    'message' => "{$description} found in {$file->getRelativePathname()}",
                                    'solution' => 'Move secret to environment variable'
                                ];
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Audit routes for security
     */
    private function auditRoutes(): void
    {
        $this->info('🛣️  Auditing routes...');

        $routes = Route::getRoutes();
        $apiRoutes = [];

        foreach ($routes as $route) {
            if (str_starts_with($route->uri(), 'api/')) {
                $apiRoutes[] = [
                    'uri' => $route->uri(),
                    'methods' => implode(', ', $route->methods()),
                    'middleware' => implode(', ', $route->middleware())
                ];
            }
        }

        // Check for routes without authentication middleware
        foreach ($apiRoutes as $route) {
            if (!str_contains($route['middleware'], 'auth') &&
                !str_contains($route['middleware'], 'AuthMiddleware')) {

                // Skip health check and auth endpoints
                if (!str_contains($route['uri'], 'health') &&
                    !str_contains($route['uri'], 'auth/')) {

                    $this->issues[] = [
                        'type' => 'high',
                        'category' => 'routes',
                        'message' => "API route without authentication: {$route['methods']} {$route['uri']}",
                        'solution' => 'Add authentication middleware to route'
                    ];
                }
            }
        }
    }

    /**
     * Audit dependencies for vulnerabilities
     */
    private function auditDependencies(): void
    {
        $this->info('📦 Auditing dependencies...');

        $composerLock = base_path('composer.lock');
        if (File::exists($composerLock)) {
            $lockContent = json_decode(File::get($composerLock), true);

            if (isset($lockContent['packages'])) {
                foreach ($lockContent['packages'] as $package) {
                    // Check for known vulnerable packages (simplified example)
                    $vulnerablePackages = [
                        'maatwebsite/excel' => ['<2.1.0'],
                        'laravel/framework' => ['<6.0.0']
                    ];

                    if (isset($vulnerablePackages[$package['name']])) {
                        foreach ($vulnerablePackages[$package['name']] as $badVersion) {
                            if (version_compare($package['version'], $badVersion, '<')) {
                                $this->issues[] = [
                                    'type' => 'high',
                                    'category' => 'dependencies',
                                    'message' => "Vulnerable dependency: {$package['name']} {$package['version']}",
                                    'solution' => "Update to version >= {$badVersion}"
                                ];
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Audit configuration security
     */
    private function auditConfiguration(): void
    {
        $this->info('⚙️  Auditing configuration...');

        // Check app.php configuration
        $appConfig = config('app');
        if ($appConfig['debug'] && $appConfig['env'] === 'production') {
            $this->issues[] = [
                'type' => 'critical',
                'category' => 'configuration',
                'message' => 'Debug mode enabled in production',
                'solution' => 'Set APP_DEBUG=false in production'
            ];
        }

        if (empty($appConfig['key']) || $appConfig['key'] === 'base64:') {
            $this->issues[] = [
                'type' => 'critical',
                'category' => 'configuration',
                'message' => 'Application key not set',
                'solution' => 'Run php artisan key:generate'
            ];
        }
    }

    /**
     * Display audit results
     */
    private function displayResults(): void
    {
        $this->newLine();
        $this->info('📊 Audit Results:');

        if (empty($this->issues)) {
            $this->info('✅ No security issues found!');
            return;
        }

        $groupedIssues = [];
        foreach ($this->issues as $issue) {
            $groupedIssues[$issue['type']][] = $issue;
        }

        foreach (['critical', 'high', 'medium', 'low'] as $severity) {
            if (isset($groupedIssues[$severity])) {
                $this->newLine();
                $icon = $severity === 'critical' ? '🚨' : ($severity === 'high' ? '⚠️' : 'ℹ️');
                $this->line("{$icon} " . strtoupper($severity) . " Issues (" . count($groupedIssues[$severity]) . "):");

                foreach ($groupedIssues[$severity] as $issue) {
                    $this->line("  • {$issue['message']}");
                    if ($this->option('detailed')) {
                        $this->line("    Category: {$issue['category']}");
                        $this->line("    Solution: {$issue['solution']}");
                    }
                }
            }
        }

        $this->newLine();
        $this->warn("Found " . count($this->issues) . " security issues");
    }

    /**
     * Attempt to fix common issues
     */
    private function attemptFixes(): void
    {
        $this->newLine();
        $this->info('🔧 Attempting to fix issues...');

        foreach ($this->issues as $issue) {
            if ($issue['category'] === 'environment' && str_contains($issue['message'], 'Default')) {
                $this->line("Cannot automatically fix: {$issue['message']}");
                $this->line("  Please manually: {$issue['solution']}");
            }
        }

        $this->info('Manual fixes required for most issues. Review SECURITY_GUIDELINES.md for details.');
    }
}