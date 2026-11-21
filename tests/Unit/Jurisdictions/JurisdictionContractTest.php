<?php

use App\Jurisdictions\Contracts\Jurisdiction;
use App\Jurisdictions\Support\JurisdictionManager;

it('satisfies the jurisdiction contract for every registered country', function () {
    $jurisdictions = app(JurisdictionManager::class)->all();

    expect($jurisdictions)->not->toBeEmpty();

    foreach ($jurisdictions as $code => $jurisdiction) {
        expect($jurisdiction)->toBeInstanceOf(Jurisdiction::class)
            ->and($jurisdiction->code())->toBe($code)
            ->and($jurisdiction->name())->not->toBe('')
            ->and($jurisdiction->currency())->not->toBe('')
            ->and($jurisdiction->currencySymbol())->not->toBe('')
            ->and($jurisdiction->locale())->not->toBe('')
            ->and($jurisdiction->timezone())->not->toBe('')
            ->and($jurisdiction->statutoryDriver()->code())->not->toBe('')
            ->and($jurisdiction->payrollPolicy()->dayTypeMultipliers())->not->toBeEmpty()
            ->and($jurisdiction->payrollPolicy()->specialRuns())->not->toBeEmpty();
    }
});

it('does not reference the old statutory namespace anywhere in the application', function () {
    $matches = [];
    $root = base_path();

    foreach ([app_path(), base_path('routes'), base_path('config'), base_path('database')] as $path) {
        if (! is_dir($path)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if (is_string($contents) && str_contains($contents, 'App\\Services\\Statutory\\Philippines')) {
                $matches[] = str_replace($root.'/', '', $file->getPathname());
            }
        }
    }

    expect($matches)->toBe([]);
});
