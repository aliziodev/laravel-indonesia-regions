<?php

namespace Aliziodev\IndonesiaRegions\Models;

use Illuminate\Database\Eloquent\Model;

class IndonesiaRegion extends Model
{
    protected $table = 'indonesia_regions';
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'postal_code',
        'latitude',
        'longitude',
        'status'
    ];

    public function isProvince(): bool
    {
        return strlen($this->code) === 2;
    }

    public function isCity(): bool
    {
        return strlen($this->code) === 5;
    }

    public function isDistrict(): bool
    {
        return strlen($this->code) === 8;
    }

    public function isVillage(): bool
    {
        return strlen($this->code) === 13;
    }

    public function getParentCode(): ?string
    {
        if ($this->isProvince()) return null;
        if ($this->isCity()) return substr($this->code, 0, 2);
        if ($this->isDistrict()) return substr($this->code, 0, 5);
        if ($this->isVillage()) return substr($this->code, 0, 8);
        return null;
    }
}