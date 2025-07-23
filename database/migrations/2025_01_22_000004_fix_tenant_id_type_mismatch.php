<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix the tenant_id type mismatch.
     * Changes UUID tenant_id columns to unsigned bigint to match tenants.id
     */
    public function up(): void
    {
        $tables = [
            // Core Business Entity Tables
            'leads', 'persons', 'organizations', 'quotes', 'quote_items',
            'products', 'product_inventories', 'emails', 'email_attachments',
            'activities', 'activity_files', 'activity_participants',
            
            // User & Access Control Tables
            'users', 'groups', 'user_groups', 'user_password_resets',
            
            // CRM Configuration Tables
            'lead_pipelines', 'lead_stages', 'lead_pipeline_stages',
            'lead_sources', 'lead_types', 'tags', 'workflows', 'webhooks',
            'email_templates', 'web_forms',
            
            // Junction/Relationship Tables
            'lead_products', 'lead_quotes', 'lead_tags', 'lead_activities',
            'person_organizations', 'product_tags', 'contact_tags', 'email_tags',
            
            // Additional Business Tables
            'warehouses', 'warehouse_locations', 'warehouse_tags',
            'web_form_attributes', 'saved_filters',
            
            // Attribute System Tables
            'attributes', 'attribute_options',
            
            // Attribute Value Tables
            'lead_attribute_values', 'person_attribute_values',
            'organization_attribute_values', 'product_attribute_values',
            'quote_attribute_values', 'warehouse_attribute_values',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                $this->info("Fixing tenant_id type in {$table}");
                
                Schema::table($table, function (Blueprint $blueprintTable) use ($table) {
                    // First drop the foreign key constraint if it exists
                    try {
                        $blueprintTable->dropForeign(['tenant_id']);
                    } catch (\Exception $e) {
                        // Foreign key might not exist
                    }
                    
                    // Drop the UUID column
                    $blueprintTable->dropColumn('tenant_id');
                });
                
                // Add the correct type column
                Schema::table($table, function (Blueprint $blueprintTable) use ($table) {
                    $columns = Schema::getColumnListing($table);
                    
                    if (in_array('id', $columns)) {
                        $blueprintTable->unsignedBigInteger('tenant_id')->nullable()->after('id');
                    } else {
                        // For junction tables without id, add at the beginning
                        $blueprintTable->unsignedBigInteger('tenant_id')->nullable()->first();
                    }
                    
                    $blueprintTable->index('tenant_id');
                    
                    // Add foreign key constraint
                    $blueprintTable->foreign('tenant_id')
                        ->references('id')
                        ->on('tenants')
                        ->onDelete('cascade');
                });
            }
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        // This is a fix migration, reverting would recreate the problem
        // So we'll just leave it as is
    }
    
    /**
     * Log info message.
     */
    protected function info($message)
    {
        if (app()->runningInConsole()) {
            echo "[INFO] {$message}\n";
        }
    }
};