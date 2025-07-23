<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class FixDataGridTenantIsolation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:fix-datagrids {--dry-run : Show what would be changed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix tenant isolation in DataGrid classes by replacing DB::table() with TenantHelper::table()';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Scanning for DataGrid files...');
        
        $files = File::allFiles(base_path('packages'));
        $dataGridFiles = collect($files)->filter(function ($file) {
            return str_contains($file->getFilename(), 'DataGrid.php') 
                && !str_contains($file->getPathname(), 'TenantAwareDataGrid.php');
        });

        $this->info("Found {$dataGridFiles->count()} DataGrid files");
        
        $fixed = 0;
        $errors = 0;

        foreach ($dataGridFiles as $file) {
            $path = $file->getPathname();
            $content = File::get($path);
            
            // Check if file uses DB::table()
            if (!str_contains($content, 'DB::table(')) {
                continue;
            }
            
            $this->line("Processing: {$file->getRelativePathname()}");
            
            if ($this->option('dry-run')) {
                $matches = [];
                preg_match_all('/DB::table\([\'"]([^\'"]+)[\'"]\)/', $content, $matches);
                
                if (!empty($matches[1])) {
                    $this->warn("  Would fix DB::table() calls for tables: " . implode(', ', array_unique($matches[1])));
                    $fixed++;
                }
                continue;
            }
            
            try {
                // Add TenantHelper import if not present
                if (!str_contains($content, 'use App\Helpers\TenantHelper;')) {
                    $content = preg_replace(
                        '/(use Illuminate\\\\Support\\\\Facades\\\\DB;)/',
                        "$1\nuse App\\Helpers\\TenantHelper;",
                        $content
                    );
                }
                
                // Replace DB::table() with TenantHelper::table()
                $content = preg_replace(
                    '/DB::table\(/',
                    'TenantHelper::table(',
                    $content
                );
                
                // Write the file
                File::put($path, $content);
                $this->info("  ✓ Fixed");
                $fixed++;
                
            } catch (\Exception $e) {
                $this->error("  ✗ Error: " . $e->getMessage());
                $errors++;
            }
        }
        
        $this->newLine();
        
        if ($this->option('dry-run')) {
            $this->info("Dry run complete. Would fix {$fixed} files.");
        } else {
            $this->info("Complete! Fixed {$fixed} files, {$errors} errors.");
        }
        
        if ($fixed > 0 && !$this->option('dry-run')) {
            $this->warn("Important: Please review the changes and test thoroughly!");
            $this->warn("Run 'php artisan test' to ensure nothing is broken.");
        }
        
        return $errors > 0 ? 1 : 0;
    }
}