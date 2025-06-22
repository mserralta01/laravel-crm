<?php

namespace App\Providers;

use App\Models\Tenant\Tenant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * The path to the tenant admin home route.
     *
     * @var string
     */
    public const TENANT_HOME = '/admin/dashboard';

    /**
     * The path to the super admin home route.
     *
     * @var string
     */
    public const SUPER_ADMIN_HOME = '/super-admin/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureRoutePatterns();

        $this->routes(function () {
            // Super Admin routes (admin subdomain)
            $this->mapSuperAdminRoutes();

            // Tenant routes (tenant subdomains)
            $this->mapTenantRoutes();

            // API routes
            $this->mapApiRoutes();

            // Main website routes (www or root domain)
            $this->mapWebRoutes();
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting(): void
    {
        // Standard API rate limiting
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Tenant-specific API rate limiting
        RateLimiter::for('tenant-api', function (Request $request) {
            $tenant = app()->bound('tenant') ? app('tenant') : null;
            
            if ($tenant) {
                $limit = $tenant->getSetting('limits', 'api_rate_limit_per_hour', 1000);
                return Limit::perHour($limit)->by($tenant->id . ':' . ($request->user()?->id ?: $request->ip()));
            }
            
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }

    /**
     * Configure route patterns.
     *
     * @return void
     */
    protected function configureRoutePatterns(): void
    {
        Route::pattern('id', '[0-9]+');
        Route::pattern('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
        Route::pattern('slug', '[a-z0-9-]+');
    }

    /**
     * Define the "super admin" routes for the application.
     *
     * @return void
     */
    protected function mapSuperAdminRoutes(): void
    {
        // Super admin routes on admin subdomain
        Route::domain('admin.' . config('app.domain'))
            ->middleware(['web'])
            ->prefix('super-admin')
            ->name('super-admin.')
            ->group(base_path('routes/super-admin.php'));
    }

    /**
     * Define the "tenant" routes for the application.
     *
     * @return void
     */
    protected function mapTenantRoutes(): void
    {
        // Tenant subdomain routes
        Route::domain('{tenant}.' . config('app.domain'))
            ->middleware(['tenant'])
            ->group(function () {
                // Include existing Krayin admin routes for tenants
                $this->loadKrayinTenantRoutes();
                
                // Include tenant-specific routes
                if (file_exists(base_path('routes/tenant.php'))) {
                    require base_path('routes/tenant.php');
                }
            });

        // Custom domain routes (checked via middleware)
        Route::middleware(['tenant'])
            ->group(function () {
                // Only load if not on a subdomain and not on admin subdomain
                if (!$this->isSubdomainRequest() && !$this->isAdminSubdomain()) {
                    $this->loadKrayinTenantRoutes();
                    
                    if (file_exists(base_path('routes/tenant.php'))) {
                        require base_path('routes/tenant.php');
                    }
                }
            });
    }

    /**
     * Define the "api" routes for the application.
     *
     * @return void
     */
    protected function mapApiRoutes(): void
    {
        // Tenant API routes
        Route::domain('{tenant}.' . config('app.domain'))
            ->middleware(['api', 'tenant', 'throttle:tenant-api'])
            ->prefix('api')
            ->name('api.')
            ->group(base_path('routes/api.php'));

        // API routes with tenant header
        Route::middleware(['api', 'tenant.identify', 'tenant.scope', 'throttle:tenant-api'])
            ->prefix('api')
            ->name('api.')
            ->group(base_path('routes/api.php'));
    }

    /**
     * Define the "web" routes for the application.
     *
     * @return void
     */
    protected function mapWebRoutes(): void
    {
        // Main website routes (www or root domain)
        Route::domain(config('app.domain'))
            ->middleware('web')
            ->group(base_path('routes/web.php'));
        
        Route::domain('www.' . config('app.domain'))
            ->middleware('web')
            ->group(base_path('routes/web.php'));
    }

    /**
     * Load Krayin package routes for tenants.
     *
     * @return void
     */
    protected function loadKrayinTenantRoutes(): void
    {
        // Admin routes from Krayin packages
        $adminMiddleware = ['web', 'admin_locale', 'user'];
        
        Route::middleware($adminMiddleware)
            ->prefix(config('app.admin_path', 'admin'))
            ->group(function () {
                // Include all Krayin package admin routes
                $packages = [
                    'packages/Webkul/Admin/src/Routes/Admin',
                    'packages/Webkul/Activity/src/Routes',
                    'packages/Webkul/Contact/src/Routes',
                    'packages/Webkul/Lead/src/Routes',
                    'packages/Webkul/Product/src/Routes',
                    'packages/Webkul/Quote/src/Routes',
                    'packages/Webkul/Email/src/Routes',
                    'packages/Webkul/Automation/src/Routes',
                    'packages/Webkul/Tag/src/Routes',
                    'packages/Webkul/Attribute/src/Routes',
                    'packages/Webkul/User/src/Routes',
                    'packages/Webkul/WebForm/src/Routes',
                    'packages/Webkul/EmailTemplate/src/Routes',
                    'packages/Webkul/DataTransfer/src/Routes',
                    'packages/Webkul/Warehouse/src/Routes',
                ];

                foreach ($packages as $package) {
                    $routePath = base_path($package);
                    if (is_dir($routePath)) {
                        $this->loadRoutesFromDirectory($routePath);
                    }
                }
            });
    }

    /**
     * Load routes from a directory.
     *
     * @param  string  $directory
     * @return void
     */
    protected function loadRoutesFromDirectory(string $directory): void
    {
        $files = glob($directory . '/*.php');
        
        foreach ($files as $file) {
            if (file_exists($file)) {
                require $file;
            }
        }
    }

    /**
     * Check if the current request is a subdomain request.
     *
     * @return bool
     */
    protected function isSubdomainRequest(): bool
    {
        $host = request()->getHost();
        $appDomain = config('app.domain');
        
        // Check if host contains subdomain
        return $host !== $appDomain && 
               $host !== 'www.' . $appDomain && 
               str_ends_with($host, '.' . $appDomain);
    }

    /**
     * Check if the current request is on admin subdomain.
     *
     * @return bool
     */
    protected function isAdminSubdomain(): bool
    {
        $host = request()->getHost();
        return $host === 'admin.' . config('app.domain');
    }
}
