<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TenantSetting Model
 * 
 * Key-value store for tenant-specific settings.
 * Supports different data types and grouped settings.
 * 
 * @property int $id
 * @property int $tenant_id
 * @property string $group
 * @property string $key
 * @property string|null $value
 * @property string $type
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @property-read \App\Models\Tenant\Tenant $tenant
 */
class TenantSetting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'tenant_id',
        'group',
        'key',
        'value',
        'type',
    ];

    /**
     * Available setting types
     */
    const TYPE_TEXT = 'text';
    const TYPE_NUMBER = 'number';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_JSON = 'json';

    /**
     * Common setting groups
     */
    const GROUP_LIMITS = 'limits';
    const GROUP_FEATURES = 'features';
    const GROUP_BRANDING = 'branding';
    const GROUP_EMAIL = 'email';
    const GROUP_SECURITY = 'security';
    const GROUP_API = 'api';

    /**
     * Default settings structure
     */
    const DEFAULT_SETTINGS = [
        self::GROUP_LIMITS => [
            'max_users' => ['value' => 10, 'type' => self::TYPE_NUMBER],
            'max_storage_gb' => ['value' => 10, 'type' => self::TYPE_NUMBER],
            'max_leads' => ['value' => 1000, 'type' => self::TYPE_NUMBER],
            'max_contacts' => ['value' => 5000, 'type' => self::TYPE_NUMBER],
            'max_products' => ['value' => 500, 'type' => self::TYPE_NUMBER],
            'max_email_per_day' => ['value' => 500, 'type' => self::TYPE_NUMBER],
            'api_rate_limit_per_hour' => ['value' => 1000, 'type' => self::TYPE_NUMBER],
        ],
        self::GROUP_FEATURES => [
            'email_integration' => ['value' => true, 'type' => self::TYPE_BOOLEAN],
            'workflow_automation' => ['value' => true, 'type' => self::TYPE_BOOLEAN],
            'web_forms' => ['value' => true, 'type' => self::TYPE_BOOLEAN],
            'custom_fields' => ['value' => true, 'type' => self::TYPE_BOOLEAN],
            'api_access' => ['value' => true, 'type' => self::TYPE_BOOLEAN],
            'export_import' => ['value' => true, 'type' => self::TYPE_BOOLEAN],
            'email_templates' => ['value' => true, 'type' => self::TYPE_BOOLEAN],
            'activity_tracking' => ['value' => true, 'type' => self::TYPE_BOOLEAN],
        ],
        self::GROUP_SECURITY => [
            'ip_whitelist' => ['value' => [], 'type' => self::TYPE_JSON],
            'two_factor_auth' => ['value' => false, 'type' => self::TYPE_BOOLEAN],
            'password_policy' => ['value' => 'medium', 'type' => self::TYPE_TEXT],
            'session_timeout_minutes' => ['value' => 60, 'type' => self::TYPE_NUMBER],
            'max_login_attempts' => ['value' => 5, 'type' => self::TYPE_NUMBER],
        ],
    ];

    /**
     * Get the tenant that owns the setting.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the typed value.
     *
     * @return mixed
     */
    public function getTypedValue()
    {
        if ($this->value === null) {
            return null;
        }

        return match ($this->type) {
            self::TYPE_NUMBER => is_numeric($this->value) ? (float) $this->value : 0,
            self::TYPE_BOOLEAN => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            self::TYPE_JSON => json_decode($this->value, true),
            default => $this->value,
        };
    }

    /**
     * Set the typed value.
     *
     * @param mixed $value
     * @return void
     */
    public function setTypedValue($value)
    {
        $this->value = match ($this->type) {
            self::TYPE_BOOLEAN => $value ? '1' : '0',
            self::TYPE_JSON => json_encode($value),
            default => (string) $value,
        };
    }

    /**
     * Create default settings for a tenant.
     *
     * @param \App\Models\Tenant\Tenant $tenant
     * @return void
     */
    public static function createDefaultSettings(Tenant $tenant)
    {
        foreach (self::DEFAULT_SETTINGS as $group => $settings) {
            foreach ($settings as $key => $config) {
                $tenant->setSetting($group, $key, $config['value'], $config['type']);
            }
        }
    }

    /**
     * Get all settings for export.
     *
     * @param \App\Models\Tenant\Tenant $tenant
     * @return array
     */
    public static function exportSettings(Tenant $tenant): array
    {
        $settings = $tenant->settings_records()
            ->orderBy('group')
            ->orderBy('key')
            ->get();

        $export = [];
        foreach ($settings as $setting) {
            if (!isset($export[$setting->group])) {
                $export[$setting->group] = [];
            }
            $export[$setting->group][$setting->key] = [
                'value' => $setting->getTypedValue(),
                'type' => $setting->type,
            ];
        }

        return $export;
    }

    /**
     * Import settings for a tenant.
     *
     * @param \App\Models\Tenant\Tenant $tenant
     * @param array $settings
     * @return void
     */
    public static function importSettings(Tenant $tenant, array $settings)
    {
        foreach ($settings as $group => $groupSettings) {
            foreach ($groupSettings as $key => $config) {
                $tenant->setSetting(
                    $group,
                    $key,
                    $config['value'],
                    $config['type'] ?? self::TYPE_TEXT
                );
            }
        }
    }

    /**
     * Validate setting value based on type and constraints.
     *
     * @param mixed $value
     * @param array $constraints
     * @return bool
     */
    public function validateValue($value, array $constraints = []): bool
    {
        switch ($this->type) {
            case self::TYPE_NUMBER:
                if (!is_numeric($value)) {
                    return false;
                }
                $numValue = (float) $value;
                if (isset($constraints['min']) && $numValue < $constraints['min']) {
                    return false;
                }
                if (isset($constraints['max']) && $numValue > $constraints['max']) {
                    return false;
                }
                break;

            case self::TYPE_BOOLEAN:
                if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false'])) {
                    return false;
                }
                break;

            case self::TYPE_JSON:
                if (is_string($value)) {
                    json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return false;
                    }
                } elseif (!is_array($value) && !is_object($value)) {
                    return false;
                }
                break;

            case self::TYPE_TEXT:
                if (isset($constraints['max_length']) && strlen($value) > $constraints['max_length']) {
                    return false;
                }
                if (isset($constraints['allowed_values']) && !in_array($value, $constraints['allowed_values'])) {
                    return false;
                }
                break;
        }

        return true;
    }
}