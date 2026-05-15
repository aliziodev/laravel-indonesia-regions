<?php

namespace Aliziodev\IndonesiaRegions\Traits;

use Aliziodev\IndonesiaRegions\Models\IndonesiaRegion;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait RegionHelperTrait
{
    protected const CACHE_TTL = 86400;

    protected const ALL_COLUMNS = ['code', 'name', 'postal_code', 'status'];

    protected const DEFAULT_COLUMNS = ['code', 'name', 'postal_code'];

    protected const PER_PAGE = 10;

    protected const LIMIT_FULL_TEXT_SEARCH = 100;

    protected const DEFAULT_COUNTRY = 'Indonesia';

    protected const ALLOWED_COUNTRIES = [
        'Indonesia' => 'Indonesia',
        'indonesia' => 'Indonesia',
        'ID' => 'ID',
        'id' => 'ID',
    ];

    protected const REGION_TYPES = [
        'province' => 2,
        'city' => 5,
        'district' => 8,
        'village' => 13,
    ];

    protected const QUERY_CONFIG = [
        'chunk_size' => 500,
        'max_results' => 1000,
    ];

    protected const DB_CONFIG = [
        'functions' => [
            'mysql' => [
                'length' => 'LENGTH',
                'substring' => 'SUBSTRING',
            ],
            'pgsql' => [
                'length' => 'LENGTH',
                'substring' => 'SUBSTRING',
            ],
            'sqlsrv' => [
                'length' => 'LEN',
                'substring' => 'SUBSTRING',
            ],
            'sqlite' => [
                'length' => 'LENGTH',
                'substring' => 'SUBSTR',
            ],
        ],
    ];

    protected const DB_INDEXES = [
        'name' => 'name',
        'postal_code' => 'postal_code',
        'code_province' => 'idx_region_code_province',
        'code_city' => 'idx_region_code_city',
        'code_district' => 'idx_region_code_district',
        'code_length' => 'idx_region_code_length',
    ];

    protected function getDbIndex(string $type): string
    {
        return self::DB_INDEXES[$type] ?? self::DB_INDEXES['code_length'];
    }

    protected function getRegionConnectionName(): ?string
    {
        $connection = config('indonesia-regions.database.connection');

        return is_string($connection) && $connection !== '' ? $connection : null;
    }

    protected function getRegionConnection(): ConnectionInterface
    {
        return DB::connection($this->getRegionConnectionName());
    }

    protected function getRegionDatabaseDriver(): string
    {
        return $this->getRegionConnection()->getDriverName();
    }

    protected function cache(): CacheRepository
    {
        $store = config('indonesia-regions.cache.store');

        return $store ? Cache::store($store) : Cache::store();
    }

    protected function cacheTtl(): int
    {
        return (int) config('indonesia-regions.cache.ttl', self::CACHE_TTL);
    }

    protected function cachePrefix(): string
    {
        return (string) config('indonesia-regions.cache.prefix', 'indonesia_regions');
    }

    protected function cacheIndexKey(): string
    {
        return $this->cachePrefix().'.__keys__';
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
        $key = $this->cachePrefix().".{$prefix}.{$identifier}";

        if ($params !== [] && $params !== '') {
            $normalized = is_array($params) ? implode('.', $params) : $params;
            $key .= '.'.md5($normalized);
        }

        return $key;
    }

    protected function rememberCache(string $key, callable $callback): mixed
    {
        $value = $this->cache()->remember($key, $this->cacheTtl(), $callback);
        $this->trackCacheKey($key);

        return $value;
    }

    protected function trackCacheKey(string $key): void
    {
        $indexKey = $this->cacheIndexKey();
        $keys = $this->cache()->get($indexKey, []);

        if (! is_array($keys)) {
            $keys = [];
        }

        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            $this->cache()->put($indexKey, $keys, $this->cacheTtl());
        }
    }

    protected function formatName(string $name): string
    {
        return ucwords(strtolower($name));
    }

    protected function formatNameIfPresent(mixed $name): ?string
    {
        return is_string($name) && $name !== '' ? $this->formatName($name) : null;
    }

    protected function buildRegionData(IndonesiaRegion $region, string $type, array $columns): array
    {
        return array_reduce($columns, function (array $data, string $column) use ($region, $type): array {
            if ($column === 'postal_code' && $type !== 'village') {
                return $data;
            }

            $data[$column] = $column === 'name'
                ? $this->formatNameIfPresent($region->name)
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
            'postal_code' => $isRaw ? 'postal_code' : 'village.postal_code',
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
        $countryName = $this->normalizeCountryName($countryName);
        $parts = $this->buildAddressParts($data, $isRaw);

        $addressParts = array_filter([
            $this->formatNameIfPresent($parts['village']),
            $this->formatNameIfPresent($parts['district']),
            $this->formatNameIfPresent($parts['city']),
            $this->formatNameIfPresent($parts['province']),
            $countryName,
            $parts['postal_code'],
        ]);

        return implode(', ', $addressParts);
    }

    protected function normalizeCountryName(?string $countryName): string
    {
        if ($countryName === null || $countryName === '') {
            return self::DEFAULT_COUNTRY;
        }

        if (! isset(self::ALLOWED_COUNTRIES[$countryName])) {
            throw new \InvalidArgumentException('Invalid country name. Allowed values: Indonesia, ID');
        }

        return self::ALLOWED_COUNTRIES[$countryName];
    }

    protected function buildRegionQuery(?string $parentCode): Builder
    {
        $query = IndonesiaRegion::query();
        $lengthFunc = $this->getLengthFunction();

        if ($parentCode === null) {
            $query = $this->applyIndexHint($query, 'code_province')
                ->whereRaw("{$lengthFunc}(code) = ?", [self::REGION_TYPES['province']]);
        } else {
            $length = strlen($parentCode);
            $indexType = match ($length) {
                2 => 'code_city',
                5 => 'code_district',
                8 => 'code_length',
                default => throw new \InvalidArgumentException('Invalid parent code length'),
            };

            $query = $this->applyIndexHint($query, $indexType)
                ->where('code', 'like', $parentCode.'.%')
                ->whereRaw("{$lengthFunc}(code) = ?", [
                    match ($length) {
                        2 => self::REGION_TYPES['city'],
                        5 => self::REGION_TYPES['district'],
                        8 => self::REGION_TYPES['village'],
                        default => throw new \InvalidArgumentException('Invalid parent code length'),
                    },
                ]);
        }

        return $query->orderBy('name');
    }

    protected function applyIndexHint(Builder $query, string $indexType): Builder
    {
        if ($this->getRegionDatabaseDriver() === 'mysql') {
            $query->from(DB::raw('indonesia_regions FORCE INDEX ('.$this->getDbIndex($indexType).')'));
        }

        return $query;
    }

    protected function getCachedRegions(?string $parentCode, array $columns): Collection
    {
        $cacheKey = $this->getCacheKey('regions', $parentCode ?? 'root', $columns);

        $data = $this->rememberCache($cacheKey, function () use ($parentCode, $columns) {
            return $this->buildRegionQuery($parentCode)->get($columns)->toArray();
        });

        return collect($data)->map(fn (array $item) => (new IndonesiaRegion)->fill($item));
    }

    protected function getDatabaseFunction(string $type): string
    {
        $driver = $this->getRegionDatabaseDriver();

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

    protected function applyCaseInsensitiveLike(Builder|\Illuminate\Database\Query\Builder $query, string $column, string $term, string $boolean = 'and'): void
    {
        $pattern = '%'.$term.'%';

        if ($this->getRegionDatabaseDriver() === 'pgsql') {
            $method = $boolean === 'or' ? 'orWhere' : 'where';
            $query->{$method}($column, 'ilike', $pattern);

            return;
        }

        $method = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';
        $query->{$method}('LOWER('.$column.') LIKE ?', [mb_strtolower($pattern)]);
    }

    protected function handleError(\Exception $e, string $context = ''): void
    {
        report("[Indonesia Region] {$context}: ".$e->getMessage());
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
        if ($type && ! isset(self::REGION_TYPES[$type])) {
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
                if (! isset($data->$field)) {
                    $this->handleError(new \InvalidArgumentException("Missing required field: {$field}"), 'Address Validation');
                }

                continue;
            }

            $parts = explode('.', $field);
            if (! isset($data[$parts[0]][$parts[1]])) {
                $this->handleError(new \InvalidArgumentException("Missing required field: {$field}"), 'Address Validation');
            }
        }
    }

    protected function optimizeQuery(Builder $query, ?array $columns = null): Builder
    {
        return $query->select($this->resolveColumns($columns))
            ->limit(self::QUERY_CONFIG['max_results']);
    }

    protected function validateRegionCode(string $code): void
    {
        $length = strlen($code);
        if (! in_array($length, self::REGION_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid region code length: {$length}");
        }
    }

    protected function getRegionTypeFromCode(string $code): ?string
    {
        $length = strlen($code);

        return array_search($length, self::REGION_TYPES, true) ?: null;
    }

    protected function getRegionHierarchy(string $code): array
    {
        return [
            'province' => substr($code, 0, 2),
            'city' => strlen($code) >= 5 ? substr($code, 0, 5) : null,
            'district' => strlen($code) >= 8 ? substr($code, 0, 8) : null,
            'village' => strlen($code) === 13 ? $code : null,
        ];
    }
}
