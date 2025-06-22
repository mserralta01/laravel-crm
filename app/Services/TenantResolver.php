<?php

namespace App\Services;

use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantDomain;
use Illuminate\Http\Request;

/**
 * TenantResolver Service
 * 
 * Handles tenant identification from various sources including
 * domain, subdomain, headers, and session data.
 */
class TenantResolver
{
    /**
     * The current request instance.
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * Cached tenant instance.
     *
     * @var \App\Models\Tenant\Tenant|null
     */
    protected $tenant;

    /**
     * Create a new tenant resolver instance.
     *
     * @param  \Illuminate\Http\Request|null  $request
     */
    public function __construct(?Request $request = null)
    {
        $this->request = $request ?? request();
    }

    /**
     * Resolve the current tenant.
     *
     * @return \App\Models\Tenant\Tenant|null
     */
    public function resolve(): ?Tenant
    {
        if ($this->tenant !== null) {
            return $this->tenant;
        }

        // Try different resolution methods in order of priority
        $this->tenant = $this->resolveFromDomain()
            ?? $this->resolveFromSubdomain()
            ?? $this->resolveFromHeader()
            ?? $this->resolveFromSession()
            ?? $this->resolveFromQueryParameter();

        return $this->tenant;
    }

    /**
     * Resolve tenant from custom domain.
     *
     * @return \App\Models\Tenant\Tenant|null
     */
    protected function resolveFromDomain(): ?Tenant
    {
        $host = $this->request->getHost();

        $domain = TenantDomain::where('domain', $host)
            ->where('is_verified', true)
            ->with('tenant')
            ->first();

        if ($domain && $domain->tenant->isActive()) {
            return $domain->tenant;
        }

        return null;
    }

    /**
     * Resolve tenant from subdomain.
     *
     * @return \App\Models\Tenant\Tenant|null
     */
    protected function resolveFromSubdomain(): ?Tenant
    {
        $subdomain = $this->extractSubdomain();

        if (!$subdomain) {
            return null;
        }

        $tenant = Tenant::where('slug', $subdomain)
            ->where('status', Tenant::STATUS_ACTIVE)
            ->first();

        return $tenant;
    }

    /**
     * Resolve tenant from request header.
     *
     * @return \App\Models\Tenant\Tenant|null
     */
    protected function resolveFromHeader(): ?Tenant
    {
        $tenantId = $this->request->header('X-Tenant-ID');

        if (!$tenantId) {
            return null;
        }

        return Tenant::where(function ($query) use ($tenantId) {
            $query->where('uuid', $tenantId)
                ->orWhere('slug', $tenantId)
                ->orWhere('id', $tenantId);
        })
        ->where('status', Tenant::STATUS_ACTIVE)
        ->first();
    }

    /**
     * Resolve tenant from session.
     *
     * @return \App\Models\Tenant\Tenant|null
     */
    protected function resolveFromSession(): ?Tenant
    {
        if (!$this->request->hasSession()) {
            return null;
        }

        $tenantId = $this->request->session()->get('tenant_id');

        if (!$tenantId) {
            return null;
        }

        return Tenant::where('id', $tenantId)
            ->where('status', Tenant::STATUS_ACTIVE)
            ->first();
    }

    /**
     * Resolve tenant from query parameter (for testing/development).
     *
     * @return \App\Models\Tenant\Tenant|null
     */
    protected function resolveFromQueryParameter(): ?Tenant
    {
        // Only allow in non-production environments
        if (app()->environment('production')) {
            return null;
        }

        $tenantId = $this->request->query('tenant');

        if (!$tenantId) {
            return null;
        }

        return Tenant::where(function ($query) use ($tenantId) {
            $query->where('slug', $tenantId)
                ->orWhere('id', $tenantId);
        })
        ->where('status', Tenant::STATUS_ACTIVE)
        ->first();
    }

    /**
     * Extract subdomain from the current request.
     *
     * @return string|null
     */
    protected function extractSubdomain(): ?string
    {
        $host = $this->request->getHost();
        $appDomain = config('app.domain', 'localhost');

        // Remove www if present
        $host = preg_replace('/^www\./', '', $host);

        // Check if this is the main domain
        if ($host === $appDomain || $host === 'www.' . $appDomain) {
            return null;
        }

        // Check if this is a subdomain of the app domain
        if (str_ends_with($host, '.' . $appDomain)) {
            $subdomain = str_replace('.' . $appDomain, '', $host);
            
            // Ensure it's a single-level subdomain
            if (!str_contains($subdomain, '.')) {
                return $subdomain;
            }
        }

        return null;
    }

    /**
     * Set the current tenant.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @return void
     */
    public function setTenant(Tenant $tenant): void
    {
        $this->tenant = $tenant;
        
        // Store in container for easy access
        app()->instance('tenant', $tenant);
        app()->instance('tenant.id', $tenant->id);
        
        // Store in session if available
        if ($this->request->hasSession()) {
            $this->request->session()->put('tenant_id', $tenant->id);
        }
    }

    /**
     * Clear the current tenant.
     *
     * @return void
     */
    public function clearTenant(): void
    {
        $this->tenant = null;
        
        app()->forgetInstance('tenant');
        app()->forgetInstance('tenant.id');
        
        if ($this->request->hasSession()) {
            $this->request->session()->forget('tenant_id');
        }
    }

    /**
     * Check if a tenant is currently resolved.
     *
     * @return bool
     */
    public function hasTenant(): bool
    {
        return $this->resolve() !== null;
    }

    /**
     * Get the current tenant ID.
     *
     * @return int|null
     */
    public function getTenantId(): ?int
    {
        $tenant = $this->resolve();
        
        return $tenant ? $tenant->id : null;
    }

    /**
     * Check if the current request is for a specific tenant.
     *
     * @param  \App\Models\Tenant\Tenant|int  $tenant
     * @return bool
     */
    public function isTenant($tenant): bool
    {
        $currentTenant = $this->resolve();

        if (!$currentTenant) {
            return false;
        }

        if ($tenant instanceof Tenant) {
            return $currentTenant->id === $tenant->id;
        }

        return $currentTenant->id === $tenant;
    }
}