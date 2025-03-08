<?php

namespace Aliziodev\IndonesiaRegions\Traits;

use Aliziodev\IndonesiaRegions\Models\IndonesiaRegion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

trait RegionHelperTrait
{
    protected const CACHE_TTL = 86400; // 24 hours
    protected const ALL_COLUMNS = ['code', 'name', 'postal_code', 'latitude', 'longitude', 'status'];
    protected const DEFAULT_COLUMNS = ['code', 'name', 'postal_code'];
    protected const PER_PAGE = 15;

    protected const REGION_TYPES = [
        'province' => 2,
        'city' => 5,
        'district' => 8,
        'village' => 13
    ];

    protected function resolveColumns(?array $columns): array
    {
        if ($columns === ['*']) {
            return self::ALL_COLUMNS;
        }
        return $columns ?? self::DEFAULT_COLUMNS;
    }

    protected function getCacheKey(string $prefix, string $identifier, array $columns): string
    {
        return "{$prefix}.{$identifier}." . implode('.', $columns);
    }

    protected function formatName(string $name): string
    {
        return ucwords(strtolower($name));
    }

    protected function buildRegionData(IndonesiaRegion $region, string $type, array $columns): array
    {
        $data = [];

        foreach ($columns as $column) {
            if ($column === 'postal_code' && $type !== 'village') {
                continue;
            }

            if ($column === 'name') {
                $data['name'] = $this->formatName($region->name);
            } else {
                $data[$column] = $region->$column;
            }
        }

        return $data;
    }

    protected function buildFullAddress(array $regions, array $types): string
    {
        $addressParts = array_map(function ($type) use ($regions) {
            return $regions[$type]['name'] ?? null;
        }, array_reverse($types));

        $addressParts = array_filter($addressParts);
        $addressParts[] = 'Indonesia';

        $address = implode(', ', $addressParts);
        return isset($regions['village']['postal_code'])
            ? $address . ', ' . $regions['village']['postal_code']
            : $address;
    }

    protected function buildRegionQuery(?string $parentCode): \Illuminate\Database\Eloquent\Builder
    {
        $query = IndonesiaRegion::query();

        if ($parentCode === null) {
            $query->whereRaw('LENGTH(code) = ?', [self::REGION_TYPES['province']]);
        } else {
            $query->where('code', 'like', $parentCode . '.%')
                ->whereRaw('LENGTH(code) = ?', [
                    match (strlen($parentCode)) {
                        2 => self::REGION_TYPES['city'],      // Province -> City
                        5 => self::REGION_TYPES['district'],  // City -> District
                        8 => self::REGION_TYPES['village'],   // District -> Village
                        default => 0
                    }
                ]);
        }

        return $query->orderBy('name');
    }

    protected function getCachedRegions(?string $parentCode, array $columns): Collection
    {
        $cacheKey = "indonesia_regions.{$parentCode}." . implode('.', $columns);

        try {
            return Cache::store('file')->remember($cacheKey, self::CACHE_TTL, function () use ($parentCode, $columns) {
                return $this->buildRegionQuery($parentCode)->get($columns);
            });
        } catch (\Exception $e) {
            // Fallback: return direct query result if caching fails
            return $this->buildRegionQuery($parentCode)->get($columns);
        }
    }
}
