<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TemplateStorageService;

class CleanupTemplateExtractions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'templates:cleanup {--days=30 : Number of days after which to cleanup extractions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old template extractions to free disk space';

    /**
     * Execute the console command.
     */
    public function handle(TemplateStorageService $storageService)
    {
        $days = (int) $this->option('days');
        
        $this->info("Cleaning up template extractions older than {$days} days...");
        
        $cleaned = $storageService->cleanupOldExtractions($days);
        
        if ($cleaned > 0) {
            $this->info("Successfully cleaned up {$cleaned} template extractions.");
        } else {
            $this->info("No old extractions found to clean up.");
        }
        
        return Command::SUCCESS;
    }
}
