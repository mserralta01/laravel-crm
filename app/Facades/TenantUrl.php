<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * TenantUrl Facade
 * 
 * @method static string route(?\\App\\Models\\Tenant\\Tenant $tenant, string $route, $parameters = [], bool $absolute = true)
 * @method static string to(?\\App\\Models\\Tenant\\Tenant $tenant, string $path, $parameters = [], $secure = null)
 * @method static string switchTenant(\\App\\Models\\Tenant\\Tenant $tenant, ?string $path = null)
 * @method static string superAdmin(string $route, $parameters = [], bool $absolute = true)
 * @method static string signedRoute(?\\App\\Models\\Tenant\\Tenant $tenant, string $route, $parameters = [], $expiration = null)
 * @method static string temporarySignedRoute(?\\App\\Models\\Tenant\\Tenant $tenant, string $route, $expiration, $parameters = [])
 * 
 * @see \App\Services\TenantUrlGenerator
 */
class TenantUrl extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'tenant.url';
    }
}