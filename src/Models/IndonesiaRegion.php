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
        'latitude',
        'longitude',
        'status'
    ];
}