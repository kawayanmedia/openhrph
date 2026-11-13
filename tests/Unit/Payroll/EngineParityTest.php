<?php

use App\Services\Payroll\Engine\Parity\ParityCorpus;

/**
 * The Go payroll engine and the PHP driver must produce the same payslip for
 * the same input. The scenarios in payroll-engine/internal/payroll/testdata/parity
 * are computed by the Go engine (see parity_test.go); this suite replays them
 * through the PHP port so drift on either side fails the build.
 */
it('has parity fixtures committed', function () {
    expect(ParityCorpus::fixtures())->not->toBeEmpty();
});

it('reproduces the Go payroll engine for every parity fixture', function () {
    $fixtures = ParityCorpus::fixtures();

    expect($fixtures)->not->toBeEmpty();

    $mismatches = ParityCorpus::verify();

    expect($mismatches)->toBe([], "Payroll engines diverged:\n".implode("\n", $mismatches));
});
