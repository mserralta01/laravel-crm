<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The tables that need tenant_id columns.
     *
     * @var array
     */
    protected $tables = [
        // Core Business Entity Tables
        'leads',
        'persons',
        'organizations',
        'quotes',
        'quote_items',
        'products',
        'product_inventories',
        'emails',
        'email_attachments',
        'activities',
        'activity_files',
        'activity_participants',
        
        // User & Access Control Tables
        'users',
        'groups',
        'user_groups',
        'user_password_resets',
        
        // CRM Configuration Tables
        'lead_pipelines',
        'lead_stages',
        'lead_pipeline_stages',
        'lead_sources',
        'lead_types',
        'tags',
        'workflows',
        'webhooks',
        'email_templates',
        'web_forms',
        
        // Junction/Relationship Tables
        'lead_products',
        'lead_quotes',
        'lead_tags',
        'lead_activities',
        'person_organizations',
        'product_tags',
        'contact_tags',
        'email_tags',
        
        // Additional Business Tables
        'warehouses',
        'warehouse_locations',
        'warehouse_tags',
        'web_form_attributes',
        'saved_filters',
        
        // Attribute System Tables
        'attributes',
        'attribute_options',
        
        // Attribute Value Tables (dynamic)
        'lead_attribute_values',
        'person_attribute_values',
        'organization_attribute_values',
        'product_attribute_values',
        'quote_attribute_values',
        'warehouse_attribute_values',
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'tenant_id')) {
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

        // Update unique constraints to include tenant_id
        $this->updateUniqueConstraints();
        
        // Add foreign key constraints
        $this->addForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove foreign key constraints first
        $this->removeForeignKeyConstraints();
        
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('tenant_id');
                });
            }
        }
    }

    /**
     * Update unique constraints to include tenant_id.
     *
     * @return void
     */
    protected function updateUniqueConstraints()
    {
        // Update users table unique constraint
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                // Drop existing unique constraint on email
                $table->dropUnique(['email']);
                
                // Add composite unique constraint
                $table->unique(['tenant_id', 'email'], 'users_tenant_email_unique');
            });
        }

        // Update products table unique constraint
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'sku')) {
                    // Drop existing unique constraint on sku if exists
                    try {
                        $table->dropUnique(['sku']);
                    } catch (\Exception $e) {
                        // Constraint might not exist
                    }
                    
                    // Add composite unique constraint
                    $table->unique(['tenant_id', 'sku'], 'products_tenant_sku_unique');
                }
            });
        }

        // Update web_forms table unique constraint
        if (Schema::hasTable('web_forms')) {
            Schema::table('web_forms', function (Blueprint $table) {
                if (Schema::hasColumn('web_forms', 'form_id')) {
                    // Drop existing unique constraint on form_id if exists
                    try {
                        $table->dropUnique(['form_id']);
                    } catch (\Exception $e) {
                        // Constraint might not exist
                    }
                    
                    // Add composite unique constraint
                    $table->unique(['tenant_id', 'form_id'], 'web_forms_tenant_form_id_unique');
                }
            });
        }

        // Update tags table unique constraint
        if (Schema::hasTable('tags')) {
            // Check if the constraint exists before trying to drop it
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes('tags');
            
            Schema::table('tags', function (Blueprint $table) use ($indexes) {
                if (Schema::hasColumn('tags', 'name')) {
                    // Drop existing unique constraint on name if exists
                    foreach ($indexes as $index) {
                        if ($index->isUnique() && in_array('name', $index->getColumns())) {
                            try {
                                $table->dropIndex($index->getName());
                            } catch (\Exception $e) {
                                // Ignore if already dropped
                            }
                        }
                    }
                    
                    // Add composite unique constraint
                    $table->unique(['tenant_id', 'name'], 'tags_tenant_name_unique');
                }
            });
        }
    }

    /**
     * Add foreign key constraints for tenant_id.
     *
     * @return void
     */
    protected function addForeignKeyConstraints()
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->foreign('tenant_id')
                        ->references('id')
                        ->on('tenants')
                        ->onDelete('cascade');
                });
            }
        }
    }

    /**
     * Remove foreign key constraints for tenant_id.
     *
     * @return void
     */
    protected function removeForeignKeyConstraints()
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $table->dropForeign(['tenant_id']);
                });
            }
        }
    }
};