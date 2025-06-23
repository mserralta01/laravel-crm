<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class UpdateJobsForMultiTenancy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenancy:update-jobs {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update all Job classes to extend from TenantAwareJob';

    /**
     * Jobs that should not be updated (system-level jobs)
     *
     * @var array
     */
    protected $excludedJobs = [
        // Add any system-level jobs that shouldn't be tenant-aware
    ];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        $this->info($isDryRun ? 'Running in dry-run mode...' : 'Updating Job classes...');
        
        $jobFiles = $this->findJobFiles();
        $updatedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        
        foreach ($jobFiles as $file) {
            if ($this->isExcluded($file)) {
                $this->line("Skipping system job: {$file}");
                $skippedCount++;
                continue;
            }
            
            try {
                if ($this->updateJobFile($file, $isDryRun)) {
                    $this->info("Updated: {$file}");
                    $updatedCount++;
                } else {
                    $this->line("Already updated or skipped: {$file}");
                    $skippedCount++;
                }
            } catch (\Exception $e) {
                $this->error("Error updating {$file}: " . $e->getMessage());
                $errorCount++;
            }
        }
        
        $this->info("\nSummary:");
        $this->info("Updated: {$updatedCount} files");
        $this->info("Skipped: {$skippedCount} files");
        $this->info("Errors: {$errorCount} files");
        
        return $errorCount > 0 ? 1 : 0;
    }
    
    /**
     * Find all Job files
     *
     * @return array
     */
    protected function findJobFiles()
    {
        $files = [];
        $directories = [
            base_path('packages/Webkul'),
            base_path('app/Jobs'),
        ];
        
        foreach ($directories as $directory) {
            if (is_dir($directory)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($directory)
                );
                
                foreach ($iterator as $file) {
                    if ($file->isFile() && 
                        $file->getExtension() === 'php' && 
                        Str::contains($file->getPath(), '/Jobs/') &&
                        !Str::endsWith($file->getFilename(), 'TenantAwareJob.php')
                    ) {
                        $files[] = $file->getPathname();
                    }
                }
            }
        }
        
        return $files;
    }
    
    /**
     * Check if file is excluded
     *
     * @param string $file
     * @return bool
     */
    protected function isExcluded($file)
    {
        foreach ($this->excludedJobs as $excluded) {
            if (Str::contains($file, $excluded)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Update a Job file
     *
     * @param string $file
     * @param bool $isDryRun
     * @return bool
     */
    protected function updateJobFile($file, $isDryRun)
    {
        $content = file_get_contents($file);
        $originalContent = $content;
        
        // Check if already extending TenantAwareJob
        if (Str::contains($content, 'extends TenantAwareJob')) {
            return false;
        }
        
        // Check if it implements ShouldQueue
        if (!Str::contains($content, 'implements ShouldQueue')) {
            return false;
        }
        
        // Add use statement if not present
        if (!Str::contains($content, 'use App\Jobs\TenantAwareJob;')) {
            // Find the last use statement
            $lastUsePosition = strrpos($content, 'use ');
            if ($lastUsePosition !== false) {
                $endOfLinePosition = strpos($content, ';', $lastUsePosition) + 1;
                $content = substr($content, 0, $endOfLinePosition) . "\nuse App\Jobs\TenantAwareJob;" . substr($content, $endOfLinePosition);
            }
        }
        
        // Replace class definition
        $content = preg_replace(
            '/class\s+(\w+)(\s+extends\s+\w+)?\s+implements\s+ShouldQueue/',
            'class $1 extends TenantAwareJob',
            $content
        );
        
        // Update handle method to handleJob
        $content = preg_replace(
            '/public\s+function\s+handle\s*\(/',
            'protected function handleJob(',
            $content
        );
        
        // Remove traits that are already in TenantAwareJob
        $traitsToRemove = ['Dispatchable', 'InteractsWithQueue', 'Queueable', 'SerializesModels'];
        foreach ($traitsToRemove as $trait) {
            // Remove from use statements at top
            $content = preg_replace('/use\s+Illuminate\\\\[^;]+\\\\' . $trait . ';\s*\n/', '', $content);
            // Remove from class
            $content = preg_replace('/use\s+' . $trait . '[,;]/', '', $content);
        }
        
        // Clean up empty use statements
        $content = preg_replace('/use\s*;\s*\n/', '', $content);
        
        if ($content !== $originalContent) {
            if (!$isDryRun) {
                file_put_contents($file, $content);
            }
            return true;
        }
        
        return false;
    }
}