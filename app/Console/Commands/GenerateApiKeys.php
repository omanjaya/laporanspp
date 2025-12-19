<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ApiKeyService;
use Illuminate\Support\Facades\File;

class GenerateApiKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:generate
                            {--count=1 : Number of API keys to generate}
                            {--show : Display the generated keys}
                            {--update-env : Update .env file with new keys}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate secure API keys for the SPP system';

    private $apiKeyService;

    /**
     * Create a new command instance.
     */
    public function __construct(ApiKeyService $apiKeyService)
    {
        parent::__construct();
        $this->apiKeyService = $apiKeyService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = min($this->option('count'), 10); // Max 10 keys at once
        $showKeys = $this->option('show');
        $updateEnv = $this->option('update-env');

        $this->info("Generating {$count} secure API keys...");

        try {
            $newKeys = $this->apiKeyService->rotateApiKeys($count);

            $this->info('✅ Successfully generated API keys:');

            foreach ($newKeys as $index => $key) {
                $this->line("  " . ($index + 1) . ". {$key}");
            }

            if ($showKeys) {
                $this->newLine();
                $this->warn('⚠️  Keep these keys secure and do not commit them to version control!');
            }

            if ($updateEnv) {
                $this->updateEnvironmentFile($newKeys);
                $this->info('✅ Updated .env file with new API keys');
                $this->warn('⚠️  Please restart your application to load the new keys.');
            }

            $this->newLine();
            $this->info('Next steps:');
            $this->line('1. Add these keys to your API_KEYS environment variable');
            $this->line('2. Distribute keys to authorized applications');
            $this->line('3. Monitor API key usage in the application logs');

        } catch (\Exception $e) {
            $this->error('❌ Failed to generate API keys: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Update the .env file with new API keys
     */
    private function updateEnvironmentFile(array $newKeys): void
    {
        $envPath = base_path('.env');

        if (!File::exists($envPath)) {
            $this->error('❌ .env file not found!');
            return;
        }

        $envContent = File::get($envPath);
        $keysString = implode(',', $newKeys);

        // Replace or add API_KEYS line
        if (preg_match('/^API_KEYS=.*$/m', $envContent)) {
            $envContent = preg_replace('/^API_KEYS=.*$/m', "API_KEYS={$keysString}", $envContent);
        } else {
            // Add API_KEYS line after APP_KEY
            $envContent = preg_replace('/^(APP_KEY=.*$)/m', "$1\nAPI_KEYS={$keysString}", $envContent);
        }

        File::put($envPath, $envContent);
    }
}