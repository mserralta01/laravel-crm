<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Models\Tenant\Tenant|null current()
 * @method static int|null currentId()
 * @method static bool hasTenant()
 * @method static bool canAccess($model)
 * @method static mixed runAs($tenant, callable $callback)
 * @method static \App\Models\Tenant\Tenant|null find(int $tenantId)
 * @method static void clearCache(int $tenantId)
 * @method static void logSecurityViolation(string $message, array $context = [])
 * 
 * @see \App\Services\TenantService
 */
class Tenant extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'tenant.service';
    }
}