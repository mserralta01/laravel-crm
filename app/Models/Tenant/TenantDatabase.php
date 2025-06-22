<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * TenantDatabase Model
 * 
 * Stores database connection information for each tenant.
 * Supports encrypted password storage and dynamic connection configuration.
 * 
 * @property int $id
 * @property int $tenant_id
 * @property string $connection_name
 * @property string $database_name
 * @property string $host
 * @property int $port
 * @property string $username
 * @property string $password
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @property-read \App\Models\Tenant\Tenant $tenant
 */
class TenantDatabase extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'tenant_id',
        'connection_name',
        'database_name',
        'host',
        'port',
        'username',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'port' => 'integer',
    ];

    /**
     * Get the tenant that owns the database.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Set the password attribute (encrypt before storing).
     *
     * @param string $value
     * @return void
     */
    public function setPasswordAttribute(string $value)
    {
        $this->attributes['password'] = Crypt::encryptString($value);
    }

    /**
     * Get the decrypted password.
     *
     * @return string
     */
    public function getDecryptedPassword(): string
    {
        return Crypt::decryptString($this->password);
    }

    /**
     * Get the database connection configuration array.
     *
     * @return array
     */
    public function getConnectionConfig(): array
    {
        return [
            'driver' => 'mysql',
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database_name,
            'username' => $this->username,
            'password' => $this->getDecryptedPassword(),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => 'InnoDB',
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                \PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ];
    }

    /**
     * Test the database connection.
     *
     * @return bool
     * @throws \Exception
     */
    public function testConnection(): bool
    {
        try {
            $config = $this->getConnectionConfig();
            
            // Create a temporary connection
            config(['database.connections.tenant_test' => $config]);
            
            // Test the connection
            \DB::connection('tenant_test')->getPdo();
            
            // Clean up
            \DB::purge('tenant_test');
            
            return true;
        } catch (\Exception $e) {
            \DB::purge('tenant_test');
            throw new \Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Create the database if it doesn't exist.
     *
     * @return bool
     * @throws \Exception
     */
    public function createDatabase(): bool
    {
        try {
            $charset = config('database.connections.mysql.charset', 'utf8mb4');
            $collation = config('database.connections.mysql.collation', 'utf8mb4_unicode_ci');
            
            // Connect without database selection
            $pdo = new \PDO(
                "mysql:host={$this->host};port={$this->port}",
                $this->username,
                $this->getDecryptedPassword(),
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            
            // Create database if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$this->database_name}` CHARACTER SET {$charset} COLLATE {$collation}");
            
            return true;
        } catch (\Exception $e) {
            throw new \Exception('Failed to create database: ' . $e->getMessage());
        }
    }

    /**
     * Drop the database.
     *
     * @return bool
     * @throws \Exception
     */
    public function dropDatabase(): bool
    {
        try {
            // Connect without database selection
            $pdo = new \PDO(
                "mysql:host={$this->host};port={$this->port}",
                $this->username,
                $this->getDecryptedPassword(),
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            
            // Drop database
            $pdo->exec("DROP DATABASE IF EXISTS `{$this->database_name}`");
            
            return true;
        } catch (\Exception $e) {
            throw new \Exception('Failed to drop database: ' . $e->getMessage());
        }
    }

    /**
     * Get database size in bytes.
     *
     * @return int
     */
    public function getDatabaseSize(): int
    {
        try {
            $result = \DB::connection($this->connection_name)
                ->selectOne("
                    SELECT 
                        SUM(data_length + index_length) as size 
                    FROM information_schema.tables 
                    WHERE table_schema = ?
                ", [$this->database_name]);
            
            return (int) ($result->size ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get formatted database size.
     *
     * @return string
     */
    public function getFormattedDatabaseSize(): string
    {
        $bytes = $this->getDatabaseSize();
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        
        return number_format($bytes / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
    }
}