# Laravel Indonesia Regions (Laravel Wilayah Indonesia)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/aliziodev/laravel-indonesia-regions.svg?style=flat-square)](https://packagist.org/packages/aliziodev/laravel-indonesia-regions)
[![Total Downloads](https://img.shields.io/packagist/dt/aliziodev/laravel-indonesia-regions.svg?style=flat-square)](https://packagist.org/packages/aliziodev/laravel-indonesia-regions)
[![PHP Version](https://img.shields.io/packagist/php-v/aliziodev/laravel-indonesia-regions.svg?style=flat-square)](https://packagist.org/packages/aliziodev/laravel-indonesia-regions)
[![Laravel Version](https://img.shields.io/badge/Laravel-11.x-red?style=flat-square)](https://packagist.org/packages/aliziodev/laravel-indonesia-regions)
[![Laravel Version](https://img.shields.io/badge/Laravel-12.x-red?style=flat-square)](https://packagist.org/packages/aliziodev/laravel-indonesia-regions)

Package Laravel untuk data wilayah Indonesia lengkap dengan kode pos. Package ini menyediakan data provinsi, kota/kabupaten, kecamatan, dan desa/kelurahan.

## Fitur

-   Data wilayah Indonesia lengkap dan terupdate (sesuai dengan Kepmendagri No 100.1.1-6117 Tahun 2022)
-   Kode pos untuk setiap desa/kelurahan
-   Koordinat latitude dan longitude untuk setiap wilayah
-   Cache system untuk performa optimal
-   Facade untuk penggunaan yang mudah
-   Support untuk Laravel 11.x dan 12.x
-   Pencarian wilayah
-   Hirarki/Info wilayah
-   Format untuk dropdown/select
-   Pagination support

## Instalasi

```bash
composer require aliziodev/laravel-indonesia-regions
```

Kemudian jalankan command instalasi:

```bash
php artisan indonesia-regions:install
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
$cities = Indonesia::getRegions('11', ['code', 'name', 'latitude', 'longitude'], 15);

// Response dengan pagination:
{
    "current_page": 1,
    "data": [
        {
            "code": "11.01",
            "name": "KAB. ACEH SELATAN",
            "latitude": 3.31467,
            "longitude": 97.3517
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
$results = Indonesia::search('Bakongan', null, null, ['code', 'name', 'latitude', 'longitude']);

// Pencarian lengkap dengan named parameters (PHP 8.0+)
$results = Indonesia::search(
    term: 'Bakongan',
    type: 'village',
    perPage: 15,
    columns: ['code', 'name', 'latitude', 'longitude']
);

// Response dengan pagination sama dengan format getRegions()
```

### Mencari Berdasarkan Kode (findByCode)

```php
// Regular parameters
$region = Indonesia::findByCode('11.01.01.2001');
$region = Indonesia::findByCode('11.01.01.2001', ['code', 'name']);

// Named parameters (PHP 8.0+)
$region = Indonesia::findByCode(
    code: '11.01.01.2001',
    columns: ['code', 'name', 'latitude', 'longitude']
);

// Response:
{
    "code": "11.01.01.2001",
    "name": "KEUDE BAKONGAN",
    "postal_code": "23773",
    "latitude": 3.1618538408941346,
    "longitude": 97.43651771865193,
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
    "fullAddress": "KEUDE BAKONGAN, BAKONGAN, KAB. ACEH SELATAN, ACEH, 23773"
}
```

### Alamat Lengkap (getFullAddress)

```php
$address = Indonesia::getFullAddress('11.01.01.2001');

// Response:
"KEUDE BAKONGAN, BAKONGAN, KAB. ACEH SELATAN, ACEH, 23773"

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

## Struktur Kode Wilayah

-   Provinsi: 2 digit (contoh: 11)
-   Kota/Kabupaten: 5 digit (contoh: 11.01)
-   Kecamatan: 8 digit (contoh: 11.01.01)
-   Desa/Kelurahan: 13 digit (contoh: 11.01.01.2001)

## Kolom Database

-   code : Kode wilayah (primary key)
-   name : Nama wilayah
-   postal_code : Kode pos (untuk desa/kelurahan)
-   latitude : Koordinat garis lintang
-   longitude : Koordinat garis bujur
-   status : Status wilayah aktif/tidak aktif (optional)

## Method Parameters

### getRegions

-   `parentCode` (string|null) : Kode wilayah parent (opsional)
-   `columns` (array|null) : Kolom yang akan diambil (default: ['code', 'name', 'postal_code'])
-   `perPage` (int|null) : Jumlah data per halaman untuk pagination (opsional)

### search

-   `term` (string) : Kata kunci pencarian
-   `type` (string|null) : Tipe wilayah ('province'|'city'|'district'|'village')
-   `perPage` (int|null) : Jumlah data per halaman untuk pagination (opsional)

### findByCode

-   `code` (string) : Kode wilayah
-   `columns` (array|null) : Kolom yang akan diambil (default: ['*'])

### getForSelect

-   `parentCode` (string|null) : Kode wilayah parent (opsional)

### getRegionInfo

-   `code` (string) : Kode wilayah
-   `columns` (array|null) : Kolom yang akan diambil (default: ['code', 'name', 'postal_code'])

### getFullAddress

-   `villageCode` (string) : Kode desa/kelurahan

### findByPostalCode

-   `postalCode` (string) : Kode pos

### validateCode

-   `code` (string) : Kode wilayah yang akan divalidasi

### getRegionType

-   `code` (string) : Kode wilayah

## Ucapan Terima Kasih

Package ini menggunakan data wilayah dari [cahyadsn/wilayah](https://github.com/cahyadsn/wilayah) . Terima kasih kepada [@cahyadsn](https://github.com/cahyadsn) yang telah menyediakan dan memelihara data wilayah Indonesia.

## Kontribusi

Silakan buat issue atau pull request untuk kontribusi.

## Lisensi

Package ini di bawah lisensi MIT.
