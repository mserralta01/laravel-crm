<?php

namespace App\Http\Middleware\MultiTenancy;

use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantDomain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TenantIdentification Middleware
 * 
 * Identifies the current tenant based on the request domain/subdomain.
 * Sets the tenant context for the entire request lifecycle.
 * 
 * Identification methods:
 * 1. Subdomain (e.g., acme.groovecrm.com)
 * 2. Custom domain (e.g., crm.acmecorp.com)
 * 3. Request header (for API requests)
 */
class TenantIdentification
{
    /**
     * Domains that should bypass tenant identification.
     *
     * @var array
     */
    protected $bypassDomains = [
        'admin',      // Super admin subdomain
        'api',        // API subdomain
        'www',        // Main website
        '',           // Root domain
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
        // Get the current domain
        $host = $request->getHost();
        
        // Check if this is a super admin domain
        if ($this->isSuperAdminDomain($host)) {
            // No tenant context for super admin
            return $next($request);
        }

        // Try to identify tenant
        $tenant = $this->identifyTenant($request);

        if (!$tenant) {
            // No tenant found, return 404
            abort(404, 'Tenant not found');
        }

        // Verify tenant is active
        if (!$tenant->isActive()) {
            abort(403, 'This account has been suspended. Please contact support.');
        }

        // Set tenant in the application container
        app()->singleton('tenant', function () use ($tenant) {
            return $tenant;
        });

        // Set tenant context for the request
        $request->attributes->set('tenant', $tenant);

        // Configure session isolation
        $this->configureSession($tenant);

        // Configure database connection if using separate databases
        if ($tenant->database) {
            $this->configureDatabaseConnection($tenant);
        }

        // Log tenant access
        $tenant->logActivity(
            'tenant.accessed',
            'Tenant accessed via ' . $host,
            [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
            ]
        );

        return $next($request);
    }

    /**
     * Identify tenant from the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \App\Models\Tenant\Tenant|null
     */
    protected function identifyTenant(Request $request): ?Tenant
    {
        $host = $request->getHost();

        // Method 1: Check for custom domain
        $domain = TenantDomain::where('domain', $host)
            ->where('is_verified', true)
            ->first();

        if ($domain) {
            return $domain->tenant;
        }

        // Method 2: Check for subdomain
        $subdomain = $this->extractSubdomain($host);
        
        if ($subdomain && !in_array($subdomain, $this->bypassDomains)) {
            $tenant = Tenant::where('slug', $subdomain)
                ->where('status', '!=', Tenant::STATUS_INACTIVE)
                ->first();

            if ($tenant) {
                return $tenant;
            }
        }

        // Method 3: Check for tenant header (API requests)
        if ($request->hasHeader('X-Tenant-ID')) {
            $tenantId = $request->header('X-Tenant-ID');
            
            $tenant = Tenant::where('uuid', $tenantId)
                ->orWhere('slug', $tenantId)
                ->where('status', '!=', Tenant::STATUS_INACTIVE)
                ->first();

            if ($tenant) {
                return $tenant;
            }
        }

        return null;
    }

    /**
     * Extract subdomain from the host.
     *
     * @param  string  $host
     * @return string|null
     */
    protected function extractSubdomain(string $host): ?string
    {
        $appDomain = config('app.domain', 'localhost');
        
        // Remove www if present
        $host = preg_replace('/^www\./', '', $host);
        
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
     * Check if this is a super admin domain.
     *
     * @param  string  $host
     * @return bool
     */
    protected function isSuperAdminDomain(string $host): bool
    {
        $appDomain = config('app.domain', 'localhost');
        
        // Check for admin subdomain
        if ($host === 'admin.' . $appDomain) {
            return true;
        }

        // Check for root domain without subdomain
        if ($host === $appDomain || $host === 'www.' . $appDomain) {
            return true;
        }

        return false;
    }

    /**
     * Configure session for tenant isolation.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @return void
     */
    protected function configureSession(Tenant $tenant): void
    {
        // Set unique session cookie name per tenant
        config([
            'session.cookie' => 'krayin_session_' . $tenant->id,
            'session.domain' => $this->getSessionDomain($tenant),
        ]);

        // Set session prefix for cache driver
        if (config('session.driver') === 'cache') {
            config([
                'session.prefix' => 'tenant_' . $tenant->id . '_session',
            ]);
        }

        // Set file path for file driver
        if (config('session.driver') === 'file') {
            $path = storage_path('framework/sessions/tenant_' . $tenant->id);
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
            config(['session.files' => $path]);
        }
    }

    /**
     * Get session domain for the tenant.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @return string|null
     */
    protected function getSessionDomain(Tenant $tenant): ?string
    {
        // If tenant has a custom domain, use it
        $primaryDomain = $tenant->primaryDomain;
        if ($primaryDomain && !$primaryDomain->isSubdomain()) {
            return '.' . $primaryDomain->domain;
        }

        // Otherwise use subdomain
        return '.' . $tenant->slug . '.' . config('app.domain');
    }

    /**
     * Configure database connection for the tenant.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @return void
     */
    protected function configureDatabaseConnection(Tenant $tenant): void
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