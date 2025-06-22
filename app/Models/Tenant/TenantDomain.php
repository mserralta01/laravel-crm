<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TenantDomain Model
 * 
 * Manages domains and subdomains for each tenant.
 * Supports custom domain mapping and SSL verification.
 * 
 * @property int $id
 * @property int $tenant_id
 * @property string $domain
 * @property bool $is_primary
 * @property bool $is_verified
 * @property \Carbon\Carbon|null $verified_at
 * @property bool $ssl_enabled
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @property-read \App\Models\Tenant\Tenant $tenant
 */
class TenantDomain extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'tenant_id',
        'domain',
        'is_primary',
        'is_verified',
        'verified_at',
        'ssl_enabled',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_primary' => 'boolean',
        'is_verified' => 'boolean',
        'ssl_enabled' => 'boolean',
        'verified_at' => 'datetime',
    ];

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // When setting a domain as primary, unset other primary domains
        static::saving(function ($domain) {
            if ($domain->is_primary && $domain->isDirty('is_primary')) {
                static::where('tenant_id', $domain->tenant_id)
                    ->where('id', '!=', $domain->id)
                    ->update(['is_primary' => false]);
            }
        });

        // Log domain changes
        static::created(function ($domain) {
            $domain->tenant->logActivity(
                'domain.created',
                "Domain {$domain->domain} added",
                ['domain' => $domain->domain]
            );
        });

        static::deleted(function ($domain) {
            $domain->tenant->logActivity(
                'domain.deleted',
                "Domain {$domain->domain} removed",
                ['domain' => $domain->domain]
            );
        });
    }

    /**
     * Get the tenant that owns the domain.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Mark the domain as verified.
     *
     * @return void
     */
    public function markAsVerified()
    {
        $this->update([
            'is_verified' => true,
            'verified_at' => now(),
        ]);

        $this->tenant->logActivity(
            'domain.verified',
            "Domain {$this->domain} verified",
            ['domain' => $this->domain]
        );
    }

    /**
     * Mark the domain as unverified.
     *
     * @return void
     */
    public function markAsUnverified()
    {
        $this->update([
            'is_verified' => false,
            'verified_at' => null,
        ]);

        $this->tenant->logActivity(
            'domain.unverified',
            "Domain {$this->domain} unverified",
            ['domain' => $this->domain]
        );
    }

    /**
     * Set this domain as primary.
     *
     * @return void
     */
    public function setAsPrimary()
    {
        $this->update(['is_primary' => true]);
    }

    /**
     * Get the full URL for this domain.
     *
     * @param string $path
     * @return string
     */
    public function getUrl(string $path = ''): string
    {
        $protocol = $this->ssl_enabled ? 'https' : 'http';
        $url = $protocol . '://' . $this->domain;
        
        if ($path) {
            $url .= '/' . ltrim($path, '/');
        }
        
        return $url;
    }

    /**
     * Check if this is a subdomain of the main application.
     *
     * @return bool
     */
    public function isSubdomain(): bool
    {
        $appDomain = config('app.domain', 'localhost');
        return str_ends_with($this->domain, '.' . $appDomain);
    }

    /**
     * Get the subdomain part if this is a subdomain.
     *
     * @return string|null
     */
    public function getSubdomain(): ?string
    {
        if (!$this->isSubdomain()) {
            return null;
        }

        $appDomain = config('app.domain', 'localhost');
        return str_replace('.' . $appDomain, '', $this->domain);
    }

    /**
     * Generate DNS verification records.
     *
     * @return array
     */
    public function getDnsVerificationRecords(): array
    {
        $verificationKey = hash('sha256', $this->tenant->uuid . $this->domain);
        
        return [
            'txt' => [
                'type' => 'TXT',
                'name' => '_krayin-verification',
                'value' => $verificationKey,
                'ttl' => 300,
            ],
            'cname' => [
                'type' => 'CNAME',
                'name' => $this->domain,
                'value' => config('app.domain'),
                'ttl' => 300,
            ],
        ];
    }

    /**
     * Verify domain ownership via DNS.
     *
     * @return bool
     */
    public function verifyDns(): bool
    {
        try {
            $verificationKey = hash('sha256', $this->tenant->uuid . $this->domain);
            $dnsRecords = dns_get_record('_krayin-verification.' . $this->domain, DNS_TXT);
            
            foreach ($dnsRecords as $record) {
                if (isset($record['txt']) && $record['txt'] === $verificationKey) {
                    $this->markAsVerified();
                    return true;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check SSL certificate status.
     *
     * @return array
     */
    public function checkSslStatus(): array
    {
        try {
            $context = stream_context_create([
                "ssl" => [
                    "capture_peer_cert" => true,
                    "verify_peer" => false,
                    "verify_peer_name" => false,
                ],
            ]);

            $stream = @stream_socket_client(
                "ssl://{$this->domain}:443",
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$stream) {
                return [
                    'valid' => false,
                    'error' => $errstr ?: 'Could not connect to SSL',
                ];
            }

            $params = stream_context_get_params($stream);
            $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);

            fclose($stream);

            $validFrom = \Carbon\Carbon::createFromTimestamp($cert['validFrom_time_t']);
            $validTo = \Carbon\Carbon::createFromTimestamp($cert['validTo_time_t']);
            $isValid = now()->between($validFrom, $validTo);

            return [
                'valid' => $isValid,
                'issuer' => $cert['issuer']['O'] ?? 'Unknown',
                'subject' => $cert['subject']['CN'] ?? $this->domain,
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
                'days_remaining' => now()->diffInDays($validTo, false),
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}