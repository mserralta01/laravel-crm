<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Super admin users have access to all tenants and system management.
     * Separate from tenant users for security isolation.
     */
    public function up(): void
    {
        Schema::create('super_admin_users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Super admin full name');
            $table->string('email')->unique()->comment('Super admin email for login');
            $table->string('password')->comment('Hashed password');
            $table->boolean('status')->default(true)->comment('Account active status');
            $table->timestamp('last_login_at')->nullable()->comment('Last successful login');
            $table->string('remember_token', 100)->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('email');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('super_admin_users');
    }
};
