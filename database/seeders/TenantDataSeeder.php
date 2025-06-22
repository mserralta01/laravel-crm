<?php

namespace Database\Seeders;

use App\Models\Tenant\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantDataSeeder extends Seeder
{
    /**
     * The tables that need tenant_id populated.
     *
     * @var array
     */
    protected $tables = [
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
        'users',
        'groups',
        'user_groups',
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
        'lead_products',
        'lead_quotes',
        'lead_tags',
        'lead_activities',
        'person_organizations',
        'product_tags',
        'warehouses',
        'warehouse_locations',
        'warehouse_tags',
        'web_form_attributes',
        'saved_filters',
        'attributes',
        'attribute_options',
        'lead_attribute_values',
        'person_attribute_values',
        'organization_attribute_values',
        'product_attribute_values',
        'quote_attribute_values',
        'warehouse_attribute_values',
    ];

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Get or create a default tenant for existing data
        $defaultTenant = Tenant::firstOrCreate(
            ['slug' => 'default'],
            [
                'name' => 'Default Tenant',
                'email' => 'default@tenant.com',
                'status' => Tenant::STATUS_ACTIVE,
            ]
        );

        $this->command->info('Assigning existing data to default tenant: ' . $defaultTenant->name);

        // Update all tables with the default tenant_id
        foreach ($this->tables as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                $count = DB::table($table)
                    ->whereNull('tenant_id')
                    ->update(['tenant_id' => $defaultTenant->id]);
                
                if ($count > 0) {
                    $this->command->info("Updated {$count} records in {$table} table");
                }
            }
        }

        // Update core_config for tenant-specific settings
        if (DB::getSchemaBuilder()->hasTable('core_config')) {
            $count = DB::table('core_config')
                ->whereNull('tenant_id')
                ->where('is_global', false)
                ->update(['tenant_id' => $defaultTenant->id]);
            
            if ($count > 0) {
                $this->command->info("Updated {$count} tenant-specific configs in core_config table");
            }
        }

        $this->command->info('Tenant data seeding completed!');
    }
}