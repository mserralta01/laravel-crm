# Multi-Tenant Architecture Guide

## Overview

Krayin CRM implements a **login-based multi-tenant architecture** where tenant identification is based on user authentication, not subdomains or domains. Each tenant represents a separate organization with completely isolated data.

## Key Features

- **Automatic Tenant Isolation**: All queries are automatically scoped to the current tenant
- **Login-Based Identification**: Tenants are identified by user login credentials
- **Row-Level Security**: Each table has a `tenant_id` column for data isolation
- **No Subdomain Required**: Simple setup without DNS configuration
- **Transparent to Application Code**: Works seamlessly with existing code

## Architecture Components

### 1. BelongsToTenant Trait

The `BelongsToTenant` trait is the core of our multi-tenant implementation:

```php
use App\Traits\BelongsToTenant;

class Lead extends Model
{
    use BelongsToTenant;
}
```

This trait automatically:
- Sets `tenant_id` on model creation
- Applies global scope to filter by current tenant
- Prevents changing `tenant_id` after creation
- Validates tenant context exists

### 2. SetTenantByUser Middleware

The middleware sets tenant context based on authenticated user:

```php
// Applied in app/Http/Kernel.php
'web' => [
    // ... other middleware
    \App\Http\Middleware\MultiTenancy\SetTenantByUser::class,
],
```

### 3. TenantService

Centralized service for tenant operations:

```php
use App\Facades\Tenant;

// Get current tenant
$tenant = Tenant::current();
$tenantId = Tenant::currentId();

// Check access
if (!Tenant::canAccess($model)) {
    abort(403);
}

// Switch tenant context temporarily
Tenant::runAs($otherTenant, function () {
    // Code runs in different tenant context
});
```

## Usage Guide

### Creating Tenant-Aware Models

1. Add the trait to your model:

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use BelongsToTenant;
    
    protected $fillable = ['number', 'amount', 'due_date'];
}
```

2. Add `tenant_id` to your migration:

```php
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
    $table->string('number');
    $table->decimal('amount', 10, 2);
    $table->date('due_date');
    $table->timestamps();
    
    $table->index('tenant_id');
    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
});
```

### Working with Tenant Data

#### Creating Records
Records are automatically assigned to the current tenant:

```php
// Tenant ID is automatically set
$lead = Lead::create([
    'title' => 'New Lead',
    'status' => 'open',
]);

// Or explicitly for a specific tenant
$lead = Lead::createForTenant([
    'title' => 'New Lead',
    'status' => 'open',
], $tenant);
```

#### Querying Records
Queries are automatically scoped to current tenant:

```php
// Only returns leads for current tenant
$leads = Lead::all();

// Query specific tenant's data
$leads = Lead::forTenant($tenantId)->get();

// Bypass tenant scoping (admin only!)
$allLeads = Lead::withoutTenant()->get();
```

#### Checking Access
Validate if current user can access a resource:

```php
public function show(Lead $lead)
{
    if (!Tenant::canAccess($lead)) {
        abort(403, 'Access denied');
    }
    
    return view('leads.show', compact('lead'));
}
```

### Super Admin Operations

Super admins can manage all tenants:

```php
// Create new tenant
$tenant = Tenant::create([
    'name' => 'Acme Corporation',
    'email' => 'admin@acme.com',
    'status' => 'active',
    'trial_ends_at' => now()->addDays(14),
]);

// Create admin user for tenant
$admin = User::create([
    'name' => 'Admin User',
    'email' => 'admin@acme.com',
    'password' => Hash::make('secure-password'),
    'tenant_id' => $tenant->id,
]);

// Suspend tenant
$tenant->suspend('Non-payment');

// Activate tenant
$tenant->activate();
```

### Switching Tenant Context

For admin operations or background jobs:

```php
// Temporarily switch context
Tenant::runAs($tenant, function () {
    // All operations here run in $tenant context
    $stats = [
        'leads' => Lead::count(),
        'contacts' => Person::count(),
        'revenue' => Quote::sum('grand_total'),
    ];
    
    return $stats;
});

// Using the trait method
Lead::withTenant($tenant, function () {
    return Lead::where('status', 'won')->count();
});
```

## Security Considerations

### 1. Automatic Protection
- Models using `BelongsToTenant` are automatically protected
- Cannot create records without tenant context
- Cannot change `tenant_id` after creation

### 2. Validation
```php
// In controllers
public function update(Request $request, Lead $lead)
{
    // Automatically checks tenant access
    if (!Tenant::canAccess($lead)) {
        abort(403);
    }
    
    // Process update...
}
```

### 3. Logging Security Violations
```php
// Log suspicious activity
Tenant::logSecurityViolation('Attempted cross-tenant access', [
    'resource' => 'Lead',
    'resource_id' => $leadId,
    'attempted_tenant' => $attemptedTenantId,
]);
```

## Common Patterns

### 1. Tenant-Specific Configuration
```php
// Store tenant-specific settings
CoreConfig::createForTenant([
    'code' => 'general.locale',
    'value' => 'es',
], $tenant);

// Retrieve tenant settings
$locale = CoreConfig::where('code', 'general.locale')->first()?->value;
```

### 2. Unique Constraints Per Tenant
```php
// Migration with tenant-scoped unique constraint
$table->unique(['tenant_id', 'email']);
$table->unique(['tenant_id', 'sku']);
```

### 3. Background Jobs
```php
class ProcessTenantData implements ShouldQueue
{
    protected $tenantId;
    
    public function __construct($tenantId)
    {
        $this->tenantId = $tenantId;
    }
    
    public function handle()
    {
        $tenant = Tenant::find($this->tenantId);
        
        Tenant::runAs($tenant, function () {
            // Process tenant data
        });
    }
}
```

## Testing

### Unit Tests
```php
public function test_tenant_isolation()
{
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    // Create data for each tenant
    $lead1 = Lead::createForTenant(['title' => 'Lead 1'], $tenant1);
    $lead2 = Lead::createForTenant(['title' => 'Lead 2'], $tenant2);
    
    // Set context to tenant 1
    app()->singleton('tenant.id', fn() => $tenant1->id);
    
    // Should only see tenant 1's data
    $leads = Lead::all();
    $this->assertCount(1, $leads);
    $this->assertEquals($lead1->id, $leads->first()->id);
}
```

### Feature Tests
```php
public function test_user_can_only_access_own_tenant_data()
{
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    
    $this->actingAs($user)
        ->get('/admin/leads')
        ->assertOk()
        ->assertDontSee($otherTenantLead->title);
}
```

## Troubleshooting

### No Tenant Context Error
```
RuntimeException: No tenant context set. Cannot create Model without tenant.
```

**Solution**: Ensure user is authenticated and has valid `tenant_id`, or manually set context:
```php
app()->singleton('tenant.id', fn() => $tenantId);
```

### Cannot Change Tenant ID Error
```
RuntimeException: Cannot change tenant_id after creation
```

**Solution**: This is by design. Create a new record instead of changing tenant ownership.

### Foreign Key Constraint Error
Ensure `tenant_id` column type matches `tenants.id`:
```php
$table->unsignedBigInteger('tenant_id'); // Must match tenants.id type
```

## Best Practices

1. **Always use the trait** for models that should be tenant-specific
2. **Never expose tenant_id** in forms or APIs
3. **Validate access** in controllers using `Tenant::canAccess()`
4. **Use caching** via `Tenant::find()` for frequently accessed tenants
5. **Log security violations** for audit trails
6. **Test tenant isolation** thoroughly
7. **Document** which models are tenant-aware

## Migration Checklist

When adding multi-tenancy to existing models:

- [ ] Add `use BelongsToTenant` trait
- [ ] Create migration to add `tenant_id` column
- [ ] Add foreign key constraint
- [ ] Update unique constraints to include `tenant_id`
- [ ] Assign existing data to default tenant
- [ ] Update seeders to include `tenant_id`
- [ ] Add tests for tenant isolation
- [ ] Update API resources to exclude `tenant_id`