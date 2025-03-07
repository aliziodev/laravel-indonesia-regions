<?php

namespace Aliziodev\IndonesiaRegions\Contracts;

use Aliziodev\IndonesiaRegions\Models\IndonesiaRegion;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Indonesia Region Service Contract
 */
interface IndonesiaRegionInterface
{
    /**
     * Get regions based on parent code
     */
    public function getRegions(?string $parentCode = null, ?array $columns = null, ?int $perPage = null): Collection|LengthAwarePaginator;

    /**
     * Get detailed region information including hierarchy
     */
    public function getRegionInfo(string $code, ?array $columns = null): array;

    /**
     * Find region by its code
     */
    public function findByCode(string $code, ?array $columns = null): ?IndonesiaRegion;

    /**
     * Search regions by name or postal code
     */
    public function search(string $term, ?string $type = null, ?int $perPage = null, ?array $columns = null): Collection|LengthAwarePaginator;

    /**
     * Search for regions by name or postal code with full address
     */
    public function searchWithAddress(string $term, ?string $type = null, ?int $perPage = null, ?array $columns = null): Collection|LengthAwarePaginator;

    /**
     * Get full address string for a village code
     */
    public function getFullAddress(string $villageCode): ?string;

    /**
     * Get regions formatted for select input
     */
    public function getForSelect(?string $parentCode = null): array;

    /**
     * Find region by postal code
     */
    public function findByPostalCode(string $postalCode): ?IndonesiaRegion;

    /**
     * Get region type based on code length
     */
    public function getRegionType(string $code): ?string;

    /**
     * Validate region code format
     */
    public function validateCode(string $code): bool;

    /**
     * Clear all region-related cache
     */
    public function clearCache(): bool;
}
