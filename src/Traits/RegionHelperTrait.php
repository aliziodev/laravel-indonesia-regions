<?php

namespace Aliziodev\IndonesiaRegions\Traits;

use Aliziodev\IndonesiaRegions\Models\IndonesiaRegion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait RegionHelperTrait
{
    protected const CACHE_TTL = 86400; // 24 hours
    protected const ALL_COLUMNS = ['code', 'name', 'postal_code', 'latitude', 'longitude', 'status'];
    protected const DEFAULT_COLUMNS = ['code', 'name', 'postal_code'];
    protected const PER_PAGE = 10;
    protected const LIMIT_FULL_TEXT_SEARCH = 100;
    protected const DEFAULT_COUNTRY = 'Indonesia';

    protected const REGION_TYPES = [
        'province' => 2,
        'city' => 5,
        'district' => 8,
        'village' => 13
    ];

    protected const CACHE_CONFIG = [
        'store' => 'file',
        'ttl' => self::CACHE_TTL,
        'prefix' => 'indonesia_regions'
    ];

    protected const QUERY_CONFIG = [
        'chunk_size' => 500,
        'max_results' => 1000
    ];

    protected const DB_CONFIG = [
        'functions' => [
            'mysql' => [
                'length' => 'LENGTH',
                'substring' => 'SUBSTRING'
            ],
            'pgsql' => [
                'length' => 'LENGTH',
                'substring' => 'SUBSTRING'
            ],
            'sqlsrv' => [
                'length' => 'LEN',
                'substring' => 'SUBSTRING'
            ]
        ]
    ];

    protected const DB_INDEXES = [
        'name' => 'name',
        'postal_code' => 'postal_code',
        'code_province' => 'idx_region_code_province',
        'code_city' => 'idx_region_code_city',
        'code_district' => 'idx_region_code_district',
        'code_length' => 'idx_region_code_length'
    ];

    protected function getDbIndex(string $type): string
    {
        return self::DB_INDEXES[$type] ?? self::DB_INDEXES['code_length'];
    }

    protected function cache()
    {
        return Cache::store(self::CACHE_CONFIG['store']);
    }

    protected function resolveColumns(?array $columns): array
    {
        if ($columns === ['*']) {
            return self::ALL_COLUMNS;
        }
        return $columns ?? self::DEFAULT_COLUMNS;
    }

    protected function getCacheKey(string $prefix, string $identifier, array|string $params = []): string
    {
        $key = self::CACHE_CONFIG['prefix'] . ".{$prefix}.{$identifier}";

        if (!empty($params)) {
            $normalized = is_array($params) ? implode('.', $params) : $params;
            $key .= '.' . md5($normalized);
        }

        return $key;
    }

    protected function formatName(string $name): string
    {
        return ucwords(strtolower($name));
    }

    protected function buildRegionData(IndonesiaRegion $region, string $type, array $columns): array
    {
        return array_reduce($columns, function ($data, $column) use ($region, $type) {
            if ($column === 'postal_code' && $type !== 'village') {
                return $data;
            }

            $data[$column] = $column === 'name'
                ? $this->formatName($region->name)
                : $region->$column;

            return $data;
        }, []);
    }

    protected function buildAddressParts(array|object $data, bool $isRaw = false): array
    {
        $fields = [
            'village' => $isRaw ? 'village_name' : 'village.name',
            'district' => $isRaw ? 'district_name' : 'district.name',
            'city' => $isRaw ? 'city_name' : 'city.name',
            'province' => $isRaw ? 'province_name' : 'province.name',
            'postal_code' => $isRaw ? 'postal_code' : 'village.postal_code'
        ];

        $parts = [];
        foreach ($fields as $key => $field) {
            $parts[$key] = $isRaw
                ? ($data->$field ?? null)
                : (data_get($data, $field) ?? null);
        }

        return $parts;
    }

    protected function buildFullAddress(array|object $data, ?string $countryName = self::DEFAULT_COUNTRY, bool $isRaw = false): string
    {
        $parts = $this->buildAddressParts($data, $isRaw);

        $addressParts = array_filter([
            $this->formatName($parts['village']),
            $this->formatName($parts['district']),
            $this->formatName($parts['city']),
            $this->formatName($parts['province']),
            $countryName,
            $parts['postal_code']
        ]);

        return implode(', ', $addressParts);
    }

    protected function buildRegionQuery(?string $parentCode): \Illuminate\Database\Eloquent\Builder
    {
        $query = IndonesiaRegion::query();
        $lengthFunc = $this->getLengthFunction();

        if ($parentCode === null) {
            $query->from(DB::raw("indonesia_regions FORCE INDEX (" . $this->getDbIndex('code_province') . ")"))
                ->whereRaw("{$lengthFunc}(code) = ?", [self::REGION_TYPES['province']]);
        } else {
            $length = strlen($parentCode);
            $indexType = match ($length) {
                2 => 'code_city',
                5 => 'code_district',
                8 => 'code_length',
                default => throw new \InvalidArgumentException('Invalid parent code length')
            };

            $query->from(DB::raw("indonesia_regions FORCE INDEX (" . $this->getDbIndex($indexType) . ")"))
                ->where('code', 'like', $parentCode . '.%')
                ->whereRaw("{$lengthFunc}(code) = ?", [
                    match ($length) {
                        2 => self::REGION_TYPES['city'],
                        5 => self::REGION_TYPES['district'],
                        8 => self::REGION_TYPES['village'],
                        default => throw new \InvalidArgumentException('Invalid parent code length')
                    }
                ]);
        }

        return $query->orderBy('name');
    }

    protected function getCachedRegions(?string $parentCode, array $columns): Collection
    {
        $cacheKey = $this->getCacheKey('regions', $parentCode ?? 'root', $columns);

        try {
            return $this->cache()->remember($cacheKey, self::CACHE_CONFIG['ttl'], function () use ($parentCode, $columns) {
                return $this->buildRegionQuery($parentCode)->get($columns);
            });
        } catch (\Exception $e) {
            $this->handleError($e, 'Failed to retrieve cached regions');
            return $this->buildRegionQuery($parentCode)->get($columns);
        }
    }

    protected function getDatabaseFunction(string $type): string
    {
        $driver = DB::getDriverName();
        return self::DB_CONFIG['functions'][$driver][$type] ?? self::DB_CONFIG['functions']['mysql'][$type];
    }

    protected function getLengthFunction(): string
    {
        return $this->getDatabaseFunction('length');
    }

    protected function getSubstringFunction(): string
    {
        return $this->getDatabaseFunction('substring');
    }

    protected function handleError(\Exception $e, string $context = ''): void
    {
        report("[Indonesia Region] {$context}: " . $e->getMessage());
        throw new \RuntimeException("Failed to process region data: {$context}", 0, $e);
    }

    protected function handleLargeDataset(Collection $results, callable $callback): Collection
    {
        return $results->count() > self::QUERY_CONFIG['chunk_size']
            ? $results->chunk(self::QUERY_CONFIG['chunk_size'])->flatMap($callback)
            : $results->map($callback);
    }

    protected function validateRegionType(?string $type): void
    {
        if ($type && !isset(self::REGION_TYPES[$type])) {
            throw new \InvalidArgumentException("Invalid region type: {$type}");
        }
    }

    protected function validateAddressData(array|object $data, bool $isRaw = false): void
    {
        $requiredFields = $isRaw
            ? ['village_name', 'district_name', 'city_name', 'province_name']
            : ['village.name', 'district.name', 'city.name', 'province.name'];

        foreach ($requiredFields as $field) {
            if ($isRaw) {
                if (!isset($data->$field)) {
                    $this->handleError(new \InvalidArgumentException("Missing required field: {$field}"), 'Address Validation');
                }
            } else {
                $parts = explode('.', $field);
                if (!isset($data[$parts[0]][$parts[1]])) {
                    $this->handleError(new \InvalidArgumentException("Missing required field: {$field}"), 'Address Validation');
                }
            }
        }
    }

    protected function optimizeQuery(\Illuminate\Database\Eloquent\Builder $query, ?array $columns = null): \Illuminate\Database\Eloquent\Builder
    {
        return $query->select($this->resolveColumns($columns))
            ->limit(self::QUERY_CONFIG['max_results']);
    }

    protected function validateRegionCode(string $code): void
    {
        $length = strlen($code);
        if (!in_array($length, self::REGION_TYPES)) {
            throw new \InvalidArgumentException("Invalid region code length: {$length}");
        }
    }

    protected function getRegionTypeFromCode(string $code): ?string
    {
        $length = strlen($code);
        return array_search($length, self::REGION_TYPES) ?: null;
    }

    protected function getRegionHierarchy(string $code): array
    {
        return [
            'province' => substr($code, 0, 2),
            'city' => strlen($code) >= 5 ? substr($code, 0, 5) : null,
            'district' => strlen($code) >= 8 ? substr($code, 0, 8) : null,
            'village' => strlen($code) === 13 ? $code : null
        ];
    }
}
