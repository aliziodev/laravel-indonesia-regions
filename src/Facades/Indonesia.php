<?php

namespace Aliziodev\IndonesiaRegions\Facades;

use Illuminate\Support\Facades\Facade;

class Indonesia extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'indonesia-region';
    }
}