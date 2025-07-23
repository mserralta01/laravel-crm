<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Webkul\User\Models\User;
use Webkul\User\Models\Role;
use Webkul\User\Models\Group;

class TenantController extends Controller
{

    /**
     * Display a listing of tenants.
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $query = Tenant::with(['primaryDomain', 'database']);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Trial filter
        if ($request->filled('trial')) {
            if ($request->trial === 'active') {
                $query->whereNotNull('trial_ends_at')
                      ->where('trial_ends_at', '>', now());
            } elseif ($request->trial === 'expired') {
                $query->whereNotNull('trial_ends_at')
                      ->where('trial_ends_at', '<=', now());
            }
        }

        $tenants = $query->latest()->paginate(20);

        return view('super-admin.tenants.index', compact('tenants'));
    }

    /**
     * Show the form for creating a new tenant.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('super-admin.tenants.create');
    }

    /**
     * Store a newly created tenant.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'trial_days' => 'nullable|integer|min:0|max:365',
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255',
            'admin_password' => 'required|string|min:8|confirmed',
        ]);

        try {
            DB::beginTransaction();

            // Generate unique slug from company name
            $baseSlug = \Str::slug($validated['name']);
            $slug = $baseSlug;
            $counter = 1;
            
            while (Tenant::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }
            
            // Create tenant
            $tenant = Tenant::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'slug' => $slug,
                'status' => Tenant::STATUS_ACTIVE,
                'trial_ends_at' => $validated['trial_days'] ? now()->addDays($validated['trial_days']) : null,
            ]);

            // Get or create admin role
            $adminRole = Role::firstOrCreate(
                ['name' => 'Administrator'],
                [
                    'description' => 'Administrator role has all permissions',
                    'permission_type' => 'all',
                ]
            );

            // Get or create default group
            $defaultGroup = Group::firstOrCreate(
                ['name' => 'General'],
                ['description' => 'General group']
            );

            // Create admin user for the tenant
            $adminUser = User::create([
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'password' => Hash::make($validated['admin_password']),
                'role_id' => $adminRole->id,
                'status' => 1,
                'tenant_id' => $tenant->id,
            ]);

            // Attach to default group
            $adminUser->groups()->attach($defaultGroup->id);

            DB::commit();

            return redirect()
                ->route('super-admin.tenants.show', $tenant)
                ->with('success', 'Tenant created successfully. Admin user credentials have been set up.');
        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()
                ->withInput()
                ->with('error', 'Failed to create tenant: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified tenant.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @return \Illuminate\View\View
     */
    public function show(Tenant $tenant)
    {
        $recentActivity = $tenant->activity_logs()->latest()->take(5)->get();

        // Get tenant statistics by temporarily setting tenant context
        $stats = [];
        \App\Traits\BelongsToTenant::withTenant($tenant, function () use (&$stats) {
            $stats = [
                'users' => \Webkul\User\Models\User::count(),
                'leads' => \Webkul\Lead\Models\Lead::count(),
                'contacts' => \Webkul\Contact\Models\Person::count() + \Webkul\Contact\Models\Organization::count(),
                'quotes' => \Webkul\Quote\Models\Quote::count(),
            ];
        });

        return view('super-admin.tenants.show', compact('tenant', 'stats', 'recentActivity'));
    }

    /**
     * Show the form for editing the tenant.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @return \Illuminate\View\View
     */
    public function edit(Tenant $tenant)
    {
        return view('super-admin.tenants.edit', compact('tenant'));
    }

    /**
     * Update the specified tenant.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'status' => ['required', Rule::in([
                Tenant::STATUS_ACTIVE,
                Tenant::STATUS_SUSPENDED,
                Tenant::STATUS_INACTIVE
            ])],
            'trial_ends_at' => 'nullable|date',
        ]);

        try {
            $tenant->update($validated);

            // Log status change
            if ($tenant->wasChanged('status')) {
                $tenant->logActivity(
                    'tenant.status_changed',
                    "Status changed from {$tenant->getOriginal('status')} to {$tenant->status}",
                    ['changed_by' => auth('super-admin')->user()->email]
                );
            }

            return redirect()
                ->route('super-admin.tenants.show', $tenant)
                ->with('success', 'Tenant updated successfully.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', 'Failed to update tenant: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified tenant.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Tenant $tenant)
    {
        try {
            DB::beginTransaction();

            // Delete all users associated with the tenant
            User::where('tenant_id', $tenant->id)->delete();

            // Delete the tenant
            $tenant->delete();

            DB::commit();

            return redirect()
                ->route('super-admin.tenants.index')
                ->with('success', 'Tenant deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->with('error', 'Failed to delete tenant: ' . $e->getMessage());
        }
    }

    /**
     * Suspend a tenant.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @return \Illuminate\Http\RedirectResponse
     */
    public function suspend(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $reason = $validated['reason'] ?? 'Suspended by super admin';
            $tenant->suspend($reason);

            return redirect()
                ->route('super-admin.tenants.show', $tenant)
                ->with('success', 'Tenant suspended successfully.');
        } catch (\Exception $e) {
            return back()
                ->with('error', 'Failed to suspend tenant: ' . $e->getMessage());
        }
    }

    /**
     * Activate a tenant.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @return \Illuminate\Http\RedirectResponse
     */
    public function activate(Tenant $tenant)
    {
        try {
            $tenant->activate();

            return redirect()
                ->route('super-admin.tenants.show', $tenant)
                ->with('success', 'Tenant activated successfully.');
        } catch (\Exception $e) {
            return back()
                ->with('error', 'Failed to activate tenant: ' . $e->getMessage());
        }
    }

    /**
     * Impersonate a tenant.
     *
     * @param  \App\Models\Tenant\Tenant  $tenant
     * @return \Illuminate\Http\RedirectResponse
     */
    public function impersonate(Tenant $tenant)
    {
        if (!$tenant->isActive()) {
            return back()->with('error', 'Cannot impersonate inactive tenant.');
        }

        // Get the first admin user of the tenant
        $tenantAdmin = \Webkul\User\Models\User::where('tenant_id', $tenant->id)
            ->whereHas('role', function ($query) {
                $query->where('permission_type', 'all')
                    ->orWhere('name', 'Administrator');
            })
            ->first();

        if (!$tenantAdmin) {
            return back()->with('error', 'No admin user found for this tenant.');
        }

        // Store impersonation data in session
        session([
            'impersonation' => [
                'super_admin_id' => auth()->guard('super-admin')->id(),
                'tenant_id' => $tenant->id,
                'started_at' => now()->toDateTimeString(),
            ]
        ]);

        // Log impersonation
        $tenant->logActivity(
            'tenant.impersonated',
            'Tenant impersonated by super admin',
            ['super_admin' => auth()->guard('super-admin')->user()->email]
        );

        // Log out from super admin and log in as tenant admin
        auth()->guard('super-admin')->logout();
        auth()->guard('user')->login($tenantAdmin);

        // Redirect to tenant admin dashboard
        return redirect('/admin/dashboard')
            ->with('warning', 'You are now impersonating ' . $tenant->name . '. To end impersonation, click "End Impersonation" in the header.');
    }
}