<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Tenant;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Http\UploadedFile;

class TenantStorageService
{
    /**
     * Current tenant instance
     *
     * @var \App\Models\Tenant\Tenant|null
     */
    protected $tenant;
    
    /**
     * Base storage path for tenants
     *
     * @var string
     */
    protected $basePath = 'tenants';
    
    /**
     * Create a new service instance
     *
     * @param \App\Models\Tenant\Tenant|null $tenant
     */
    public function __construct(Tenant $tenant = null)
    {
        $this->tenant = $tenant ?: app()->bound('tenant') ? app('tenant') : null;
    }
    
    /**
     * Get tenant storage path
     *
     * @param string $path
     * @return string
     */
    public function getTenantPath($path = '')
    {
        if (!$this->tenant) {
            return $path;
        }
        
        $tenantPath = $this->basePath . '/' . $this->tenant->id;
        
        return $path ? $tenantPath . '/' . ltrim($path, '/') : $tenantPath;
    }
    
    /**
     * Store a file in tenant's storage
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $directory
     * @param string $disk
     * @return string|false
     */
    public function store(UploadedFile $file, $directory = '', $disk = 'public')
    {
        $path = $this->getTenantPath($directory);
        
        return $file->store($path, $disk);
    }
    
    /**
     * Store a file with a specific name
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $name
     * @param string $directory
     * @param string $disk
     * @return string|false
     */
    public function storeAs(UploadedFile $file, $name, $directory = '', $disk = 'public')
    {
        $path = $this->getTenantPath($directory);
        
        return $file->storeAs($path, $name, $disk);
    }
    
    /**
     * Get file from tenant's storage
     *
     * @param string $path
     * @param string $disk
     * @return string|null
     */
    public function get($path, $disk = 'public')
    {
        $fullPath = $this->getTenantPath($path);
        
        if (Storage::disk($disk)->exists($fullPath)) {
            return Storage::disk($disk)->get($fullPath);
        }
        
        return null;
    }
    
    /**
     * Check if file exists in tenant's storage
     *
     * @param string $path
     * @param string $disk
     * @return bool
     */
    public function exists($path, $disk = 'public')
    {
        $fullPath = $this->getTenantPath($path);
        
        return Storage::disk($disk)->exists($fullPath);
    }
    
    /**
     * Delete file from tenant's storage
     *
     * @param string $path
     * @param string $disk
     * @return bool
     */
    public function delete($path, $disk = 'public')
    {
        $fullPath = $this->getTenantPath($path);
        
        return Storage::disk($disk)->delete($fullPath);
    }
    
    /**
     * Get URL for a file in tenant's storage
     *
     * @param string $path
     * @param string $disk
     * @return string
     */
    public function url($path, $disk = 'public')
    {
        $fullPath = $this->getTenantPath($path);
        
        return Storage::disk($disk)->url($fullPath);
    }
    
    /**
     * Create tenant storage directories
     *
     * @param string $disk
     * @return void
     */
    public function createTenantDirectories($disk = 'public')
    {
        if (!$this->tenant) {
            return;
        }
        
        $directories = [
            $this->getTenantPath(),
            $this->getTenantPath('images'),
            $this->getTenantPath('documents'),
            $this->getTenantPath('exports'),
            $this->getTenantPath('imports'),
            $this->getTenantPath('email-attachments'),
            $this->getTenantPath('avatars'),
            $this->getTenantPath('logos'),
        ];
        
        foreach ($directories as $directory) {
            Storage::disk($disk)->makeDirectory($directory);
        }
    }
    
    /**
     * Copy file to tenant storage
     *
     * @param string $sourcePath
     * @param string $destinationPath
     * @param string $disk
     * @return bool
     */
    public function copy($sourcePath, $destinationPath, $disk = 'public')
    {
        $fullDestinationPath = $this->getTenantPath($destinationPath);
        
        return Storage::disk($disk)->copy($sourcePath, $fullDestinationPath);
    }
    
    /**
     * Move file to tenant storage
     *
     * @param string $sourcePath
     * @param string $destinationPath
     * @param string $disk
     * @return bool
     */
    public function move($sourcePath, $destinationPath, $disk = 'public')
    {
        $fullDestinationPath = $this->getTenantPath($destinationPath);
        
        return Storage::disk($disk)->move($sourcePath, $fullDestinationPath);
    }
    
    /**
     * Get all files in a directory
     *
     * @param string $directory
     * @param string $disk
     * @return array
     */
    public function files($directory = '', $disk = 'public')
    {
        $path = $this->getTenantPath($directory);
        
        return Storage::disk($disk)->files($path);
    }
    
    /**
     * Get all directories in a path
     *
     * @param string $directory
     * @param string $disk
     * @return array
     */
    public function directories($directory = '', $disk = 'public')
    {
        $path = $this->getTenantPath($directory);
        
        return Storage::disk($disk)->directories($path);
    }
    
    /**
     * Get tenant storage size in bytes
     *
     * @param string $disk
     * @return int
     */
    public function getTenantStorageSize($disk = 'public')
    {
        if (!$this->tenant) {
            return 0;
        }
        
        $path = $this->getTenantPath();
        $size = 0;
        
        $files = Storage::disk($disk)->allFiles($path);
        
        foreach ($files as $file) {
            $size += Storage::disk($disk)->size($file);
        }
        
        return $size;
    }
    
    /**
     * Get tenant storage size formatted
     *
     * @param string $disk
     * @return string
     */
    public function getTenantStorageSizeFormatted($disk = 'public')
    {
        $bytes = $this->getTenantStorageSize($disk);
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(($bytes ? log($bytes) : 0) / log(1024));
        $power = min($power, count($units) - 1);
        
        $bytes /= pow(1024, $power);
        
        return round($bytes, 2) . ' ' . $units[$power];
    }
    
    /**
     * Clean up old temporary files
     *
     * @param int $daysOld
     * @param string $disk
     * @return int
     */
    public function cleanupOldFiles($daysOld = 7, $disk = 'public')
    {
        if (!$this->tenant) {
            return 0;
        }
        
        $tempPath = $this->getTenantPath('temp');
        $files = Storage::disk($disk)->files($tempPath);
        $deletedCount = 0;
        
        foreach ($files as $file) {
            $lastModified = Storage::disk($disk)->lastModified($file);
            $daysAgo = (time() - $lastModified) / 86400;
            
            if ($daysAgo > $daysOld) {
                Storage::disk($disk)->delete($file);
                $deletedCount++;
            }
        }
        
        return $deletedCount;
    }
}