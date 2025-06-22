<?php

namespace Tests\Feature\Feature\MultiTenancy;

use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TenantIdentificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test routes
        Route::middleware(['tenant'])->group(function () {
            Route::get('/tenant-test', function () {
                $tenant = app('tenant');
                return response()->json([
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                ]);
            })->name('tenant.test');
        });
    }

    /**
     * Test tenant identification via subdomain.
     */
    public function test_tenant_identification_via_subdomain(): void
    {
        // Create test tenant
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test',
            'email' => 'test@tenant.com',
            'status' => 'active',
        ]);

        $tenant->domains()->create([
            'domain' => 'test.' . config('app.domain'),
            'is_primary' => true,
            'is_verified' => true,
            'verified_at' => now(),
        ]);

        // Make request to subdomain
        $response = $this->get('http://test.' . config('app.domain') . '/tenant-test');

        $response->assertStatus(200)
            ->assertJson([
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
            ]);
    }

    /**
     * Test tenant identification via custom domain.
     */
    public function test_tenant_identification_via_custom_domain(): void
    {
        // Create test tenant
        $tenant = Tenant::create([
            'name' => 'Custom Domain Tenant',
            'slug' => 'custom',
            'email' => 'custom@tenant.com',
            'status' => 'active',
        ]);

        $tenant->domains()->create([
            'domain' => 'custom.example.com',
            'is_primary' => true,
            'is_verified' => true,
            'verified_at' => now(),
        ]);

        // Make request to custom domain
        $response = $this->get('http://custom.example.com/tenant-test');

        $response->assertStatus(200)
            ->assertJson([
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
            ]);
    }

    /**
     * Test tenant identification via header.
     */
    public function test_tenant_identification_via_header(): void
    {
        // Create test tenant
        $tenant = Tenant::create([
            'name' => 'Header Tenant',
            'slug' => 'header',
            'email' => 'header@tenant.com',
            'status' => 'active',
        ]);

        // Make request with tenant header
        $response = $this->withHeaders([
            'X-Tenant-ID' => $tenant->uuid,
        ])->get('/tenant-test');

        $response->assertStatus(200)
            ->assertJson([
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
            ]);
    }

    /**
     * Test request to unknown tenant returns 404.
     */
    public function test_unknown_tenant_returns_404(): void
    {
        // Make request to non-existent subdomain
        $response = $this->get('http://nonexistent.' . config('app.domain') . '/tenant-test');

        $response->assertStatus(404);
    }

    /**
     * Test suspended tenant returns 403.
     */
    public function test_suspended_tenant_returns_403(): void
    {
        // Create suspended tenant
        $tenant = Tenant::create([
            'name' => 'Suspended Tenant',
            'slug' => 'suspended',
            'email' => 'suspended@tenant.com',
            'status' => 'suspended',
        ]);

        $tenant->domains()->create([
            'domain' => 'suspended.' . config('app.domain'),
            'is_primary' => true,
            'is_verified' => true,
            'verified_at' => now(),
        ]);

        // Make request to suspended tenant
        $response = $this->get('http://suspended.' . config('app.domain') . '/tenant-test');

        $response->assertStatus(403);
    }

    /**
     * Test tenant context is available in container.
     */
    public function test_tenant_context_is_available_in_container(): void
    {
        // Create test tenant
        $tenant = Tenant::create([
            'name' => 'Container Test Tenant',
            'slug' => 'container',
            'email' => 'container@tenant.com',
            'status' => 'active',
        ]);

        $tenant->domains()->create([
            'domain' => 'container.' . config('app.domain'),
            'is_primary' => true,
            'is_verified' => true,
            'verified_at' => now(),
        ]);

        // Create route that checks container
        Route::middleware(['tenant'])->get('/container-test', function () {
            return response()->json([
                'has_tenant' => app()->bound('tenant'),
                'tenant_id_bound' => app()->bound('tenant.id'),
                'tenant_id' => app('tenant.id') ?? null,
            ]);
        });

        $response = $this->get('http://container.' . config('app.domain') . '/container-test');

        $response->assertStatus(200)
            ->assertJson([
                'has_tenant' => true,
                'tenant_id_bound' => true,
                'tenant_id' => $tenant->id,
            ]);
    }

    /**
     * Test session configuration is isolated per tenant.
     */
    public function test_session_is_isolated_per_tenant(): void
    {
        // Create two tenants
        $tenant1 = Tenant::create([
            'name' => 'Tenant One',
            'slug' => 'tenant1',
            'email' => 'tenant1@test.com',
            'status' => 'active',
        ]);

        $tenant1->domains()->create([
            'domain' => 'tenant1.' . config('app.domain'),
            'is_primary' => true,
            'is_verified' => true,
            'verified_at' => now(),
        ]);

        $tenant2 = Tenant::create([
            'name' => 'Tenant Two',
            'slug' => 'tenant2',
            'email' => 'tenant2@test.com',
            'status' => 'active',
        ]);

        $tenant2->domains()->create([
            'domain' => 'tenant2.' . config('app.domain'),
            'is_primary' => true,
            'is_verified' => true,
            'verified_at' => now(),
        ]);

        // Create route that checks session configuration
        Route::middleware(['tenant'])->get('/session-test', function () {
            return response()->json([
                'session_cookie' => config('session.cookie'),
                'session_domain' => config('session.domain'),
            ]);
        });

        // Test tenant 1
        $response1 = $this->get('http://tenant1.' . config('app.domain') . '/session-test');
        $response1->assertStatus(200)
            ->assertJson([
                'session_cookie' => 'krayin_session_' . $tenant1->id,
            ]);

        // Test tenant 2
        $response2 = $this->get('http://tenant2.' . config('app.domain') . '/session-test');
        $response2->assertStatus(200)
            ->assertJson([
                'session_cookie' => 'krayin_session_' . $tenant2->id,
            ]);
    }
}
