<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\TenantService;
use App\Models\Tenant\Tenant;
use App\Facades\Tenant as TenantFacade;
use Webkul\Lead\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class TenantServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $tenantService;
    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantService = new TenantService();
        
        // Create a test tenant
        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'email' => 'test@tenant.com',
            'status' => 'active',
        ]);
    }

    /**
     * Test getting current tenant.
     */
    public function test_get_current_tenant()
    {
        // No tenant set
        $this->assertNull($this->tenantService->current());
        $this->assertNull($this->tenantService->currentId());
        $this->assertFalse($this->tenantService->hasTenant());

        // Set tenant
        app()->singleton('tenant', function () {
            return $this->tenant;
        });

        $this->assertEquals($this->tenant->id, $this->tenantService->current()->id);
        $this->assertEquals($this->tenant->id, $this->tenantService->currentId());
        $this->assertTrue($this->tenantService->hasTenant());
    }

    /**
     * Test canAccess method.
     */
    public function test_can_access_method()
    {
        // Set tenant context
        app()->singleton('tenant', function () {
            return $this->tenant;
        });
        app()->singleton('tenant.id', function () {
            return $this->tenant->id;
        });

        // Create a lead for the tenant
        $lead = Lead::createForTenant([
            'title' => 'Test Lead',
            'status' => 1,
        ], $this->tenant);

        // Should have access
        $this->assertTrue($this->tenantService->canAccess($lead));

        // Create lead for different tenant
        $otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'email' => 'other@tenant.com',
            'status' => 'active',
        ]);

        $otherLead = Lead::createForTenant([
            'title' => 'Other Lead',
            'status' => 1,
        ], $otherTenant);

        // Should not have access
        $this->assertFalse($this->tenantService->canAccess($otherLead));
    }

    /**
     * Test runAs method.
     */
    public function test_run_as_method()
    {
        // Create another tenant
        $otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'email' => 'other@tenant.com',
            'status' => 'active',
        ]);

        // Set initial tenant context
        app()->singleton('tenant', function () {
            return $this->tenant;
        });
        app()->singleton('tenant.id', function () {
            return $this->tenant->id;
        });

        // Verify initial context
        $this->assertEquals($this->tenant->id, $this->tenantService->currentId());

        // Run code as different tenant
        $result = $this->tenantService->runAs($otherTenant, function () use ($otherTenant) {
            // Inside callback, should have different tenant
            $this->assertEquals($otherTenant->id, app('tenant.id'));
            return 'success';
        });

        $this->assertEquals('success', $result);

        // Context should be restored
        $this->assertEquals($this->tenant->id, $this->tenantService->currentId());
    }

    /**
     * Test runAs with inactive tenant throws exception.
     */
    public function test_run_as_with_inactive_tenant_throws_exception()
    {
        // Create inactive tenant
        $inactiveTenant = Tenant::create([
            'name' => 'Inactive Tenant',
            'slug' => 'inactive-tenant',
            'email' => 'inactive@tenant.com',
            'status' => 'inactive',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid or inactive tenant');

        $this->tenantService->runAs($inactiveTenant, function () {
            return 'should not reach here';
        });
    }

    /**
     * Test find method with caching.
     */
    public function test_find_method_with_caching()
    {
        // First call should query database
        $foundTenant = $this->tenantService->find($this->tenant->id);
        $this->assertEquals($this->tenant->id, $foundTenant->id);

        // Update tenant name directly in database
        \DB::table('tenants')
            ->where('id', $this->tenant->id)
            ->update(['name' => 'Updated Name']);

        // Second call should return cached version (not updated)
        $cachedTenant = $this->tenantService->find($this->tenant->id);
        $this->assertEquals('Test Tenant', $cachedTenant->name);

        // Clear cache
        $this->tenantService->clearCache($this->tenant->id);

        // Now should get updated version
        $freshTenant = $this->tenantService->find($this->tenant->id);
        $this->assertEquals('Updated Name', $freshTenant->name);
    }

    /**
     * Test security violation logging.
     */
    public function test_log_security_violation()
    {
        // Set tenant context
        app()->singleton('tenant', function () {
            return $this->tenant;
        });

        // Mock the Log facade
        Log::shouldReceive('warning')
            ->once()
            ->with(
                \Mockery::on(function ($message) {
                    return str_contains($message, 'Tenant Security Violation: Unauthorized access attempt');
                }),
                \Mockery::on(function ($context) {
                    return isset($context['tenant_id']) && 
                           $context['tenant_id'] === $this->tenant->id &&
                           isset($context['resource']) &&
                           $context['resource'] === 'Lead';
                })
            );

        $this->tenantService->logSecurityViolation('Unauthorized access attempt', [
            'resource' => 'Lead',
            'resource_id' => 123,
        ]);
    }

    /**
     * Test facade access.
     */
    public function test_facade_access()
    {
        // Set tenant
        app()->singleton('tenant', function () {
            return $this->tenant;
        });

        // Test facade methods
        $this->assertEquals($this->tenant->id, TenantFacade::currentId());
        $this->assertTrue(TenantFacade::hasTenant());
        $this->assertEquals($this->tenant->id, TenantFacade::current()->id);
    }
}