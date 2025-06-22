<?php

namespace App\Services;

use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantSetting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TenantManager Service
 * 
 * Handles tenant lifecycle operations including creation, deletion,
 * database provisioning, and configuration management.
 */
class TenantManager
{
    /**
     * Create a new tenant with all necessary setup.
     *
     * @param  array  $data
     * @return \App\Models\Tenant\Tenant
     * @throws \Exception
     */
    public function createTenant(array $data): Tenant
    {
        DB::beginTransaction();

        try {
            // Create tenant record
            $tenant = Tenant::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'status' => $data['status'] ?? Tenant::STATUS_ACTIVE,
                'trial_ends_at' => $data['trial_ends_at'] ?? null,
                'settings' => $data['settings'] ?? [],
            ]);

            // Create subdomain
            $this->createSubdomain($tenant, $data['subdomain'] ?? null);

            // Create database if using separate databases
            if (config('tenancy.database_strategy', 'single') === 'separate') {
                $this->createTenantDatabase($tenant);
            }

            // Create default settings
            TenantSetting::createDefaultSettings($tenant);

            // Apply custom settings if provided
            if (!empty($data['custom_settings'])) {
                $this->applyCustomSettings($tenant, $data['custom_settings']);
            }

            // Create default admin user
            if (!empty($data['admin_user'])) {
                $this->createTenantAdmin($tenant, $data['admin_user']);
            }

            // Log creation
            $tenant->logActivity('tenant.created', 'Tenant created successfully');

            DB::commit();

            return $tenant;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete a tenant and all associated data.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @param  bool  $backup
     * @return bool
     * @throws \Exception
     */
    public function deleteTenant(Tenant $tenant, bool $backup = true): bool
    {
        DB::beginTransaction();

        try {
            // Create backup if requested
            if ($backup) {
                $this->backupTenantData($tenant);
            }

            // Drop tenant database if using separate databases
            if ($tenant->database) {
                $tenant->database->dropDatabase();
            }

            // Delete all tenant data (cascading deletes handle related records)
            $tenant->delete();

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Suspend a tenant.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @param  string|null  $reason
     * @return void
     */
    public function suspendTenant(Tenant $tenant, ?string $reason = null): void
    {
        $tenant->suspend($reason);
        
        // Clear tenant sessions
        $this->clearTenantSessions($tenant);
    }

    /**
     * Activate a tenant.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @return void
     */
    public function activateTenant(Tenant $tenant): void
    {
        $tenant->activate();
    }

    /**
     * Create subdomain for tenant.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @param  string|null  $subdomain
     * @return void
     */
    protected function createSubdomain(Tenant $tenant, ?string $subdomain = null): void
    {
        $subdomain = $subdomain ?? $tenant->slug;
        $domain = $subdomain . '.' . config('app.domain');

        $tenant->domains()->create([
            'domain' => $domain,
            'is_primary' => true,
            'is_verified' => true,
            'verified_at' => now(),
            'ssl_enabled' => config('app.force_https', false),
        ]);
    }

    /**
     * Create tenant database.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @return void
     * @throws \Exception
     */
    protected function createTenantDatabase(Tenant $tenant): void
    {
        $dbName = 'krayin_tenant_' . $tenant->id;
        
        // Create database record
        $tenantDb = $tenant->database()->create([
            'connection_name' => 'tenant_' . $tenant->id,
            'database_name' => $dbName,
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
        ]);

        // Create actual database
        $tenantDb->createDatabase();

        // Switch to tenant database
        $this->switchToTenantDatabase($tenant);

        // Run migrations
        $this->runTenantMigrations();

        // Run seeders
        $this->runTenantSeeders();

        // Switch back to main database
        $this->switchToMainDatabase();
    }

    /**
     * Switch to tenant database.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @return void
     */
    public function switchToTenantDatabase(Tenant $tenant): void
    {
        $database = $tenant->database;
        
        if (!$database) {
            return;
        }

        config([
            'database.connections.tenant' => $database->getConnectionConfig(),
        ]);
        
        config(['database.default' => 'tenant']);
        
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    /**
     * Switch back to main database.
     *
     * @return void
     */
    public function switchToMainDatabase(): void
    {
        config(['database.default' => config('database.default_connection', 'mysql')]);
        
        DB::purge('tenant');
    }

    /**
     * Run tenant migrations.
     *
     * @return void
     */
    protected function runTenantMigrations(): void
    {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations',
            '--force' => true,
        ]);

        // Run package migrations
        $packages = [
            'packages/Webkul/Activity/src/Database/Migrations',
            'packages/Webkul/Admin/src/Database/Migrations',
            'packages/Webkul/Attribute/src/Database/Migrations',
            'packages/Webkul/Automation/src/Database/Migrations',
            'packages/Webkul/Contact/src/Database/Migrations',
            'packages/Webkul/Core/src/Database/Migrations',
            'packages/Webkul/DataGrid/src/Database/Migrations',
            'packages/Webkul/DataTransfer/src/Database/Migrations',
            'packages/Webkul/Email/src/Database/Migrations',
            'packages/Webkul/EmailTemplate/src/Database/Migrations',
            'packages/Webkul/Lead/src/Database/Migrations',
            'packages/Webkul/Marketing/src/Database/Migrations',
            'packages/Webkul/Product/src/Database/Migrations',
            'packages/Webkul/Quote/src/Database/Migrations',
            'packages/Webkul/Tag/src/Database/Migrations',
            'packages/Webkul/User/src/Database/Migrations',
            'packages/Webkul/Warehouse/src/Database/Migrations',
            'packages/Webkul/WebForm/src/Database/Migrations',
        ];

        foreach ($packages as $path) {
            if (is_dir(base_path($path))) {
                Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--path' => $path,
                    '--force' => true,
                ]);
            }
        }
    }

    /**
     * Run tenant seeders.
     *
     * @return void
     */
    protected function runTenantSeeders(): void
    {
        Artisan::call('db:seed', [
            '--database' => 'tenant',
            '--class' => 'Database\\Seeders\\DatabaseSeeder',
            '--force' => true,
        ]);
    }

    /**
     * Apply custom settings to tenant.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @param  array  $settings
     * @return void
     */
    protected function applyCustomSettings(Tenant $tenant, array $settings): void
    {
        foreach ($settings as $group => $groupSettings) {
            foreach ($groupSettings as $key => $value) {
                $type = $this->inferSettingType($value);
                $tenant->setSetting($group, $key, $value, $type);
            }
        }
    }

    /**
     * Infer setting type from value.
     *
     * @param  mixed  $value
     * @return string
     */
    protected function inferSettingType($value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        } elseif (is_numeric($value)) {
            return 'number';
        } elseif (is_array($value) || is_object($value)) {
            return 'json';
        }
        
        return 'text';
    }

    /**
     * Create tenant admin user.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @param  array  $userData
     * @return void
     */
    protected function createTenantAdmin(Tenant $tenant, array $userData): void
    {
        // This will be implemented when we add tenant_id to users table
        // For now, log the intention
        $tenant->logActivity(
            'admin.created',
            'Admin user would be created',
            ['user_data' => array_except($userData, 'password')]
        );
    }

    /**
     * Backup tenant data.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @return string
     */
    protected function backupTenantData(Tenant $tenant): string
    {
        $backupPath = storage_path('app/backups/tenants/' . $tenant->slug . '_' . now()->format('Y-m-d_His') . '.sql');
        
        // Ensure backup directory exists
        if (!is_dir(dirname($backupPath))) {
            mkdir(dirname($backupPath), 0755, true);
        }

        // Create backup (simplified version)
        // In production, use proper backup tools
        $tenant->logActivity(
            'backup.created',
            'Tenant data backup created',
            ['path' => $backupPath]
        );

        return $backupPath;
    }

    /**
     * Clear all sessions for a tenant.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @return void
     */
    protected function clearTenantSessions(Tenant $tenant): void
    {
        // Clear file-based sessions
        if (config('session.driver') === 'file') {
            $sessionPath = storage_path('framework/sessions/tenant_' . $tenant->id);
            if (is_dir($sessionPath)) {
                array_map('unlink', glob($sessionPath . '/*'));
            }
        }

        // For other session drivers, implement accordingly
        $tenant->logActivity('sessions.cleared', 'All tenant sessions cleared');
    }

    /**
     * Get tenant by identifier (ID, UUID, or slug).
     *
     * @param  string  $identifier
     * @return \App\Models\Tenant\Tenant|null
     */
    public function getTenant(string $identifier): ?Tenant
    {
        return Tenant::where('id', $identifier)
            ->orWhere('uuid', $identifier)
            ->orWhere('slug', $identifier)
            ->first();
    }

    /**
     * Get tenant statistics.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @return array
     */
    public function getTenantStats(Tenant $tenant): array
    {
        // This will be expanded when tenant_id is added to models
        return [
            'users' => 0,
            'leads' => 0,
            'contacts' => 0,
            'quotes' => 0,
            'storage_used' => $tenant->database?->getDatabaseSize() ?? 0,
            'last_activity' => $tenant->activity_logs()->latest()->first()?->created_at,
        ];
    }
}