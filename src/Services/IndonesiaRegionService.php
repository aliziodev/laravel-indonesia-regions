<?php

namespace Aliziodev\IndonesiaRegions\Services;

use Aliziodev\IndonesiaRegions\Contracts\IndonesiaRegionInterface;
use Aliziodev\IndonesiaRegions\Models\IndonesiaRegion;
use Aliziodev\IndonesiaRegions\Traits\RegionHelperTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;

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
     * @param string|null $parentCode Parent region code
     * @param array|null $columns Columns to retrieve
     * @param int|null $perPage Items per page for pagination
     * @return Collection|LengthAwarePaginator
     */
    public function getRegions(?string $parentCode = null, ?array $columns = null, ?int $perPage = null): Collection|LengthAwarePaginator
    {
        $columns = $this->resolveColumns($columns);

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

        $query = $this->buildRegionQuery($parentCode);
        return $perPage
            ? $query->paginate($perPage, $columns)
            : $this->getCachedRegions($parentCode, $columns);
    }

    /**
     * Get detailed region information including hierarchy
     * 
     * @param string $code Region code
     * @param array|null $columns Columns to retrieve
     * @return array Region information with hierarchy
     */
    public function getRegionInfo(string $code, ?array $columns = null): array
    {
        $columns = $this->resolveColumns($columns);
        $cacheKey = $this->getCacheKey('hierarchy', $code, $columns);

        return Cache::store('file')->remember($cacheKey, self::CACHE_TTL, function () use ($code, $columns) {
            $regions = [];

            $regionTypes = [
                'province' => substr($code, 0, 2),
                'city' => substr($code, 0, 5),
                'district' => substr($code, 0, 8),
                'village' => strlen($code) === 13 ? $code : null
            ];

            foreach ($regionTypes as $type => $regionCode) {
                if ($regionCode && $region = $this->findByCode($regionCode, $columns)) {
                    $regions[$type] = $this->buildRegionData($region, $type, $columns);
                }
            }

            if (empty($regions['province'])) {
                return [];
            }

            $regions['full_address'] = $this->buildFullAddress($regions, array_keys($regionTypes));
            return $regions;
        });
    }

    /**
     * Find region by its code
     * 
     * @param string $code Region code
     * @param array|null $columns Columns to retrieve
     * @return IndonesiaRegion|null
     */
    public function findByCode(string $code, ?array $columns = null): ?IndonesiaRegion
    {
        $columns = $this->resolveColumns($columns);
        $cacheKey = $this->getCacheKey('region', $code, $columns);

        return Cache::store('file')->remember($cacheKey, self::CACHE_TTL, function () use ($code, $columns) {
            return IndonesiaRegion::select($columns)->find($code);
        });
    }

    /**
     * Search for regions by name or postal code
     * 
     * @param string $term Search term (region name or postal code)
     * @param string|null $type Region type filter (province, city, district, village)
     * @param int|null $perPage Items per page for pagination
     * @param array|null $columns Columns to retrieve
     * @return \Illuminate\Support\Collection|\Illuminate\Pagination\LengthAwarePaginator
     */
    public function search(string $term, ?string $type = null, ?int $perPage = null, ?array $columns = null): Collection|LengthAwarePaginator
    {
        // Only cache if not paginated
        if ($perPage) {
            return $this->performSearch($term, $type, $perPage, $columns);
        }

        $cacheKey = $this->getCacheKey('search', $term, [
            'type' => $type ?? 'all',
            'columns' => $columns ? implode(',', $columns) : 'default'
        ]);

        return Cache::store('file')->remember($cacheKey, self::CACHE_TTL, function () use ($term, $type, $columns) {
            return $this->performSearch($term, $type, null, $columns);
        });
    }

    /**
     * Search for regions by name or postal code with full address
     * 
     * @param string $term Search term (region name or postal code)
     * @param string|null $type Region type filter (province, city, district, village)
     * @param int|null $perPage Items per page for pagination
     * @param array|null $columns Columns to retrieve
     * @return Collection|LengthAwarePaginator
     */
    public function searchWithAddress(string $term, ?string $type = null, ?int $perPage = null, ?array $columns = null): Collection|LengthAwarePaginator
    {
        $results = $this->search($term, $type, $perPage, $columns);

        // Transform results to include full address
        $results->transform(function ($item) {
            $item = is_array($item) ? (object)$item : $item;
            $item->full_address = $this->getFullAddress($item->code);
            return $item;
        });

        return $results;
    }

    /**
     * Perform search for regions
     *
     * @param string $term Search term
     * @param string|null $type Region type (province, city, district, village)
     * @param int|null $perPage Items per page for pagination
     * @param array|null $columns Columns to retrieve
     * @return Collection|LengthAwarePaginator
     */
    protected function performSearch(string $term, ?string $type = null, ?int $perPage = null, ?array $columns = null): Collection|LengthAwarePaginator
    {
        $columns = $this->resolveColumns($columns);

        // Always include code and name
        $selectedColumns = array_unique(array_merge(['code', 'name'], $columns));

        // Add postal_code for village searches or if explicitly requested
        if ($type === 'village' || in_array('postal_code', $columns)) {
            $selectedColumns[] = 'postal_code';
        }

        $query = IndonesiaRegion::query()
            ->select($selectedColumns)
            ->where(function ($q) use ($term, $type) {
                $q->where('name', 'like', "%{$term}%");

                if (is_numeric($term)) {
                    // Only include postal code search for villages or when type is not specified
                    if ($type === 'village' || $type === null) {
                        $q->orWhere(function ($sq) use ($term) {
                            $sq->where('postal_code', 'like', "%{$term}%")
                                ->whereRaw('LENGTH(code) = ?', [self::REGION_TYPES['village']]);
                        });
                    }
                }
            });

        if ($type && isset(self::REGION_TYPES[$type])) {
            $query->whereRaw('LENGTH(code) = ?', [self::REGION_TYPES[$type]]);
        }

        $results = $perPage
            ? $query->paginate($perPage)
            : $query->get();

        // Remove postal_code from non-village results
        if (in_array('postal_code', $selectedColumns)) {
            $results->transform(function ($item) {
                if (strlen($item->code) !== 13) {
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
     * @param string $villageCode Village code
     * @return string|null Full address with postal code
     */
    public function getFullAddress(string $villageCode): ?string
    {
        return $this->getRegionInfo($villageCode)['full_address'] ?? null;
    }

    /**
     * Get regions formatted for select input
     * 
     * @param string|null $parentCode Parent region code
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
     * @param string $postalCode Postal code
     * @return IndonesiaRegion|null
     */
    public function findByPostalCode(string $postalCode): ?IndonesiaRegion
    {
        $cacheKey = "postal_code.{$postalCode}";

        return Cache::store('file')->remember($cacheKey, self::CACHE_TTL, function () use ($postalCode) {
            return IndonesiaRegion::where('postal_code', $postalCode)
                ->select(self::DEFAULT_COLUMNS)
                ->first();
        });
    }

    /**
     * Get region type based on code length
     * 
     * @param string $code Region code
     * @return string|null Region type (province, city, district, village)
     */
    public function getRegionType(string $code): ?string
    {
        $length = strlen($code);
        return array_search($length, self::REGION_TYPES, true) ?: null;
    }

    /**
     * Validate region code format
     * 
     * @param string $code Region code to validate
     * @return bool True if valid, false otherwise
     */
    public function validateCode(string $code): bool
    {
        $type = $this->getRegionType($code);
        if (!$type) {
            return false;
        }

        // Then verify if the code exists in the database
        return Cache::store('file')->remember("validate_code.{$code}", self::CACHE_TTL, function () use ($code) {
            return IndonesiaRegion::where('code', $code)->exists();
        });
    }

    /**
     * Clear all region-related cache
     *
     * @return bool True if successful, false otherwise
     */
    public function clearCache(): bool
    {
        try {
            $patterns = [
                'indonesia_regions.*',
                'region.*',
                'regions.*',
                'hierarchy.*',
                'postal_code.*',
                'search.*',
                'validate_code.*'
            ];

            foreach ($patterns as $pattern) {
                try {
                    Cache::store('file')->forget($pattern);
                } catch (\Exception $e) {
                    continue;
                }
            }

            // Try specific cache clear first
            try {
                Cache::store('file')->flush();
            } catch (\Exception $e) {
                // If file store fails, try default store
                Cache::flush();
            }

            return true;
        } catch (\Exception $e) {
            report($e); // Log the error
            return false;
        }
    }
}
