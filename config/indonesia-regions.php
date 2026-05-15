<?php

/**
 * Konfigurasi paket Laravel Indonesia Regions.
 *
 * File ini mengatur semua aspek paket, mulai dari sumber data,
 * koneksi database, endpoint API, hingga pengaturan cache.
 *
 * @see      https://github.com/aliziodev/laravel-indonesia-regions
 */

return [

    /**
     * Path direktori data wilayah Indonesia.
     *
     * Menunjuk ke folder yang berisi file-file PHP yang me-return array
     * dengan daftar provinsi, kabupaten/kota, kecamatan, dan kelurahan.
     * Secara default, data diambil dari direktori vendor paket ini.
     *
     * @var string
     */
    'data_path' => base_path('vendor/aliziodev/laravel-indonesia-regions/data'),

    /**
     * Pengaturan koneksi database.
     *
     * Menentukan koneksi database mana yang digunakan oleh paket ini
     * saat menyimpan atau membaca data wilayah. Jika bernilai null,
     * paket akan menggunakan koneksi default aplikasi Laravel.
     *
     * Didukung: nama koneksi yang terdefinisi di config/database.php
     * (contoh: "mysql", "pgsql", "sqlite").
     *
     * @var array{
     *     connection: string|null,
     * }
     */
    'database' => [

        /**
         * Nama koneksi database yang digunakan paket.
         *
         * Nilai diambil dari environment variable `INDONESIA_REGIONS_DB_CONNECTION`.
         * Biarkan null untuk menggunakan koneksi default Laravel.
         *
         * @var string|null
         */
        'connection' => env('INDONESIA_REGIONS_DB_CONNECTION'),
    ],

    /**
     * Pengaturan REST API wilayah Indonesia.
     *
     * Paket ini menyediakan endpoint API bawaan untuk mengakses data wilayah
     * secara langsung tanpa perlu menulis controller atau route sendiri.
     *
     * @var array{
     *     enabled:    bool,
     *     prefix:     string,
     *     middleware: string[],
     *     responder:  class-string|null,
     * }
     */
    'api' => [

        /**
         * Aktifkan atau nonaktifkan endpoint API bawaan paket.
         *
         * Jika dinonaktifkan, semua route API paket tidak akan didaftarkan.
         * Berguna saat Anda ingin mengelola route secara manual.
         *
         * @var bool
         */
        'enabled' => env('INDONESIA_REGIONS_API_ENABLED', true),

        /**
         * Prefix URL untuk semua endpoint API wilayah.
         *
         * Contoh: jika diset ke "api/indonesia-regions", maka endpoint
         * provinsi akan menjadi "/api/indonesia-regions/provinces".
         *
         * @var string
         */
        'prefix' => env('INDONESIA_REGIONS_API_PREFIX', 'api/indonesia-regions'),

        /**
         * Daftar middleware yang diterapkan pada route API.
         *
         * Anda dapat menambahkan middleware kustom, misalnya "auth:sanctum"
         * untuk membatasi akses hanya bagi pengguna yang telah terautentikasi.
         *
         * @var string[]
         */
        'middleware' => ['api'],

        /**
         * Class responder kustom untuk memformat response API.
         *
         * Jika null, paket menggunakan format response bawaan (JSON standar).
         * Anda dapat mengisi dengan class yang mengimplementasikan
         * interface responder paket untuk menyesuaikan struktur output.
         *
         * @var class-string|null
         */
        'responder' => null,
    ],

    /**
     * Pengaturan cache untuk data wilayah Indonesia.
     *
     * Caching sangat direkomendasikan untuk meningkatkan performa,
     * karena data wilayah bersifat statis dan jarang berubah.
     *
     * @var array{
     *     store:  string|null,
     *     ttl:    int,
     *     prefix: string,
     * }
     */
    'cache' => [

        /**
         * Driver cache yang digunakan oleh paket.
         *
         * Jika null, paket akan menggunakan driver cache default Laravel
         * yang terdefinisi di config/cache.php.
         * Nilai yang didukung: "redis", "memcached", "file", "array", dll.
         *
         * @var string|null
         */
        'store' => env('INDONESIA_REGIONS_CACHE_STORE'),

        /**
         * Waktu kadaluarsa cache dalam satuan detik (Time To Live).
         *
         * Nilai default 86400 detik setara dengan 24 jam.
         * Sesuaikan nilai ini berdasarkan seberapa sering data perlu diperbarui.
         *
         * @var int
         */
        'ttl' => (int) env('INDONESIA_REGIONS_CACHE_TTL', 86400),

        /**
         * Prefix yang digunakan untuk semua key cache paket.
         *
         * Berguna untuk menghindari konflik dengan key cache lain di aplikasi.
         * Pastikan prefix bersifat unik jika Anda menjalankan beberapa instance.
         *
         * @var string
         */
        'prefix' => env('INDONESIA_REGIONS_CACHE_PREFIX', 'indonesia_regions'),
    ],

    /**
     * Pengaturan format alamat.
     *
     * @var array{
     *     show_country: bool,
     * }
     */
    'address' => [

        /**
         * Tampilkan nama negara pada format alamat lengkap.
         *
         * Jika diset ke false, nama negara (seperti "Indonesia") tidak akan
         * disertakan dalam string alamat yang dihasilkan.
         *
         * @var bool
         */
        'show_country' => env('INDONESIA_REGIONS_SHOW_COUNTRY', true),
    ],
];
