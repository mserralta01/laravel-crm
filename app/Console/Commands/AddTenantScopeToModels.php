<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AddTenantScopeToModels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:add-scope-to-models {--dry-run : Show what would be changed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add BelongsToTenant trait to Krayin models';

    /**
     * Models that need the BelongsToTenant trait.
     *
     * @var array
     */
    protected $models = [
        // Lead package
        'packages/Webkul/Lead/src/Models/Lead.php',
        'packages/Webkul/Lead/src/Models/Pipeline.php',
        'packages/Webkul/Lead/src/Models/Stage.php',
        'packages/Webkul/Lead/src/Models/Source.php',
        'packages/Webkul/Lead/src/Models/Type.php',
        
        // Contact package
        'packages/Webkul/Contact/src/Models/Person.php',
        'packages/Webkul/Contact/src/Models/Organization.php',
        
        // Product package
        'packages/Webkul/Product/src/Models/Product.php',
        'packages/Webkul/Product/src/Models/ProductInventory.php',
        
        // Quote package
        'packages/Webkul/Quote/src/Models/Quote.php',
        'packages/Webkul/Quote/src/Models/QuoteItem.php',
        
        // Email package
        'packages/Webkul/Email/src/Models/Email.php',
        'packages/Webkul/Email/src/Models/Attachment.php',
        
        // Activity package
        'packages/Webkul/Activity/src/Models/Activity.php',
        'packages/Webkul/Activity/src/Models/File.php',
        'packages/Webkul/Activity/src/Models/Participant.php',
        
        // User package
        'packages/Webkul/User/src/Models/User.php',
        'packages/Webkul/User/src/Models/Group.php',
        
        // Tag package
        'packages/Webkul/Tag/src/Models/Tag.php',
        
        // Workflow package
        'packages/Webkul/Automation/src/Models/Workflow.php',
        'packages/Webkul/Automation/src/Models/Webhook.php',
        
        // EmailTemplate package
        'packages/Webkul/EmailTemplate/src/Models/EmailTemplate.php',
        
        // WebForm package
        'packages/Webkul/WebForm/src/Models/WebForm.php',
        'packages/Webkul/WebForm/src/Models/WebFormAttribute.php',
        
        // Warehouse package
        'packages/Webkul/Warehouse/src/Models/Warehouse.php',
        'packages/Webkul/Warehouse/src/Models/Location.php',
        
        // Attribute package
        'packages/Webkul/Attribute/src/Models/Attribute.php',
        'packages/Webkul/Attribute/src/Models/AttributeOption.php',
        
        // DataGrid package
        'packages/Webkul/DataGrid/src/Models/SavedFilter.php',
    ];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        $this->info($dryRun ? 'DRY RUN - No changes will be made' : 'Adding BelongsToTenant trait to models...');
        
        $updatedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        
        foreach ($this->models as $modelPath) {
            $fullPath = base_path($modelPath);
            
            if (!file_exists($fullPath)) {
                $this->warn("Model not found: {$modelPath}");
                $errorCount++;
                continue;
            }
            
            $content = file_get_contents($fullPath);
            
            // Check if trait is already added
            if (str_contains($content, 'BelongsToTenant')) {
                $this->line("Skipped (already has trait): {$modelPath}");
                $skippedCount++;
                continue;
            }
            
            // Add use statement for the trait
            $useStatementAdded = false;
            $traitAdded = false;
            
            // Add use statement after namespace
            if (!str_contains($content, 'use App\Traits\BelongsToTenant;')) {
                $content = preg_replace(
                    '/(namespace [^;]+;)(\s*)(use )/s',
                    "$1$2use App\Traits\BelongsToTenant;\n$3",
                    $content,
                    1,
                    $count
                );
                
                if ($count === 0) {
                    // No existing use statements, add after namespace
                    $content = preg_replace(
                        '/(namespace [^;]+;)(\s*)(class )/s',
                        "$1$2\nuse App\Traits\BelongsToTenant;\n$2$3",
                        $content
                    );
                }
                $useStatementAdded = true;
            }
            
            // Add trait to class
            if (preg_match('/class\s+\w+[^{]*\{/', $content, $matches)) {
                $classDeclaration = $matches[0];
                
                // Check if class already has traits
                if (preg_match('/\{\s*use\s+([^;]+);/s', $content, $traitMatches)) {
                    // Add to existing traits
                    $existingTraits = $traitMatches[1];
                    $newTraits = $existingTraits . ', BelongsToTenant';
                    $content = str_replace(
                        "use {$existingTraits};",
                        "use {$newTraits};",
                        $content
                    );
                    $traitAdded = true;
                } else {
                    // Add as first trait
                    $content = preg_replace(
                        '/(\{)(\s*)/s',
                        "$1$2\n    use BelongsToTenant;\n$2",
                        $content,
                        1
                    );
                    $traitAdded = true;
                }
            }
            
            if ($useStatementAdded && $traitAdded) {
                if (!$dryRun) {
                    file_put_contents($fullPath, $content);
                }
                $this->info("Updated: {$modelPath}");
                $updatedCount++;
            } else {
                $this->error("Failed to update: {$modelPath}");
                $errorCount++;
            }
        }
        
        $this->newLine();
        $this->info("Summary:");
        $this->info("- Updated: {$updatedCount}");
        $this->info("- Skipped: {$skippedCount}");
        $this->info("- Errors: {$errorCount}");
        
        if ($dryRun) {
            $this->newLine();
            $this->comment("This was a dry run. Run without --dry-run to apply changes.");
        }
        
        return Command::SUCCESS;
    }
}