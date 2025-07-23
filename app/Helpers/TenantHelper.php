<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

/**
 * TenantHelper
 * 
 * Provides safe database query methods that automatically apply tenant filtering.
 * Use these methods instead of direct DB::table() calls.
 */
class TenantHelper
{
    /**
     * Get a tenant-scoped query builder for a table.
     * 
     * @param string $table
     * @return \Illuminate\Database\Query\Builder
     * @throws \RuntimeException
     */
    public static function table(string $table)
    {
        $tenantId = static::getCurrentTenantId();
        
        if (!$tenantId) {
            throw new \RuntimeException('No tenant context set. Cannot query without tenant.');
        }
        
        $query = DB::table($table);
        
        // Only add tenant filter if table has tenant_id column
        if (static::tableHasTenantId($table)) {
            $query->where($table . '.tenant_id', $tenantId);
        }
        
        return $query;
    }
    
    /**
     * Get current tenant ID.
     * 
     * @return int|null
     */
    public static function getCurrentTenantId(): ?int
    {
        if (app()->bound('tenant.id')) {
            return app('tenant.id');
        }
        
        if (app()->bound('tenant')) {
            return app('tenant')->id;
        }
        
        return null;
    }
    
    /**
     * Check if a table has tenant_id column.
     * 
     * @param string $table
     * @return bool
     */
    protected static function tableHasTenantId(string $table): bool
    {
        static $cache = [];
        
        if (!isset($cache[$table])) {
            try {
                $cache[$table] = DB::getSchemaBuilder()->hasColumn($table, 'tenant_id');
            } catch (\Exception $e) {
                $cache[$table] = false;
            }
        }
        
        return $cache[$table];
    }
    
    /**
     * Wrap a raw SQL query with tenant filtering.
     * 
     * @param string $sql
     * @param array $bindings
     * @return \Illuminate\Support\Collection
     */
    public static function select(string $sql, array $bindings = [])
    {
        $tenantId = static::getCurrentTenantId();
        
        if (!$tenantId) {
            throw new \RuntimeException('No tenant context set. Cannot query without tenant.');
        }
        
        // Add tenant_id to bindings if SQL contains :tenant_id placeholder
        if (strpos($sql, ':tenant_id') !== false) {
            $bindings['tenant_id'] = $tenantId;
        }
        
        return collect(DB::select($sql, $bindings));
    }
    
    /**
     * Create a helper function for common tenant queries.
     * 
     * @param string $table
     * @param array $conditions
     * @return \Illuminate\Support\Collection
     */
    public static function where(string $table, array $conditions)
    {
        $query = static::table($table);
        
        foreach ($conditions as $column => $value) {
            $query->where($column, $value);
        }
        
        return $query->get();
    }
}