<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BankCsvImportService;
use Illuminate\Http\UploadedFile;
use Exception;

class TestBankCsvImportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:bank-csv-import {file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Bank CSV Import with detailed logging';

    /**
     * Execute the console command.
     */
    public function handle(BankCsvImportService $service)
    {
        $filePath = $this->argument('file');
        
        if (!file_exists($filePath)) {
            $this->error("File not found: $filePath");
            return 1;
        }
        
        try {
            $this->info("Testing Bank CSV Import for file: $filePath");
            
            // Create UploadedFile instance from file
            $uploadedFile = new UploadedFile(
                $filePath,
                basename($filePath),
                mime_content_type($filePath),
                UPLOAD_ERR_OK,
                true // test mode
            );
            
            $this->info("File details:");
            $this->line("- Name: " . $uploadedFile->getClientOriginalName());
            $this->line("- Size: " . $uploadedFile->getSize());
            $this->line("- Mime: " . $uploadedFile->getMimeType());
            
            $this->info("Service class: " . get_class($service));
            
            // Try importing the file
            $this->info("Attempting to import CSV...");
            $result = $service->importFromBankCsv($uploadedFile);
            
            $this->info("Import completed successfully!");
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
            
            return 0;
            
        } catch (Exception $e) {
            $this->error("Import failed: " . $e->getMessage());
            $this->line($e->getTraceAsString());
            return 1;
        }
    }
}