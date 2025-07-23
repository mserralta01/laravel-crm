<?php

namespace App\DataGrids;

use Webkul\DataGrid\DataGrid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

abstract class TenantAwareDataGrid extends DataGrid
{
    /**
     * Override prepareActions to apply tenant filtering after query is built
     */
    protected function prepareColumns()
    {
        parent::prepareColumns();
        
        // Apply tenant filtering after the query builder is set up
        if ($this->queryBuilder && $tenantId = $this->getCurrentTenantId()) {
            $this->addTenantFilter($tenantId);
        }
    }
    
    /**
     * Add tenant filter to the query
     *
     * @param int $tenantId
     * @return void
     */
    protected function addTenantFilter($tenantId)
    {
        $primaryTable = $this->getPrimaryTable();
        
        if ($primaryTable && $this->tableHasTenantId($primaryTable)) {
            $this->queryBuilder->where($primaryTable . '.tenant_id', $tenantId);
        }
        
        $this->filterJoinedTables($tenantId);
    }
    
    /**
     * Filter joined tables for tenant
     *
     * @param int $tenantId
     * @return void
     */
    protected function filterJoinedTables($tenantId)
    {
        $query = $this->queryBuilder->getQuery();
        
        if (property_exists($query, 'joins') && $query->joins) {
            foreach ($query->joins as $join) {
                $table = $join->table;
                
                if (is_string($table) && $this->tableHasTenantId($table)) {
                    $join->where($table . '.tenant_id', '=', $tenantId);
                }
            }
        }
    }
    
    /**
     * Get the primary table name from the query
     *
     * @return string|null
     */
    protected function getPrimaryTable()
    {
        $query = $this->queryBuilder->getQuery();
        
        if (property_exists($query, 'from')) {
            return $query->from;
        }
        
        return null;
    }
    
    /**
     * Check if table has tenant_id column
     *
     * @param string $table
     * @return bool
     */
    protected function tableHasTenantId($table)
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
     * Override prepareActions to ensure tenant context in actions
     */
    protected function prepareActions()
    {
        parent::prepareActions();
        
        if ($tenantId = $this->getCurrentTenantId()) {
            foreach ($this->massActions as &$action) {
                if (isset($action['url'])) {
                    $action['url'] = $this->ensureTenantUrl($action['url']);
                }
            }
        }
    }
    
    /**
     * Ensure URL includes tenant context
     *
     * @param string $url
     * @return string
     */
    protected function ensureTenantUrl($url)
    {
        if (function_exists('tenant_url')) {
            return tenant_url($url);
        }
        
        return $url;
    }
    
    /**
     * Override export functionality to respect tenant boundaries
     */
    public function export()
    {
        if ($tenantId = $this->getCurrentTenantId()) {
            $this->addTenantFilter($tenantId);
        }
        
        return parent::export();
    }
}