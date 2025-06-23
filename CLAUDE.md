# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Krayin CRM is an open-source Laravel-based CRM system with a modular architecture. The application uses Laravel 10 for the backend and Vue.js 3 for the frontend components. It is designed for SMEs and Enterprises for complete customer lifecycle management.

## Key Commands

### Development Setup
```bash
# Install PHP dependencies
composer install

# Install frontend dependencies
npm install

# Set up environment
cp .env.example .env
php artisan key:generate

# Run database migrations and seeders
php artisan migrate --seed
php artisan krayin-crm:install  # Interactive installer
```

### Production Setup
```bash
# Install without dev dependencies
composer install --no-dev

# Build and optimize
npm run build
php artisan optimize
```

### Development Commands
```bash
# Start development server
php artisan serve

# Watch frontend assets (Vite)
npm run dev

# Build frontend assets for production
npm run build

# Run PHP linter
./vendor/bin/pint

# Run tests
php artisan test
./vendor/bin/pest
./vendor/bin/pest --filter="TestName"
./vendor/bin/pest --parallel  # Run tests in parallel

# Clear all caches
php artisan optimize:clear

# View routes
php artisan route:list

# Create a new package
php artisan package:make
```

## Architecture Overview

### Package-Based Structure
The application follows a modular architecture with packages located in `packages/Webkul/`. Each package is self-contained with its own:
- Models, Controllers, and Repositories
- Database migrations
- Service providers
- Configuration files
- Routes
- Views and frontend assets

### Core Packages
- **Core**: Foundation functionality, repositories, and base classes
  - Base Repository class: `Webkul\Core\Eloquent\Repository`
  - Core traits and helpers
  - Configuration management
- **Admin**: Admin panel UI, controllers, and DataGrids
  - All UI components in `Resources/assets/js/components/`
  - Main layout: `Resources/views/components/layouts/index.blade.php`
  - Route prefix: `/admin`
- **Lead**: Lead management functionality
  - Pipeline and stage management
  - Lead activities and timeline
- **Contact**: Person and Organization management
  - Separate entities for persons and organizations
  - Relationship management between contacts
- **Product**: Product catalog with inventory
- **Quote**: Quote generation and management
- **Email**: Email integration and parsing
  - Sendgrid webhook support
  - IMAP email fetching
- **Activity**: Activity logging and tracking
- **Automation**: Workflow automation (webhooks and workflows)
- **WebForm**: Form builder for lead capture
- **User**: User and role management
- **Attribute**: EAV system for custom fields
- **Tag**: Tagging system for entities
- **Warehouse**: Inventory and location management
- **EmailTemplate**: Email template management
- **DataGrid**: Reusable data table system
- **DataTransfer**: Import/export functionality
- **Marketing**: Campaign management

### Data Flow Pattern
1. Routes are defined in each package's route files (`Routes/Admin/`)
2. Controllers handle requests and use Repositories for data access
3. Repositories extend `Webkul\Core\Eloquent\Repository` for CRUD operations
4. Models use Concord for modularity (proxy pattern)
5. DataGrids provide listing functionality with filtering/sorting
6. Views use Blade templates with Vue.js components

### Repository Pattern
All data access uses the Repository pattern:
```php
// Example: LeadRepository
$leadRepository = app(\Webkul\Lead\Repositories\LeadRepository::class);
$lead = $leadRepository->create($data);
```

### DataGrid System
DataGrids handle listing pages with built-in features:
- Pagination, sorting, and filtering
- Mass actions
- Excel export
- Located in `packages/*/src/DataGrids/`
- Extended from `Webkul\DataGrid\DataGrid`

### Frontend Architecture
- Vue.js 3 components in `packages/Webkul/Admin/src/Resources/assets/js/`
- Vite for asset bundling (config in `vite.config.js`)
- Admin package has its own Vite config at `packages/Webkul/Admin/vite.config.js`
- Tailwind CSS for styling
- Custom admin UI components library
- Dark mode support

### Vue.js Components
Key components are prefixed with `v-`:
- `<v-datagrid>` - Data table with sorting/filtering
- `<v-form>` - Form wrapper with validation
- `<v-modal>` - Modal dialogs
- `<v-drawer>` - Slide-out panels
- `<v-dropdown>` - Dropdown menus
- `<v-tabs>` - Tab navigation

Components are auto-registered globally in `app.js`

## Multi-Tenancy Implementation

The application includes comprehensive multi-tenancy support with:

### Core Components
- **TenantManager**: Handles tenant lifecycle (creation, deletion, suspension)
- **TenantResolver**: Resolves current tenant from request
- **TenantIdentification** middleware: Identifies tenant from subdomain/domain
- **TenantScope** middleware: Applies global scopes
- **ConfigureTenantEmail** middleware: Sets tenant-specific email config
- **BelongsToTenant** trait: Adds tenant scoping to models

### Features
- Subdomain-based tenant identification (e.g., acme.groovecrm.com)
- Custom domain support
- Row-level security with `tenant_id` columns
- Super admin panel at `/super-admin`
- Tenant-specific configurations and branding
- Session isolation per tenant
- Tenant-specific storage paths

### Helper Functions
```php
current_tenant()        // Get current tenant
tenant_route()         // Generate tenant-specific routes
tenant_config()        // Get tenant-specific configuration
tenant_storage_path()  // Get tenant storage path
```

## Email Integration
The system supports email parsing via Sendgrid webhook. Email configuration is managed through:
- `config/mail.php` - Mail driver settings
- `config/imap.php` - IMAP configuration for email fetching
- `.env` - Email credentials and webhook endpoints

## Database Considerations
- Uses Laravel migrations with modular structure
- Each package has its own migrations in `Database/Migrations/`
- Supports MySQL 5.7.23+ or MariaDB 10.2.7+
- Uses Laravel's query builder and Eloquent ORM
- **Important**: When using DigitalOcean or other managed databases with `sql_require_primary_key=ON`, all tables must have primary keys. Pivot tables should use composite primary keys

## Testing Approach
- PHPUnit 10.5 configuration in `phpunit.xml`
- Pest PHP as the primary testing framework
- Test files located in `tests/` directory
- Run specific tests with: `./vendor/bin/pest --filter="TestName"`
- Parallel test execution: `./vendor/bin/pest --parallel`
- Test helpers available in `tests/Pest.php`:
  - `getDefaultAdmin()` - Gets the default admin user
  - `actingAsSanctumAuthenticatedAdmin()` - Sanctum authentication helper
- E2E testing with Playwright in `packages/Webkul/Admin/tests/e2e-pw/`

## Code Quality
- Laravel Pint for PHP code style (configured in `pint.json`)
- Uses Laravel preset with custom alignment rules
- GitHub Actions CI/CD pipeline (`.github/workflows/ci.yml`)
- Tests run on PHP 8.2 and 8.3 with MySQL 8.0

## Architecture Patterns
- **Repository Pattern**: All data access through repositories
- **Service Layer**: Business logic in service classes
- **Proxy Pattern**: Models use Concord proxy pattern
- **Event-Driven**: Extensive use of Laravel events
- **Modular Design**: Self-contained packages
- **Global Scoping**: Automatic tenant scoping via traits

## Custom Attributes System
The application includes a flexible attributes system allowing custom fields on entities (leads, contacts, products). Attributes are managed through the `Attribute` package and stored using EAV pattern.

## Configuration
- Environment variables in `.env`
- Package configurations in `config/` directory
- Package registration in `config/concord.php` (using Konekt Concord)
- Core settings managed through admin panel
- Multi-language support with translations in `packages/*/src/Resources/lang/`

## Authentication
- Laravel Sanctum for API authentication
- Role-based access control (ACL)
- User groups for team management
- Super admin authentication for multi-tenancy

## Default Admin Access
- URL: `/admin/dashboard`
- Email: `admin@example.com`
- Password: `admin123`

## Requirements
- PHP 8.1 or higher (8.2+ recommended)
- MySQL 5.7.23+ or MariaDB 10.2.7+
- Node.js 8.11.3 LTS or higher
- Composer 2.5 or higher
- Apache 2 or NGINX
- 3GB RAM or higher