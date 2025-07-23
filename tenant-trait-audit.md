# Tenant Trait Audit Report

## Overview
This report identifies all models in the packages directory and their tenant-awareness status.

## Models Currently Using BelongsToTenant Trait (29 models)

### Activity Package
- `Activity.php` ✅
- `File.php` ✅
- `Participant.php` ✅

### Attribute Package
- `Attribute.php` ✅
- `AttributeOption.php` ✅

### Automation Package
- `Webhook.php` ✅
- `Workflow.php` ✅

### Contact Package
- `Organization.php` ✅
- `Person.php` ✅

### DataGrid Package
- `SavedFilter.php` ✅

### Email Package
- `Attachment.php` ✅
- `Email.php` ✅

### EmailTemplate Package
- `EmailTemplate.php` ✅

### Lead Package
- `Lead.php` ✅
- `Pipeline.php` ✅
- `Source.php` ✅
- `Stage.php` ✅
- `Type.php` ✅

### Product Package
- `Product.php` ✅
- `ProductInventory.php` ✅

### Quote Package
- `Quote.php` ✅
- `QuoteItem.php` ✅

### Tag Package
- `Tag.php` ✅

### User Package
- `Group.php` ✅
- `User.php` ✅

### Warehouse Package
- `Location.php` ✅
- `Warehouse.php` ✅

### WebForm Package
- `WebForm.php` ✅
- `WebFormAttribute.php` ✅

## Models NOT Using BelongsToTenant Trait (10 models)

### Critical Business Models That Should Be Tenant-Aware
1. **`packages/Webkul/User/src/Models/Role.php`** - Roles should be tenant-specific
2. **`packages/Webkul/Marketing/src/Models/Campaign.php`** - Marketing campaigns are business data
3. **`packages/Webkul/Marketing/src/Models/Event.php`** - Marketing events are business data
4. **`packages/Webkul/DataTransfer/src/Models/Import.php`** - Import operations should be tenant-specific
5. **`packages/Webkul/DataTransfer/src/Models/ImportBatch.php`** - Import batches should be tenant-specific

### Models That May Not Need Tenant Awareness
1. **`packages/Webkul/Core/src/Models/Country.php`** - Global reference data
2. **`packages/Webkul/Core/src/Models/CountryState.php`** - Global reference data
3. **`packages/Webkul/Core/src/Models/CoreConfig.php`** - May need special handling for tenant-specific configs

### Special Cases
1. **`packages/Webkul/Attribute/src/Models/AttributeValue.php`** - Already tenant-aware through parent entities
2. **`packages/Webkul/Lead/src/Models/Product.php`** - This is a pivot table (lead_products), tenant-aware through Lead model

## Recommendations

### Immediate Actions Required
1. Add `BelongsToTenant` trait to these models:
   - `Role.php` - Critical for tenant-specific permissions
   - `Campaign.php` - Marketing data must be isolated
   - `Event.php` - Marketing events must be isolated
   - `Import.php` - Import operations must be tenant-specific
   - `ImportBatch.php` - Import batches must be tenant-specific

### Models Requiring Further Analysis
1. **CoreConfig**: May need a hybrid approach where some configs are global and others are tenant-specific
2. **Country/CountryState**: These are reference data and likely don't need tenant isolation

### Already Properly Handled
1. **AttributeValue**: Doesn't need the trait as it's polymorphically related to entities that are already tenant-aware
2. **Lead Product (pivot)**: Inherits tenant context from the Lead model

## Database Migration Requirements
For models that need the `BelongsToTenant` trait added, ensure their database tables have:
- A `tenant_id` column (VARCHAR or UUID)
- An index on `tenant_id` for performance
- A foreign key constraint to the tenants table