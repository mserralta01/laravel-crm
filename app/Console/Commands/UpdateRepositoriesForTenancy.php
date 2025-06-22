<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class UpdateRepositoriesForTenancy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:update-repositories {--dry-run : Show what would be changed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Krayin repositories to extend TenantAwareRepository';

    /**
     * Repositories that need to be updated.
     *
     * @var array
     */
    protected $repositories = [
        // Lead package
        'packages/Webkul/Lead/src/Repositories/LeadRepository.php',
        'packages/Webkul/Lead/src/Repositories/PipelineRepository.php',
        'packages/Webkul/Lead/src/Repositories/StageRepository.php',
        'packages/Webkul/Lead/src/Repositories/SourceRepository.php',
        'packages/Webkul/Lead/src/Repositories/TypeRepository.php',
        
        // Contact package
        'packages/Webkul/Contact/src/Repositories/PersonRepository.php',
        'packages/Webkul/Contact/src/Repositories/OrganizationRepository.php',
        
        // Product package
        'packages/Webkul/Product/src/Repositories/ProductRepository.php',
        
        // Quote package
        'packages/Webkul/Quote/src/Repositories/QuoteRepository.php',
        'packages/Webkul/Quote/src/Repositories/QuoteItemRepository.php',
        
        // Email package
        'packages/Webkul/Email/src/Repositories/EmailRepository.php',
        'packages/Webkul/Email/src/Repositories/AttachmentRepository.php',
        
        // Activity package
        'packages/Webkul/Activity/src/Repositories/ActivityRepository.php',
        'packages/Webkul/Activity/src/Repositories/FileRepository.php',
        'packages/Webkul/Activity/src/Repositories/ParticipantRepository.php',
        
        // User package
        'packages/Webkul/User/src/Repositories/UserRepository.php',
        'packages/Webkul/User/src/Repositories/GroupRepository.php',
        
        // Tag package
        'packages/Webkul/Tag/src/Repositories/TagRepository.php',
        
        // Workflow package
        'packages/Webkul/Automation/src/Repositories/WorkflowRepository.php',
        'packages/Webkul/Automation/src/Repositories/WebhookRepository.php',
        
        // EmailTemplate package
        'packages/Webkul/EmailTemplate/src/Repositories/EmailTemplateRepository.php',
        
        // WebForm package
        'packages/Webkul/WebForm/src/Repositories/WebFormRepository.php',
        
        // Warehouse package
        'packages/Webkul/Warehouse/src/Repositories/WarehouseRepository.php',
        'packages/Webkul/Warehouse/src/Repositories/LocationRepository.php',
        
        // Attribute package
        'packages/Webkul/Attribute/src/Repositories/AttributeRepository.php',
        'packages/Webkul/Attribute/src/Repositories/AttributeOptionRepository.php',
        'packages/Webkul/Attribute/src/Repositories/AttributeValueRepository.php',
    ];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        $this->info($dryRun ? 'DRY RUN - No changes will be made' : 'Updating repositories to use TenantAwareRepository...');
        
        $updatedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        
        foreach ($this->repositories as $repositoryPath) {
            $fullPath = base_path($repositoryPath);
            
            if (!file_exists($fullPath)) {
                $this->warn("Repository not found: {$repositoryPath}");
                $errorCount++;
                continue;
            }
            
            $content = file_get_contents($fullPath);
            $originalContent = $content;
            
            // Check if already extends TenantAwareRepository
            if (str_contains($content, 'TenantAwareRepository')) {
                $this->line("Skipped (already extends TenantAwareRepository): {$repositoryPath}");
                $skippedCount++;
                continue;
            }
            
            // Add use statement for TenantAwareRepository
            if (!str_contains($content, 'use App\Repositories\TenantAwareRepository;')) {
                // Add after namespace
                $content = preg_replace(
                    '/(namespace [^;]+;)(\s*)(use )/s',
                    "$1$2use App\Repositories\TenantAwareRepository;\n$3",
                    $content,
                    1,
                    $count
                );
                
                if ($count === 0) {
                    // No existing use statements, add after namespace
                    $content = preg_replace(
                        '/(namespace [^;]+;)(\s*)(class )/s',
                        "$1$2\nuse App\Repositories\TenantAwareRepository;\n$2$3",
                        $content
                    );
                }
            }
            
            // Remove existing Repository import if exists
            $content = preg_replace(
                '/use Webkul\\\\Core\\\\Eloquent\\\\Repository;\s*\n?/',
                '',
                $content
            );
            
            // Replace extends Repository with extends TenantAwareRepository
            $content = preg_replace(
                '/extends\s+Repository/',
                'extends TenantAwareRepository',
                $content
            );
            
            if ($content !== $originalContent) {
                if (!$dryRun) {
                    file_put_contents($fullPath, $content);
                }
                $this->info("Updated: {$repositoryPath}");
                $updatedCount++;
            } else {
                $this->error("Failed to update: {$repositoryPath}");
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