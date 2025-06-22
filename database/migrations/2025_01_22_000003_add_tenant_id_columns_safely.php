<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
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

        // Add tenant_id column to each table
        foreach ($tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'tenant_id')) {
                $this->info("Adding tenant_id to {$table}");
                
                Schema::table($table, function (Blueprint $blueprintTable) use ($table) {
                    // Check if table has 'id' column
                    $columns = Schema::getColumnListing($table);
                    
                    if (in_array('id', $columns)) {
                        $blueprintTable->uuid('tenant_id')->nullable()->after('id');
                    } else {
                        // For junction tables without id, add at the beginning
                        $blueprintTable->uuid('tenant_id')->nullable()->first();
                    }
                    
                    $blueprintTable->index('tenant_id');
                });
            }
        }

        // Update unique constraints for specific tables
        $this->updateUniqueConstraints();

        // Add foreign key constraints
        $this->addForeignKeyConstraints($tables);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $tables = [
            'leads', 'persons', 'organizations', 'quotes', 'quote_items',
            'products', 'product_inventories', 'emails', 'email_attachments',
            'activities', 'activity_files', 'activity_participants',
            'users', 'groups', 'user_groups', 'user_password_resets',
            'lead_pipelines', 'lead_stages', 'lead_pipeline_stages',
            'lead_sources', 'lead_types', 'tags', 'workflows', 'webhooks',
            'email_templates', 'web_forms', 'lead_products', 'lead_quotes',
            'lead_tags', 'lead_activities', 'person_organizations',
            'product_tags', 'contact_tags', 'email_tags', 'warehouses',
            'warehouse_locations', 'warehouse_tags', 'web_form_attributes',
            'saved_filters', 'attributes', 'attribute_options',
            'lead_attribute_values', 'person_attribute_values',
            'organization_attribute_values', 'product_attribute_values',
            'quote_attribute_values', 'warehouse_attribute_values',
        ];

        // Remove foreign key constraints
        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                try {
                    Schema::table($table, function (Blueprint $blueprintTable) {
                        $blueprintTable->dropForeign(['tenant_id']);
                    });
                } catch (\Exception $e) {
                    // Foreign key might not exist
                }
            }
        }

        // Restore original unique constraints
        $this->restoreUniqueConstraints();

        // Remove tenant_id columns
        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $blueprintTable) {
                    $blueprintTable->dropColumn('tenant_id');
                });
            }
        }
    }

    /**
     * Update unique constraints to include tenant_id.
     */
    protected function updateUniqueConstraints()
    {
        // Update users table
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'email')) {
            $this->updateUniqueConstraint('users', ['email'], ['tenant_id', 'email']);
        }

        // Update products table
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'sku')) {
            $this->updateUniqueConstraint('products', ['sku'], ['tenant_id', 'sku']);
        }

        // Update web_forms table
        if (Schema::hasTable('web_forms') && Schema::hasColumn('web_forms', 'form_id')) {
            $this->updateUniqueConstraint('web_forms', ['form_id'], ['tenant_id', 'form_id']);
        }

        // Update tags table
        if (Schema::hasTable('tags') && Schema::hasColumn('tags', 'name')) {
            $this->updateUniqueConstraint('tags', ['name'], ['tenant_id', 'name']);
        }
    }

    /**
     * Restore original unique constraints.
     */
    protected function restoreUniqueConstraints()
    {
        // Restore users table
        if (Schema::hasTable('users')) {
            $this->updateUniqueConstraint('users', ['tenant_id', 'email'], ['email']);
        }

        // Restore products table
        if (Schema::hasTable('products')) {
            $this->updateUniqueConstraint('products', ['tenant_id', 'sku'], ['sku']);
        }

        // Restore web_forms table
        if (Schema::hasTable('web_forms')) {
            $this->updateUniqueConstraint('web_forms', ['tenant_id', 'form_id'], ['form_id']);
        }

        // Restore tags table
        if (Schema::hasTable('tags')) {
            $this->updateUniqueConstraint('tags', ['tenant_id', 'name'], ['name']);
        }
    }

    /**
     * Update unique constraint on a table.
     */
    protected function updateUniqueConstraint($table, $oldColumns, $newColumns)
    {
        try {
            // Get existing indexes
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes($table);
            
            Schema::table($table, function (Blueprint $blueprintTable) use ($indexes, $oldColumns, $newColumns, $table) {
                // Drop existing unique constraints on old columns
                foreach ($indexes as $index) {
                    if ($index->isUnique()) {
                        $indexColumns = $index->getColumns();
                        if (count($indexColumns) === count($oldColumns) && 
                            empty(array_diff($indexColumns, $oldColumns))) {
                            try {
                                $blueprintTable->dropIndex($index->getName());
                            } catch (\Exception $e) {
                                // Index might not exist
                            }
                        }
                    }
                }
                
                // Add new unique constraint
                $constraintName = $table . '_' . implode('_', $newColumns) . '_unique';
                $blueprintTable->unique($newColumns, $constraintName);
            });
        } catch (\Exception $e) {
            $this->warn("Could not update unique constraint for {$table}: " . $e->getMessage());
        }
    }

    /**
     * Add foreign key constraints for tenant_id.
     */
    protected function addForeignKeyConstraints($tables)
    {
        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                try {
                    Schema::table($table, function (Blueprint $blueprintTable) {
                        $blueprintTable->foreign('tenant_id')
                            ->references('id')
                            ->on('tenants')
                            ->onDelete('cascade');
                    });
                } catch (\Exception $e) {
                    $this->warn("Could not add foreign key for {$table}: " . $e->getMessage());
                }
            }
        }
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

    /**
     * Log warning message.
     */
    protected function warn($message)
    {
        if (app()->runningInConsole()) {
            echo "[WARN] {$message}\n";
        }
    }
};