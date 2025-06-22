<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('core_config')) {
            Schema::table('core_config', function (Blueprint $table) {
                // Add tenant_id column
                $table->uuid('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
                
                // Add is_global flag to identify which settings are global vs tenant-specific
                $table->boolean('is_global')->default(false)->after('tenant_id');
                
                // Update unique constraint to include tenant_id
                // First check if there's an existing unique constraint
                try {
                    $table->dropUnique(['code']);
                } catch (\Exception $e) {
                    // Constraint might not exist or have a different name
                }
                
                // Add composite unique constraint
                $table->unique(['tenant_id', 'code'], 'core_config_tenant_code_unique');
                
                // Add foreign key
                $table->foreign('tenant_id')
                    ->references('id')
                    ->on('tenants')
                    ->onDelete('cascade');
            });
            
            // Mark certain configurations as global
            $this->markGlobalConfigurations();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('core_config')) {
            Schema::table('core_config', function (Blueprint $table) {
                // Drop foreign key
                $table->dropForeign(['tenant_id']);
                
                // Drop unique constraint
                $table->dropUnique('core_config_tenant_code_unique');
                
                // Drop columns
                $table->dropColumn(['tenant_id', 'is_global']);
                
                // Restore original unique constraint if needed
                $table->unique(['code']);
            });
        }
    }

    /**
     * Mark certain configurations as global (shared across all tenants).
     *
     * @return void
     */
    protected function markGlobalConfigurations()
    {
        $globalConfigs = [
            // System-wide settings that should be shared
            'general.locale_options',
            'general.timezone_options',
            'general.country_options',
            'general.state_options',
            'general.currency_options',
            
            // Email driver settings (if using same email server for all)
            'emails.mail_driver',
            'emails.smtp_host',
            'emails.smtp_port',
            'emails.smtp_encryption',
            
            // System limits that apply to all tenants
            'general.max_file_size',
            'general.allowed_file_types',
        ];
        
        DB::table('core_config')
            ->whereIn('code', $globalConfigs)
            ->update(['is_global' => true]);
    }
};