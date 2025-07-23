<?php

namespace Tests\Feature\MultiTenancy;

use Tests\TestCase;
use App\Models\Tenant\Tenant;
use Webkul\User\Models\User;
use Webkul\Lead\Models\Lead;
use Webkul\Contact\Models\Person;
use Webkul\Product\Models\Product;
use Webkul\Quote\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant1;
    protected $tenant2;
    protected $user1;
    protected $user2;

    protected function setUp(): void
    {
        parent::setUp();

        // Create two test tenants
        $this->tenant1 = Tenant::create([
            'name' => 'Test Company 1',
            'slug' => 'test-company-1',
            'email' => 'admin@testcompany1.com',
            'status' => 'active',
        ]);

        $this->tenant2 = Tenant::create([
            'name' => 'Test Company 2',
            'slug' => 'test-company-2',
            'email' => 'admin@testcompany2.com',
            'status' => 'active',
        ]);

        // Create users for each tenant
        $this->user1 = User::create([
            'name' => 'User 1',
            'email' => 'user1@test.com',
            'password' => Hash::make('password'),
            'tenant_id' => $this->tenant1->id,
            'status' => 1,
        ]);

        $this->user2 = User::create([
            'name' => 'User 2',
            'email' => 'user2@test.com',
            'password' => Hash::make('password'),
            'tenant_id' => $this->tenant2->id,
            'status' => 1,
        ]);
    }

    /**
     * Test that users can only see data from their own tenant.
     */
    public function test_users_can_only_see_own_tenant_data()
    {
        // Create leads for both tenants
        $lead1 = Lead::createForTenant([
            'title' => 'Lead for Tenant 1',
            'status' => 1,
            'person_id' => 1,
        ], $this->tenant1);

        $lead2 = Lead::createForTenant([
            'title' => 'Lead for Tenant 2',
            'status' => 1,
            'person_id' => 1,
        ], $this->tenant2);

        // Act as user 1
        $this->actingAs($this->user1);
        
        // User 1 should only see their tenant's lead
        $leads = Lead::all();
        $this->assertCount(1, $leads);
        $this->assertEquals($lead1->id, $leads->first()->id);
        $this->assertEquals($this->tenant1->id, $leads->first()->tenant_id);
    }

    /**
     * Test that tenant context is automatically set on model creation.
     */
    public function test_tenant_context_automatically_set_on_creation()
    {
        // Set tenant context
        app()->singleton('tenant', function () {
            return $this->tenant1;
        });
        app()->singleton('tenant.id', function () {
            return $this->tenant1->id;
        });

        // Create a product without explicitly setting tenant_id
        $product = Product::create([
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'price' => 99.99,
        ]);

        // Assert tenant_id was automatically set
        $this->assertEquals($this->tenant1->id, $product->tenant_id);
    }

    /**
     * Test that users cannot access data from other tenants.
     */
    public function test_users_cannot_access_other_tenant_data()
    {
        // Create a person for tenant 2
        $person = Person::createForTenant([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'emails' => [['value' => 'john@tenant2.com']],
        ], $this->tenant2);

        // Set context to tenant 1
        app()->singleton('tenant.id', function () {
            return $this->tenant1->id;
        });

        // Try to find the person from tenant 2
        $foundPerson = Person::find($person->id);
        $this->assertNull($foundPerson);

        // Try to query for the person
        $queriedPerson = Person::where('id', $person->id)->first();
        $this->assertNull($queriedPerson);
    }

    /**
     * Test that tenant_id cannot be changed after creation.
     */
    public function test_tenant_id_cannot_be_changed_after_creation()
    {
        // Create a quote for tenant 1
        $quote = Quote::createForTenant([
            'subject' => 'Test Quote',
            'expired_at' => now()->addDays(30),
            'person_id' => 1,
        ], $this->tenant1);

        // Try to change tenant_id
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot change tenant_id after creation');

        $quote->tenant_id = $this->tenant2->id;
        $quote->save();
    }

    /**
     * Test that models without tenant context throw exception.
     */
    public function test_creating_model_without_tenant_context_throws_exception()
    {
        // Clear tenant context
        app()->forgetInstance('tenant');
        app()->forgetInstance('tenant.id');

        // Try to create a lead without tenant context
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No tenant context set');

        Lead::create([
            'title' => 'Test Lead',
            'status' => 1,
            'person_id' => 1,
        ]);
    }

    /**
     * Test the withTenant scope method.
     */
    public function test_with_tenant_scope_temporarily_switches_context()
    {
        // Create products for both tenants
        $product1 = Product::createForTenant([
            'name' => 'Product 1',
            'sku' => 'PROD-001',
        ], $this->tenant1);

        $product2 = Product::createForTenant([
            'name' => 'Product 2',
            'sku' => 'PROD-002',
        ], $this->tenant2);

        // Set context to tenant 1
        app()->singleton('tenant.id', function () {
            return $this->tenant1->id;
        });

        // Should only see tenant 1's product
        $this->assertCount(1, Product::all());

        // Use withTenant to temporarily switch context
        $result = Product::withTenant($this->tenant2, function () {
            return Product::all();
        });

        // Should see tenant 2's product
        $this->assertCount(1, $result);
        $this->assertEquals($this->tenant2->id, $result->first()->tenant_id);

        // Context should be restored
        $this->assertCount(1, Product::all());
        $this->assertEquals($this->tenant1->id, Product::all()->first()->tenant_id);
    }

    /**
     * Test the forTenant scope method.
     */
    public function test_for_tenant_scope_bypasses_global_scope()
    {
        // Create leads for both tenants
        Lead::createForTenant(['title' => 'Lead 1', 'status' => 1], $this->tenant1);
        Lead::createForTenant(['title' => 'Lead 2', 'status' => 1], $this->tenant2);

        // Set context to tenant 1
        app()->singleton('tenant.id', function () {
            return $this->tenant1->id;
        });

        // Use forTenant to query specific tenant
        $tenant2Leads = Lead::forTenant($this->tenant2->id)->get();
        $this->assertCount(1, $tenant2Leads);
        $this->assertEquals($this->tenant2->id, $tenant2Leads->first()->tenant_id);
    }

    /**
     * Test inactive tenant blocks user access.
     */
    public function test_inactive_tenant_blocks_user_access()
    {
        // Suspend tenant 1
        $this->tenant1->status = 'suspended';
        $this->tenant1->save();

        // Try to access as user 1
        $response = $this->actingAs($this->user1)
            ->get('/admin/dashboard');

        // Should be redirected to login with error
        $response->assertRedirect('/login');
        $response->assertSessionHas('error', 'Your organization account is not active. Please contact support.');
    }

    /**
     * Test the belongsToTenant method.
     */
    public function test_belongs_to_tenant_method()
    {
        $lead = Lead::createForTenant([
            'title' => 'Test Lead',
            'status' => 1,
        ], $this->tenant1);

        // Test with tenant model
        $this->assertTrue($lead->belongsToTenant($this->tenant1));
        $this->assertFalse($lead->belongsToTenant($this->tenant2));

        // Test with tenant ID
        $this->assertTrue($lead->belongsToTenant($this->tenant1->id));
        $this->assertFalse($lead->belongsToTenant($this->tenant2->id));
    }

    /**
     * Test unique constraints work per tenant.
     */
    public function test_unique_constraints_work_per_tenant()
    {
        // Create product with same SKU for different tenants
        $product1 = Product::createForTenant([
            'name' => 'Product 1',
            'sku' => 'SAME-SKU',
            'price' => 100,
        ], $this->tenant1);

        // Should be able to create same SKU for different tenant
        $product2 = Product::createForTenant([
            'name' => 'Product 2',
            'sku' => 'SAME-SKU',
            'price' => 200,
        ], $this->tenant2);

        $this->assertNotEquals($product1->id, $product2->id);
        $this->assertEquals('SAME-SKU', $product1->sku);
        $this->assertEquals('SAME-SKU', $product2->sku);
        $this->assertNotEquals($product1->tenant_id, $product2->tenant_id);
    }
}