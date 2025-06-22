<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Stores database connection information for each tenant.
     * Supports multiple database strategies (shared or isolated).
     */
    public function up(): void
    {
        Schema::create('tenant_databases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade')->comment('Reference to tenant');
            $table->string('connection_name')->comment('Laravel database connection name');
            $table->string('database_name')->unique()->comment('Actual database name');
            $table->string('host')->default('localhost')->comment('Database host');
            $table->integer('port')->default(3306)->comment('Database port');
            $table->string('username')->comment('Database username');
            $table->text('password')->comment('Encrypted database password');
            $table->timestamps();
            
            // Indexes
            $table->index('tenant_id');
            $table->unique(['tenant_id', 'connection_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_databases');
    }
};
