<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * SuperAdmin Model
 * 
 * Super administrators who can manage all tenants and system settings.
 * Separate from regular users for complete isolation.
 * 
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property bool $status
 * @property \Carbon\Carbon|null $last_login_at
 * @property string|null $remember_token
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class SuperAdmin extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'super_admin_users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    /**
     * Check if the super admin is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === true;
    }

    /**
     * Update last login timestamp.
     *
     * @return void
     */
    public function updateLastLogin()
    {
        $this->update(['last_login_at' => now()]);
    }

    /**
     * Log activity as super admin.
     *
     * @param int $tenantId
     * @param string $action
     * @param string|null $description
     * @param array|null $metadata
     * @return \App\Models\Tenant\TenantActivityLog
     */
    public function logTenantActivity(int $tenantId, string $action, ?string $description = null, ?array $metadata = null)
    {
        return \App\Models\Tenant\TenantActivityLog::create([
            'tenant_id' => $tenantId,
            'user_id' => null, // Super admin activities are tracked differently
            'action' => $action,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => array_merge($metadata ?? [], [
                'super_admin_id' => $this->id,
                'super_admin_email' => $this->email,
            ]),
        ]);
    }

    /**
     * Get impersonation token for a tenant.
     *
     * @param \App\Models\Tenant\Tenant $tenant
     * @return string
     */
    public function getImpersonationToken(\App\Models\Tenant\Tenant $tenant): string
    {
        $payload = [
            'super_admin_id' => $this->id,
            'tenant_id' => $tenant->id,
            'timestamp' => time(),
            'expires_at' => time() + 3600, // 1 hour expiry
        ];

        return encrypt($payload);
    }

    /**
     * Verify impersonation token.
     *
     * @param string $token
     * @return array|null
     */
    public static function verifyImpersonationToken(string $token): ?array
    {
        try {
            $payload = decrypt($token);
            
            // Check expiry
            if ($payload['expires_at'] < time()) {
                return null;
            }

            // Verify super admin exists and is active
            $superAdmin = static::find($payload['super_admin_id']);
            if (!$superAdmin || !$superAdmin->isActive()) {
                return null;
            }

            return $payload;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get display name with role indicator.
     *
     * @return string
     */
    public function getDisplayName(): string
    {
        return $this->name . ' (Super Admin)';
    }

    /**
     * Check if super admin can access tenant.
     * 
     * @param \App\Models\Tenant\Tenant $tenant
     * @return bool
     */
    public function canAccessTenant(\App\Models\Tenant\Tenant $tenant): bool
    {
        // Super admins can access all tenants by default
        // This method exists for future permission granularity
        return $this->isActive();
    }

    /**
     * Get the guard name for the model.
     *
     * @return string
     */
    public function guardName(): string
    {
        return 'super-admin';
    }
}