<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Key-value store for tenant-specific settings.
     * Allows flexible configuration without schema changes.
     */
    public function up(): void
    {
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade')->comment('Reference to tenant');
            $table->string('group')->comment('Setting group (e.g., limits, features, branding)');
            $table->string('key')->comment('Setting key within the group');
            $table->text('value')->nullable()->comment('Setting value (can be JSON)');
            $table->enum('type', ['text', 'number', 'boolean', 'json'])->default('text')->comment('Value data type');
            $table->timestamps();
            
            // Composite unique constraint and indexes
            $table->unique(['tenant_id', 'group', 'key'], 'tenant_settings_unique');
            $table->index(['tenant_id', 'group']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
    }
};
