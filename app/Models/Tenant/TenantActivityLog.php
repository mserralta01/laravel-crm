<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TenantActivityLog Model
 * 
 * Audit trail for tenant-level activities.
 * Tracks important actions for security and compliance.
 * 
 * @property int $id
 * @property int $tenant_id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $description
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * 
 * @property-read \App\Models\Tenant\Tenant $tenant
 */
class TenantActivityLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tenant_activity_logs';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'action',
        'description',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Common action types
     */
    const ACTION_TENANT_CREATED = 'tenant.created';
    const ACTION_TENANT_UPDATED = 'tenant.updated';
    const ACTION_TENANT_SUSPENDED = 'tenant.suspended';
    const ACTION_TENANT_ACTIVATED = 'tenant.activated';
    const ACTION_TENANT_DELETED = 'tenant.deleted';
    
    const ACTION_USER_CREATED = 'user.created';
    const ACTION_USER_UPDATED = 'user.updated';
    const ACTION_USER_DELETED = 'user.deleted';
    const ACTION_USER_SUSPENDED = 'user.suspended';
    const ACTION_USER_LOGIN = 'user.login';
    const ACTION_USER_LOGOUT = 'user.logout';
    const ACTION_USER_PASSWORD_RESET = 'user.password_reset';
    
    const ACTION_DOMAIN_CREATED = 'domain.created';
    const ACTION_DOMAIN_UPDATED = 'domain.updated';
    const ACTION_DOMAIN_DELETED = 'domain.deleted';
    const ACTION_DOMAIN_VERIFIED = 'domain.verified';
    
    const ACTION_SETTINGS_UPDATED = 'settings.updated';
    const ACTION_LIMITS_EXCEEDED = 'limits.exceeded';
    const ACTION_BACKUP_CREATED = 'backup.created';
    const ACTION_BACKUP_RESTORED = 'backup.restored';
    
    const ACTION_IMPERSONATION_STARTED = 'impersonation.started';
    const ACTION_IMPERSONATION_ENDED = 'impersonation.ended';

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Set created_at on creating
        static::creating(function ($model) {
            $model->created_at = $model->created_at ?: now();
        });
    }

    /**
     * Get the tenant that owns the activity log.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user that performed the action.
     * Note: This could be a regular user or super admin.
     */
    public function user(): BelongsTo
    {
        // This will need to be updated when we add tenant_id to users table
        return $this->belongsTo(\Webkul\User\Models\User::class);
    }

    /**
     * Scope a query to only include logs for a specific action.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $action
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope a query to only include logs from a specific time period.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Carbon\Carbon $from
     * @param \Carbon\Carbon|null $to
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForPeriod($query, $from, $to = null)
    {
        $query->where('created_at', '>=', $from);
        
        if ($to) {
            $query->where('created_at', '<=', $to);
        }
        
        return $query;
    }

    /**
     * Scope a query to only include logs from a specific IP address.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $ipAddress
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFromIpAddress($query, string $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }

    /**
     * Get a formatted description of the activity.
     *
     * @return string
     */
    public function getFormattedDescription(): string
    {
        if ($this->description) {
            return $this->description;
        }

        // Generate description based on action
        return match ($this->action) {
            self::ACTION_TENANT_CREATED => 'Tenant was created',
            self::ACTION_TENANT_UPDATED => 'Tenant settings were updated',
            self::ACTION_TENANT_SUSPENDED => 'Tenant was suspended',
            self::ACTION_TENANT_ACTIVATED => 'Tenant was activated',
            self::ACTION_USER_LOGIN => 'User logged in',
            self::ACTION_USER_LOGOUT => 'User logged out',
            self::ACTION_DOMAIN_CREATED => 'New domain was added',
            self::ACTION_DOMAIN_VERIFIED => 'Domain was verified',
            self::ACTION_SETTINGS_UPDATED => 'Settings were updated',
            self::ACTION_LIMITS_EXCEEDED => 'Resource limit was exceeded',
            default => ucfirst(str_replace('.', ' ', $this->action)),
        };
    }

    /**
     * Get the action category (first part before dot).
     *
     * @return string
     */
    public function getActionCategory(): string
    {
        return explode('.', $this->action)[0] ?? 'other';
    }

    /**
     * Get the action type (part after dot).
     *
     * @return string
     */
    public function getActionType(): string
    {
        $parts = explode('.', $this->action);
        return $parts[1] ?? $parts[0] ?? 'unknown';
    }

    /**
     * Get metadata value by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getMetadata(string $key, $default = null)
    {
        return data_get($this->metadata, $key, $default);
    }

    /**
     * Check if the activity was performed by a system process.
     *
     * @return bool
     */
    public function isSystemActivity(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Get browser info from user agent.
     *
     * @return array
     */
    public function getBrowserInfo(): array
    {
        if (!$this->user_agent) {
            return [
                'browser' => 'Unknown',
                'version' => '',
                'platform' => 'Unknown',
            ];
        }

        // Simple browser detection (can be enhanced with a proper library)
        $browser = 'Unknown';
        $version = '';
        $platform = 'Unknown';

        // Detect browser
        if (preg_match('/MSIE|Trident/i', $this->user_agent)) {
            $browser = 'Internet Explorer';
        } elseif (preg_match('/Edge/i', $this->user_agent)) {
            $browser = 'Edge';
        } elseif (preg_match('/Firefox/i', $this->user_agent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Chrome/i', $this->user_agent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Safari/i', $this->user_agent)) {
            $browser = 'Safari';
        }

        // Detect platform
        if (preg_match('/Windows/i', $this->user_agent)) {
            $platform = 'Windows';
        } elseif (preg_match('/Mac/i', $this->user_agent)) {
            $platform = 'Mac';
        } elseif (preg_match('/Linux/i', $this->user_agent)) {
            $platform = 'Linux';
        } elseif (preg_match('/Android/i', $this->user_agent)) {
            $platform = 'Android';
        } elseif (preg_match('/iOS|iPhone|iPad/i', $this->user_agent)) {
            $platform = 'iOS';
        }

        return compact('browser', 'version', 'platform');
    }

    /**
     * Create a log entry for tenant creation.
     *
     * @param \App\Models\Tenant\Tenant $tenant
     * @param array $metadata
     * @return static
     */
    public static function logTenantCreated(Tenant $tenant, array $metadata = [])
    {
        return static::create([
            'tenant_id' => $tenant->id,
            'action' => self::ACTION_TENANT_CREATED,
            'description' => "Tenant '{$tenant->name}' was created",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => array_merge($metadata, [
                'tenant_name' => $tenant->name,
                'tenant_slug' => $tenant->slug,
            ]),
        ]);
    }
}