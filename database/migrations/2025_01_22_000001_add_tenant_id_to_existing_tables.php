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
        // First run the tenant tables migrations
        if (!Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('email');
                $table->string('phone', 20)->nullable();
                $table->enum('status', ['active', 'suspended', 'inactive'])->default('active');
                $table->timestamp('trial_ends_at')->nullable();
                $table->json('settings')->nullable();
                $table->timestamps();
                
                $table->index('status');
                $table->index('slug');
                $table->index(['status', 'trial_ends_at']);
            });
        }

        // Add tenant_id to all tables
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $blueprintTable) use ($table) {
                    $blueprintTable->unsignedBigInteger('tenant_id')->nullable()->after('id');
                    $blueprintTable->index('tenant_id');
                });
            }
        }

        // Update unique constraints safely
        $this->updateUniqueConstraintsSafely();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('tenant_id');
                });
            }
        }
    }

    /**
     * Update unique constraints safely.
     */
    protected function updateUniqueConstraintsSafely()
    {
        // Update users table
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'email')) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->unique(['tenant_id', 'email'], 'users_tenant_email_unique');
                });
            } catch (\Exception $e) {
                // Constraint might already exist
            }
        }

        // Update products table
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'sku')) {
            try {
                Schema::table('products', function (Blueprint $table) {
                    $table->unique(['tenant_id', 'sku'], 'products_tenant_sku_unique');
                });
            } catch (\Exception $e) {
                // Constraint might already exist
            }
        }

        // Update tags table
        if (Schema::hasTable('tags') && Schema::hasColumn('tags', 'name')) {
            try {
                Schema::table('tags', function (Blueprint $table) {
                    $table->unique(['tenant_id', 'name'], 'tags_tenant_name_unique');
                });
            } catch (\Exception $e) {
                // Constraint might already exist
            }
        }

        // Update web_forms table
        if (Schema::hasTable('web_forms') && Schema::hasColumn('web_forms', 'form_id')) {
            try {
                Schema::table('web_forms', function (Blueprint $table) {
                    $table->unique(['tenant_id', 'form_id'], 'web_forms_tenant_form_id_unique');
                });
            } catch (\Exception $e) {
                // Constraint might already exist
            }
        }
    }
};