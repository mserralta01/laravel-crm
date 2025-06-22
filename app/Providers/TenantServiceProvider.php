<?php

namespace App\Providers;

use App\Models\Tenant\Tenant;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

/**
 * TenantServiceProvider
 * 
 * Manages tenant-related services and configurations.
 * Handles tenant context for queued jobs and provides helper services.
 */
class TenantServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register tenant manager service
        $this->app->singleton('tenant.manager', function ($app) {
            return new \App\Services\TenantManager();
        });

        // Register tenant resolver
        $this->app->singleton('tenant.resolver', function ($app) {
            return new \App\Services\TenantResolver();
        });

        // Register tenant URL generator
        $this->app->singleton('tenant.url', function ($app) {
            return new \App\Services\TenantUrlGenerator();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Configure tenant context for queued jobs
        $this->configureQueueTenantContext();

        // Add tenant ID to log context
        $this->configureLogging();

        // Register tenant-aware blade directives
        $this->registerBladeDirectives();

        // Configure tenant-specific configurations
        $this->configureTenantSettings();
    }

    /**
     * Configure tenant context for queued jobs.
     *
     * @return void
     */
    protected function configureQueueTenantContext(): void
    {
        // Before job is processed, restore tenant context
        Queue::before(function (JobProcessing $event) {
            if (isset($event->job->payload()['tenant_id'])) {
                $tenantId = $event->job->payload()['tenant_id'];
                $tenant = Tenant::find($tenantId);

                if ($tenant) {
                    app()->instance('tenant', $tenant);
                    app()->instance('tenant.id', $tenant->id);
                    
                    // Apply tenant configuration
                    if ($tenant->database) {
                        $this->configureTenantDatabase($tenant);
                    }
                }
            }
        });

        // After job is processed, clear tenant context
        Queue::after(function () {
            app()->forgetInstance('tenant');
            app()->forgetInstance('tenant.id');
            
            // Reset to default database connection
            config(['database.default' => config('database.default_connection', 'mysql')]);
        });
    }

    /**
     * Configure logging to include tenant context.
     *
     * @return void
     */
    protected function configureLogging(): void
    {
        if (app()->bound('tenant')) {
            $tenant = app('tenant');
            
            // Add tenant context to all log entries
            \Log::withContext([
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
            ]);
        }
    }

    /**
     * Register tenant-aware Blade directives.
     *
     * @return void
     */
    protected function registerBladeDirectives(): void
    {
        // @tenant directive
        \Blade::if('tenant', function () {
            return app()->bound('tenant');
        });

        // @superadmin directive
        \Blade::if('superadmin', function () {
            return auth()->guard('super-admin')->check();
        });

        // @impersonating directive
        \Blade::if('impersonating', function () {
            return app()->bound('impersonation');
        });

        // @tenantfeature directive
        \Blade::if('tenantfeature', function ($feature) {
            if (!app()->bound('tenant')) {
                return false;
            }

            $tenant = app('tenant');
            return $tenant->getSetting('features', $feature, false);
        });
    }

    /**
     * Configure tenant-specific settings.
     *
     * @return void
     */
    protected function configureTenantSettings(): void
    {
        if (!app()->bound('tenant')) {
            return;
        }

        $tenant = app('tenant');

        // Apply tenant-specific configurations
        $this->applyTenantLimits($tenant);
        $this->applyTenantFeatures($tenant);
        $this->applyTenantBranding($tenant);
    }

    /**
     * Apply tenant resource limits.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @return void
     */
    protected function applyTenantLimits(Tenant $tenant): void
    {
        $limits = $tenant->getSettingsGroup('limits');

        // Apply rate limiting
        if (isset($limits['api_rate_limit_per_hour'])) {
            config(['app.api_rate_limit' => $limits['api_rate_limit_per_hour']]);
        }

        // Apply other limits as needed
        config(['tenant.limits' => $limits]);
    }

    /**
     * Apply tenant feature flags.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @return void
     */
    protected function applyTenantFeatures(Tenant $tenant): void
    {
        $features = $tenant->getSettingsGroup('features');
        config(['tenant.features' => $features]);
    }

    /**
     * Apply tenant branding settings.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @return void
     */
    protected function applyTenantBranding(Tenant $tenant): void
    {
        $branding = $tenant->getSettingsGroup('branding');
        
        if (!empty($branding)) {
            config(['tenant.branding' => $branding]);
            
            // Share branding with all views
            view()->share('tenantBranding', $branding);
        }
    }

    /**
     * Configure tenant database connection.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @return void
     */
    protected function configureTenantDatabase(Tenant $tenant): void
    {
        $database = $tenant->database;
        
        if (!$database) {
            return;
        }

        // Set the tenant database configuration
        config([
            'database.connections.tenant' => $database->getConnectionConfig(),
        ]);

        // Set default connection to tenant
        config(['database.default' => 'tenant']);
        
        // Purge and reconnect
        \DB::purge('tenant');
        \DB::reconnect('tenant');
    }
}
