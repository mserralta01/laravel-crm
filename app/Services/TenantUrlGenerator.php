<?php

namespace App\Services;

use App\Models\Tenant\Tenant;
use Illuminate\Support\Facades\URL;

/**
 * TenantUrlGenerator Service
 * 
 * Generates URLs for tenant-specific routes, handling both
 * subdomain and custom domain scenarios.
 */
class TenantUrlGenerator
{
    /**
     * Generate a URL for a tenant route.
     *
     * @param  \App\Models\Tenant\Tenant|null  $tenant
     * @param  string  $route
     * @param  mixed  $parameters
     * @param  bool  $absolute
     * @return string
     */
    public function route(?Tenant $tenant, string $route, $parameters = [], bool $absolute = true): string
    {
        if (!$tenant) {
            $tenant = $this->getCurrentTenant();
        }

        if (!$tenant) {
            try {
                return route($route, $parameters, $absolute);
            } catch (\Exception $e) {
                // If route doesn't exist and no tenant, generate basic URL
                return $this->generateUrl(config('app.domain'), $route, $parameters, $absolute);
            }
        }

        // Get the tenant's primary domain
        $domain = $this->getTenantDomain($tenant);

        // Generate the URL with the tenant's domain
        return $this->generateUrl($domain, $route, $parameters, $absolute);
    }

    /**
     * Generate a URL to a path for a tenant.
     *
     * @param  \App\Models\Tenant\Tenant|null  $tenant
     * @param  string  $path
     * @param  mixed  $parameters
     * @param  bool|null  $secure
     * @return string
     */
    public function to(?Tenant $tenant, string $path, $parameters = [], $secure = null): string
    {
        if (!$tenant) {
            $tenant = $this->getCurrentTenant();
        }

        if (!$tenant) {
            return url($path, $parameters, $secure);
        }

        $domain = $this->getTenantDomain($tenant);
        $scheme = $secure ?? request()->isSecure() ? 'https' : 'http';

        $url = $scheme . '://' . $domain . '/' . ltrim($path, '/');

        if (!empty($parameters)) {
            $url .= '?' . http_build_query($parameters);
        }

        return $url;
    }

    /**
     * Generate a URL for switching between tenants.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @param  string|null  $path
     * @return string
     */
    public function switchTenant(Tenant $tenant, ?string $path = null): string
    {
        $domain = $this->getTenantDomain($tenant);
        $scheme = request()->isSecure() ? 'https' : 'http';
        
        $url = $scheme . '://' . $domain;
        
        if ($path) {
            $url .= '/' . ltrim($path, '/');
        } else {
            // Default to admin dashboard
            $url .= '/' . config('app.admin_path', 'admin') . '/dashboard';
        }

        return $url;
    }

    /**
     * Generate a URL for the super admin panel.
     *
     * @param  string  $route
     * @param  mixed  $parameters
     * @param  bool  $absolute
     * @return string
     */
    public function superAdmin(string $route, $parameters = [], bool $absolute = true): string
    {
        $domain = 'admin.' . config('app.domain');
        
        return $this->generateUrl($domain, $route, $parameters, $absolute);
    }

    /**
     * Get the current tenant from the container.
     *
     * @return \App\Models\Tenant\Tenant|null
     */
    protected function getCurrentTenant(): ?Tenant
    {
        return app()->bound('tenant') ? app('tenant') : null;
    }

    /**
     * Get the domain for a tenant.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @return string
     */
    protected function getTenantDomain(Tenant $tenant): string
    {
        // Check for primary custom domain
        $primaryDomain = $tenant->primaryDomain;
        
        if ($primaryDomain && !$primaryDomain->isSubdomain()) {
            return $primaryDomain->domain;
        }

        // Default to subdomain
        return $tenant->slug . '.' . config('app.domain');
    }

    /**
     * Generate a URL with a specific domain.
     *
     * @param  string  $domain
     * @param  string  $route
     * @param  mixed  $parameters
     * @param  bool  $absolute
     * @return string
     */
    protected function generateUrl(string $domain, string $route, $parameters = [], bool $absolute = true): string
    {
        try {
            // Store current URL root
            $originalRoot = URL::to('/');
            
            // Set temporary root with tenant domain
            $scheme = request()->isSecure() ? 'https' : 'http';
            URL::forceRootUrl($scheme . '://' . $domain);
            
            // Generate the route
            $url = route($route, $parameters, $absolute);
            
            // Restore original root
            URL::forceRootUrl($originalRoot);
            
            return $url;
        } catch (\Exception $e) {
            // If route doesn't exist, generate a basic URL
            $scheme = request()->isSecure() ? 'https' : 'http';
            $baseUrl = $scheme . '://' . $domain;
            
            // Try to get the route path from the route name
            $path = '/' . str_replace('.', '/', $route);
            
            return $baseUrl . $path;
        }
    }

    /**
     * Generate signed URL for a tenant route.
     *
     * @param  \App\Models\Tenant\Tenant|null  $tenant
     * @param  string  $route
     * @param  mixed  $parameters
     * @param  \DateTimeInterface|\DateInterval|int  $expiration
     * @return string
     */
    public function signedRoute(?Tenant $tenant, string $route, $parameters = [], $expiration = null): string
    {
        if (!$tenant) {
            $tenant = $this->getCurrentTenant();
        }

        if (!$tenant) {
            try {
                return URL::signedRoute($route, $parameters, $expiration);
            } catch (\Exception $e) {
                return $this->generateUrl(config('app.domain'), $route, $parameters);
            }
        }

        $domain = $this->getTenantDomain($tenant);
        
        try {
            // Store current URL root
            $originalRoot = URL::to('/');
            
            // Set temporary root with tenant domain
            $scheme = request()->isSecure() ? 'https' : 'http';
            URL::forceRootUrl($scheme . '://' . $domain);
            
            // Generate signed route
            $url = URL::signedRoute($route, $parameters, $expiration);
            
            // Restore original root
            URL::forceRootUrl($originalRoot);
            
            return $url;
        } catch (\Exception $e) {
            // If route doesn't exist, generate a basic signed URL
            $baseUrl = $this->generateUrl($domain, $route, $parameters);
            
            // Add basic signature for testing
            $signature = hash_hmac('sha256', $baseUrl, config('app.key'));
            $separator = parse_url($baseUrl, PHP_URL_QUERY) ? '&' : '?';
            
            return $baseUrl . $separator . 'signature=' . $signature;
        }
    }

    /**
     * Generate temporary signed URL for a tenant route.
     *
     * @param  \App\Models\Tenant\Tenant|null  $tenant
     * @param  string  $route
     * @param  \DateTimeInterface|\DateInterval|int  $expiration
     * @param  mixed  $parameters
     * @return string
     */
    public function temporarySignedRoute(?Tenant $tenant, string $route, $expiration, $parameters = []): string
    {
        return $this->signedRoute($tenant, $route, $parameters, $expiration);
    }
}