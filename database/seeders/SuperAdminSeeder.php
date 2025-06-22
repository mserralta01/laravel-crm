<?php

namespace Database\Seeders;

use App\Models\SuperAdmin;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates a default super admin user for initial system access.
     */
    public function run(): void
    {
        // Check if super admin already exists
        if (SuperAdmin::where('email', 'superadmin@groovecrm.com')->exists()) {
            $this->command->info('Super admin already exists, skipping...');
            return;
        }

        // Create default super admin
        $superAdmin = SuperAdmin::create([
            'name' => 'Super Administrator',
            'email' => 'superadmin@groovecrm.com',
            'password' => Hash::make('superadmin123'),
            'status' => true,
        ]);

        $this->command->info('Super admin created successfully!');
        $this->command->info('Email: superadmin@groovecrm.com');
        $this->command->info('Password: superadmin123');
        $this->command->warn('Please change the password after first login!');
    }
}
