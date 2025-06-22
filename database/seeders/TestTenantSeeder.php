<?php

namespace Database\Seeders;

use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TestTenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates test tenants for development and testing purposes.
     */
    public function run(): void
    {
        // Check if test tenants already exist
        if (Tenant::where('slug', 'acme')->exists()) {
            $this->command->info('Test tenants already exist, skipping...');
            return;
        }

        // Create first test tenant
        $acmeTenant = Tenant::create([
            'name' => 'Acme Corporation',
            'slug' => 'acme',
            'email' => 'admin@acmecorp.com',
            'phone' => '+1-555-123-4567',
            'status' => Tenant::STATUS_ACTIVE,
            'trial_ends_at' => now()->addDays(30),
            'settings' => [
                'industry' => 'Technology',
                'timezone' => 'America/New_York',
            ],
        ]);

        // Create domain for tenant
        $acmeTenant->domains()->create([
            'domain' => 'acme.' . config('app.domain', 'groovecrm.test'),
            'is_primary' => true,
            'is_verified' => true,
            'verified_at' => now(),
            'ssl_enabled' => false,
        ]);

        // Create database configuration (for testing, we'll use prefixed database names)
        $acmeTenant->database()->create([
            'connection_name' => 'tenant_' . $acmeTenant->id,
            'database_name' => config('database.connections.mysql.database') . '_tenant_' . $acmeTenant->id,
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
        ]);

        // Create default settings
        TenantSetting::createDefaultSettings($acmeTenant);

        // Log creation activity
        $acmeTenant->logActivity('tenant.created', 'Test tenant created via seeder');

        $this->command->info('Test tenant "Acme Corporation" created successfully!');
        $this->command->info('Domain: acme.' . config('app.domain', 'groovecrm.test'));

        // Create second test tenant
        $betaTenant = Tenant::create([
            'name' => 'Beta Industries',
            'slug' => 'beta',
            'email' => 'admin@betaindustries.com',
            'phone' => '+1-555-987-6543',
            'status' => Tenant::STATUS_ACTIVE,
            'settings' => [
                'industry' => 'Manufacturing',
                'timezone' => 'Europe/London',
            ],
        ]);

        // Create domain for tenant
        $betaTenant->domains()->create([
            'domain' => 'beta.' . config('app.domain', 'groovecrm.test'),
            'is_primary' => true,
            'is_verified' => true,
            'verified_at' => now(),
            'ssl_enabled' => false,
        ]);

        // Create database configuration
        $betaTenant->database()->create([
            'connection_name' => 'tenant_' . $betaTenant->id,
            'database_name' => config('database.connections.mysql.database') . '_tenant_' . $betaTenant->id,
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
        ]);

        // Create default settings with some customizations
        TenantSetting::createDefaultSettings($betaTenant);
        
        // Customize some settings
        $betaTenant->setSetting('limits', 'max_users', 50, 'number');
        $betaTenant->setSetting('features', 'api_access', false, 'boolean');

        // Log creation activity
        $betaTenant->logActivity('tenant.created', 'Test tenant created via seeder');

        $this->command->info('Test tenant "Beta Industries" created successfully!');
        $this->command->info('Domain: beta.' . config('app.domain', 'groovecrm.test'));
    }
}
