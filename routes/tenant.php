<?php

use Illuminate\Support\Facades\Route;

/**
 * Tenant-Specific Routes
 * 
 * These routes are loaded in addition to the standard Krayin routes
 * when in a tenant context. They provide tenant-specific functionality
 * and overrides.
 * 
 * Available in:
 * - Tenant subdomains (tenant.domain.com)
 * - Custom domains (custom-domain.com)
 */

// Tenant-specific overrides and additions
Route::middleware(['web', 'admin_locale', 'user'])->prefix(config('app.admin_path', 'admin'))->group(function () {
    
    // Override dashboard to show tenant-specific data
    Route::get('dashboard', 'Tenant\DashboardController@index')->name('tenant.dashboard');
    
    // Tenant Profile & Settings (for tenant admins)
    Route::prefix('tenant')->name('tenant.')->group(function () {
        Route::get('profile', 'Tenant\ProfileController@index')->name('profile.index');
        Route::put('profile', 'Tenant\ProfileController@update')->name('profile.update');
        
        Route::get('settings', 'Tenant\SettingsController@index')->name('settings.index');
        Route::put('settings', 'Tenant\SettingsController@update')->name('settings.update');
        
        // Branding
        Route::get('branding', 'Tenant\BrandingController@index')->name('branding.index');
        Route::put('branding', 'Tenant\BrandingController@update')->name('branding.update');
        Route::post('branding/logo', 'Tenant\BrandingController@uploadLogo')->name('branding.logo');
        Route::delete('branding/logo/{type}', 'Tenant\BrandingController@deleteLogo')->name('branding.logo.delete');
        
        // Usage & Limits
        Route::get('usage', 'Tenant\UsageController@index')->name('usage.index');
        Route::get('usage/export', 'Tenant\UsageController@export')->name('usage.export');
    });
    
    // Tenant-aware user management
    Route::prefix('users')->name('admin.settings.users.')->group(function () {
        // Override user creation to enforce tenant limits
        Route::post('/', 'Tenant\UserController@store')->name('store');
    });
    
    // Tenant-specific integrations
    Route::prefix('integrations')->name('tenant.integrations.')->group(function () {
        Route::get('/', 'Tenant\IntegrationController@index')->name('index');
        Route::get('{integration}/configure', 'Tenant\IntegrationController@configure')->name('configure');
        Route::post('{integration}/configure', 'Tenant\IntegrationController@save')->name('save');
        Route::post('{integration}/test', 'Tenant\IntegrationController@test')->name('test');
        Route::post('{integration}/disconnect', 'Tenant\IntegrationController@disconnect')->name('disconnect');
    });
    
    // Tenant data export
    Route::prefix('export')->name('tenant.export.')->group(function () {
        Route::get('/', 'Tenant\ExportController@index')->name('index');
        Route::post('request', 'Tenant\ExportController@request')->name('request');
        Route::get('download/{export}', 'Tenant\ExportController@download')->name('download');
    });
});

// Public tenant routes (no auth required)
Route::middleware(['web'])->group(function () {
    // Custom login page with tenant branding
    Route::get('login', 'Tenant\Auth\LoginController@showLoginForm')->name('tenant.login');
    
    // WebForm routes with tenant context
    Route::prefix('forms')->name('tenant.forms.')->group(function () {
        Route::get('{slug}', 'Tenant\WebFormController@show')->name('show');
        Route::post('{slug}', 'Tenant\WebFormController@submit')->name('submit');
        Route::get('{slug}/thank-you', 'Tenant\WebFormController@thankYou')->name('thank-you');
    });
    
    // Public API endpoints with tenant context
    Route::prefix('api/public')->name('tenant.api.public.')->group(function () {
        Route::post('webhook/{service}', 'Tenant\Api\WebhookController@handle')->name('webhook');
    });
});

// Tenant API routes
Route::middleware(['api', 'auth:sanctum'])->prefix('api/v1')->name('api.')->group(function () {
    // Override API routes to ensure tenant context
    Route::middleware(['tenant.scope'])->group(function () {
        // Tenant-aware API endpoints
        Route::get('tenant/info', 'Tenant\Api\TenantController@info')->name('tenant.info');
        Route::get('tenant/limits', 'Tenant\Api\TenantController@limits')->name('tenant.limits');
        Route::get('tenant/usage', 'Tenant\Api\TenantController@usage')->name('tenant.usage');
    });
});

// Development/Testing Routes (non-production only)
if (!app()->environment('production')) {
    Route::prefix('_tenant')->name('_tenant.')->group(function () {
        Route::get('info', function () {
            $tenant = app('tenant');
            return response()->json([
                'tenant' => $tenant ? [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'domain' => request()->getHost(),
                ] : null,
                'session' => [
                    'cookie' => config('session.cookie'),
                    'domain' => config('session.domain'),
                ],
                'database' => [
                    'connection' => config('database.default'),
                ],
            ]);
        });
    });
}