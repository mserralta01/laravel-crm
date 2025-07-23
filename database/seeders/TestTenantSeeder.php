<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant\Tenant;
use Webkul\User\Models\User;
use Webkul\User\Models\Group;
use Webkul\User\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class TestTenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // Create a test tenant
            $tenant = Tenant::create([
                'name' => 'Acme Corporation',
                'slug' => 'acme',
                'email' => 'admin@acme.com',
                'phone' => '555-0100',
                'status' => Tenant::STATUS_ACTIVE,
                'trial_ends_at' => now()->addDays(30),
                'settings' => [
                    'theme' => 'default',
                    'timezone' => 'America/New_York',
                ],
            ]);

            $this->command->info("Created tenant: {$tenant->name} (ID: {$tenant->id})");

            // Get or create admin role
            $adminRole = Role::firstOrCreate(
                ['name' => 'Administrator'],
                [
                    'description' => 'Administrator role has all permissions',
                    'permission_type' => 'all',
                ]
            );

            // Get or create default group
            $defaultGroup = Group::firstOrCreate(
                ['name' => 'General'],
                ['description' => 'General group']
            );

            // Create tenant admin user
            $tenantAdmin = User::create([
                'name' => 'Acme Admin',
                'email' => 'admin@acme.com',
                'password' => Hash::make('password'),
                'role_id' => $adminRole->id,
                'status' => 1,
                'tenant_id' => $tenant->id,
            ]);

            // Attach to default group
            $tenantAdmin->groups()->attach($defaultGroup->id);

            $this->command->info("Created tenant admin user: {$tenantAdmin->email}");

            // Create another tenant for testing
            $tenant2 = Tenant::create([
                'name' => 'TechCorp Solutions',
                'slug' => 'techcorp',
                'email' => 'admin@techcorp.com',
                'phone' => '555-0200',
                'status' => Tenant::STATUS_ACTIVE,
                'trial_ends_at' => now()->addDays(30),
                'settings' => [
                    'theme' => 'default',
                    'timezone' => 'America/Los_Angeles',
                ],
            ]);

            $this->command->info("Created tenant: {$tenant2->name} (ID: {$tenant2->id})");

            // Create tenant admin user for second tenant
            $tenantAdmin2 = User::create([
                'name' => 'TechCorp Admin',
                'email' => 'admin@techcorp.com',
                'password' => Hash::make('password'),
                'role_id' => $adminRole->id,
                'status' => 1,
                'tenant_id' => $tenant2->id,
            ]);

            // Attach to default group
            $tenantAdmin2->groups()->attach($defaultGroup->id);

            $this->command->info("Created tenant admin user: {$tenantAdmin2->email}");

            $this->command->info("\nTest tenants created successfully!");
            $this->command->info("You can now log in with:");
            $this->command->info("- Tenant 1: admin@acme.com / password");
            $this->command->info("- Tenant 2: admin@techcorp.com / password");
            $this->command->info("- Super Admin: admin@example.com / admin123");
        });
    }
}