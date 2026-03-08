# Laravel Indonesia Regions (Laravel Wilayah Indonesia)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/aliziodev/laravel-indonesia-regions.svg?style=flat-square)](https://packagist.org/packages/aliziodev/laravel-indonesia-regions)
[![Total Downloads](https://img.shields.io/packagist/dt/aliziodev/laravel-indonesia-regions.svg?style=flat-square)](https://packagist.org/packages/aliziodev/laravel-indonesia-regions)
[![PHP Version](https://img.shields.io/packagist/php-v/aliziodev/laravel-indonesia-regions.svg?style=flat-square)](https://packagist.org/packages/aliziodev/laravel-indonesia-regions)
[![Laravel Version](https://img.shields.io/badge/Laravel-11.x-red?style=flat-square)](https://packagist.org/packages/aliziodev/laravel-indonesia-regions)
[![Laravel Version](https://img.shields.io/badge/Laravel-12.x-red?style=flat-square)](https://packagist.org/packages/aliziodev/laravel-indonesia-regions)

Package Laravel untuk data wilayah Indonesia lengkap dengan kode pos. Package ini menyediakan data provinsi, kota/kabupaten, kecamatan, dan desa/kelurahan.

Package ini dipertahankan sebagai package kompatibilitas untuk integrasi lama. Dataset package ini disinkronkan dari repo upstream `aliziodev/laravel-wilayah` dan disimpan sebagai file PHP agar install dan update tetap lintas database.

## Fitur

-   Data wilayah Indonesia lengkap dan terupdate (sesuai Kepmendagri No 300.2.2-2138 Tahun 2025)
-   Kode pos untuk setiap desa/kelurahan
-   Cache system untuk performa optimal
-   Facade untuk penggunaan yang mudah
-   Support untuk Laravel 11.x dan 12.x
-   Pencarian wilayah
-   Pencarian dengan alamat lengkap
-   Pencarian full text hingga level desa
-   Hirarki/Info wilayah
-   Format untuk dropdown/select
-   Pagination support
-   Format nama negara terbatas ke `Indonesia` atau `ID`
-   Sync dataset via command setelah `composer update`
-   Pencarian case-insensitive termasuk di PostgreSQL
-   Endpoint API bawaan yang siap dipakai

## Instalasi

```bash
composer require aliziodev/laravel-indonesia-regions
```

Kemudian jalankan command instalasi:

```bash
php artisan indonesia-regions:install
```

Untuk menyinkronkan dataset terbaru setelah package diupdate:

```bash
php artisan indonesia-regions:sync
```

Opsional, publish konfigurasi cache package:

```bash
php artisan vendor:publish --tag=indonesia-regions-config
```

## Sumber Data

Data package ini digenerate dari repo upstream berikut:

```text
https://github.com/aliziodev/laravel-wilayah
```

Saat workflow sync berjalan, package akan mengambil dataset dari repo upstream tersebut, menyalinnya ke folder `data/`, lalu aplikasi pengguna cukup menjalankan `php artisan indonesia-regions:sync` untuk melakukan `upsert` ke database.

## API Bawaan

Package ini menyediakan endpoint API bawaan tanpa perlu membuat controller sendiri. Secara default route akan aktif dengan prefix:

```text
/api/indonesia-regions
```

Konfigurasi tersedia di `config/indonesia-regions.php`:

```php
'api' => [
    'enabled' => true,
    'prefix' => 'api/indonesia-regions',
    'middleware' => ['api'],
    'responder' => null,
],
```

Jika aplikasi Anda memiliki wrapper response sendiri, Anda bisa mengganti responder bawaan package:

```php
'api' => [
    'enabled' => true,
    'prefix' => 'api/indonesia-regions',
    'middleware' => ['api'],
    'responder' => App\Support\Api\RegionApiResponder::class,
],
```

Class tersebut harus mengimplementasikan `Aliziodev\IndonesiaRegions\Contracts\ApiResponderInterface`.

Contoh implementasi:

```php
<?php

namespace App\Support\Api;

use Aliziodev\IndonesiaRegions\Contracts\ApiResponderInterface;
use Illuminate\Http\JsonResponse;

class RegionApiResponder implements ApiResponderInterface
{
    public function respond(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
        ], $status);
    }
}
```

Contoh jika aplikasi Anda sudah punya helper seperti `ApiResponse`:

```php
<?php

namespace App\Support\Api;

use Aliziodev\IndonesiaRegions\Contracts\ApiResponderInterface;
use Illuminate\Http\JsonResponse;

class RegionApiResponder implements ApiResponderInterface
{
    public function respond(mixed $data, int $status = 200): JsonResponse
    {
        return ApiResponse::success($data, status: $status);
    }
}
```

Endpoint yang tersedia:

- `GET /api/indonesia-regions`
- `GET /api/indonesia-regions/cascade`
- `GET /api/indonesia-regions/select`
- `GET /api/indonesia-regions/search?term=bakongan`
- `GET /api/indonesia-regions/search/address?term=bakongan`
- `GET /api/indonesia-regions/search/full-text?term=aceh`
- `GET /api/indonesia-regions/postal-code/23773`
- `GET /api/indonesia-regions/11.01.01.2001`
- `GET /api/indonesia-regions/11.01.01.2001/info`
- `GET /api/indonesia-regions/11.01.01.2001/select-context`
- `GET /api/indonesia-regions/11.01.01.2001/full-address?country_name=ID`

Contoh endpoint options untuk frontend:

```text
GET /api/indonesia-regions/select
GET /api/indonesia-regions/select?parent_code=11
GET /api/indonesia-regions/select?format=map
```

Response default:

```json
[
  { "value": "11", "label": "ACEH" }
]
```

Response `format=map`:

```json
{
  "11": "ACEH"
}
```

Untuk frontend yang ingin langsung mendapat semua options bertingkat sekaligus, gunakan endpoint cascade:

```text
GET /api/indonesia-regions/cascade
GET /api/indonesia-regions/cascade?region_code=11.01.01.2001
GET /api/indonesia-regions/cascade?region_code=11.01.01.2001&country_name=ID
```

Response:

```json
{
  "selected": {
    "province": { "value": "11", "label": "Aceh" },
    "city": { "value": "11.01", "label": "Kab. Aceh Selatan" },
    "district": { "value": "11.01.01", "label": "Bakongan" },
    "village": { "value": "11.01.01.2001", "label": "Keude Bakongan", "postal_code": "23773" }
  },
  "options": {
    "provinces": [
      { "value": "11", "label": "ACEH" }
    ],
    "cities": [
      { "value": "11.01", "label": "KAB. ACEH SELATAN" }
    ],
    "districts": [
      { "value": "11.01.01", "label": "BAKONGAN" }
    ],
    "villages": [
      { "value": "11.01.01.2001", "label": "KEUDE BAKONGAN" }
    ]
  },
  "full_address": "Keude Bakongan, Bakongan, Kab. Aceh Selatan, Aceh, ID, 23773"
}
```

Contoh helper untuk prefill cascading select dari `region_code` yang sudah tersimpan:

```text
GET /api/indonesia-regions/11.01.01.2001/select-context
GET /api/indonesia-regions/11.01.01.2001/select-context?country_name=ID
```

Response:

```json
{
  "selected": {
    "province": { "value": "11", "label": "Aceh" },
    "city": { "value": "11.01", "label": "Kab. Aceh Selatan" },
    "district": { "value": "11.01.01", "label": "Bakongan" },
    "village": { "value": "11.01.01.2001", "label": "Keude Bakongan", "postal_code": "23773" }
  },
  "full_address": "Keude Bakongan, Bakongan, Kab. Aceh Selatan, Aceh, Indonesia, 23773"
}
```

### Contoh Lengkap API dengan Wilayah Bandung

Contoh kode wilayah yang digunakan:

- `32` : Jawa Barat
- `32.73` : Kota Bandung
- `32.73.02` : Coblong
- `32.73.02.1001` : Dago

Ambil daftar kota/kabupaten di Jawa Barat:

```http
GET /api/indonesia-regions?parent_code=32
```

Ambil options kota/kabupaten untuk dropdown:

```http
GET /api/indonesia-regions/select?parent_code=32
```

Atau ambil seluruh context cascade sekaligus:

```http
GET /api/indonesia-regions/cascade?region_code=32.73.02.1001
```

Response:

```json
[
  { "value": "32.73", "label": "KOTA BANDUNG" },
  { "value": "32.04", "label": "KAB. BANDUNG" }
]
```

Cari wilayah dengan kata kunci Bandung:

```http
GET /api/indonesia-regions/search?term=bandung
```

Cari hanya level kota/kabupaten:

```http
GET /api/indonesia-regions/search?term=bandung&type=city
```

Cari full text, hasil selalu village:

```http
GET /api/indonesia-regions/search/full-text?term=bandung
GET /api/indonesia-regions/search/full-text?term=coblong
GET /api/indonesia-regions/search/full-text?term=dago&country_name=ID
```

Contoh response:

```json
[
  {
    "code": "32.73.02.1001",
    "province": "Jawa Barat",
    "city": "Kota Bandung",
    "district": "Coblong",
    "village": "Dago",
    "postal_code": "40135",
    "full_address": "Dago, Coblong, Kota Bandung, Jawa Barat, ID, 40135"
  }
]
```

Ambil detail region berdasarkan kode:

```http
GET /api/indonesia-regions/32.73
GET /api/indonesia-regions/32.73.02.1001
```

Ambil hierarchy lengkap berdasarkan `region_code`:

```http
GET /api/indonesia-regions/32.73.02.1001/info
GET /api/indonesia-regions/32.73.02.1001/info?country_name=ID
```

Contoh response:

```json
{
  "province": {
    "code": "32",
    "name": "Jawa Barat"
  },
  "city": {
    "code": "32.73",
    "name": "Kota Bandung"
  },
  "district": {
    "code": "32.73.02",
    "name": "Coblong"
  },
  "village": {
    "code": "32.73.02.1001",
    "name": "Dago",
    "postal_code": "40135"
  },
  "full_address": "Dago, Coblong, Kota Bandung, Jawa Barat, Indonesia, 40135"
}
```

Ambil alamat lengkap saja:

```http
GET /api/indonesia-regions/32.73.02.1001/full-address
GET /api/indonesia-regions/32.73.02.1001/full-address?country_name=ID
```

Response:

```json
{
  "full_address": "Dago, Coblong, Kota Bandung, Jawa Barat, ID, 40135"
}
```

Ambil region dari kode pos:

```http
GET /api/indonesia-regions/postal-code/40135
```

Prefill cascading select dari `region_code` yang sudah tersimpan:

```http
GET /api/indonesia-regions/32.73.02.1001/select-context
```

Response:

```json
{
  "selected": {
    "province": { "value": "32", "label": "Jawa Barat" },
    "city": { "value": "32.73", "label": "Kota Bandung" },
    "district": { "value": "32.73.02", "label": "Coblong" },
    "village": { "value": "32.73.02.1001", "label": "Dago", "postal_code": "40135" }
  },
  "full_address": "Dago, Coblong, Kota Bandung, Jawa Barat, Indonesia, 40135"
}
```

## Gaya Penulisan Parameter

Package ini mendukung dua gaya penulisan parameter:

### 1. Regular Parameters (Traditional)

```php
// Parameter harus sesuai urutan
$cities = Indonesia::getRegions('11', ['code', 'name'], 15);
```

### 2. Named Parameters

```php
// Urutan tidak penting, lebih jelas dan mudah dibaca
$cities = Indonesia::getRegions(
    parentCode: '11',
    columns: ['code', 'name'],
    perPage: 15
);
```

Kedua gaya penulisan akan memberikan hasil yang sama. Named parameters (PHP 8.0+) memiliki beberapa keunggulan:

-   Lebih mudah dibaca dan dipahami
-   Mengurangi kesalahan dalam urutan parameter
-   Memungkinkan untuk melewati parameter opsional di tengah
-   Self-documenting code
-   IDE support yang lebih baik
    Pilih gaya penulisan yang sesuai dengan kebutuhan dan versi PHP yang Anda gunakan.

## Penggunaan

### Mengambil Data Wilayah (getRegions)

```php
use Aliziodev\IndonesiaRegions\Facades\Indonesia;

// Mengambil semua provinsi
$provinces = Indonesia::getRegions();

// Response:
[
    {
        "code": "11",
        "name": "ACEH",
    },
    {
        "code": "12",
        "name": "SUMATERA UTARA",
    }
]

// Mengambil kota/kabupaten di Aceh dengan pagination
$cities = Indonesia::getRegions('11', ['code', 'name'], 15);

// Response dengan pagination:
{
    "current_page": 1,
    "data": [
        {
            "code": "11.01",
            "name": "KAB. ACEH SELATAN",
        },
        // ... more cities
    ],
    "first_page_url": "http://example.com/api?page=1",
    "from": 1,
    "last_page": 23,
    "last_page_url": "http://example.com/api?page=23",
    "next_page_url": "http://example.com/api?page=2",
    "path": "http://example.com/api",
    "per_page": 15,
    "prev_page_url": null,
    "to": 15,
    "total": 343
}

// postal_code hanya muncul untuk desa/kelurahan
```

Perlu diingat bahwa jika parameter `columns` tidak diisi (null), maka akan menggunakan default columns yaitu `['code', 'name', 'postal_code']`. Untuk mengambil semua kolom, Anda perlu secara eksplisit menentukan `columns: ['*']`.

### Pencarian (search)

```php
use Aliziodev\IndonesiaRegions\Facades\Indonesia;

// Pencarian umum
$results = Indonesia::search('Bakongan');

// Search bersifat case-insensitive, termasuk di PostgreSQL
$results = Indonesia::search('bakongan');
$results = Indonesia::search('BAKONGAN');

// Response:
[
    {
        "code": "11.01.01",
        "name": "BAKONGAN"
    },
    {
        "code": "11.01.01.2001",
        "name": "KEUDE BAKONGAN",
        "postal_code": "23773"  // postal_code hanya muncul untuk desa/kelurahan
    }
]

// Pencarian dengan tipe spesifik
$villages = Indonesia::search('Bakongan', 'village');

// Pencarian dengan kolom tambahan
$results = Indonesia::search('Bakongan', null, null, ['code', 'name']);

// Pencarian lengkap dengan named parameters (PHP 8.0+)
$results = Indonesia::search(
    term: 'Bakongan',
    type: 'village',
    perPage: 15,
    columns: ['code', 'name']
);

// Response dengan pagination sama dengan format getRegions()
```

### Pencarian dengan Alamat Lengkap (searchWithAddress)

```php
use Aliziodev\IndonesiaRegions\Facades\Indonesia;

// Pencarian umum dengan alamat lengkap
$results = Indonesia::searchWithAddress('Bakongan');

// Response:
[
    {
        "code": "11.01.01",
        "name": "BAKONGAN",
        "full_address": "BAKONGAN, KAB. ACEH SELATAN, ACEH"
    },
    {
        "code": "11.01.01.2001",
        "name": "KEUDE BAKONGAN",
        "postal_code": "23773",
        "full_address": "KEUDE BAKONGAN, BAKONGAN, KAB. ACEH SELATAN, ACEH, 23773"
    }
]

// Pencarian dengan tipe spesifik
$villages = Indonesia::searchWithAddress('Bakongan', 'village');

// Pencarian dengan kolom tambahan
$results = Indonesia::searchWithAddress('Bakongan', null, null, ['code', 'name']);

// Pencarian lengkap dengan named parameters (PHP 8.0+)
$results = Indonesia::searchWithAddress(
    term: 'Bakongan',
    type: 'village',
    perPage: 15,
    columns: ['code', 'name']
);

// Response dengan pagination sama dengan format getRegions()

// Menggunakan nama negara singkat
$results = Indonesia::searchWithAddress('Bakongan', null, null, null, 'ID');

```

### Pencarian Full Text (searchWithFullText)

```php
use Aliziodev\IndonesiaRegions\Facades\Indonesia;

// Pencarian full text dengan default limit (15)
$results = Indonesia::searchWithFullText('Bakongan');

// Pencarian dengan limit kustom
$results = Indonesia::searchWithFullText('Bakongan', 25);

// Pencarian dengan nama negara singkat
$results = Indonesia::searchWithFullText(
    term: 'Bakongan',
    limit: 25,
    countryName: 'ID'
);

// Response:
[
    {
        "code": "11.01.01.2001",
        "province": "ACEH",
        "city": "KAB. ACEH SELATAN",
        "district": "BAKONGAN",
        "village": "KEUDE BAKONGAN",
        "postal_code": "23773",
        "full_address": "KEUDE BAKONGAN, BAKONGAN, KAB. ACEH SELATAN, ACEH, ID, 23773"
    }
    // ... more results
]
```

Metode ini selalu mengembalikan hasil pada level `village`. Kata kunci dapat berasal dari nama village, district, city, province, atau postal code, tetapi hasil akhirnya tetap berupa data village lengkap dengan hirarkinya.

Nilai `countryName` yang didukung hanya:

- `Indonesia` (default)
- `ID`

### Mencari Berdasarkan Kode (findByCode)

```php
// Regular parameters
$region = Indonesia::findByCode('11.01.01.2001');
$region = Indonesia::findByCode('11.01.01.2001', ['code', 'name']);

// Named parameters (PHP 8.0+)
$region = Indonesia::findByCode(
    code: '11.01.01.2001',
    columns: ['code', 'name']
);

// Response:
{
    "code": "11.01.01.2001",
    "name": "KEUDE BAKONGAN",
    "postal_code": "23773",
}
```

### Format Dropdown/Select (getForSelect)

```php
$provinces = Indonesia::getForSelect();

// Response:
{
    "11": "ACEH",
    "12": "SUMATERA UTARA",
    "13": "SUMATERA BARAT"
    // ... more provinces
}

// Get cities for select
$cities = Indonesia::getForSelect('11');

// Response:
{
    "11.01": "KAB. ACEH SELATAN",
    "11.02": "KAB. ACEH TENGGARA",
    // ... more cities
}
```

### Informasi Detail Wilayah (getRegionInfo)

```php

$info = Indonesia::getRegionInfo('11.01.01.2001');

// Response:
{
    "province": {
        "code": "11",
        "name": "ACEH"
    },
    "city": {
        "code": "11.01",
        "name": "KAB. ACEH SELATAN"
    },
    "district": {
        "code": "11.01.01",
        "name": "BAKONGAN"
    },
    "village": {
        "code": "11.01.01.2001",
        "name": "KEUDE BAKONGAN",
        "postal_code": "23773"
    },
    "full_address": "KEUDE BAKONGAN, BAKONGAN, KAB. ACEH SELATAN, ACEH, 23773"
}

// Menggunakan nama negara singkat
$info = Indonesia::getRegionInfo('11.01.01.2001', null, 'ID');
// Response akan menggunakan nama negara yang dipilih
"KEUDE BAKONGAN, BAKONGAN, KAB. ACEH SELATAN, ACEH, ID, 23773"
```

### Alamat Lengkap (getFullAddress)

```php
$address = Indonesia::getFullAddress('11.01.01.2001');

// Response:
"KEUDE BAKONGAN, BAKONGAN, KAB. ACEH SELATAN, ACEH, 23773"


// Menggunakan nama negara singkat
$address = Indonesia::getFullAddress('11.01.01.2001', 'ID');
// Response akan menggunakan nama negara yang dipilih
"KEUDE BAKONGAN, BAKONGAN, KAB. ACEH SELATAN, ACEH, ID, 23773"
```

### Pencarian Kode Pos (findByPostalCode)

```php
$region = Indonesia::findByPostalCode('23773');
// Response:
{
    "code": "11.01.01.2001",
    "name": "KEUDE BAKONGAN",
    "postal_code": "23773"
}
```

### Validasi Kode (validateCode)

```php
$isValid = Indonesia::validateCode('11.01.01.2001'); // true
$isValid = Indonesia::validateCode('11.99'); // false
$isValid = Indonesia::validateCode('11.12345'); // false
```

### Mendapatkan Tipe Wilayah (getRegionType)

```php
$type = Indonesia::getRegionType('11.01.01.2001'); // 'village'
$type = Indonesia::getRegionType('11.01.01'); // 'district'
$type = Indonesia::getRegionType('11.01'); // 'city'
$type = Indonesia::getRegionType('11'); // 'province'
```

### Cache Management

```bash
php artisan indonesia-regions:clear-cache
```

Pengaturan cache dapat dikustomisasi melalui `config/indonesia-regions.php`.

## Struktur Kode Wilayah

-   Provinsi: 2 digit (contoh: 11)
-   Kota/Kabupaten: 5 digit (contoh: 11.01)
-   Kecamatan: 8 digit (contoh: 11.01.01)
-   Desa/Kelurahan: 13 digit (contoh: 11.01.01.2001)

## Kolom Database

-   code : Kode wilayah (primary key)
-   name : Nama wilayah
-   postal_code : Kode pos (untuk desa/kelurahan)
-   status : Status wilayah aktif/tidak aktif (optional)
-   search_text : Kolom internal untuk optimasi `searchWithFullText()` pada level village

## Method Parameters

### getRegions

-   `parentCode` (string|null) : Kode wilayah parent (opsional)
-   `columns` (array|null) : Kolom yang akan diambil (default: ['code', 'name', 'postal_code'])
-   `perPage` (int|null) : Jumlah data per halaman untuk pagination (opsional)

### search

-   `term` (string) : Kata kunci pencarian
-   `type` (string|null) : Tipe wilayah ('province'|'city'|'district'|'village')
-   `perPage` (int|null) : Jumlah data per halaman untuk pagination (opsional)

### searchWithAddress

-   `term` (string) : Kata kunci pencarian
-   `type` (string|null) : Tipe wilayah ('province'|'city'|'district'|'village')
-   `perPage` (int|null) : Jumlah data per halaman untuk pagination (opsional)
-   `columns` (array|null) : Kolom yang akan diambil
-   `countryName` (string|null) : Nama negara untuk alamat lengkap. Nilai yang didukung hanya `Indonesia` atau `ID` (default: `Indonesia`)

### searchWithFullText

-   `term` (string) : Kata kunci pencarian
-   `limit` (int|null) : Batas jumlah hasil pencarian (default: 15)
-   `countryName` (string|null) : Nama negara untuk alamat lengkap. Nilai yang didukung hanya `Indonesia` atau `ID` (default: `Indonesia`)
-   Catatan: hasil selalu dikembalikan pada level `village`

### findByCode

-   `code` (string) : Kode wilayah
-   `columns` (array|null) : Kolom yang akan diambil (default: ['*'])

### getForSelect

-   `parentCode` (string|null) : Kode wilayah parent (opsional)

### getRegionInfo

-   `code` (string) : Kode wilayah
-   `columns` (array|null) : Kolom yang akan diambil (default: ['code', 'name', 'postal_code'])
-   `countryName` (string|null) : Nama negara untuk alamat lengkap. Nilai yang didukung hanya `Indonesia` atau `ID` (default: `Indonesia`)

### getFullAddress

-   `villageCode` (string) : Kode desa/kelurahan
-   `countryName` (string|null) : Nama negara untuk alamat lengkap. Nilai yang didukung hanya `Indonesia` atau `ID` (default: `Indonesia`)

### findByPostalCode

-   `postalCode` (string) : Kode pos

### validateCode

-   `code` (string) : Kode wilayah yang akan divalidasi

### getRegionType

-   `code` (string) : Kode wilayah

## Ucapan Terima Kasih
Data wilayah pada package ini pada dasarnya berasal dari [cahyadsn/wilayah](https://github.com/cahyadsn/wilayah). Data tersebut kemudian diolah dan dipelihara lebih lanjut melalui repo upstream [aliziodev/laravel-wilayah](https://github.com/aliziodev/laravel-wilayah), lalu disinkronkan ke package ini.


## Kontribusi

Silakan buat issue atau pull request untuk kontribusi.

## Lisensi

Package ini di bawah lisensi MIT.
