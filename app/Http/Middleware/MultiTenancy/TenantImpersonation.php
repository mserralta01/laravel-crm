<?php

namespace App\Http\Middleware\MultiTenancy;

use App\Models\SuperAdmin;
use App\Models\Tenant\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TenantImpersonation Middleware
 * 
 * Allows super admins to impersonate tenant users for support and debugging.
 * Tracks all actions performed during impersonation for audit purposes.
 */
class TenantImpersonation
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
        // Check for impersonation token
        $token = $request->session()->get('impersonation_token');
        
        if (!$token) {
            return $next($request);
        }

        // Verify impersonation token
        $payload = SuperAdmin::verifyImpersonationToken($token);
        
        if (!$payload) {
            // Invalid or expired token
            $request->session()->forget('impersonation_token');
            return $next($request);
        }

        // Get tenant and super admin
        $tenant = Tenant::find($payload['tenant_id']);
        $superAdmin = SuperAdmin::find($payload['super_admin_id']);

        if (!$tenant || !$superAdmin || !$superAdmin->isActive()) {
            $request->session()->forget('impersonation_token');
            return $next($request);
        }

        // Set impersonation context
        app()->instance('impersonation', [
            'super_admin' => $superAdmin,
            'tenant' => $tenant,
            'started_at' => $payload['timestamp'],
        ]);

        // Add impersonation indicator to view
        view()->share('impersonating', true);
        view()->share('impersonation_data', [
            'super_admin_name' => $superAdmin->name,
            'tenant_name' => $tenant->name,
        ]);

        // Log activity if this is a state-changing request
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $tenant->logActivity(
                'impersonation.action',
                'Action performed during impersonation',
                [
                    'super_admin_id' => $superAdmin->id,
                    'super_admin_email' => $superAdmin->email,
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'ip' => $request->ip(),
                ]
            );
        }

        $response = $next($request);

        // Add impersonation header to response
        $response->headers->set('X-Impersonating', 'true');

        return $response;
    }
}