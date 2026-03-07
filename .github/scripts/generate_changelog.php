<?php

declare(strict_types=1);

if ($argc < 7) {
    fwrite(STDERR, "Usage: php generate_changelog.php <previous-version.php> <current-version.php> <changelog.md> <release-notes.md> <previous-package-version> <new-package-version>\n");
    exit(1);
}

[$script, $previousVersionPath, $currentVersionPath, $changelogPath, $releaseNotesPath, $previousPackageVersion, $newPackageVersion] = $argv;

$previous = loadVersionFile($previousVersionPath);
$current = loadVersionFile($currentVersionPath);

$releaseDate = $current['data_date'] ?? gmdate('Y-m-d');
$oldHash = shortHash($previous['source']['hash'] ?? $previous['source_hash'] ?? 'unknown');
$newHash = shortHash($current['source']['hash'] ?? $current['source_hash'] ?? 'unknown');
$oldTotal = (int) ($previous['counts']['total'] ?? 0);
$newTotal = (int) ($current['counts']['total'] ?? 0);
$delta = $newTotal - $oldTotal;

$entry = implode("\n", [
    "## [{$newPackageVersion}] — {$releaseDate}",
    '',
    '### Data Sync',
    "- Package version: `{$previousPackageVersion}` -> `{$newPackageVersion}`",
    "- Source `aliziodev/laravel-wilayah`: `{$oldHash}` -> `{$newHash}`",
    '',
    '### Statistik',
    '- Total rows: '.$oldTotal.' -> '.$newTotal.' ('.formatDelta($delta).')',
    '',
]);

$releaseNotes = implode("\n", [
    '## Update Data Indonesia Regions',
    '',
    "Release package: `v{$newPackageVersion}`",
    "Tanggal sync: `{$releaseDate}`",
    '',
    '### Source',
    "- `aliziodev/laravel-wilayah`: `{$oldHash}` -> `{$newHash}`",
    '',
    '### Statistik Data',
    '- Total rows: '.$oldTotal.' -> '.$newTotal.' ('.formatDelta($delta).')',
    '',
    '### Update di project Anda',
    '```bash',
    'composer update aliziodev/laravel-indonesia-regions',
    'php artisan indonesia-regions:clear-cache',
    '```',
    '',
]);

$existing = file_exists($changelogPath) ? file_get_contents($changelogPath) : '';
if ($existing === false) {
    fwrite(STDERR, "Failed to read {$changelogPath}\n");
    exit(1);
}

$updated = injectChangelogEntry($existing, $entry, $newPackageVersion);
file_put_contents($changelogPath, $updated);
file_put_contents($releaseNotesPath, $releaseNotes);

function loadVersionFile(string $path): array
{
    if (! file_exists($path)) {
        return [];
    }

    $data = include $path;

    return is_array($data) ? $data : [];
}

function shortHash(string $hash): string
{
    if ($hash === '' || $hash === 'unknown' || $hash === 'none') {
        return $hash;
    }

    return strlen($hash) > 12 ? substr($hash, 0, 12) : $hash;
}

function formatDelta(int $delta): string
{
    if ($delta > 0) {
        return '+'.$delta;
    }

    return (string) $delta;
}

function injectChangelogEntry(string $contents, string $entry, string $newVersion): string
{
    if (trim($contents) === '') {
        return "# Changelog\n\n{$entry}\n";
    }

    if (str_contains($contents, '## [Unreleased]')) {
        $contents = preg_replace('/## \[Unreleased\]\s*/', "## [Unreleased]\n\n---\n\n{$entry}", $contents, 1);
    } else {
        $contents = "# Changelog\n\n{$entry}\n".ltrim($contents);
    }

    if (preg_match('/^\[Unreleased\]: .*$/m', $contents)) {
        $contents = preg_replace(
            '/^\[Unreleased\]: .*$/m',
            "[Unreleased]: https://github.com/aliziodev/laravel-indonesia-regions/compare/v{$newVersion}...HEAD",
            $contents,
            1
        );
    }

    if (! preg_match('/^\['.preg_quote($newVersion, '/').'\]:/m', $contents)) {
        $contents = rtrim($contents)."\n[".$newVersion."]: https://github.com/aliziodev/laravel-indonesia-regions/releases/tag/v{$newVersion}\n";
    }

    return $contents;
}
