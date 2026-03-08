<?php

namespace Aliziodev\IndonesiaRegions\Database\Seeders;

use Aliziodev\IndonesiaRegions\Models\IndonesiaRegion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;

class IndonesiaRegionSeeder extends Seeder
{
    public function run(): void
    {
        $dataPath = (string) config('indonesia-regions.data_path', dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'data');
        $paths = $this->datasetPaths($dataPath);

        if ($paths === []) {
            $this->command?->error('Dataset files not found in: '.$dataPath);

            return;
        }

        $this->command?->info('Starting to sync Indonesia regions from PHP dataset...');

        $rows = $this->loadRows($paths);
        $chunks = array_chunk($rows, 1000);
        $total = count($rows);
        $totalChunks = count($chunks);

        $this->command?->info("Total rows   : {$total}");
        $this->command?->info("Total chunks : {$totalChunks} (1.000 rows/chunk)");
        $this->command?->newLine();

        /** @var ProgressBar|null $bar */
        $bar = $this->command?->getOutput()->createProgressBar($total);
        $bar?->setFormat(
            " %current%/%max% [%bar%] %percent:3s%%\n".
            " Chunk: %chunk% | Elapsed: %elapsed:6s% | Remaining: %estimated:-6s%\n"
        );
        $bar?->setMessage('0/'.$totalChunks, 'chunk');
        $bar?->start();

        foreach ($chunks as $i => $chunk) {
            IndonesiaRegion::query()->upsert(
                $chunk,
                ['code'],
                ['name', 'postal_code', 'status', 'search_text']
            );

            $bar?->setMessage(($i + 1).'/'.$totalChunks, 'chunk');
            $bar?->advance(count($chunk));
        }

        $bar?->finish();

        $this->command?->newLine(1);
        $this->command?->info("✓ Successfully upserted {$total} region rows.");
        $this->command?->newLine(1);
    }

    /**
     * @return list<string>
     */
    protected function datasetPaths(string $dataPath): array
    {
        $paths = [];

        foreach ([
            $dataPath.DIRECTORY_SEPARATOR.'provinces.php',
            $dataPath.DIRECTORY_SEPARATOR.'regencies.php',
        ] as $path) {
            if (File::exists($path)) {
                $paths[] = $path;
            }
        }

        foreach (['districts', 'villages'] as $directory) {
            $glob = glob($dataPath.DIRECTORY_SEPARATOR.$directory.DIRECTORY_SEPARATOR.'*.php') ?: [];

            sort($glob);

            foreach ($glob as $path) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * @param  list<string>  $paths
     * @return list<array{code:string,name:string,postal_code:?string,status:string,search_text:?string}>
     */
    protected function loadRows(array $paths): array
    {
        $rows = [];

        foreach ($paths as $path) {
            $data = require $path;

            if (! is_array($data)) {
                throw new RuntimeException("Dataset file must return an array: {$path}");
            }

            foreach ($data as $row) {
                if (! is_array($row) || ! isset($row['code'], $row['name'])) {
                    continue;
                }

                $rows[(string) $row['code']] = [
                    'code' => (string) $row['code'],
                    'name' => mb_strtoupper((string) $row['name']),
                    'postal_code' => isset($row['postal_code']) && $row['postal_code'] !== ''
                        ? (string) $row['postal_code']
                        : null,
                    'status' => 'active',
                    'search_text' => null,
                ];
            }
        }

        foreach ($rows as $code => &$row) {
            if (strlen($code) !== 13) {
                continue;
            }

            $row['search_text'] = $this->buildSearchText($code, $rows);
        }
        unset($row);

        ksort($rows);

        return array_values($rows);
    }

    /**
     * @param  array<string,array{code:string,name:string,postal_code:?string,status:string,search_text:?string}>  $rows
     */
    protected function buildSearchText(string $code, array $rows): string
    {
        $village = $rows[$code] ?? null;
        $district = $rows[substr($code, 0, 8)] ?? null;
        $city = $rows[substr($code, 0, 5)] ?? null;
        $province = $rows[substr($code, 0, 2)] ?? null;

        $parts = array_filter([
            $this->normalizeSearchSegment($village['name'] ?? null),
            $this->normalizeSearchSegment($district['name'] ?? null),
            $this->normalizeSearchSegment($city['name'] ?? null),
            $this->normalizeSearchSegment($province['name'] ?? null),
            $village['postal_code'] ?? null,
        ]);

        return implode(' ', $parts);
    }

    protected function normalizeSearchSegment(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = preg_replace('/[^\pL\pN\s]+/u', ' ', mb_strtoupper($value));
        $normalized = preg_replace('/\s+/u', ' ', trim((string) $normalized));

        return $normalized !== '' ? $normalized : null;
    }
}
