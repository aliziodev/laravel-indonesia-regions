<?php

use Aliziodev\IndonesiaRegions\Facades\Indonesia;
use Aliziodev\IndonesiaRegions\Commands\InstallCommand;
use Aliziodev\IndonesiaRegions\Database\Seeders\IndonesiaRegionSeeder;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

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

test('find by code mengembalikan region yang benar', function () {
    $region = Indonesia::findByCode('11.01.01.2001');

    expect($region)->not->toBeNull()
        ->and($region->code)->toBe('11.01.01.2001')
        ->and($region->postal_code)->toBe('23773');
});

test('find by postal code mengembalikan village yang benar', function () {
    $region = Indonesia::findByPostalCode('23773');

    expect($region)->not->toBeNull()
        ->and($region->code)->toBe('11.01.01.2001');
});

test('search mengembalikan district dan village yang cocok', function () {
    $results = Indonesia::search('Bakongan');

    expect($results)->toHaveCount(2)
        ->and($results->pluck('code')->all())->toBe([
            '11.01.01',
            '11.01.01.2001',
        ]);
});

test('search dengan pagination mengembalikan paginator', function () {
    $results = Indonesia::search('Bakongan', perPage: 1);

    expect($results)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class)
        ->and($results->total())->toBe(2)
        ->and(count($results->items()))->toBe(1);
});

test('search with address menambahkan full address', function () {
    $results = Indonesia::searchWithAddress('Bakongan');

    expect($results)->toHaveCount(2)
        ->and($results->last()->full_address)->toContain('Keude Bakongan')
        ->and($results->last()->full_address)->toContain('Aceh');
});

test('search with full text mengembalikan hasil level village', function () {
    $results = Indonesia::searchWithFullText('Bakongan');

    expect($results)->toHaveCount(1)
        ->and($results->first()['code'])->toBe('11.01.01.2001')
        ->and($results->first()['full_address'])->toContain('Keude Bakongan')
        ->and($results->first()['postal_code'])->toBe('23773');
});

test('get for select mengembalikan pasangan code dan name', function () {
    expect(Indonesia::getForSelect())->toBe([
        '11' => 'ACEH',
    ]);

    expect(Indonesia::getForSelect('11'))->toBe([
        '11.01' => 'KAB. ACEH SELATAN',
    ]);
});

test('get full address mengembalikan alamat lengkap village', function () {
    $address = Indonesia::getFullAddress('11.01.01.2001');

    expect($address)->toContain('Keude Bakongan')
        ->and($address)->toContain('Bakongan')
        ->and($address)->toContain('23773');
});

test('get region type dan validate code bekerja sesuai struktur kode', function () {
    expect(Indonesia::getRegionType('11'))->toBe('province')
        ->and(Indonesia::getRegionType('11.01'))->toBe('city')
        ->and(Indonesia::getRegionType('11.01.01'))->toBe('district')
        ->and(Indonesia::getRegionType('11.01.01.2001'))->toBe('village')
        ->and(Indonesia::validateCode('11.01.01.2001'))->toBeTrue()
        ->and(Indonesia::validateCode('11.99'))->toBeFalse();
});

test('clear cache command berhasil dijalankan', function () {
    Cache::store('array')->put('external.key', 'keep', 3600);
    Indonesia::getRegions();

    $this->artisan('indonesia-regions:clear-cache')
        ->expectsOutput('Clearing Indonesia Regions cache...')
        ->expectsOutput('Cache cleared successfully!')
        ->assertExitCode(0);

    expect(Cache::store('array')->get('external.key'))->toBe('keep');
});

test('install command memanggil publish migrate dan seed', function () {
    $command = \Mockery::mock(InstallCommand::class)->makePartial();
    $command->shouldReceive('call')
        ->once()
        ->with('vendor:publish', ['--tag' => 'indonesia-regions-migrations'])
        ->andReturn(0);
    $command->shouldReceive('call')
        ->once()
        ->with('migrate')
        ->andReturn(0);
    $command->shouldReceive('call')
        ->once()
        ->with('db:seed', ['--class' => IndonesiaRegionSeeder::class, '--force' => true])
        ->andReturn(0);

    $command->setLaravel($this->app);
    $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput()));

    expect($command->handle())->toBeNull();
});

test('upgrade migration menghapus kolom latitude dan longitude pada schema lama', function () {
    Schema::table('indonesia_regions', function ($table) {
        $table->decimal('latitude', 10, 8)->nullable();
        $table->decimal('longitude', 11, 8)->nullable();
    });

    expect(Schema::hasColumn('indonesia_regions', 'latitude'))->toBeTrue()
        ->and(Schema::hasColumn('indonesia_regions', 'longitude'))->toBeTrue();

    $migration = require __DIR__.'/../../src/Database/Migrations/2026_03_07_000002_drop_latitude_longitude_from_indonesia_regions_table.php';
    $migration->up();

    expect(Schema::hasColumn('indonesia_regions', 'latitude'))->toBeFalse()
        ->and(Schema::hasColumn('indonesia_regions', 'longitude'))->toBeFalse();
});
