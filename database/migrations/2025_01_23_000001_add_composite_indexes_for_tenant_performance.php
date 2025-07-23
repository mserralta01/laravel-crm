<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add composite indexes for common tenant queries to improve performance.
     */
    public function up(): void
    {
        // Leads - common queries by status and stage
        if (Schema::hasTable('leads')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->index(['tenant_id', 'status'], 'leads_tenant_status_index');
                $table->index(['tenant_id', 'lead_pipeline_stage_id'], 'leads_tenant_stage_index');
                $table->index(['tenant_id', 'user_id'], 'leads_tenant_user_index');
                $table->index(['tenant_id', 'created_at'], 'leads_tenant_created_index');
            });
        }

        // Contacts - common queries
        if (Schema::hasTable('persons')) {
            Schema::table('persons', function (Blueprint $table) {
                $table->index(['tenant_id', 'name'], 'persons_tenant_name_index');
                $table->index(['tenant_id', 'created_at'], 'persons_tenant_created_index');
            });
        }

        if (Schema::hasTable('organizations')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->index(['tenant_id', 'name'], 'organizations_tenant_name_index');
            });
        }

        // Products - common queries by SKU and name
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $table->index(['tenant_id', 'sku'], 'products_tenant_sku_index');
                $table->index(['tenant_id', 'name'], 'products_tenant_name_index');
                $table->index(['tenant_id', 'created_at'], 'products_tenant_created_index');
            });
        }

        // Quotes - common queries by status
        if (Schema::hasTable('quotes')) {
            Schema::table('quotes', function (Blueprint $table) {
                $table->index(['tenant_id', 'expired_at'], 'quotes_tenant_expired_index');
                $table->index(['tenant_id', 'created_at'], 'quotes_tenant_created_index');
                $table->index(['tenant_id', 'person_id'], 'quotes_tenant_person_index');
            });
        }

        // Activities - common queries by type and date
        if (Schema::hasTable('activities')) {
            Schema::table('activities', function (Blueprint $table) {
                $table->index(['tenant_id', 'type'], 'activities_tenant_type_index');
                $table->index(['tenant_id', 'is_done'], 'activities_tenant_done_index');
                $table->index(['tenant_id', 'created_at'], 'activities_tenant_created_index');
                $table->index(['tenant_id', 'user_id'], 'activities_tenant_user_index');
            });
        }

        // Emails - common queries by lead/person
        if (Schema::hasTable('emails')) {
            Schema::table('emails', function (Blueprint $table) {
                $table->index(['tenant_id', 'lead_id'], 'emails_tenant_lead_index');
                $table->index(['tenant_id', 'person_id'], 'emails_tenant_person_index');
                $table->index(['tenant_id', 'created_at'], 'emails_tenant_created_index');
            });
        }

        // Users - for login queries
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['tenant_id', 'email'], 'users_tenant_email_index');
                $table->index(['tenant_id', 'status'], 'users_tenant_status_index');
            });
        }

        // Tags - common lookups
        if (Schema::hasTable('tags')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->index(['tenant_id', 'name'], 'tags_tenant_name_index');
            });
        }

        // Attributes - for custom fields
        if (Schema::hasTable('attributes')) {
            Schema::table('attributes', function (Blueprint $table) {
                $table->index(['tenant_id', 'entity_type'], 'attributes_tenant_entity_index');
                $table->index(['tenant_id', 'code'], 'attributes_tenant_code_index');
            });
        }

        // Web forms
        if (Schema::hasTable('web_forms')) {
            Schema::table('web_forms', function (Blueprint $table) {
                $table->index(['tenant_id', 'form_id'], 'web_forms_tenant_form_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $indexes = [
            'leads' => [
                'leads_tenant_status_index',
                'leads_tenant_stage_index',
                'leads_tenant_user_index',
                'leads_tenant_created_index',
            ],
            'persons' => [
                'persons_tenant_name_index',
                'persons_tenant_created_index',
            ],
            'organizations' => [
                'organizations_tenant_name_index',
            ],
            'products' => [
                'products_tenant_sku_index',
                'products_tenant_name_index',
                'products_tenant_created_index',
            ],
            'quotes' => [
                'quotes_tenant_expired_index',
                'quotes_tenant_created_index',
                'quotes_tenant_person_index',
            ],
            'activities' => [
                'activities_tenant_type_index',
                'activities_tenant_done_index',
                'activities_tenant_created_index',
                'activities_tenant_user_index',
            ],
            'emails' => [
                'emails_tenant_lead_index',
                'emails_tenant_person_index',
                'emails_tenant_created_index',
            ],
            'users' => [
                'users_tenant_email_index',
                'users_tenant_status_index',
            ],
            'tags' => [
                'tags_tenant_name_index',
            ],
            'attributes' => [
                'attributes_tenant_entity_index',
                'attributes_tenant_code_index',
            ],
            'web_forms' => [
                'web_forms_tenant_form_index',
            ],
        ];

        foreach ($indexes as $table => $tableIndexes) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) use ($tableIndexes) {
                    foreach ($tableIndexes as $index) {
                        try {
                            $table->dropIndex($index);
                        } catch (\Exception $e) {
                            // Index might not exist
                        }
                    }
                });
            }
        }
    }
};