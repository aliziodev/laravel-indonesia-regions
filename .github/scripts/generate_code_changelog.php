<?php

declare(strict_types=1);

/**
 * Generate changelog dan release notes untuk perubahan kode (bukan data sync).
 * Usage: php generate_code_changelog.php <last_tag> <new_version> <changelog.md> <release-notes.md> <commits>
 */

if ($argc < 5) {
    fwrite(STDERR, "Usage: php generate_code_changelog.php <last_tag> <new_version> <changelog.md> <release-notes.md> [commits]\n");
    exit(1);
}

[, $lastTag, $newVersion, $changelogPath, $releaseNotesPath] = $argv;
$rawCommits = $argv[5] ?? '';

$releaseDate = gmdate('Y-m-d');

// Parse daftar commit
$commitLines = array_filter(
    array_map('trim', explode("\n", $rawCommits)),
    fn ($line) => $line !== ''
);

if (empty($commitLines)) {
    $commitLines = ['- Perbaikan dan peningkatan internal'];
}

$commitList = implode("\n", $commitLines);

// ── Release Notes ────────────────────────────────────────────────────────────

$releaseNotes = implode("\n", [
    "## Perubahan Kode v{$newVersion}",
    '',
    "Release: `v{$newVersion}`",
    "Tanggal: `{$releaseDate}`",
    '',
    '### Perubahan',
    $commitList,
    '',
    '### Update di project Anda',
    '```bash',
    'composer update aliziodev/laravel-indonesia-regions',
    '```',
    '',
]);

file_put_contents($releaseNotesPath, $releaseNotes);

// ── Changelog Entry ──────────────────────────────────────────────────────────

$entry = implode("\n", [
    "## [{$newVersion}] — {$releaseDate}",
    '',
    '### Code Changes',
    $commitList,
    '',
]);

$existing = file_exists($changelogPath) ? file_get_contents($changelogPath) : '';

$updated = injectChangelogEntry($existing, $entry, $newVersion);
file_put_contents($changelogPath, $updated);

echo "✅ Changelog dan release notes untuk v{$newVersion} berhasil dibuat.\n";

// ── Helpers ──────────────────────────────────────────────────────────────────

function injectChangelogEntry(string $contents, string $entry, string $newVersion): string
{
    if (trim($contents) === '') {
        return "# Changelog\n\n{$entry}\n";
    }

    if (str_contains($contents, '## [Unreleased]')) {
        $contents = preg_replace('/## \[Unreleased\]\s*/', "## [Unreleased]\n\n---\n\n{$entry}", $contents, 1);
    } else {
        $contents = "# Changelog\n\n{$entry}\n" . ltrim($contents);
    }

    if (preg_match('/^\[Unreleased\]: .*$/m', $contents)) {
        $contents = preg_replace(
            '/^\[Unreleased\]: .*$/m',
            "[Unreleased]: https://github.com/aliziodev/laravel-indonesia-regions/compare/v{$newVersion}...HEAD",
            $contents,
            1
        );
    }

    if (! preg_match('/^\['.preg_quote($newVersion, '/').'\\]:/m', $contents)) {
        $contents = rtrim($contents)."\n[{$newVersion}]: https://github.com/aliziodev/laravel-indonesia-regions/releases/tag/v{$newVersion}\n";
    }

    return $contents;
}
