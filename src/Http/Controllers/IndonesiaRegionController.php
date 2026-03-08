<?php

namespace Aliziodev\IndonesiaRegions\Http\Controllers;

use Aliziodev\IndonesiaRegions\Contracts\ApiResponderInterface;
use Aliziodev\IndonesiaRegions\Contracts\IndonesiaRegionInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class IndonesiaRegionController extends Controller
{
    public function __construct(
        protected IndonesiaRegionInterface $regions,
        protected ApiResponderInterface $responder
    ) {
    }

    public function index(Request $request)
    {
        return $this->regions->getRegions(
            parentCode: $request->query('parent_code'),
            columns: $this->parseColumns($request),
            perPage: $this->parseNullableInt($request->query('per_page'))
        );
    }

    public function show(Request $request, string $code): JsonResponse
    {
        $region = $this->regions->findByCode($code, $this->parseColumns($request));

        if ($region === null) {
            abort(404);
        }

        return $this->respond($region);
    }

    public function showInfo(Request $request, string $code): JsonResponse
    {
        return $this->respond($this->regions->getRegionInfo(
            code: $code,
            columns: $this->parseColumns($request),
            countryName: $request->query('country_name')
        ));
    }

    public function selectContext(Request $request, string $code): JsonResponse
    {
        $info = $this->regions->getRegionInfo(
            code: $code,
            columns: ['code', 'name', 'postal_code'],
            countryName: $request->query('country_name')
        );

        return $this->respond([
            'selected' => [
                'province' => $this->mapSelectOption($info['province'] ?? null),
                'city' => $this->mapSelectOption($info['city'] ?? null),
                'district' => $this->mapSelectOption($info['district'] ?? null),
                'village' => $this->mapSelectOption($info['village'] ?? null),
            ],
            'full_address' => $info['full_address'] ?? null,
        ]);
    }

    public function cascade(Request $request): JsonResponse
    {
        $regionCode = $request->query('region_code');
        $countryName = $request->query('country_name');
        $context = $regionCode ? $this->regions->getRegionInfo($regionCode, ['code', 'name', 'postal_code'], $countryName) : [];

        $selected = [
            'province' => $this->mapSelectOption($context['province'] ?? null),
            'city' => $this->mapSelectOption($context['city'] ?? null),
            'district' => $this->mapSelectOption($context['district'] ?? null),
            'village' => $this->mapSelectOption($context['village'] ?? null),
        ];

        $provinceCode = $selected['province']['value'] ?? null;
        $cityCode = $selected['city']['value'] ?? null;
        $districtCode = $selected['district']['value'] ?? null;

        return $this->respond([
            'selected' => $selected,
            'options' => [
                'provinces' => $this->mapSelectOptions($this->regions->getForSelect()),
                'cities' => $provinceCode ? $this->mapSelectOptions($this->regions->getForSelect($provinceCode)) : [],
                'districts' => $cityCode ? $this->mapSelectOptions($this->regions->getForSelect($cityCode)) : [],
                'villages' => $districtCode ? $this->mapSelectOptions($this->regions->getForSelect($districtCode)) : [],
            ],
            'full_address' => $context['full_address'] ?? null,
        ]);
    }

    public function search(Request $request)
    {
        return $this->regions->search(
            term: (string) $request->query('term', ''),
            type: $request->query('type'),
            perPage: $this->parseNullableInt($request->query('per_page')),
            columns: $this->parseColumns($request)
        );
    }

    public function searchWithAddress(Request $request)
    {
        return $this->regions->searchWithAddress(
            term: (string) $request->query('term', ''),
            type: $request->query('type'),
            perPage: $this->parseNullableInt($request->query('per_page')),
            columns: $this->parseColumns($request),
            countryName: $request->query('country_name')
        );
    }

    public function select(Request $request): JsonResponse
    {
        $items = $this->regions->getForSelect($request->query('parent_code'));

        if ($request->query('format') === 'map') {
            return $this->respond($items);
        }

        return $this->respond(
            collect($items)
                ->map(fn (string $label, string $value): array => [
                    'value' => $value,
                    'label' => $label,
                ])
                ->values()
        );
    }

    public function searchWithFullText(Request $request)
    {
        return $this->regions->searchWithFullText(
            term: (string) $request->query('term', ''),
            limit: $this->parseNullableInt($request->query('limit')),
            countryName: $request->query('country_name')
        );
    }

    public function fullAddress(Request $request, string $code): JsonResponse
    {
        return $this->respond([
            'full_address' => $this->regions->getFullAddress($code, $request->query('country_name')),
        ]);
    }

    public function findByPostalCode(string $postalCode): JsonResponse
    {
        $region = $this->regions->findByPostalCode($postalCode);

        if ($region === null) {
            abort(404);
        }

        return $this->respond($region);
    }

    protected function parseColumns(Request $request): ?array
    {
        $columns = $request->query('columns');

        if ($columns === null || $columns === '') {
            return null;
        }

        if (is_array($columns)) {
            return $columns;
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $columns))));
    }

    protected function parseNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    protected function mapSelectOption(?array $item): ?array
    {
        if ($item === null) {
            return null;
        }

        $option = [
            'value' => $item['code'] ?? null,
            'label' => $item['name'] ?? null,
        ];

        if (isset($item['postal_code'])) {
            $option['postal_code'] = $item['postal_code'];
        }

        return $option;
    }

    protected function mapSelectOptions(array $items): array
    {
        return collect($items)
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    protected function respond(mixed $data, int $status = 200): JsonResponse
    {
        return $this->responder->respond($data, $status);
    }
}
