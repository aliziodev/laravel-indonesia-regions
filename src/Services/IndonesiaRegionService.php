<?php

namespace Aliziodev\IndonesiaRegions\Services;

use Aliziodev\IndonesiaRegions\Contracts\IndonesiaRegionInterface;
use Aliziodev\IndonesiaRegions\Models\IndonesiaRegion;
use Aliziodev\IndonesiaRegions\Traits\RegionHelperTrait;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Indonesia Region Service
 *
 * Provides functionality to manage and retrieve Indonesia regional data
 */
class IndonesiaRegionService implements IndonesiaRegionInterface
{
    use RegionHelperTrait;

    /**
     * Get regions based on parent code
     *
     * @param  string|null  $parentCode  Parent region code
     * @param  array|null  $columns  Columns to retrieve
     * @param  int|null  $perPage  Items per page for pagination
     */
    public function getRegions(?string $parentCode = null, ?array $columns = null, ?int $perPage = null): Collection|LengthAwarePaginator
    {
        $columns = $this->resolveColumns($columns);
        $query = $this->buildRegionQuery($parentCode);

        // For provinces, exclude postal_code from default columns
        if ($columns === self::DEFAULT_COLUMNS) {
            if (strlen($parentCode ?? '') === 8) {
                // For villages (parent is district/kecamatan), include postal_code
                $columns = ['code', 'name', 'postal_code'];
            } else {
                // For other levels (province, city, district)
                $columns = ['code', 'name'];
            }
        }

        // Apply query optimization
        $query = $this->optimizeQuery($query, $columns);

        return $perPage
            ? $query->paginate($perPage, $columns)
            : $this->getCachedRegions($parentCode, $columns);
    }

    /**
     * Get detailed region information including hierarchy
     *
     * @param  string  $code  Region code
     * @param  array|null  $columns  Columns to retrieve
     * @return array Region information with hierarchy
     */
    public function getRegionInfo(string $code, ?array $columns = null, ?string $countryName = self::DEFAULT_COUNTRY): array
    {
        $this->validateRegionCode($code);
        $columns = $this->resolveColumns($columns);
        $cacheKey = $this->getCacheKey('hierarchy', $code, $columns);

        return $this->rememberCache($cacheKey, function () use ($code, $columns, $countryName) {
            $regions = [];
            $hierarchy = $this->getRegionHierarchy($code);

            foreach ($hierarchy as $type => $regionCode) {
                if ($regionCode && $region = $this->findByCode($regionCode, $columns)) {
                    $regions[$type] = $this->buildRegionData($region, $type, $columns);
                }
            }

            if (empty($regions['province'])) {
                return [];
            }

            $regions['full_address'] = $this->buildFullAddress($regions, $countryName);

            return $regions;
        });
    }

    /**
     * Find region by its code
     *
     * @param  string  $code  Region code
     * @param  array|null  $columns  Columns to retrieve
     */
    public function findByCode(string $code, ?array $columns = null): ?IndonesiaRegion
    {
        $columns = $this->resolveColumns($columns);
        $cacheKey = $this->getCacheKey('region', $code, $columns);

        $data = $this->rememberCache($cacheKey, function () use ($code, $columns) {
            $region = IndonesiaRegion::select($columns)->find($code);

            return $region ? $region->toArray() : null;
        });

        if ($data === null) {
            return null;
        }

        return (new IndonesiaRegion)->fill($data);
    }

    /**
     * Search for regions by name or postal code
     *
     * @param  string  $term  Search term (region name or postal code)
     * @param  string|null  $type  Region type filter (province, city, district, village)
     * @param  int|null  $perPage  Items per page for pagination
     * @param  array|null  $columns  Columns to retrieve
     */
    public function search(string $term, ?string $type = null, ?int $perPage = null, ?array $columns = null): Collection|LengthAwarePaginator
    {
        try {
            // Validate and sanitize input
            $term = trim($term);
            if (empty($term)) {
                return collect([]);
            }

            $this->validateRegionType($type);

            // Use direct search for paginated results
            if ($perPage) {
                try {
                    return $this->performSearch($term, $type, $perPage, $columns);
                } catch (\Exception $e) {
                    report('[Indonesia Region] Paginated search failed: '.$e->getMessage());

                    return new LengthAwarePaginator([], 0, $perPage);
                }
            }

            // Use cached results for non-paginated searches
            $cacheKey = $this->getCacheKey('search', $term, [
                'type' => $type ?? 'all',
                'columns' => $columns ? implode(',', $columns) : 'default',
            ]);

            try {
                $data = $this->rememberCache($cacheKey, function () use ($term, $type, $columns) {
                    return $this->performSearch($term, $type, null, $columns)->toArray();
                });

                return collect($data)->map(fn (array $item) => (new IndonesiaRegion)->fill($item));
            } catch (\Exception $e) {
                report('[Indonesia Region] Cached search failed: '.$e->getMessage());

                return collect([]);
            }
        } catch (\Exception $e) {
            report('[Indonesia Region] Search operation failed: '.$e->getMessage());

            return $perPage ? new LengthAwarePaginator([], 0, $perPage) : collect([]);
        }
    }

    /**
     * Search for regions by name or postal code with full address
     *
     * @param  string  $term  Search term (region name or postal code)
     * @param  string|null  $type  Region type filter (province, city, district, village)
     * @param  int|null  $perPage  Items per page for pagination
     * @param  array|null  $columns  Columns to retrieve
     */
    public function searchWithAddress(string $term, ?string $type = null, ?int $perPage = null, ?array $columns = null, ?string $countryName = self::DEFAULT_COUNTRY): Collection|LengthAwarePaginator
    {
        $results = $this->search($term, $type, $perPage, $columns);

        // Transform results to include full address
        $results->transform(function ($item) use ($countryName) {
            $item = is_array($item) ? (object) $item : $item;
            $item->full_address = $this->getFullAddress($item->code, $countryName);

            return $item;
        });

        return $results;
    }

    /**
     * Search for regions by name or postal code with full address
     * Always returns results up to village level
     *
     * @param  string  $term  Search term (region name or postal code)
     * @param  int|null  $limit  Maximum number of results to return (default: 100)
     * @param  string|null  $countryName  Country name to append (default: 'Indonesia')
     */
    public function searchWithFullText(string $term, ?int $limit = self::LIMIT_FULL_TEXT_SEARCH, ?string $countryName = self::DEFAULT_COUNTRY): Collection
    {
        $cacheKey = $this->getCacheKey('full_text_search', $term, [
            'limit' => $limit,
            'country' => $countryName,
        ]);

        return $this->rememberCache($cacheKey, function () use ($term, $limit, $countryName) {
            return $this->performFullTextSearch($term, $limit, $countryName);
        });
    }

    protected function performFullTextSearch(string $term, ?int $limit, ?string $countryName): Collection
    {
        try {
            $lengthFunc = $this->getLengthFunction();
            $substringFunc = $this->getSubstringFunction();
            $driver = $this->getRegionDatabaseDriver();

            $query = IndonesiaRegion::query()
                ->select([
                    'v.code as village_code',
                    'v.name as village_name',
                    'v.postal_code',
                    'p.name as province_name',
                    'c.name as city_name',
                    'd.name as district_name',
                ])
                ->from('indonesia_regions as v')
                ->join('indonesia_regions as d', DB::raw("$substringFunc(v.code, 1, 8)"), '=', 'd.code')
                ->join('indonesia_regions as c', DB::raw("$substringFunc(v.code, 1, 5)"), '=', 'c.code')
                ->join('indonesia_regions as p', DB::raw("$substringFunc(v.code, 1, 2)"), '=', 'p.code')
                ->whereRaw("$lengthFunc(v.code) = ?", [self::REGION_TYPES['village']])
                ->whereNotNull('v.search_text')
                ->limit(min($limit, self::QUERY_CONFIG['max_results']));

            if ($driver === 'pgsql') {
                $query
                    ->whereRaw("to_tsvector('simple', COALESCE(v.search_text, '')) @@ plainto_tsquery('simple', ?)", [$term])
                    ->orderByRaw("ts_rank(to_tsvector('simple', COALESCE(v.search_text, '')), plainto_tsquery('simple', ?)) DESC", [$term]);
            } elseif ($driver === 'mysql') {
                $query
                    ->whereRaw('MATCH(v.search_text) AGAINST (? IN NATURAL LANGUAGE MODE)', [$term])
                    ->orderByRaw('MATCH(v.search_text) AGAINST (? IN NATURAL LANGUAGE MODE) DESC', [$term]);
            } else {
                $this->applyCaseInsensitiveLike($query, 'v.search_text', $term);
            }

            return $this->handleLargeDataset($query->get(), function ($item) use ($countryName) {
                return [
                    'code' => $item->village_code,
                    'province' => $this->formatName($item->province_name),
                    'city' => $this->formatName($item->city_name),
                    'district' => $this->formatName($item->district_name),
                    'village' => $this->formatName($item->village_name),
                    'postal_code' => $item->postal_code,
                    'full_address' => $this->buildFullAddress($item, $countryName, true),
                ];
            });
        } catch (\Exception $e) {
            $this->handleError($e, 'Full text search failed');
            throw $e;
        }
    }

    /**
     * Perform search for regions
     *
     * @param  string  $term  Search term
     * @param  string|null  $type  Region type (province, city, district, village)
     * @param  int|null  $perPage  Items per page for pagination
     * @param  array|null  $columns  Columns to retrieve
     */
    protected function performSearch(string $term, ?string $type = null, ?int $perPage = null, ?array $columns = null): Collection|LengthAwarePaginator
    {
        try {
            $columns = $this->resolveColumns($columns);
            $selectedColumns = array_unique(array_merge(['code', 'name'], $columns));

            if ($type === 'village' || in_array('postal_code', $columns)) {
                $selectedColumns[] = 'postal_code';
            }

            $lengthFunc = $this->getLengthFunction();
            $query = IndonesiaRegion::query()
                ->select($selectedColumns);

            // Build search conditions
            $query->where(function ($q) use ($term, $type, $lengthFunc) {
                $this->applyCaseInsensitiveLike($q, 'name', $term);

                if (is_numeric($term) && ($type === 'village' || $type === null)) {
                    $q->orWhere(function ($sq) use ($term, $lengthFunc) {
                        $sq->where('postal_code', 'like', '%'.$term.'%')
                            ->whereRaw("{$lengthFunc}(code) = ?", [self::REGION_TYPES['village']]);
                    });
                }
            });

            // Apply region type filter
            if ($type && isset(self::REGION_TYPES[$type])) {
                $query->whereRaw("{$lengthFunc}(code) = ?", [self::REGION_TYPES[$type]]);
            }

            // Apply pagination or get all results
            $results = $perPage ? $query->paginate($perPage) : $query->get();

            return $this->handleSearchResults($results, $selectedColumns);
        } catch (\Exception $e) {
            report('[Indonesia Region] Search query failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle search results based on selected columns
     *
     * @param  Collection|LengthAwarePaginator  $results  Search results
     * @param  array  $selectedColumns  Selected columns
     * @return Collection|LengthAwarePaginator
     */
    protected function handleSearchResults($results, array $selectedColumns)
    {
        if (in_array('postal_code', $selectedColumns)) {
            $results->transform(function ($item) {
                if (strlen($item->code) !== self::REGION_TYPES['village']) {
                    unset($item->postal_code);
                }

                return $item;
            });
        }

        return $results;
    }

    /**
     * Get full address string for a village code
     *
     * @param  string  $villageCode  Village code
     * @return string|null Full address with postal code
     */
    public function getFullAddress(string $villageCode, ?string $countryName = self::DEFAULT_COUNTRY): ?string
    {
        return $this->getRegionInfo($villageCode, null, $countryName)['full_address'] ?? null;
    }

    /**
     * Get regions formatted for select input
     *
     * @param  string|null  $parentCode  Parent region code
     * @return array Array with region code as key and name as value
     */
    public function getForSelect(?string $parentCode = null): array
    {
        return $this->getCachedRegions($parentCode, ['code', 'name'])
            ->pluck('name', 'code')
            ->toArray();
    }

    /**
     * Find region by postal code
     *
     * @param  string  $postalCode  Postal code
     */
    public function findByPostalCode(string $postalCode): ?IndonesiaRegion
    {
        $cacheKey = $this->getCacheKey('postal_code', $postalCode);

        $data = $this->rememberCache($cacheKey, function () use ($postalCode) {
            $region = IndonesiaRegion::query()
                ->select($this->resolveColumns(null))
                ->where('postal_code', $postalCode)
                ->whereRaw("{$this->getLengthFunction()}(code) = ?", [self::REGION_TYPES['village']])
                ->first();

            return $region ? $region->toArray() : null;
        });

        if ($data === null) {
            return null;
        }

        return (new IndonesiaRegion)->fill($data);
    }

    /**
     * Get region type based on code length
     *
     * @param  string  $code  Region code
     * @return string|null Region type (province, city, district, village)
     */
    public function getRegionType(string $code): ?string
    {
        return $this->getRegionTypeFromCode($code);
    }

    /**
     * Validate region code format
     *
     * @param  string  $code  Region code to validate
     * @return bool True if valid, false otherwise
     */
    public function validateCode(string $code): bool
    {
        try {
            $type = $this->getRegionTypeFromCode($code);
            if (! $type) {
                return false;
            }

            $cacheKey = $this->getCacheKey('validate', $code);

            return $this->rememberCache($cacheKey, function () use ($code) {
                return IndonesiaRegion::where('code', $code)->exists();
            });
        } catch (\Exception $e) {
            $this->handleError($e, 'Code validation failed');

            return false;
        }
    }

    /**
     * Clear all region-related cache
     *
     * @return bool True if successful, false otherwise
     */
    public function clearCache(): bool
    {
        try {
            $indexKey = $this->cacheIndexKey();
            $keys = $this->cache()->get($indexKey, []);

            if (is_array($keys)) {
                foreach ($keys as $key) {
                    $this->cache()->forget($key);
                }
            }

            $this->cache()->forget($indexKey);

            return true;
        } catch (\Exception $e) {
            $this->handleError($e, 'Cache clear failed');

            return false;
        }
    }
}
