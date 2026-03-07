<?php

use Aliziodev\IndonesiaRegions\Facades\Indonesia;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->seedRegions();
});

test('get regions berjalan di sqlite untuk root provinces', function () {
    $regions = Indonesia::getRegions();

    expect($regions)->toHaveCount(1)
        ->and($regions->first()->code)->toBe('11')
        ->and(isset($regions->first()->postal_code))->toBeFalse();
});

test('get regions untuk villages menyertakan postal code default', function () {
    $regions = Indonesia::getRegions('11.01.01');

    expect($regions)->toHaveCount(1)
        ->and($regions->first()->code)->toBe('11.01.01.2001')
        ->and($regions->first()->postal_code)->toBe('23773');
});

test('get region info mengembalikan hierarchy dan full address', function () {
    $info = Indonesia::getRegionInfo('11.01.01.2001');

    expect($info['province']['name'])->toBe('Aceh')
        ->and($info['city']['name'])->toBe('Kab. Aceh Selatan')
        ->and($info['district']['name'])->toBe('Bakongan')
        ->and($info['village']['postal_code'])->toBe('23773')
        ->and($info['full_address'])->toContain('Keude Bakongan')
        ->and($info['full_address'])->toContain('23773');
});

test('clear cache hanya menghapus key package yang terlacak', function () {
    Cache::store('array')->put('external.key', 'keep', 3600);

    Indonesia::getRegions();
    expect(Indonesia::clearCache())->toBeTrue();

    expect(Cache::store('array')->get('external.key'))->toBe('keep');
});
