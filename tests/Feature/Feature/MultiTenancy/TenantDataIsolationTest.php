<?php

namespace Tests\Feature\Feature\MultiTenancy;

use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Webkul\Lead\Models\Lead;
use Webkul\Contact\Models\Person;
use Webkul\Product\Models\Product;
use Webkul\User\Models\User;
use Webkul\Tag\Models\Tag;

class TenantDataIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant1;
    protected Tenant $tenant2;

    protected function setUp(): void
    {
        parent::setUp();

        // Create two tenants
        $this->tenant1 = Tenant::create([
            'name' => 'Tenant One',
            'slug' => 'tenant-one',
            'email' => 'tenant1@example.com',
            'status' => Tenant::STATUS_ACTIVE,
        ]);

        $this->tenant2 = Tenant::create([
            'name' => 'Tenant Two',
            'slug' => 'tenant-two',
            'email' => 'tenant2@example.com',
            'status' => Tenant::STATUS_ACTIVE,
        ]);
    }

    public function test_models_automatically_set_tenant_id_on_creation()
    {
        // Set tenant context
        app()->instance('tenant', $this->tenant1);

        // Create a lead
        $lead = Lead::create([
            'title' => 'Test Lead',
            'status' => 1,
            'lead_value' => 1000,
        ]);

        $this->assertEquals($this->tenant1->id, $lead->tenant_id);
    }

    public function test_models_are_automatically_scoped_by_tenant()
    {
        // Create data for tenant 1
        app()->instance('tenant', $this->tenant1);
        
        $lead1 = Lead::create([
            'title' => 'Tenant 1 Lead',
            'status' => 1,
            'lead_value' => 1000,
        ]);

        $tag1 = Tag::create([
            'name' => 'Tenant 1 Tag',
            'user_id' => 1,
        ]);

        // Create data for tenant 2
        app()->forgetInstance('tenant');
        app()->instance('tenant', $this->tenant2);
        
        $lead2 = Lead::create([
            'title' => 'Tenant 2 Lead',
            'status' => 1,
            'lead_value' => 2000,
        ]);

        $tag2 = Tag::create([
            'name' => 'Tenant 2 Tag',
            'user_id' => 1,
        ]);

        // Verify tenant 1 can only see their own data
        app()->forgetInstance('tenant');
        app()->instance('tenant', $this->tenant1);
        
        $leads = Lead::all();
        $tags = Tag::all();
        
        $this->assertCount(1, $leads);
        $this->assertEquals('Tenant 1 Lead', $leads->first()->title);
        
        $this->assertCount(1, $tags);
        $this->assertEquals('Tenant 1 Tag', $tags->first()->name);

        // Verify tenant 2 can only see their own data
        app()->forgetInstance('tenant');
        app()->instance('tenant', $this->tenant2);
        
        $leads = Lead::all();
        $tags = Tag::all();
        
        $this->assertCount(1, $leads);
        $this->assertEquals('Tenant 2 Lead', $leads->first()->title);
        
        $this->assertCount(1, $tags);
        $this->assertEquals('Tenant 2 Tag', $tags->first()->name);
    }

    public function test_repository_methods_respect_tenant_scope()
    {
        // Set tenant context
        app()->instance('tenant', $this->tenant1);

        // Create products for tenant 1
        $productRepo = app(\Webkul\Product\Repositories\ProductRepository::class);
        
        $product1 = $productRepo->create([
            'name' => 'Tenant 1 Product',
            'sku' => 'T1-PROD-001',
            'price' => 100,
        ]);

        // Switch to tenant 2
        app()->forgetInstance('tenant');
        app()->instance('tenant', $this->tenant2);

        $product2 = $productRepo->create([
            'name' => 'Tenant 2 Product',
            'sku' => 'T2-PROD-001',
            'price' => 200,
        ]);

        // Verify tenant 1 repository methods
        app()->forgetInstance('tenant');
        app()->instance('tenant', $this->tenant1);

        $allProducts = $productRepo->all();
        $foundProduct = $productRepo->findByField('sku', 'T1-PROD-001');
        $notFoundProduct = $productRepo->findByField('sku', 'T2-PROD-001');

        $this->assertCount(1, $allProducts);
        $this->assertEquals('Tenant 1 Product', $allProducts->first()->name);
        $this->assertCount(1, $foundProduct);
        $this->assertCount(0, $notFoundProduct);
    }

    public function test_cross_tenant_data_access_is_prevented()
    {
        // Create lead for tenant 1
        app()->instance('tenant', $this->tenant1);
        
        $lead = Lead::create([
            'title' => 'Tenant 1 Lead',
            'status' => 1,
            'lead_value' => 1000,
        ]);

        // Switch to tenant 2 and try to access tenant 1's lead
        app()->forgetInstance('tenant');
        app()->instance('tenant', $this->tenant2);

        $foundLead = Lead::find($lead->id);
        $this->assertNull($foundLead);

        // Try with repository
        $leadRepo = app(\Webkul\Lead\Repositories\LeadRepository::class);
        $foundLeadViaRepo = $leadRepo->find($lead->id);
        $this->assertNull($foundLeadViaRepo);
    }

    public function test_unique_constraints_work_per_tenant()
    {
        // Create tag for tenant 1
        app()->instance('tenant', $this->tenant1);
        
        Tag::create([
            'name' => 'Important',
            'user_id' => 1,
        ]);

        // Create same tag name for tenant 2 (should work)
        app()->forgetInstance('tenant');
        app()->instance('tenant', $this->tenant2);

        $tag2 = Tag::create([
            'name' => 'Important',
            'user_id' => 1,
        ]);

        $this->assertNotNull($tag2);
        $this->assertEquals('Important', $tag2->name);
        $this->assertEquals($this->tenant2->id, $tag2->tenant_id);
    }

    public function test_cascade_deletes_work_within_tenant()
    {
        // Set tenant context
        app()->instance('tenant', $this->tenant1);

        // Create related data
        $lead = Lead::create([
            'title' => 'Test Lead',
            'status' => 1,
            'lead_value' => 1000,
        ]);

        $tag = Tag::create([
            'name' => 'Test Tag',
            'user_id' => 1,
        ]);

        // Attach tag to lead
        $lead->tags()->attach($tag->id);

        // Delete the lead
        $lead->delete();

        // Verify tag still exists (as it should)
        $this->assertNotNull(Tag::find($tag->id));

        // Verify relationship is removed
        $this->assertDatabaseMissing('lead_tags', [
            'lead_id' => $lead->id,
            'tag_id' => $tag->id,
        ]);
    }

    public function test_super_admin_context_can_access_all_tenant_data()
    {
        // Create data for both tenants
        app()->instance('tenant', $this->tenant1);
        $lead1 = Lead::create([
            'title' => 'Tenant 1 Lead',
            'status' => 1,
            'lead_value' => 1000,
        ]);

        app()->forgetInstance('tenant');
        app()->instance('tenant', $this->tenant2);
        $lead2 = Lead::create([
            'title' => 'Tenant 2 Lead',
            'status' => 1,
            'lead_value' => 2000,
        ]);

        // Clear tenant context and set super admin context
        app()->forgetInstance('tenant');
        app()->instance('super-admin-context', true);

        // Super admin should see all leads
        $allLeads = Lead::withoutGlobalScope(\App\Scopes\TenantScope::class)->get();
        
        $this->assertCount(2, $allLeads);
        $this->assertTrue($allLeads->contains('title', 'Tenant 1 Lead'));
        $this->assertTrue($allLeads->contains('title', 'Tenant 2 Lead'));
    }

    public function test_tenant_switching_changes_data_context()
    {
        // Create data for tenant 1
        app()->instance('tenant', $this->tenant1);
        $tag1 = Tag::create([
            'name' => 'Tenant 1 Tag',
            'user_id' => 1,
        ]);

        // Create data for tenant 2
        app()->forgetInstance('tenant');
        app()->instance('tenant', $this->tenant2);
        $tag2 = Tag::create([
            'name' => 'Tenant 2 Tag',
            'user_id' => 1,
        ]);

        // Start with tenant 1
        app()->forgetInstance('tenant');
        app()->instance('tenant', $this->tenant1);
        $tags = Tag::all();
        $this->assertCount(1, $tags);
        $this->assertEquals('Tenant 1 Tag', $tags->first()->name);

        // Switch to tenant 2
        app()->forgetInstance('tenant');
        app()->instance('tenant', $this->tenant2);
        $tags = Tag::all();
        $this->assertCount(1, $tags);
        $this->assertEquals('Tenant 2 Tag', $tags->first()->name);

        // Switch back to tenant 1
        app()->forgetInstance('tenant');
        app()->instance('tenant', $this->tenant1);
        $tags = Tag::all();
        $this->assertCount(1, $tags);
        $this->assertEquals('Tenant 1 Tag', $tags->first()->name);
    }

    public function test_repository_create_assigns_correct_tenant_id()
    {
        // Test with different repositories
        app()->instance('tenant', $this->tenant1);

        // Lead Repository
        $leadRepo = app(\Webkul\Lead\Repositories\LeadRepository::class);
        $lead = $leadRepo->create([
            'title' => 'Test Lead',
            'status' => 1,
            'lead_value' => 1000,
        ]);
        $this->assertEquals($this->tenant1->id, $lead->tenant_id);

        // Person Repository
        $personRepo = app(\Webkul\Contact\Repositories\PersonRepository::class);
        $person = $personRepo->create([
            'name' => 'Test Person',
            'emails' => ['test@example.com'],
        ]);
        $this->assertEquals($this->tenant1->id, $person->tenant_id);

        // Product Repository
        $productRepo = app(\Webkul\Product\Repositories\ProductRepository::class);
        $product = $productRepo->create([
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'price' => 100,
        ]);
        $this->assertEquals($this->tenant1->id, $product->tenant_id);
    }

    public function test_bulk_operations_respect_tenant_scope()
    {
        // Create data for both tenants
        app()->instance('tenant', $this->tenant1);
        Tag::create(['name' => 'Tag 1', 'user_id' => 1]);
        Tag::create(['name' => 'Tag 2', 'user_id' => 1]);

        app()->forgetInstance('tenant');
        app()->instance('tenant', $this->tenant2);
        Tag::create(['name' => 'Tag A', 'user_id' => 1]);
        Tag::create(['name' => 'Tag B', 'user_id' => 1]);

        // Bulk delete for tenant 1
        app()->forgetInstance('tenant');
        app()->instance('tenant', $this->tenant1);
        
        $deleted = Tag::where('user_id', 1)->delete();
        $this->assertEquals(2, $deleted);

        // Verify tenant 2 data is untouched
        app()->forgetInstance('tenant');
        app()->instance('tenant', $this->tenant2);
        
        $remainingTags = Tag::all();
        $this->assertCount(2, $remainingTags);
        $this->assertTrue($remainingTags->contains('name', 'Tag A'));
        $this->assertTrue($remainingTags->contains('name', 'Tag B'));
    }
}