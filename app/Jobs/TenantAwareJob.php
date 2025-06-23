<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\Tenant\Tenant;

abstract class TenantAwareJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    /**
     * The tenant ID for this job
     *
     * @var int|null
     */
    protected $tenantId;
    
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->tenantId = $this->getCurrentTenantId();
    }
    
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->initializeTenantContext();
        
        try {
            $this->handleJob();
        } finally {
            $this->cleanupTenantContext();
        }
    }
    
    /**
     * Initialize tenant context for the job
     *
     * @return void
     */
    protected function initializeTenantContext()
    {
        if ($this->tenantId) {
            $tenant = Tenant::find($this->tenantId);
            
            if ($tenant) {
                app()->instance('tenant', $tenant);
                
                // Set database connection if tenant uses separate database
                if ($tenant->database) {
                    config([
                        'database.connections.tenant' => [
                            'driver'    => 'mysql',
                            'host'      => $tenant->database_host ?: config('database.connections.mysql.host'),
                            'port'      => $tenant->database_port ?: config('database.connections.mysql.port'),
                            'database'  => $tenant->database,
                            'username'  => $tenant->database_username ?: config('database.connections.mysql.username'),
                            'password'  => $tenant->database_password ?: config('database.connections.mysql.password'),
                            'charset'   => config('database.connections.mysql.charset'),
                            'collation' => config('database.connections.mysql.collation'),
                            'prefix'    => config('database.connections.mysql.prefix'),
                            'strict'    => config('database.connections.mysql.strict'),
                            'engine'    => config('database.connections.mysql.engine'),
                        ],
                    ]);
                    
                    // Set default connection to tenant
                    config(['database.default' => 'tenant']);
                }
                
                // Set tenant-specific configurations
                $this->setTenantConfigurations($tenant);
            }
        }
    }
    
    /**
     * Set tenant-specific configurations
     *
     * @param \App\Models\Tenant\Tenant $tenant
     * @return void
     */
    protected function setTenantConfigurations($tenant)
    {
        // Set mail configuration if tenant has custom settings
        if ($tenant->mail_driver) {
            config([
                'mail.default' => $tenant->mail_driver,
                'mail.mailers.smtp.host' => $tenant->mail_host,
                'mail.mailers.smtp.port' => $tenant->mail_port,
                'mail.mailers.smtp.encryption' => $tenant->mail_encryption,
                'mail.mailers.smtp.username' => $tenant->mail_username,
                'mail.mailers.smtp.password' => $tenant->mail_password,
                'mail.from.address' => $tenant->mail_from_address ?: config('mail.from.address'),
                'mail.from.name' => $tenant->mail_from_name ?: config('mail.from.name'),
            ]);
        }
        
        // Set filesystem configuration for tenant isolation
        if ($tenant->storage_disk) {
            config(['filesystems.default' => $tenant->storage_disk]);
        }
        
        // Add tenant ID to storage paths
        $tenantPath = 'tenants/' . $tenant->id;
        config([
            'filesystems.disks.local.root' => storage_path('app/' . $tenantPath),
            'filesystems.disks.public.root' => storage_path('app/public/' . $tenantPath),
        ]);
    }
    
    /**
     * Cleanup tenant context after job execution
     *
     * @return void
     */
    protected function cleanupTenantContext()
    {
        // Reset to default database connection
        config(['database.default' => config('database.connections.mysql')]);
        
        // Clear tenant instance
        app()->forgetInstance('tenant');
    }
    
    /**
     * Get current tenant ID
     *
     * @return int|null
     */
    protected function getCurrentTenantId()
    {
        if (app()->bound('tenant')) {
            return app('tenant')->id;
        }
        
        return null;
    }
    
    /**
     * The actual job logic to be implemented by child classes
     *
     * @return void
     */
    abstract protected function handleJob();
    
    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array
     */
    public function tags()
    {
        $tags = ['tenant'];
        
        if ($this->tenantId) {
            $tags[] = 'tenant:' . $this->tenantId;
        }
        
        return $tags;
    }
}