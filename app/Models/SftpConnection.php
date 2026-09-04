<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A client's SFTP connection (one per client, reused across templates).
 * Credentials are encrypted at rest via the 'encrypted' cast.
 */
class SftpConnection extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'host', 'port', 'username', 'auth_type',
        'password', 'private_key', 'passphrase', 'base_path', 'processed_path',
    ];

    protected $casts = [
        'port'        => 'integer',
        // Encrypted at rest — a DB dump won't leak client credentials.
        'password'    => 'encrypted',
        'private_key' => 'encrypted',
        'passphrase'  => 'encrypted',
    ];

    // Never expose secrets in JSON/arrays.
    protected $hidden = ['password', 'private_key', 'passphrase'];

    /**
     * Build the Laravel filesystem disk config array for this connection.
     * Consumed by Storage::build() in InputSourceResolver.
     */
    public function toDiskConfig(): array
    {
        $config = [
            'driver'   => 'sftp',
            'host'     => $this->host,
            'port'     => $this->port,
            'username' => $this->username,
            'root'     => rtrim($this->base_path ?: '/', '/') . '/',
            'timeout'  => 30,
        ];

        if ($this->auth_type === 'key') {
            $config['privateKey'] = $this->private_key;
            if (! empty($this->passphrase)) {
                $config['passphrase'] = $this->passphrase;
            }
        } else {
            $config['password'] = $this->password;
        }

        return $config;
    }
}
