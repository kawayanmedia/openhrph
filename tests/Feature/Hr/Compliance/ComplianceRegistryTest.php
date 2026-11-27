<?php

use App\Models\Company;
use App\Models\ComplianceRule;
use App\Models\ComplianceRuleVersion;
use App\Models\User;
use App\Services\Compliance\ComplianceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->company = Company::factory()->create(['name' => 'Compliance Co']);
    $this->user = User::factory()->forCompany($this->company)->create();
    $this->actingAs($this->user);

    $this->registry = app(ComplianceRegistry::class);

    $this->source = $this->registry->registerSource([
        'authority' => 'Department of Labor and Employment',
        'title' => 'Labor Code of the Philippines',
        'issuance_number' => 'PD 442',
        'effective_from' => '1974-11-01',
    ], $this->user);
});

it('registers a rule with a draft configuration version', function (): void {
    $rule = $this->registry->registerRule($this->source, [
        'code' => 'OT_PREMIUM',
        'name' => 'Overtime premium',
        'sector' => 'Private',
        'effective_from' => now()->subYear()->toDateString(),
        'version' => '1.0.0',
    ], ['rate' => 1.25, 'basis' => 'daily_rate'], $this->user);

    expect($rule->status)->toBe(ComplianceRule::STATUS_DRAFT)
        ->and($rule->versions)->toHaveCount(1)
        ->and($rule->versions->first()->status)->toBe(ComplianceRuleVersion::STATUS_DRAFT);
});

it('submits and approves a version, then resolves it as of an effective date', function (): void {
    $rule = $this->registry->registerRule($this->source, [
        'code' => 'NIGHT_DIFF',
        'name' => 'Night shift differential',
        'sector' => 'Private',
        'effective_from' => now()->subMonth()->toDateString(),
        'version' => '2.0.0',
    ], ['rate' => 0.10], $this->user);

    $version = $rule->versions->first();

    $this->registry->submitForReview($version, $this->user);
    $approved = $this->registry->approveVersion($version->refresh(), $this->user);

    expect($approved->status)->toBe(ComplianceRuleVersion::STATUS_APPROVED)
        ->and($approved->approved_at)->not->toBeNull();

    $resolved = $this->registry->resolve('NIGHT_DIFF', 'Private', now()->toDateString());

    expect($resolved['configuration'])->toBe(['rate' => 0.1])
        ->and($resolved['rule']->code)->toBe('NIGHT_DIFF');
});

it('rejects resolution when no approved rule is effective', function (): void {
    $rule = $this->registry->registerRule($this->source, [
        'code' => 'OLD_RULE',
        'name' => 'Retired rule',
        'sector' => 'Private',
        'effective_from' => now()->subYears(2)->toDateString(),
        'effective_until' => now()->subYear()->toDateString(),
        'version' => '1.0.0',
    ], ['rate' => 1], $this->user);

    $version = $rule->versions->first();
    $this->registry->submitForReview($version, $this->user);
    $this->registry->approveVersion($version->refresh(), $this->user);

    expect(fn () => $this->registry->resolve('OLD_RULE', 'Private', now()->toDateString()))
        ->toThrow(ValidationException::class);
});

it('does not allow executable configuration keys or duplicate versions', function (): void {
    expect(fn () => $this->registry->registerRule($this->source, [
        'code' => 'BAD_RULE',
        'name' => 'Bad rule',
        'effective_from' => now()->toDateString(),
    ], ['php' => 'system("rm -rf /")'], $this->user))->toThrow(ValidationException::class);

    $rule = $this->registry->registerRule($this->source, [
        'code' => 'GOOD_RULE',
        'name' => 'Good rule',
        'effective_from' => now()->toDateString(),
        'version' => '1.0.0',
    ], ['rate' => 1], $this->user);

    expect(fn () => $this->registry->addVersion($rule, '1.0.0', ['rate' => 2], $this->user))
        ->toThrow(ValidationException::class);
});

it('freezes approved rule versions', function (): void {
    $rule = $this->registry->registerRule($this->source, [
        'code' => 'FROZEN_RULE',
        'name' => 'Frozen rule',
        'effective_from' => now()->toDateString(),
        'version' => '1.0.0',
    ], ['rate' => 1], $this->user);

    $version = $rule->versions->first();
    $this->registry->submitForReview($version, $this->user);
    $approved = $this->registry->approveVersion($version->refresh(), $this->user);

    expect(fn () => $approved->update(['configuration' => ['rate' => 9]]))
        ->toThrow(LogicException::class);
});
