<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class UpdateDataGridsForMultiTenancy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenancy:update-datagrids {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update all DataGrid classes to extend from TenantAwareDataGrid';

    /**
     * DataGrids that should not be updated (system-level grids)
     *
     * @var array
     */
    protected $excludedDataGrids = [
        'packages/Webkul/DataGrid/src/DataGrid.php',
        'packages/Webkul/Admin/src/DataGrids/Settings/UserDataGrid.php',
        'packages/Webkul/Admin/src/DataGrids/Settings/RoleDataGrid.php',
        'packages/Webkul/Admin/src/DataGrids/Settings/GroupDataGrid.php',
    ];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        $this->info($isDryRun ? 'Running in dry-run mode...' : 'Updating DataGrid classes...');
        
        $dataGridFiles = $this->findDataGridFiles();
        $updatedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        
        foreach ($dataGridFiles as $file) {
            if ($this->isExcluded($file)) {
                $this->line("Skipping system DataGrid: {$file}");
                $skippedCount++;
                continue;
            }
            
            try {
                if ($this->updateDataGridFile($file, $isDryRun)) {
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
     * Find all DataGrid files
     *
     * @return array
     */
    protected function findDataGridFiles()
    {
        $files = [];
        $directories = [
            base_path('packages/Webkul/Admin/src/DataGrids'),
            base_path('packages/Webkul/WebForm/src/DataGrids'),
        ];
        
        foreach ($directories as $directory) {
            if (is_dir($directory)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($directory)
                );
                
                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() === 'php' && Str::endsWith($file->getFilename(), 'DataGrid.php')) {
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
        foreach ($this->excludedDataGrids as $excluded) {
            if (Str::contains($file, $excluded)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Update a DataGrid file
     *
     * @param string $file
     * @param bool $isDryRun
     * @return bool
     */
    protected function updateDataGridFile($file, $isDryRun)
    {
        $content = file_get_contents($file);
        $originalContent = $content;
        
        // Check if already extending TenantAwareDataGrid
        if (Str::contains($content, 'extends TenantAwareDataGrid')) {
            return false;
        }
        
        // Check if it extends DataGrid
        if (!preg_match('/class\s+\w+\s+extends\s+DataGrid/', $content)) {
            return false;
        }
        
        // Add use statement if not present
        if (!Str::contains($content, 'use App\DataGrids\TenantAwareDataGrid;')) {
            // Find the last use statement
            $lastUsePosition = strrpos($content, 'use ');
            if ($lastUsePosition !== false) {
                $endOfLinePosition = strpos($content, ';', $lastUsePosition) + 1;
                $content = substr($content, 0, $endOfLinePosition) . "\nuse App\DataGrids\TenantAwareDataGrid;" . substr($content, $endOfLinePosition);
            }
        }
        
        // Replace extends DataGrid with extends TenantAwareDataGrid
        $content = preg_replace(
            '/class\s+(\w+)\s+extends\s+DataGrid/',
            'class $1 extends TenantAwareDataGrid',
            $content
        );
        
        // Remove the original DataGrid import if it exists and is not used elsewhere
        if (!Str::contains($content, 'DataGrid::')) {
            $content = preg_replace('/use\s+Webkul\\\\DataGrid\\\\DataGrid;\s*\n/', '', $content);
        }
        
        if ($content !== $originalContent) {
            if (!$isDryRun) {
                file_put_contents($file, $content);
            }
            return true;
        }
        
        return false;
    }
}