<?php

namespace Aliziodev\IndonesiaRegions\Models;

use Illuminate\Database\Eloquent\Model;

class IndonesiaRegion extends Model
{
    protected $table = 'indonesia_regions';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'postal_code',
        'status',
        'search_text',
    ];

    public function getConnectionName()
    {
        $connectionName = config('indonesia-regions.database.connection');

        // Jika tidak ada konfigurasi koneksi, gunakan koneksi default aplikasi
        if (empty($connectionName)) {
            return null;
        }

        // Validasi koneksi agar sesuai format Illuminate DatabaseManager
        $connections = config('database.connections');
        if (! is_array($connections) || ! isset($connections[$connectionName])) {
            throw new \RuntimeException(
                "Koneksi database '{$connectionName}' tidak ditemukan dalam konfigurasi database."
            );
        }

        return $connectionName;
    }
}
