<?php

namespace Tests\Feature\Feature\MultiTenancy;

use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TenantModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that a tenant can be created with proper attributes.
     */
    public function test_tenant_can_be_created(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Company',
            'email' => 'test@company.com',
            'phone' => '+1-555-000-0000',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('tenants', [
            'name' => 'Test Company',
            'email' => 'test@company.com',
            'slug' => 'test-company',
        ]);

        $this->assertNotNull($tenant->uuid);
        $this->assertEquals('test-company', $tenant->slug);
        $this->assertTrue($tenant->isActive());
    }

    /**
     * Test that tenant slug is unique.
     */
    public function test_tenant_slug_is_unique(): void
    {
        // Create first tenant
        $tenant1 = Tenant::create([
            'name' => 'Test Company',
            'email' => 'test1@company.com',
        ]);

        // Create second tenant with same name
        $tenant2 = Tenant::create([
            'name' => 'Test Company',
            'email' => 'test2@company.com',
        ]);

        $this->assertEquals('test-company', $tenant1->slug);
        $this->assertEquals('test-company-1', $tenant2->slug);
    }

    /**
     * Test tenant settings functionality.
     */
    public function test_tenant_settings_work_correctly(): void
    {
        $tenant = Tenant::create([
            'name' => 'Settings Test Company',
            'email' => 'settings@test.com',
        ]);

        // Create default settings
        TenantSetting::createDefaultSettings($tenant);

        // Test getting settings
        $maxUsers = $tenant->getSetting('limits', 'max_users');
        $this->assertEquals(10, $maxUsers);

        // Test setting values
        $tenant->setSetting('limits', 'max_users', 25, 'number');
        $this->assertEquals(25, $tenant->getSetting('limits', 'max_users'));

        // Test boolean settings
        $tenant->setSetting('features', 'api_access', false, 'boolean');
        $this->assertFalse($tenant->getSetting('features', 'api_access'));

        // Test JSON settings
        $tenant->setSetting('security', 'ip_whitelist', ['192.168.1.1', '10.0.0.1'], 'json');
        $ipList = $tenant->getSetting('security', 'ip_whitelist');
        $this->assertIsArray($ipList);
        $this->assertContains('192.168.1.1', $ipList);
    }

    /**
     * Test tenant domain management.
     */
    public function test_tenant_domain_management(): void
    {
        $tenant = Tenant::create([
            'name' => 'Domain Test Company',
            'email' => 'domain@test.com',
        ]);

        // Create primary domain
        $primaryDomain = $tenant->domains()->create([
            'domain' => 'test.' . config('app.domain'),
            'is_primary' => true,
            'is_verified' => true,
            'verified_at' => now(),
        ]);

        // Create secondary domain
        $secondaryDomain = $tenant->domains()->create([
            'domain' => 'custom.example.com',
            'is_primary' => false,
            'is_verified' => false,
        ]);

        // Test primary domain relationship
        $this->assertTrue($tenant->primaryDomain->is($primaryDomain));
        
        // Test domain count
        $this->assertEquals(2, $tenant->domains()->count());

        // Test subdomain detection
        // Debug: Check the actual domain values
        $this->assertEquals('test.' . config('app.domain'), $primaryDomain->domain);
        $this->assertTrue($primaryDomain->isSubdomain(), 'Primary domain should be detected as subdomain. Domain: ' . $primaryDomain->domain . ', App domain: ' . config('app.domain'));
        $this->assertFalse($secondaryDomain->isSubdomain());
        $this->assertEquals('test', $primaryDomain->getSubdomain());
    }

    /**
     * Test tenant activity logging.
     */
    public function test_tenant_activity_logging(): void
    {
        $tenant = Tenant::create([
            'name' => 'Activity Test Company',
            'email' => 'activity@test.com',
        ]);

        // Log an activity
        $activity = $tenant->logActivity(
            'test.action',
            'Test action performed',
            ['key' => 'value']
        );

        $this->assertDatabaseHas('tenant_activity_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'test.action',
            'description' => 'Test action performed',
        ]);

        $this->assertEquals('value', $activity->getMetadata('key'));
        $this->assertEquals('test', $activity->getActionCategory());
        $this->assertEquals('action', $activity->getActionType());
    }

    /**
     * Test tenant status management.
     */
    public function test_tenant_status_management(): void
    {
        $tenant = Tenant::create([
            'name' => 'Status Test Company',
            'email' => 'status@test.com',
            'status' => 'active',
        ]);

        $this->assertTrue($tenant->isActive());
        $this->assertFalse($tenant->isSuspended());

        // Suspend tenant
        $tenant->suspend('Test suspension');
        $tenant->refresh();

        $this->assertFalse($tenant->isActive());
        $this->assertTrue($tenant->isSuspended());

        // Check activity log
        $this->assertDatabaseHas('tenant_activity_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'tenant.suspended',
        ]);

        // Activate tenant
        $tenant->activate();
        $tenant->refresh();

        $this->assertTrue($tenant->isActive());
        $this->assertFalse($tenant->isSuspended());
    }
}
