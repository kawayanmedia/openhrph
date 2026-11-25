<?php

declare(strict_types=1);

/**
 * Installs the kawayanmedia packages from the local checkouts in ./kawayanmedia
 * instead of from Packagist.
 *
 * The committed composer.json/composer.lock always resolve the released
 * Packagist versions, which is what deployments use. This script writes a
 * composer.local.json (both it and composer.local.lock are git-ignored) that
 * adds a path repository per locally checked-out package and symlinks them into
 * vendor.
 */

const PACKAGE_PREFIX = 'kawayanmedia/';
const LOCAL_ROOT = './kawayanmedia';

$root = dirname(__DIR__);

$manifestPath = $root.'/composer.json';
$localManifestPath = $root.'/composer.local.json';
$lockPath = $root.'/composer.lock';
$localLockPath = $root.'/composer.local.lock';

$manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

$required = array_values(array_filter(
    array_keys(($manifest['require'] ?? []) + ($manifest['require-dev'] ?? [])),
    static fn (string $package): bool => preg_match('/^(php|hhvm|ext-|lib-|composer-)/', $package) !== 1,
));

/** @var array<string, string> $localPackages package name => relative path */
$localPackages = [];

foreach ($required as $package) {
    if (! str_starts_with($package, PACKAGE_PREFIX)) {
        continue;
    }

    $path = LOCAL_ROOT.'/'.substr($package, strlen(PACKAGE_PREFIX));

    if (is_file($root.'/'.$path.'/composer.json')) {
        $localPackages[$package] = $path;
    }
}

$repositories = array_values(array_filter(
    $manifest['repositories'] ?? [],
    static fn (array $repository): bool => ($repository['type'] ?? null) !== 'path'
        || ! str_starts_with((string) ($repository['url'] ?? ''), LOCAL_ROOT),
));

foreach ($localPackages as $path) {
    $repositories[] = [
        'type' => 'path',
        'url' => $path,
        'options' => ['symlink' => true],
    ];
}

if ($repositories === []) {
    unset($manifest['repositories']);
} else {
    $manifest['repositories'] = $repositories;
}

file_put_contents(
    $localManifestPath,
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n",
);

if ($localPackages === []) {
    fwrite(STDERR, 'No local kawayanmedia packages found; wrote composer.local.json without path repositories.'.PHP_EOL);

    exit(0);
}

if (! is_file($localLockPath) && is_file($lockPath)) {
    copy($lockPath, $localLockPath);
}

$locked = [];

if (is_file($localLockPath)) {
    $localLock = json_decode((string) file_get_contents($localLockPath), true, 512, JSON_THROW_ON_ERROR);

    $locked = array_column([...($localLock['packages'] ?? []), ...($localLock['packages-dev'] ?? [])], 'name');
}

$arguments = ['update'];

// Partial updates keep every unrelated package pinned to the committed lock.
// A lock that predates a root requirement change needs a full update instead.
if ($locked !== [] && array_diff($required, $locked) === []) {
    $arguments = [...$arguments, ...array_keys($localPackages), '--with-dependencies'];
}

fwrite(STDOUT, sprintf(
    'Linking %s from %s',
    implode(', ', array_keys($localPackages)),
    LOCAL_ROOT,
).PHP_EOL);

exit(runComposer($root, $arguments));

/**
 * Runs Composer against composer.local.json so the local resolution keeps its
 * own lock file and the committed composer.lock stays deployable.
 *
 * @param  list<string>  $arguments
 */
function runComposer(string $root, array $arguments): int
{
    $binary = getenv('COMPOSER_BINARY') ?: 'composer';

    $command = str_ends_with($binary, '.phar')
        ? escapeshellarg(PHP_BINARY).' '.escapeshellarg($binary)
        : escapeshellarg($binary);

    $command .= ' --working-dir='.escapeshellarg($root)
        .' --no-interaction '.implode(' ', array_map('escapeshellarg', $arguments));

    putenv('COMPOSER=composer.local.json');

    passthru($command, $exitCode);

    return $exitCode;
}
