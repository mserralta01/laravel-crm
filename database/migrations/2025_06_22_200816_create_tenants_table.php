<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This creates the main tenants table for multi-tenancy support.
     * Each tenant represents a separate customer/organization using the CRM.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('Unique identifier for external references');
            $table->string('name')->comment('Tenant company/organization name');
            $table->string('slug')->unique()->comment('Subdomain identifier (e.g., "acme" for acme.groovecrm.com)');
            $table->string('email')->comment('Primary contact email');
            $table->string('phone', 20)->nullable()->comment('Primary contact phone');
            $table->enum('status', ['active', 'suspended', 'inactive'])->default('active')->comment('Tenant account status');
            $table->timestamp('trial_ends_at')->nullable()->comment('Trial expiration date');
            $table->json('settings')->nullable()->comment('Tenant-specific settings and configurations');
            $table->timestamps();
            
            // Indexes for performance
            $table->index('status');
            $table->index('slug');
            $table->index(['status', 'trial_ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
