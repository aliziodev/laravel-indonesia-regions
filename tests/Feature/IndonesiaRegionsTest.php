<?php

use Aliziodev\IndonesiaRegions\Commands\InstallCommand;
use Aliziodev\IndonesiaRegions\Commands\SyncCommand;
use Aliziodev\IndonesiaRegions\Database\Seeders\IndonesiaRegionSeeder;
use Aliziodev\IndonesiaRegions\Facades\Indonesia;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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

test('search bersifat case insensitive', function () {
    $lower = Indonesia::search('bakongan');
    $upper = Indonesia::search('BAKONGAN');
    $mixed = Indonesia::search('Bakongan');

    expect($lower->pluck('code')->all())->toBe([
        '11.01.01',
        '11.01.01.2001',
    ])->and($upper->pluck('code')->all())->toBe([
        '11.01.01',
        '11.01.01.2001',
    ])->and($mixed->pluck('code')->all())->toBe([
        '11.01.01',
        '11.01.01.2001',
    ]);
});

test('search postal code numeric mengembalikan village', function () {
    $results = Indonesia::search('23773');

    expect($results)->toHaveCount(1)
        ->and($results->first()->code)->toBe('11.01.01.2001')
        ->and($results->first()->postal_code)->toBe('23773');
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

test('search with address menerima country name ID', function () {
    $results = Indonesia::searchWithAddress('Bakongan', null, null, null, 'ID');

    expect($results)->toHaveCount(2)
        ->and($results->last()->full_address)->toContain('ID');
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

test('get full address menerima country name ID', function () {
    $address = Indonesia::getFullAddress('11.01.01.2001', 'ID');

    expect($address)->toContain('ID')
        ->and($address)->not->toContain('Indonesia');
});

test('full address melempar exception untuk country name yang tidak didukung', function () {
    expect(fn () => Indonesia::getFullAddress('11.01.01.2001', 'Republic of Indonesia'))
        ->toThrow(\InvalidArgumentException::class, 'Invalid country name');
});

test('get region type dan validate code bekerja sesuai struktur kode', function () {
    expect(Indonesia::getRegionType('11'))->toBe('province')
        ->and(Indonesia::getRegionType('11.01'))->toBe('city')
        ->and(Indonesia::getRegionType('11.01.01'))->toBe('district')
        ->and(Indonesia::getRegionType('11.01.01.2001'))->toBe('village')
        ->and(Indonesia::validateCode('11.01.01.2001'))->toBeTrue()
        ->and(Indonesia::validateCode('11.99'))->toBeFalse();
});

test('get region info melempar exception untuk kode yang tidak valid', function () {
    expect(fn () => Indonesia::getRegionInfo('11.0'))
        ->toThrow(\InvalidArgumentException::class, 'Invalid region code length');
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

test('api index mengembalikan daftar regions', function () {
    $this->getJson('/api/indonesia-regions')
        ->assertOk()
        ->assertJsonFragment([
            'code' => '11',
            'name' => 'ACEH',
        ]);
});

test('api select mengembalikan options untuk frontend', function () {
    $this->getJson('/api/indonesia-regions/select')
        ->assertOk()
        ->assertJsonFragment([
            'value' => '11',
            'label' => 'ACEH',
        ]);
});

test('api select dapat mengembalikan map format', function () {
    $this->getJson('/api/indonesia-regions/select?format=map')
        ->assertOk()
        ->assertJson([
            '11' => 'ACEH',
        ]);
});

test('api cascade mengembalikan selected dan options bertingkat', function () {
    $this->getJson('/api/indonesia-regions/cascade?region_code=11.01.01.2001&country_name=ID')
        ->assertOk()
        ->assertJson([
            'selected' => [
                'province' => ['value' => '11', 'label' => 'Aceh'],
                'city' => ['value' => '11.01', 'label' => 'Kab. Aceh Selatan'],
                'district' => ['value' => '11.01.01', 'label' => 'Bakongan'],
                'village' => ['value' => '11.01.01.2001', 'label' => 'Keude Bakongan', 'postal_code' => '23773'],
            ],
            'options' => [
                'provinces' => [
                    ['value' => '11', 'label' => 'ACEH'],
                ],
                'cities' => [
                    ['value' => '11.01', 'label' => 'KAB. ACEH SELATAN'],
                ],
                'districts' => [
                    ['value' => '11.01.01', 'label' => 'BAKONGAN'],
                ],
                'villages' => [
                    ['value' => '11.01.01.2001', 'label' => 'KEUDE BAKONGAN'],
                ],
            ],
            'full_address' => 'Keude Bakongan, Bakongan, Kab. Aceh Selatan, Aceh, ID, 23773',
        ]);
});

test('api show mengembalikan detail region', function () {
    $this->getJson('/api/indonesia-regions/11.01.01.2001')
        ->assertOk()
        ->assertJsonFragment([
            'code' => '11.01.01.2001',
            'name' => 'KEUDE BAKONGAN',
            'postal_code' => '23773',
        ]);
});

test('api search mengembalikan hasil pencarian', function () {
    $this->getJson('/api/indonesia-regions/search?term=bakongan')
        ->assertOk()
        ->assertJsonFragment([
            'code' => '11.01.01',
            'name' => 'BAKONGAN',
        ]);
});

test('api full text search mengembalikan hasil level village', function () {
    $this->getJson('/api/indonesia-regions/search/full-text?term=aceh')
        ->assertOk()
        ->assertJsonFragment([
            'code' => '11.01.01.2001',
            'province' => 'Aceh',
            'postal_code' => '23773',
        ]);
});

test('api full address mengembalikan alamat lengkap', function () {
    $this->getJson('/api/indonesia-regions/11.01.01.2001/full-address?country_name=ID')
        ->assertOk()
        ->assertJson([
            'full_address' => 'Keude Bakongan, Bakongan, Kab. Aceh Selatan, Aceh, ID, 23773',
        ]);
});

test('api select context mengembalikan selected options bertingkat', function () {
    $this->getJson('/api/indonesia-regions/11.01.01.2001/select-context?country_name=ID')
        ->assertOk()
        ->assertJson([
            'selected' => [
                'province' => [
                    'value' => '11',
                    'label' => 'Aceh',
                ],
                'city' => [
                    'value' => '11.01',
                    'label' => 'Kab. Aceh Selatan',
                ],
                'district' => [
                    'value' => '11.01.01',
                    'label' => 'Bakongan',
                ],
                'village' => [
                    'value' => '11.01.01.2001',
                    'label' => 'Keude Bakongan',
                    'postal_code' => '23773',
                ],
            ],
            'full_address' => 'Keude Bakongan, Bakongan, Kab. Aceh Selatan, Aceh, ID, 23773',
        ]);
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
        ->with('indonesia-regions:sync', ['--force' => true])
        ->andReturn(0);

    $command->setLaravel($this->app);
    $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));

    expect($command->handle())->toBeNull();
});

test('sync command memanggil seeder package', function () {
    $command = \Mockery::mock(SyncCommand::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $command->shouldReceive('option')
        ->once()
        ->with('force')
        ->andReturn(true);
    $command->shouldReceive('call')
        ->once()
        ->with('db:seed', ['--class' => IndonesiaRegionSeeder::class, '--force' => true])
        ->andReturn(0);
    $command->shouldReceive('clearPackageCache')
        ->once()
        ->andReturn(true);

    $command->setLaravel($this->app);
    $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));

    expect($command->handle())->toBe(0);
});

test('sync command membersihkan cache setelah seeder berhasil', function () {
    $command = \Mockery::mock(SyncCommand::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $command->shouldReceive('option')
        ->once()
        ->with('force')
        ->andReturn(true);
    $command->shouldReceive('call')
        ->once()
        ->with('db:seed', ['--class' => IndonesiaRegionSeeder::class, '--force' => true])
        ->andReturn(0);

    Cache::store('array')->put('external.key', 'keep', 3600);
    Indonesia::getRegions();

    $command->shouldReceive('clearPackageCache')
        ->once()
        ->passthru();

    $command->setLaravel($this->app);
    $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));

    expect($command->handle())->toBe(0)
        ->and(Cache::store('array')->get('external.key'))->toBe('keep');
});

test('sync command gagal jika clear cache gagal', function () {
    $command = \Mockery::mock(SyncCommand::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $command->shouldReceive('option')
        ->once()
        ->with('force')
        ->andReturn(true);
    $command->shouldReceive('call')
        ->once()
        ->with('db:seed', ['--class' => IndonesiaRegionSeeder::class, '--force' => true])
        ->andReturn(0);
    $command->shouldReceive('clearPackageCache')
        ->once()
        ->andReturn(false);

    $command->setLaravel($this->app);
    $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));

    expect($command->handle())->toBe(1);
});

test('seeder melakukan upsert dari dataset php', function () {
    $dataPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'indonesia-regions-test-'.uniqid();

    File::ensureDirectoryExists($dataPath.DIRECTORY_SEPARATOR.'districts');
    File::ensureDirectoryExists($dataPath.DIRECTORY_SEPARATOR.'villages');

    File::put($dataPath.DIRECTORY_SEPARATOR.'provinces.php', <<<'PHP'
<?php
return [
    ['code' => '11', 'name' => 'Aceh Baru'],
];
PHP);

    File::put($dataPath.DIRECTORY_SEPARATOR.'regencies.php', <<<'PHP'
<?php
return [
    ['code' => '11.01', 'name' => 'Kabupaten Aceh Selatan'],
];
PHP);

    File::put($dataPath.DIRECTORY_SEPARATOR.'districts'.DIRECTORY_SEPARATOR.'districts_11.php', <<<'PHP'
<?php
return [
    ['code' => '11.01.01', 'name' => 'Bakongan'],
];
PHP);

    File::put($dataPath.DIRECTORY_SEPARATOR.'villages'.DIRECTORY_SEPARATOR.'villages_11.php', <<<'PHP'
<?php
return [
    ['code' => '11.01.01.2001', 'name' => 'Keude Bakongan', 'postal_code' => '23773'],
];
PHP);

    config()->set('indonesia-regions.data_path', $dataPath);

    DB::table('indonesia_regions')
        ->where('code', '11')
        ->update([
            'name' => 'ACEH',
            'postal_code' => null,
            'status' => 'inactive',
        ]);

    (new IndonesiaRegionSeeder)->run();

    expect(DB::table('indonesia_regions')->where('code', '11')->value('name'))->toBe('ACEH BARU')
        ->and(DB::table('indonesia_regions')->where('code', '11')->value('status'))->toBe('active')
        ->and(DB::table('indonesia_regions')->where('code', '11.01.01.2001')->value('search_text'))->toContain('ACEH BARU');

    File::deleteDirectory($dataPath);
});

test('search with full text dapat mencari berdasarkan nama province', function () {
    $results = Indonesia::searchWithFullText('Aceh');

    expect($results)->toHaveCount(1)
        ->and($results->first()['code'])->toBe('11.01.01.2001')
        ->and($results->first()['province'])->toBe('Aceh');
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
