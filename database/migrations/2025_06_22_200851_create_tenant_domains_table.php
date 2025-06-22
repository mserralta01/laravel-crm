<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Manages custom domains and subdomains for each tenant.
     * Supports multiple domains per tenant with primary domain designation.
     */
    public function up(): void
    {
        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade')->comment('Reference to tenant');
            $table->string('domain')->unique()->comment('Full domain (e.g., acme.groovecrm.com or custom.com)');
            $table->boolean('is_primary')->default(false)->comment('Primary domain for the tenant');
            $table->boolean('is_verified')->default(false)->comment('Domain ownership verification status');
            $table->timestamp('verified_at')->nullable()->comment('When domain was verified');
            $table->boolean('ssl_enabled')->default(false)->comment('SSL certificate status');
            $table->timestamps();
            
            // Indexes
            $table->index('tenant_id');
            $table->index('domain');
            $table->index(['tenant_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_domains');
    }
};
