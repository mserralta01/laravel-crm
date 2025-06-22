<?php

namespace Tests\Unit\MultiTenancy;

use App\Models\Tenant\Tenant;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

class TestModel extends Model
{
    use BelongsToTenant;
    
    protected $fillable = ['name', 'tenant_id'];
    
    // Disable database operations for unit test
    public $exists = false;
    
    public function save(array $options = [])
    {
        // Mock save for unit test
        return true;
    }
}

class BelongsToTenantTraitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_trait_adds_tenant_id_to_fillable()
    {
        $model = new TestModel();
        
        $this->assertContains('tenant_id', $model->getFillable());
    }

    public function test_get_current_tenant_id_returns_tenant_id_when_tenant_exists()
    {
        // Create a mock tenant
        $tenant = new Tenant();
        $tenant->id = 'test-tenant-id';
        
        // Set tenant in container
        app()->instance('tenant', $tenant);
        
        $model = new TestModel();
        $tenantId = $model->getCurrentTenantId();
        
        $this->assertEquals('test-tenant-id', $tenantId);
    }

    public function test_get_current_tenant_id_returns_null_when_no_tenant()
    {
        // Make sure no tenant is set
        app()->forgetInstance('tenant');
        
        $model = new TestModel();
        $tenantId = $model->getCurrentTenantId();
        
        $this->assertNull($tenantId);
    }

    public function test_tenant_relationship_is_defined()
    {
        $model = new TestModel();
        
        // Check if tenant method exists
        $this->assertTrue(method_exists($model, 'tenant'));
    }

    public function test_scope_tenant_filters_by_current_tenant()
    {
        // Create a mock tenant
        $tenant = new Tenant();
        $tenant->id = 'test-tenant-id';
        
        // Set tenant in container
        app()->instance('tenant', $tenant);
        
        // Create a mock query builder
        $query = $this->getMockBuilder(\Illuminate\Database\Eloquent\Builder::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $query->expects($this->once())
            ->method('where')
            ->with('tenant_id', 'test-tenant-id')
            ->willReturn($query);
        
        $model = new TestModel();
        $result = $model->scopeTenant($query);
        
        $this->assertSame($query, $result);
    }

    public function test_scope_tenant_returns_query_unchanged_when_no_tenant()
    {
        // Make sure no tenant is set
        app()->forgetInstance('tenant');
        
        // Create a mock query builder
        $query = $this->getMockBuilder(\Illuminate\Database\Eloquent\Builder::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $query->expects($this->never())
            ->method('where');
        
        $model = new TestModel();
        $result = $model->scopeTenant($query);
        
        $this->assertSame($query, $result);
    }
}