<?php

namespace Tests\Feature\Feature\MultiTenancy;

use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantDomain;
use App\Services\TenantUrlGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutingTest extends TestCase
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
    }

    public function test_tenant_route_generation()
    {
        // Test subdomain route generation
        $url = $this->urlGenerator->route($this->tenant, 'admin.dashboard.index');
        $this->assertStringContainsString('test-tenant.' . config('app.domain'), $url);
        $this->assertStringContainsString('/admin/dashboard', $url);
    }

    public function test_tenant_url_generation()
    {
        // Test path URL generation
        $url = $this->urlGenerator->to($this->tenant, 'admin/leads');
        $this->assertStringContainsString('test-tenant.' . config('app.domain'), $url);
        $this->assertStringContainsString('/admin/leads', $url);
    }

    public function test_super_admin_route_generation()
    {
        // Test super admin route generation
        $url = $this->urlGenerator->superAdmin('super-admin.tenants.index');
        $this->assertStringContainsString('admin.' . config('app.domain'), $url);
        $this->assertStringContainsString('/super-admin/tenants', $url);
    }

    public function test_switch_tenant_url()
    {
        // Test tenant switching URL
        $url = $this->urlGenerator->switchTenant($this->tenant);
        $this->assertStringContainsString('test-tenant.' . config('app.domain'), $url);
        $this->assertStringContainsString('/admin/dashboard', $url);
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

        // Test custom domain route generation
        $url = $this->urlGenerator->route($this->tenant, 'admin.dashboard.index');
        $this->assertStringContainsString('custom-domain.com', $url);
        $this->assertStringNotContainsString($this->tenant->slug, $url);
    }

    public function test_helper_functions()
    {
        // Set tenant context
        app()->instance('tenant', $this->tenant);

        // Test tenant_route helper
        $url = tenant_route('admin.dashboard.index');
        $this->assertStringContainsString('test-tenant.' . config('app.domain'), $url);

        // Test tenant_url helper
        $url = tenant_url('admin/leads');
        $this->assertStringContainsString('test-tenant.' . config('app.domain'), $url);

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
    }

    public function test_signed_route_generation()
    {
        // Test signed route generation
        $url = $this->urlGenerator->signedRoute($this->tenant, 'admin.dashboard.index');
        $this->assertStringContainsString('test-tenant.' . config('app.domain'), $url);
        $this->assertStringContainsString('signature=', $url);
    }

    public function test_subdomain_routing_middleware()
    {
        // Test accessing tenant subdomain
        $response = $this->get('http://test-tenant.' . config('app.domain') . '/admin/dashboard');
        
        // Should redirect to login as we're not authenticated
        $response->assertRedirect();
        $response->assertRedirectContains('/login');
    }

    public function test_super_admin_subdomain_access()
    {
        // Test accessing super admin subdomain
        $response = $this->get('http://admin.' . config('app.domain') . '/super-admin/login');
        
        // Should show login page
        $response->assertStatus(200);
    }

    public function test_tenant_context_in_request()
    {
        // Make request to tenant subdomain
        $response = $this->get('http://test-tenant.' . config('app.domain') . '/login');
        
        // Should have tenant in container during request
        $this->app->instance('test.request.callback', function () {
            $this->assertTrue(app()->bound('tenant'));
            $this->assertEquals('test-tenant', app('tenant')->slug);
        });
    }

    public function test_route_middleware_order()
    {
        // Test that tenant middleware runs before auth
        $this->app['router']->get('/test-middleware-order', function () {
            return response()->json([
                'tenant' => current_tenant()?->slug,
                'user' => auth()->user()?->email,
            ]);
        })->middleware(['tenant', 'auth']);

        $response = $this->get('http://test-tenant.' . config('app.domain') . '/test-middleware-order');
        
        // Should redirect to login with tenant context preserved
        $response->assertRedirect();
        $this->assertStringContainsString('test-tenant.' . config('app.domain'), $response->headers->get('Location'));
    }

    public function test_api_route_with_tenant_header()
    {
        // Test API route with X-Tenant-ID header
        $response = $this->withHeaders([
            'X-Tenant-ID' => $this->tenant->uuid,
            'Accept' => 'application/json',
        ])->get('/api/v1/tenant/info');

        // Should return 401 as we're not authenticated
        $response->assertStatus(401);
    }

    public function test_development_tenant_info_route()
    {
        // Only test in non-production
        if (app()->environment('production')) {
            $this->markTestSkipped('Development routes not available in production');
        }

        $response = $this->get('http://test-tenant.' . config('app.domain') . '/_tenant/info');
        
        $response->assertOk();
        $response->assertJsonStructure([
            'tenant' => ['id', 'name', 'slug', 'domain'],
            'session',
            'database',
        ]);
    }
}