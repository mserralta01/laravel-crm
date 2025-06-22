<?php

use Illuminate\Support\Facades\Route;

/**
 * Super Admin Routes
 * 
 * These routes are accessible only on the admin subdomain (admin.domain.com)
 * and require super admin authentication.
 * 
 * All routes are automatically prefixed with 'super-admin.' name
 */

// Super Admin Authentication Routes
Route::middleware('guest:super-admin')->group(function () {
    Route::get('login', 'Auth\SuperAdminLoginController@showLoginForm')->name('login');
    Route::post('login', 'Auth\SuperAdminLoginController@login')->name('login.post');
    
    Route::get('forgot-password', 'Auth\SuperAdminForgotPasswordController@showLinkRequestForm')->name('password.request');
    Route::post('forgot-password', 'Auth\SuperAdminForgotPasswordController@sendResetLinkEmail')->name('password.email');
    
    Route::get('reset-password/{token}', 'Auth\SuperAdminResetPasswordController@showResetForm')->name('password.reset');
    Route::post('reset-password', 'Auth\SuperAdminResetPasswordController@reset')->name('password.update');
});

// Authenticated Super Admin Routes
Route::middleware('super.admin')->group(function () {
    // Logout
    Route::post('logout', 'Auth\SuperAdminLoginController@logout')->name('logout');
    
    // Dashboard
    Route::get('/', 'DashboardController@index')->name('dashboard.index');
    Route::get('dashboard', 'DashboardController@index')->name('dashboard');
    Route::get('dashboard/stats', 'DashboardController@stats')->name('dashboard.stats');
    
    // Tenant Management
    Route::prefix('tenants')->name('tenants.')->group(function () {
        Route::get('/', 'TenantController@index')->name('index');
        Route::get('create', 'TenantController@create')->name('create');
        Route::post('/', 'TenantController@store')->name('store');
        Route::get('{tenant}', 'TenantController@show')->name('show');
        Route::get('{tenant}/edit', 'TenantController@edit')->name('edit');
        Route::put('{tenant}', 'TenantController@update')->name('update');
        Route::delete('{tenant}', 'TenantController@destroy')->name('destroy');
        
        // Tenant Actions
        Route::post('{tenant}/suspend', 'TenantController@suspend')->name('suspend');
        Route::post('{tenant}/activate', 'TenantController@activate')->name('activate');
        Route::post('{tenant}/impersonate', 'TenantController@impersonate')->name('impersonate');
        Route::get('{tenant}/backup', 'TenantController@backup')->name('backup');
        Route::post('{tenant}/restore', 'TenantController@restore')->name('restore');
        
        // Tenant Settings
        Route::get('{tenant}/settings', 'TenantSettingsController@index')->name('settings.index');
        Route::put('{tenant}/settings', 'TenantSettingsController@update')->name('settings.update');
        Route::post('{tenant}/settings/reset', 'TenantSettingsController@reset')->name('settings.reset');
        
        // Tenant Domains
        Route::get('{tenant}/domains', 'TenantDomainController@index')->name('domains.index');
        Route::post('{tenant}/domains', 'TenantDomainController@store')->name('domains.store');
        Route::put('{tenant}/domains/{domain}', 'TenantDomainController@update')->name('domains.update');
        Route::delete('{tenant}/domains/{domain}', 'TenantDomainController@destroy')->name('domains.destroy');
        Route::post('{tenant}/domains/{domain}/verify', 'TenantDomainController@verify')->name('domains.verify');
        Route::post('{tenant}/domains/{domain}/set-primary', 'TenantDomainController@setPrimary')->name('domains.set-primary');
        
        // Tenant Activity
        Route::get('{tenant}/activity', 'TenantActivityController@index')->name('activity.index');
        Route::get('{tenant}/activity/export', 'TenantActivityController@export')->name('activity.export');
    });
    
    // Super Admin Management
    Route::prefix('admins')->name('admins.')->group(function () {
        Route::get('/', 'SuperAdminController@index')->name('index');
        Route::get('create', 'SuperAdminController@create')->name('create');
        Route::post('/', 'SuperAdminController@store')->name('store');
        Route::get('{admin}/edit', 'SuperAdminController@edit')->name('edit');
        Route::put('{admin}', 'SuperAdminController@update')->name('update');
        Route::delete('{admin}', 'SuperAdminController@destroy')->name('destroy');
        Route::post('{admin}/toggle-status', 'SuperAdminController@toggleStatus')->name('toggle-status');
    });
    
    // System Settings
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', 'SystemSettingsController@index')->name('index');
        Route::put('general', 'SystemSettingsController@updateGeneral')->name('general.update');
        Route::put('email', 'SystemSettingsController@updateEmail')->name('email.update');
        Route::put('security', 'SystemSettingsController@updateSecurity')->name('security.update');
        Route::put('limits', 'SystemSettingsController@updateLimits')->name('limits.update');
    });
    
    // System Monitoring
    Route::prefix('system')->name('system.')->group(function () {
        Route::get('health', 'SystemController@health')->name('health');
        Route::get('logs', 'SystemController@logs')->name('logs');
        Route::get('cache', 'SystemController@cache')->name('cache');
        Route::post('cache/clear', 'SystemController@clearCache')->name('cache.clear');
        Route::get('queue', 'SystemController@queue')->name('queue');
        Route::get('database', 'SystemController@database')->name('database');
    });
    
    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', 'ReportsController@index')->name('index');
        Route::get('tenants', 'ReportsController@tenants')->name('tenants');
        Route::get('usage', 'ReportsController@usage')->name('usage');
        Route::get('activity', 'ReportsController@activity')->name('activity');
        Route::post('export', 'ReportsController@export')->name('export');
    });
    
    // Profile
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', 'ProfileController@index')->name('index');
        Route::put('/', 'ProfileController@update')->name('update');
        Route::put('password', 'ProfileController@updatePassword')->name('password.update');
        Route::get('activity', 'ProfileController@activity')->name('activity');
    });
    
    // API for DataGrids and AJAX
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('tenants', 'Api\TenantController@index')->name('tenants.index');
        Route::get('tenants/{tenant}/stats', 'Api\TenantController@stats')->name('tenants.stats');
        Route::get('dashboard/stats', 'Api\DashboardController@stats')->name('dashboard.stats');
        Route::get('system/metrics', 'Api\SystemController@metrics')->name('system.metrics');
    });
});

// Impersonation End Route (accessible while impersonating)
Route::middleware(['tenant.impersonate'])->group(function () {
    Route::post('impersonation/end', 'TenantController@endImpersonation')->name('impersonation.end');
});