<?php

namespace App\Services;

use App\Models\Tenant\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * TenantService
 * 
 * Centralized service for tenant operations and validations.
 * Provides helper methods for tenant context management.
 */
class TenantService
{
    /**
     * Get the current tenant instance.
     *
     * @return Tenant|null
     */
    public function current(): ?Tenant
    {
        if (!app()->bound('tenant')) {
            return null;
        }
        
        return app('tenant');
    }
    
    /**
     * Get the current tenant ID.
     *
     * @return int|null
     */
    public function currentId(): ?int
    {
        $tenant = $this->current();
        return $tenant ? $tenant->id : null;
    }
    
    /**
     * Check if a tenant context is set.
     *
     * @return bool
     */
    public function hasTenant(): bool
    {
        return $this->current() !== null;
    }
    
    /**
     * Validate if the current user can access a resource.
     *
     * @param mixed $model
     * @return bool
     */
    public function canAccess($model): bool
    {
        if (!$this->hasTenant()) {
            return false;
        }
        
        // Check if model has tenant_id
        if (!property_exists($model, 'tenant_id') && !method_exists($model, 'getAttribute')) {
            return true; // Model doesn't support multi-tenancy
        }
        
        return $model->tenant_id === $this->currentId();
    }
    
    /**
     * Switch tenant context temporarily.
     *
     * @param Tenant|int $tenant
     * @param callable $callback
     * @return mixed
     */
    public function runAs($tenant, callable $callback)
    {
        $tenantModel = $tenant instanceof Tenant ? $tenant : Tenant::find($tenant);
        
        if (!$tenantModel || !$tenantModel->isActive()) {
            throw new \InvalidArgumentException('Invalid or inactive tenant');
        }
        
        $originalTenant = $this->current();
        $originalTenantId = $this->currentId();
        
        try {
            // Set new tenant context
            app()->singleton('tenant', function () use ($tenantModel) {
                return $tenantModel;
            });
            app()->singleton('tenant.id', function () use ($tenantModel) {
                return $tenantModel->id;
            });
            
            Log::info('Switching tenant context', [
                'from_tenant_id' => $originalTenantId,
                'to_tenant_id' => $tenantModel->id,
                'user_id' => auth()->id()
            ]);
            
            return $callback();
        } finally {
            // Restore original tenant context
            if ($originalTenant) {
                app()->singleton('tenant', function () use ($originalTenant) {
                    return $originalTenant;
                });
                app()->singleton('tenant.id', function () use ($originalTenantId) {
                    return $originalTenantId;
                });
            } else {
                app()->forgetInstance('tenant');
                app()->forgetInstance('tenant.id');
            }
        }
    }
    
    /**
     * Get tenant by ID with caching.
     *
     * @param int $tenantId
     * @return Tenant|null
     */
    public function find(int $tenantId): ?Tenant
    {
        return Cache::remember(
            "tenant.{$tenantId}",
            now()->addMinutes(5),
            fn() => Tenant::find($tenantId)
        );
    }
    
    /**
     * Clear tenant cache.
     *
     * @param int $tenantId
     * @return void
     */
    public function clearCache(int $tenantId): void
    {
        Cache::forget("tenant.{$tenantId}");
    }
    
    /**
     * Log a security violation.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function logSecurityViolation(string $message, array $context = []): void
    {
        Log::warning('Tenant Security Violation: ' . $message, array_merge([
            'tenant_id' => $this->currentId(),
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
            'url' => request()->fullUrl(),
        ], $context));
    }
}