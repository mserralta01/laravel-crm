<?php

namespace App\Http\Middleware\MultiTenancy;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SetTenantByUser Middleware
 * 
 * Sets the tenant context based on the authenticated user's tenant_id.
 * This is a simpler approach than subdomain-based identification.
 */
class SetTenantByUser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to authenticated users
        if (auth()->check()) {
            $user = auth()->user();
            
            // Check if user has tenant_id
            if ($user->tenant_id) {
                // Find the tenant
                $tenant = \App\Models\Tenant\Tenant::find($user->tenant_id);
                
                if ($tenant && $tenant->isActive()) {
                    // Set tenant in the application container
                    app()->singleton('tenant', function () use ($tenant) {
                        return $tenant;
                    });
                    
                    // Set tenant ID for easy access
                    app()->singleton('tenant.id', function () use ($tenant) {
                        return $tenant->id;
                    });
                    
                    // Configure tenant-specific settings
                    $this->configureTenantContext($tenant);
                }
            }
        }
        
        return $next($request);
    }
    
    /**
     * Configure tenant-specific context.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @return void
     */
    protected function configureTenantContext($tenant): void
    {
        // Set tenant configuration
        config(['tenant.current_id' => $tenant->id]);
        config(['app.name' => $tenant->name . ' - ' . config('app.name')]);
        
        // Configure session
        config([
            'session.cookie' => 'krayin_session_tenant_' . $tenant->id,
        ]);
        
        // Apply tenant settings if needed
        if ($tenant->settings) {
            foreach ($tenant->settings as $key => $value) {
                config(['tenant.settings.' . $key => $value]);
            }
        }
    }
}