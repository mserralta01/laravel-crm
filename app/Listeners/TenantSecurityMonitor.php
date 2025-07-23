<?php

namespace App\Listeners;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;
use App\Facades\Tenant;

/**
 * TenantSecurityMonitor
 * 
 * Monitors database queries for potential cross-tenant access attempts.
 * Logs suspicious queries that might bypass tenant isolation.
 */
class TenantSecurityMonitor
{
    /**
     * Tables that should always be filtered by tenant_id
     */
    protected $tenantTables = [
        'leads', 'persons', 'organizations', 'quotes', 'quote_items',
        'products', 'product_inventories', 'emails', 'email_attachments',
        'activities', 'activity_files', 'activity_participants',
        'users', 'groups', 'roles', 'tags', 'workflows', 'webhooks',
        'email_templates', 'web_forms', 'campaigns', 'events',
        'imports', 'import_batches', 'warehouses', 'attributes',
    ];

    /**
     * Handle the query executed event.
     *
     * @param  \Illuminate\Database\Events\QueryExecuted  $event
     * @return void
     */
    public function handle(QueryExecuted $event)
    {
        // Only monitor in production or if explicitly enabled
        if (!config('tenant.monitor_queries', false) && !app()->environment('production')) {
            return;
        }

        $sql = strtolower($event->sql);
        $bindings = $event->bindings;
        
        // Skip if no tenant context
        if (!Tenant::hasTenant()) {
            return;
        }

        $currentTenantId = Tenant::currentId();

        // Check each tenant table
        foreach ($this->tenantTables as $table) {
            if ($this->queryAccessesTable($sql, $table)) {
                // Check if query includes tenant_id filter
                if (!$this->queryHasTenantFilter($sql, $table, $currentTenantId, $bindings)) {
                    $this->logSuspiciousQuery($event, $table, $currentTenantId);
                }
            }
        }
    }

    /**
     * Check if query accesses a specific table.
     */
    protected function queryAccessesTable(string $sql, string $table): bool
    {
        // Check for various query patterns
        $patterns = [
            "from `?{$table}`?",
            "join `?{$table}`?",
            "update `?{$table}`?",
            "into `?{$table}`?",
            "from {$table}",
            "join {$table}",
        ];

        foreach ($patterns as $pattern) {
            if (preg_match("/{$pattern}/i", $sql)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if query has tenant_id filter.
     */
    protected function queryHasTenantFilter(string $sql, string $table, int $tenantId, array $bindings): bool
    {
        // Check for tenant_id in WHERE clause
        $patterns = [
            "`?{$table}`?\.`?tenant_id`?\s*=",
            "tenant_id\s*=",
            "where.*tenant_id",
        ];

        foreach ($patterns as $pattern) {
            if (preg_match("/{$pattern}/i", $sql)) {
                // Check if the tenant_id value matches current tenant
                if (in_array($tenantId, $bindings)) {
                    return true;
                }
            }
        }

        // Check for queries that might be using withoutTenant scope
        if (str_contains($sql, '/* without-tenant */')) {
            return true; // Explicitly marked as admin query
        }

        return false;
    }

    /**
     * Log suspicious query.
     */
    protected function logSuspiciousQuery(QueryExecuted $event, string $table, int $tenantId): void
    {
        $context = [
            'table' => $table,
            'tenant_id' => $tenantId,
            'sql' => $event->sql,
            'bindings' => $event->bindings,
            'time' => $event->time,
            'connection' => $event->connectionName,
            'user_id' => auth()->id(),
            'url' => request()->fullUrl(),
            'ip' => request()->ip(),
            'trace' => $this->getQueryTrace(),
        ];

        Log::channel('tenant_security')->warning(
            "Potential cross-tenant query detected on table: {$table}",
            $context
        );

        // In production, you might want to alert immediately
        if (app()->environment('production')) {
            // Send alert to security team
            // dispatch(new SecurityAlertJob($context));
        }
    }

    /**
     * Get a simplified stack trace for the query.
     */
    protected function getQueryTrace(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        
        return collect($trace)
            ->map(function ($frame) {
                return [
                    'file' => $frame['file'] ?? 'unknown',
                    'line' => $frame['line'] ?? 0,
                    'function' => $frame['function'] ?? 'unknown',
                    'class' => $frame['class'] ?? null,
                ];
            })
            ->filter(function ($frame) {
                // Filter out framework internals
                return !str_contains($frame['file'], 'vendor/');
            })
            ->take(5)
            ->values()
            ->toArray();
    }
}