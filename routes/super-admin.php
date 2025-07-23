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
    Route::get('login', [\App\Http\Controllers\SuperAdmin\Auth\SuperAdminLoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [\App\Http\Controllers\SuperAdmin\Auth\SuperAdminLoginController::class, 'login'])->name('login.post');
    
    Route::get('forgot-password', [\App\Http\Controllers\SuperAdmin\Auth\SuperAdminForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('forgot-password', [\App\Http\Controllers\SuperAdmin\Auth\SuperAdminForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
    
    Route::get('reset-password/{token}', [\App\Http\Controllers\SuperAdmin\Auth\SuperAdminResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('reset-password', [\App\Http\Controllers\SuperAdmin\Auth\SuperAdminResetPasswordController::class, 'reset'])->name('password.update');
});

// Authenticated Super Admin Routes
Route::middleware('super.admin')->group(function () {
    // Logout
    Route::post('logout', [\App\Http\Controllers\SuperAdmin\Auth\SuperAdminLoginController::class, 'logout'])->name('logout');
    
    // Dashboard
    Route::get('/', [\App\Http\Controllers\SuperAdmin\DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('dashboard', [\App\Http\Controllers\SuperAdmin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/stats', [\App\Http\Controllers\SuperAdmin\DashboardController::class, 'stats'])->name('dashboard.stats');
    
    // Tenant Management
    Route::prefix('tenants')->name('tenants.')->group(function () {
        Route::get('/', [\App\Http\Controllers\SuperAdmin\TenantController::class, 'index'])->name('index');
        Route::get('create', [\App\Http\Controllers\SuperAdmin\TenantController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\SuperAdmin\TenantController::class, 'store'])->name('store');
        Route::get('{tenant}', [\App\Http\Controllers\SuperAdmin\TenantController::class, 'show'])->name('show');
        Route::get('{tenant}/edit', [\App\Http\Controllers\SuperAdmin\TenantController::class, 'edit'])->name('edit');
        Route::put('{tenant}', [\App\Http\Controllers\SuperAdmin\TenantController::class, 'update'])->name('update');
        Route::delete('{tenant}', [\App\Http\Controllers\SuperAdmin\TenantController::class, 'destroy'])->name('destroy');
        
        // Tenant Actions
        Route::post('{tenant}/suspend', [\App\Http\Controllers\SuperAdmin\TenantController::class, 'suspend'])->name('suspend');
        Route::post('{tenant}/activate', [\App\Http\Controllers\SuperAdmin\TenantController::class, 'activate'])->name('activate');
        Route::post('{tenant}/impersonate', [\App\Http\Controllers\SuperAdmin\TenantController::class, 'impersonate'])->name('impersonate');
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