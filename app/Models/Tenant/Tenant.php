<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Tenant Model
 * 
 * Represents a tenant (customer organization) in the multi-tenant system.
 * Each tenant has their own subdomain, database, and isolated data.
 * 
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string $email
 * @property string|null $phone
 * @property string $status
 * @property \Carbon\Carbon|null $trial_ends_at
 * @property array|null $settings
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @property-read \App\Models\Tenant\TenantDatabase|null $database
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Tenant\TenantDomain[] $domains
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Tenant\TenantSetting[] $settings_records
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Tenant\TenantActivityLog[] $activity_logs
 */
class Tenant extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'email',
        'phone',
        'status',
        'trial_ends_at',
        'settings',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'array',
        'trial_ends_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_INACTIVE = 'inactive';

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Generate UUID on creation
        static::creating(function ($tenant) {
            if (empty($tenant->uuid)) {
                $tenant->uuid = (string) Str::uuid();
            }
            
            // Generate slug from name if not provided
            if (empty($tenant->slug)) {
                $tenant->slug = Str::slug($tenant->name);
                
                // Ensure slug is unique
                $originalSlug = $tenant->slug;
                $count = 1;
                while (static::where('slug', $tenant->slug)->exists()) {
                    $tenant->slug = $originalSlug . '-' . $count++;
                }
            }
        });
    }

    /**
     * Get the database connection for the tenant.
     */
    public function database(): HasOne
    {
        return $this->hasOne(TenantDatabase::class);
    }

    /**
     * Get all domains for the tenant.
     */
    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    /**
     * Get the primary domain for the tenant.
     */
    public function primaryDomain(): HasOne
    {
        return $this->hasOne(TenantDomain::class)->where('is_primary', true);
    }

    /**
     * Get all settings records for the tenant.
     */
    public function settings_records(): HasMany
    {
        return $this->hasMany(TenantSetting::class);
    }

    /**
     * Get all activity logs for the tenant.
     */
    public function activity_logs(): HasMany
    {
        return $this->hasMany(TenantActivityLog::class)->latest();
    }

    /**
     * Check if the tenant is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if the tenant is suspended.
     *
     * @return bool
     */
    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    /**
     * Check if the tenant is in trial period.
     *
     * @return bool
     */
    public function isInTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Check if the tenant's trial has expired.
     *
     * @return bool
     */
    public function isTrialExpired(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Get a specific setting value.
     *
     * @param string $group
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getSetting(string $group, string $key, $default = null)
    {
        $setting = $this->settings_records()
            ->where('group', $group)
            ->where('key', $key)
            ->first();

        if (!$setting) {
            return $default;
        }

        return $this->castSettingValue($setting->value, $setting->type);
    }

    /**
     * Set a specific setting value.
     *
     * @param string $group
     * @param string $key
     * @param mixed $value
     * @param string $type
     * @return void
     */
    public function setSetting(string $group, string $key, $value, string $type = 'text')
    {
        $this->settings_records()->updateOrCreate(
            [
                'group' => $group,
                'key' => $key,
            ],
            [
                'value' => $this->prepareSettingValue($value, $type),
                'type' => $type,
            ]
        );
    }

    /**
     * Get all settings for a specific group.
     *
     * @param string $group
     * @return array
     */
    public function getSettingsGroup(string $group): array
    {
        $settings = $this->settings_records()
            ->where('group', $group)
            ->get();

        $result = [];
        foreach ($settings as $setting) {
            $result[$setting->key] = $this->castSettingValue($setting->value, $setting->type);
        }

        return $result;
    }

    /**
     * Cast setting value based on type.
     *
     * @param string|null $value
     * @param string $type
     * @return mixed
     */
    protected function castSettingValue(?string $value, string $type)
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'number' => is_numeric($value) ? (float) $value : 0,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Prepare setting value for storage.
     *
     * @param mixed $value
     * @param string $type
     * @return string
     */
    protected function prepareSettingValue($value, string $type): string
    {
        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value),
            default => (string) $value,
        };
    }

    /**
     * Log an activity for this tenant.
     *
     * @param string $action
     * @param string|null $description
     * @param array|null $metadata
     * @param int|null $userId
     * @return \App\Models\Tenant\TenantActivityLog
     */
    public function logActivity(string $action, ?string $description = null, ?array $metadata = null, ?int $userId = null)
    {
        return $this->activity_logs()->create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Suspend the tenant.
     *
     * @param string|null $reason
     * @return void
     */
    public function suspend(?string $reason = null)
    {
        $this->update(['status' => self::STATUS_SUSPENDED]);
        
        $this->logActivity(
            'tenant.suspended',
            $reason ?? 'Tenant suspended',
            ['previous_status' => $this->getOriginal('status')]
        );
    }

    /**
     * Activate the tenant.
     *
     * @return void
     */
    public function activate()
    {
        $this->update(['status' => self::STATUS_ACTIVE]);
        
        $this->logActivity(
            'tenant.activated',
            'Tenant activated',
            ['previous_status' => $this->getOriginal('status')]
        );
    }

    /**
     * Get the subdomain URL for the tenant.
     *
     * @return string
     */
    public function getUrl(): string
    {
        $domain = $this->primaryDomain?->domain ?? $this->slug . '.' . config('app.domain', 'localhost');
        $protocol = config('app.force_https', false) ? 'https' : request()->getScheme();
        
        return $protocol . '://' . $domain;
    }
}