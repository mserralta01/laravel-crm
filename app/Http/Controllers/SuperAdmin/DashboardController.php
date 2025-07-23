<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Tenant;
use App\Models\SuperAdmin;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display the super admin dashboard.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Get statistics
        $stats = [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('status', Tenant::STATUS_ACTIVE)->count(),
            'suspended_tenants' => Tenant::where('status', Tenant::STATUS_SUSPENDED)->count(),
            'trial_tenants' => Tenant::whereNotNull('trial_ends_at')
                ->where('trial_ends_at', '>', now())
                ->count(),
            'total_super_admins' => SuperAdmin::count(),
            'recent_activities' => $this->getRecentActivities(),
            'storage_stats' => $this->getStorageStats(),
            'growth_stats' => $this->getGrowthStats(),
            'trial_ending_soon' => Tenant::whereNotNull('trial_ends_at')
                ->where('trial_ends_at', '>', now())
                ->where('trial_ends_at', '<=', now()->addDays(7))
                ->count(),
            'inactive_tenants' => Tenant::where('status', Tenant::STATUS_INACTIVE)->count(),
        ];

        // Get recent tenants
        $recentTenants = Tenant::with('primaryDomain')
            ->latest()
            ->take(5)
            ->get();

        // Get chart data
        $chartData = $this->getChartData();

        return view('super-admin.dashboard.index', compact('stats', 'recentTenants', 'chartData'));
    }

    /**
     * Get recent tenant activities.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getRecentActivities()
    {
        return \DB::table('tenant_activity_logs')
            ->join('tenants', 'tenant_activity_logs.tenant_id', '=', 'tenants.id')
            ->select(
                'tenant_activity_logs.*',
                'tenants.name as tenant_name',
                'tenants.slug as tenant_slug'
            )
            ->orderBy('tenant_activity_logs.created_at', 'desc')
            ->take(10)
            ->get();
    }

    /**
     * Get storage statistics.
     *
     * @return array
     */
    protected function getStorageStats()
    {
        $totalSize = 0;
        $tenantSizes = [];

        // Get database sizes for tenants with separate databases
        $tenantsWithDb = Tenant::has('database')->with('database')->get();
        
        foreach ($tenantsWithDb as $tenant) {
            $size = $tenant->database->getDatabaseSize();
            $totalSize += $size;
            $tenantSizes[$tenant->id] = $size;
        }

        return [
            'total_size' => $totalSize,
            'formatted_size' => $this->formatBytes($totalSize),
            'tenant_sizes' => $tenantSizes,
        ];
    }

    /**
     * Format bytes to human readable format.
     *
     * @param int $bytes
     * @return string
     */
    protected function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        
        return number_format($bytes / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
    }

    /**
     * Get growth statistics for the last 30 days.
     *
     * @return array
     */
    protected function getGrowthStats()
    {
        $thirtyDaysAgo = now()->subDays(30);
        
        return [
            'new_tenants' => Tenant::where('created_at', '>=', $thirtyDaysAgo)->count(),
            'suspended_count' => Tenant::where('status', Tenant::STATUS_SUSPENDED)
                ->where('updated_at', '>=', $thirtyDaysAgo)
                ->count(),
        ];
    }

    /**
     * Get chart data for dashboard visualizations.
     *
     * @return array
     */
    protected function getChartData()
    {
        // Tenant growth over last 7 days
        $tenantGrowth = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $tenantGrowth[] = [
                'date' => $date->format('M d'),
                'count' => Tenant::whereDate('created_at', '<=', $date)->count(),
            ];
        }

        // Status distribution
        $statusDistribution = [
            'active' => Tenant::where('status', Tenant::STATUS_ACTIVE)->count(),
            'suspended' => Tenant::where('status', Tenant::STATUS_SUSPENDED)->count(),
            'inactive' => Tenant::where('status', Tenant::STATUS_INACTIVE)->count(),
        ];

        // Trial status
        $trialStatus = [
            'in_trial' => Tenant::whereNotNull('trial_ends_at')
                ->where('trial_ends_at', '>', now())
                ->count(),
            'trial_expired' => Tenant::whereNotNull('trial_ends_at')
                ->where('trial_ends_at', '<=', now())
                ->count(),
            'no_trial' => Tenant::whereNull('trial_ends_at')->count(),
        ];

        return [
            'tenant_growth' => $tenantGrowth,
            'status_distribution' => $statusDistribution,
            'trial_status' => $trialStatus,
        ];
    }

    /**
     * Get dashboard statistics via AJAX.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats()
    {
        $stats = [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('status', Tenant::STATUS_ACTIVE)->count(),
            'suspended_tenants' => Tenant::where('status', Tenant::STATUS_SUSPENDED)->count(),
            'trial_ending_soon' => Tenant::whereNotNull('trial_ends_at')
                ->where('trial_ends_at', '>', now())
                ->where('trial_ends_at', '<=', now()->addDays(7))
                ->count(),
        ];

        return response()->json($stats);
    }
}