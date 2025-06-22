<?php

namespace Tests\Feature\Feature\MultiTenancy;

use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantDomain;
use App\Services\TenantUrlGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpleRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected TenantUrlGenerator $urlGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'email' => 'test@tenant.com',
            'status' => Tenant::STATUS_ACTIVE,
        ]);

        $this->urlGenerator = app('tenant.url');

        // Register test routes
        $this->registerTestRoutes();
    }

    protected function registerTestRoutes()
    {
        // Register test routes
        app('router')->get('/test-route', function () {
            return 'test';
        })->name('test.route');

        app('router')->get('/admin/dashboard', function () {
            return 'dashboard';
        })->name('admin.dashboard.index');

        app('router')->get('/super-admin/tenants', function () {
            return 'tenants';
        })->name('super-admin.tenants.index');
    }

    public function test_tenant_url_generator_service_is_registered()
    {
        $this->assertInstanceOf(TenantUrlGenerator::class, app('tenant.url'));
        $this->assertInstanceOf(TenantUrlGenerator::class, app(TenantUrlGenerator::class));
    }

    public function test_tenant_route_generation_with_test_routes()
    {
        // Test subdomain route generation
        $url = $this->urlGenerator->route($this->tenant, 'test.route');
        $this->assertStringContainsString('test-tenant.' . config('app.domain'), $url);
        $this->assertStringContainsString('/test/route', $url);
    }

    public function test_tenant_url_path_generation()
    {
        // Test path URL generation
        $url = $this->urlGenerator->to($this->tenant, 'admin/leads');
        $this->assertStringContainsString('test-tenant.' . config('app.domain'), $url);
        $this->assertStringContainsString('/admin/leads', $url);

        // Test with query parameters
        $url = $this->urlGenerator->to($this->tenant, 'admin/leads', ['filter' => 'active']);
        $this->assertStringContainsString('?filter=active', $url);
    }

    public function test_super_admin_route_generation()
    {
        // Test super admin route generation
        $url = $this->urlGenerator->superAdmin('super-admin.tenants.index');
        $this->assertStringContainsString('admin.' . config('app.domain'), $url);
        $this->assertStringContainsString('/super-admin/tenants', $url);
    }

    public function test_switch_tenant_url_generation()
    {
        // Test tenant switching URL
        $url = $this->urlGenerator->switchTenant($this->tenant);
        $this->assertStringContainsString('test-tenant.' . config('app.domain'), $url);
        $this->assertStringContainsString('/admin/dashboard', $url);

        // Test with custom path
        $url = $this->urlGenerator->switchTenant($this->tenant, 'admin/leads');
        $this->assertStringContainsString('/admin/leads', $url);
    }

    public function test_custom_domain_route_generation()
    {
        // Create custom domain
        $customDomain = TenantDomain::create([
            'tenant_id' => $this->tenant->id,
            'domain' => 'custom-domain.com',
            'is_primary' => true,
            'is_verified' => true,
        ]);

        // Force reload the relationship
        $this->tenant->load('domains');

        // Test custom domain route generation
        $url = $this->urlGenerator->route($this->tenant, 'test.route');
        $this->assertStringContainsString('custom-domain.com', $url);
        $this->assertStringNotContainsString($this->tenant->slug, $url);
    }

    public function test_helper_functions_are_available()
    {
        // Set tenant context
        app()->instance('tenant', $this->tenant);

        // Test current_tenant helper
        $this->assertEquals($this->tenant->id, current_tenant()->id);

        // Test is_tenant_context helper
        $this->assertTrue(is_tenant_context());

        // Test tenant_cache_key helper
        $key = tenant_cache_key('test-key');
        $this->assertEquals('tenant:' . $this->tenant->id . ':test-key', $key);

        // Test tenant_storage_path helper
        $path = tenant_storage_path('uploads');
        $this->assertStringContainsString('app/tenants/' . $this->tenant->id . '/uploads', $path);

        // Test tenant_asset helper
        $asset = tenant_asset('logo.png');
        $this->assertStringContainsString('tenants/' . $this->tenant->slug . '/logo.png', $asset);
    }

    public function test_tenant_config_helper()
    {
        // Set tenant context
        app()->instance('tenant', $this->tenant);

        // Add test setting
        $this->tenant->setSetting('test', 'config_key', 'custom_value');

        // Test tenant_config helper
        $value = tenant_config('test.config_key', 'default');
        $this->assertEquals('custom_value', $value);

        // Test fallback to regular config
        $value = tenant_config('app.name');
        $this->assertEquals(config('app.name'), $value);
    }

    public function test_signed_route_generation()
    {
        // Test signed route generation
        $url = $this->urlGenerator->signedRoute($this->tenant, 'test.route');
        $this->assertStringContainsString('test-tenant.' . config('app.domain'), $url);
        $this->assertStringContainsString('signature=', $url);

        // Test temporary signed route
        $url = $this->urlGenerator->temporarySignedRoute($this->tenant, 'test.route', now()->addHour());
        $this->assertStringContainsString('signature=', $url);
    }

    public function test_url_generation_without_tenant_context()
    {
        // Clear tenant context
        app()->forgetInstance('tenant');

        // Test route generation without tenant
        $url = $this->urlGenerator->route(null, 'test.route');
        $this->assertStringNotContainsString('test-tenant', $url);

        // Test with helpers
        $url = tenant_url('some/path');
        $this->assertStringNotContainsString('test-tenant', $url);
    }

    public function test_subdomain_detection_in_is_super_admin_context()
    {
        // Mock request host
        $this->app['request']->headers->set('host', 'admin.' . config('app.domain'));
        $this->assertTrue(is_super_admin_context());

        // Test regular domain
        $this->app['request']->headers->set('host', config('app.domain'));
        $this->assertFalse(is_super_admin_context());

        // Test tenant subdomain
        $this->app['request']->headers->set('host', 'test-tenant.' . config('app.domain'));
        $this->assertFalse(is_super_admin_context());
    }
}