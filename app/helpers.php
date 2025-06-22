<?php

use App\Models\Tenant\Tenant;
use App\Facades\TenantUrl;

if (!function_exists('tenant_route')) {
    /**
     * Generate a URL for a tenant route.
     *
     * @param  string  $route
     * @param  mixed  $parameters
     * @param  bool  $absolute
     * @return string
     */
    function tenant_route(string $route, $parameters = [], bool $absolute = true): string
    {
        return TenantUrl::route(null, $route, $parameters, $absolute);
    }
}

if (!function_exists('tenant_url')) {
    /**
     * Generate a URL to a path for the current tenant.
     *
     * @param  string  $path
     * @param  mixed  $parameters
     * @param  bool|null  $secure
     * @return string
     */
    function tenant_url(string $path = '', $parameters = [], $secure = null): string
    {
        return TenantUrl::to(null, $path, $parameters, $secure);
    }
}

if (!function_exists('super_admin_route')) {
    /**
     * Generate a URL for a super admin route.
     *
     * @param  string  $route
     * @param  mixed  $parameters
     * @param  bool  $absolute
     * @return string
     */
    function super_admin_route(string $route, $parameters = [], bool $absolute = true): string
    {
        return TenantUrl::superAdmin($route, $parameters, $absolute);
    }
}

if (!function_exists('switch_tenant_url')) {
    /**
     * Generate a URL for switching to a different tenant.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @param  string|null  $path
     * @return string
     */
    function switch_tenant_url(Tenant $tenant, ?string $path = null): string
    {
        return TenantUrl::switchTenant($tenant, $path);
    }
}

if (!function_exists('current_tenant')) {
    /**
     * Get the current tenant instance.
     *
     * @return \App\Models\Tenant\Tenant|null
     */
    function current_tenant(): ?Tenant
    {
        return app()->bound('tenant') ? app('tenant') : null;
    }
}

if (!function_exists('is_tenant_context')) {
    /**
     * Check if the application is in tenant context.
     *
     * @return bool
     */
    function is_tenant_context(): bool
    {
        return app()->bound('tenant');
    }
}

if (!function_exists('is_super_admin_context')) {
    /**
     * Check if the application is in super admin context.
     *
     * @return bool
     */
    function is_super_admin_context(): bool
    {
        $host = request()->getHost();
        return $host === 'admin.' . config('app.domain');
    }
}

if (!function_exists('tenant_asset')) {
    /**
     * Generate a URL for a tenant-specific asset.
     *
     * @param  string  $path
     * @param  bool|null  $secure
     * @return string
     */
    function tenant_asset(string $path, $secure = null): string
    {
        $tenant = current_tenant();
        
        if ($tenant) {
            // Generate asset URL with tenant domain
            return TenantUrl::to($tenant, 'storage/tenants/' . $tenant->slug . '/' . ltrim($path, '/'), [], $secure);
        }
        
        return asset($path, $secure);
    }
}

if (!function_exists('tenant_storage_path')) {
    /**
     * Get the storage path for the current tenant.
     *
     * @param  string  $path
     * @return string
     */
    function tenant_storage_path(string $path = ''): string
    {
        $tenant = current_tenant();
        
        if ($tenant) {
            return storage_path('app/tenants/' . $tenant->id . ($path ? '/' . $path : ''));
        }
        
        return storage_path('app' . ($path ? '/' . $path : ''));
    }
}

if (!function_exists('tenant_cache_key')) {
    /**
     * Generate a tenant-specific cache key.
     *
     * @param  string  $key
     * @return string
     */
    function tenant_cache_key(string $key): string
    {
        $tenant = current_tenant();
        
        if ($tenant) {
            return 'tenant:' . $tenant->id . ':' . $key;
        }
        
        return $key;
    }
}

if (!function_exists('tenant_config')) {
    /**
     * Get a tenant-specific configuration value.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    function tenant_config(string $key, $default = null)
    {
        $tenant = current_tenant();
        
        if ($tenant) {
            // First check tenant settings
            $parts = explode('.', $key);
            if (count($parts) >= 2) {
                $group = $parts[0];
                $settingKey = implode('.', array_slice($parts, 1));
                
                $value = $tenant->getSetting($group, $settingKey);
                if ($value !== null) {
                    return $value;
                }
            }
        }
        
        // Fall back to regular config
        return config($key, $default);
    }
}