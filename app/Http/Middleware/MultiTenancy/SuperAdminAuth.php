<?php

namespace App\Http\Middleware\MultiTenancy;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SuperAdminAuth Middleware
 * 
 * Ensures that only authenticated super admins can access protected routes.
 * Used for the super admin panel and tenant management features.
 */
class SuperAdminAuth
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
        // Check if super admin is authenticated
        if (!auth()->guard('super-admin')->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }
            
            return redirect()->route('super-admin.login');
        }

        // Check if super admin account is active
        $superAdmin = auth()->guard('super-admin')->user();
        if (!$superAdmin->isActive()) {
            auth()->guard('super-admin')->logout();
            
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Account deactivated'], 403);
            }
            
            return redirect()->route('super-admin.login')
                ->with('error', 'Your account has been deactivated.');
        }

        // Update last login timestamp
        if (!$request->routeIs('super-admin.logout')) {
            $superAdmin->updateLastLogin();
        }

        return $next($request);
    }
}