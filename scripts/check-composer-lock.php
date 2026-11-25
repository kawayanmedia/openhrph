<?php

declare(strict_types=1);

/**
 * Fails when composer.lock pins a package to a path repository.
 *
 * A path lock can only be installed where the package sources live next to the
 * application, so it breaks every deployment built from a git checkout. Local
 * development uses composer.local.lock instead.
 */
$lockPath = dirname(__DIR__).'/composer.lock';

$lock = json_decode((string) file_get_contents($lockPath), true, 512, JSON_THROW_ON_ERROR);

$offenders = [];

foreach ([...($lock['packages'] ?? []), ...($lock['packages-dev'] ?? [])] as $package) {
    if (($package['dist']['type'] ?? null) === 'path') {
        $offenders[] = $package['name'];
    }
}

if ($offenders !== []) {
    fwrite(STDERR, 'composer.lock pins path repositories: '.implode(', ', $offenders).PHP_EOL);
    fwrite(STDERR, 'Run `composer update` with the committed composer.json before deploying.'.PHP_EOL);

    exit(1);
}

fwrite(STDOUT, 'composer.lock has no path repository entries.'.PHP_EOL);
