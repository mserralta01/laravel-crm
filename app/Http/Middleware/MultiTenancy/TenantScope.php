<?php

namespace App\Http\Middleware\MultiTenancy;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TenantScope Middleware
 * 
 * Applies tenant filtering to all Eloquent models automatically.
 * This ensures data isolation between tenants at the query level.
 * 
 * Works in conjunction with the BelongsToTenant trait to provide
 * automatic tenant scoping for all database queries.
 */
class TenantScope
{
    /**
     * Models that should not be scoped by tenant.
     * These are typically system-wide models.
     *
     * @var array
     */
    protected $excludedModels = [
        \App\Models\Tenant\Tenant::class,
        \App\Models\Tenant\TenantDatabase::class,
        \App\Models\Tenant\TenantDomain::class,
        \App\Models\Tenant\TenantSetting::class,
        \App\Models\Tenant\TenantActivityLog::class,
        \App\Models\SuperAdmin::class,
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get current tenant from container
        $tenant = app()->bound('tenant') ? app('tenant') : null;

        if ($tenant) {
            // Apply global scopes to tenant-aware models
            $this->applyTenantScopes($tenant);
            
            // Set tenant ID in config for easy access
            config(['tenant.current_id' => $tenant->id]);
            config(['tenant.current' => $tenant]);
        }

        return $next($request);
    }

    /**
     * Apply tenant scopes to models.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @return void
     */
    protected function applyTenantScopes($tenant): void
    {
        // Get all models that need tenant scoping
        $models = $this->getTenantScopedModels();

        foreach ($models as $model) {
            // Skip excluded models
            if (in_array($model, $this->excludedModels)) {
                continue;
            }

            // Apply global scope if model has tenant_id column
            if ($this->modelHasTenantId($model)) {
                $model::addGlobalScope('tenant', function (Builder $builder) use ($tenant) {
                    $builder->where('tenant_id', $tenant->id);
                });
            }
        }
    }

    /**
     * Get all models that should be tenant scoped.
     *
     * @return array
     */
    protected function getTenantScopedModels(): array
    {
        // List of Krayin models that will need tenant scoping
        // Note: These models don't exist yet with tenant_id, but will be updated in Phase 1.2
        return [
            // User Management
            \Webkul\User\Models\User::class,
            \Webkul\User\Models\Role::class,
            \Webkul\User\Models\Group::class,
            
            // Core Entities
            \Webkul\Lead\Models\Lead::class,
            \Webkul\Lead\Models\Pipeline::class,
            \Webkul\Lead\Models\Stage::class,
            \Webkul\Lead\Models\Type::class,
            \Webkul\Lead\Models\Source::class,
            
            // Contacts
            \Webkul\Contact\Models\Person::class,
            \Webkul\Contact\Models\Organization::class,
            
            // Products
            \Webkul\Product\Models\Product::class,
            
            // Quotes
            \Webkul\Quote\Models\Quote::class,
            
            // Activities
            \Webkul\Activity\Models\Activity::class,
            \Webkul\Activity\Models\Participant::class,
            
            // Email
            \Webkul\Email\Models\Email::class,
            \Webkul\Email\Models\Attachment::class,
            
            // Tags
            \Webkul\Tag\Models\Tag::class,
            
            // Attributes
            \Webkul\Attribute\Models\Attribute::class,
            \Webkul\Attribute\Models\AttributeValue::class,
            
            // Workflows
            \Webkul\Automation\Models\Workflow::class,
            
            // WebForms
            \Webkul\WebForm\Models\WebForm::class,
            
            // Core Config
            \Webkul\Core\Models\CoreConfig::class,
            
            // Email Templates
            \Webkul\EmailTemplate\Models\EmailTemplate::class,
        ];
    }

    /**
     * Check if a model has tenant_id column.
     *
     * @param  string  $modelClass
     * @return bool
     */
    protected function modelHasTenantId(string $modelClass): bool
    {
        try {
            $model = new $modelClass;
            $table = $model->getTable();
            
            // Check if column exists in the schema
            return \Schema::hasColumn($table, 'tenant_id');
        } catch (\Exception $e) {
            // If model doesn't exist or there's an error, assume no tenant_id
            return false;
        }
    }
}