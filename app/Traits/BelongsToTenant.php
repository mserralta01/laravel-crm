<?php

namespace App\Traits;

use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BelongsToTenant Trait
 * 
 * Add this trait to any model that should be scoped by tenant.
 * Automatically sets tenant_id on creation and provides helper methods.
 * 
 * Requirements:
 * - Model's table must have a 'tenant_id' column
 * - The tenant_id should be indexed for performance
 * 
 * Usage:
 * ```php
 * class Lead extends Model
 * {
 *     use BelongsToTenant;
 * }
 * ```
 */
trait BelongsToTenant
{
    /**
     * Boot the trait.
     * 
     * @return void
     */
    public static function bootBelongsToTenant(): void
    {
        // Automatically set tenant_id when creating a new model
        static::creating(function (Model $model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = static::getCurrentTenantId();
            }
        });

        // Apply tenant scope for all queries
        static::addGlobalScope('tenant', function ($builder) {
            $tenantId = static::getCurrentTenantId();
            
            if ($tenantId) {
                $builder->where($builder->getModel()->getTable() . '.tenant_id', $tenantId);
            }
        });
    }

    /**
     * Get the tenant that owns the model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope a query to a specific tenant.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $tenantId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to exclude tenant filtering.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithoutTenant($query)
    {
        return $query->withoutGlobalScope('tenant');
    }

    /**
     * Get the current tenant ID.
     *
     * @return string|null
     */
    protected static function getCurrentTenantId(): ?string
    {
        // First check if we're in a queued job with tenant context
        if (app()->bound('tenant.id')) {
            return app('tenant.id');
        }

        // Check if tenant is set in the container
        if (app()->bound('tenant')) {
            return app('tenant')->id;
        }

        // Check config
        if (config('tenant.current_id')) {
            return config('tenant.current_id');
        }

        // For console commands, check if tenant is specified
        if (app()->runningInConsole() && app()->bound('command.tenant')) {
            return app('command.tenant')->id;
        }

        return null;
    }

    /**
     * Set the tenant for this model instance.
     *
     * @param  \App\Models\Tenant\Tenant|string  $tenant
     * @return $this
     */
    public function setTenant($tenant)
    {
        if ($tenant instanceof Tenant) {
            $this->tenant_id = $tenant->id;
        } else {
            $this->tenant_id = $tenant;
        }

        return $this;
    }

    /**
     * Check if the model belongs to a specific tenant.
     *
     * @param  \App\Models\Tenant\Tenant|string  $tenant
     * @return bool
     */
    public function belongsToTenant($tenant): bool
    {
        if ($tenant instanceof Tenant) {
            return $this->tenant_id === $tenant->id;
        }

        return $this->tenant_id === $tenant;
    }

    /**
     * Create a new model instance for a specific tenant.
     *
     * @param  array  $attributes
     * @param  \App\Models\Tenant\Tenant|string  $tenant
     * @return static
     */
    public static function createForTenant(array $attributes, $tenant)
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        
        return static::create(array_merge($attributes, [
            'tenant_id' => $tenantId,
        ]));
    }

    /**
     * Switch tenant context temporarily.
     *
     * @param  \App\Models\Tenant\Tenant|string  $tenant
     * @param  \Closure  $callback
     * @return mixed
     */
    public static function withTenant($tenant, \Closure $callback)
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        $originalTenantId = static::getCurrentTenantId();

        try {
            // Set temporary tenant context
            app()->instance('tenant.id', $tenantId);
            
            return $callback();
        } finally {
            // Restore original tenant context
            if ($originalTenantId) {
                app()->instance('tenant.id', $originalTenantId);
            } else {
                app()->forgetInstance('tenant.id');
            }
        }
    }
}