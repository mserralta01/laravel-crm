<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Audit trail for tenant-level activities.
     * Tracks important actions for security and compliance.
     */
    public function up(): void
    {
        Schema::create('tenant_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade')->comment('Reference to tenant');
            $table->unsignedBigInteger('user_id')->nullable()->comment('User who performed the action (null for system)');
            $table->string('action')->comment('Action identifier (e.g., tenant.created, user.suspended)');
            $table->text('description')->nullable()->comment('Human-readable description');
            $table->string('ip_address', 45)->nullable()->comment('IP address of the request');
            $table->text('user_agent')->nullable()->comment('Browser/client user agent');
            $table->json('metadata')->nullable()->comment('Additional context data');
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes for efficient querying
            $table->index('tenant_id');
            $table->index('action');
            $table->index('created_at');
            $table->index(['tenant_id', 'action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_activity_logs');
    }
};
