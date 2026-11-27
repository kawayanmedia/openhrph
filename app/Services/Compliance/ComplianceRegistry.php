<?php

namespace App\Services\Compliance;

use App\Models\ComplianceRule;
use App\Models\ComplianceRuleVersion;
use App\Models\ComplianceSource;
use App\Models\User;
use App\Services\Hr\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Source register and versioned rule catalog.
 *
 * Configurations are inert JSON: the registry never executes user-supplied
 * formulas or PHP. Approved versions are immutable and every calculation must
 * resolve a version as of its event date.
 */
class ComplianceRegistry
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function registerSource(array $attributes, User $actor): ComplianceSource
    {
        return DB::transaction(function () use ($attributes, $actor): ComplianceSource {
            $source = ComplianceSource::create([
                ...$attributes,
                'company_id' => $attributes['company_id'] ?? null,
            ]);

            $this->audit->record('compliance.source_registered', $source, after: $source->getAttributes(), metadata: [
                'actor_id' => $actor->getKey(),
            ]);

            return $source->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $configuration
     */
    public function registerRule(
        ComplianceSource $source,
        array $attributes,
        array $configuration,
        User $actor,
    ): ComplianceRule {
        return DB::transaction(function () use ($source, $attributes, $configuration, $actor): ComplianceRule {
            $this->assertConfiguration($configuration);

            $rule = ComplianceRule::create([
                ...$attributes,
                'company_id' => $attributes['company_id'] ?? $source->company_id,
                'compliance_source_id' => $source->getKey(),
                'version' => $attributes['version'] ?? '1.0.0',
                'status' => ComplianceRule::STATUS_DRAFT,
            ]);

            $rule->versions()->create([
                'company_id' => $rule->company_id,
                'version' => $rule->version,
                'configuration' => $configuration,
                'status' => ComplianceRuleVersion::STATUS_DRAFT,
                'change_summary' => 'Initial version.',
            ]);

            $this->audit->record('compliance.rule_registered', $rule, after: $rule->getAttributes(), metadata: [
                'actor_id' => $actor->getKey(),
            ]);

            return $rule->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    public function addVersion(
        ComplianceRule $rule,
        string $version,
        array $configuration,
        User $actor,
        ?string $summary = null,
    ): ComplianceRuleVersion {
        return DB::transaction(function () use ($rule, $version, $configuration, $actor, $summary): ComplianceRuleVersion {
            $this->assertConfiguration($configuration);

            if ($rule->versions()->where('version', $version)->exists()) {
                throw ValidationException::withMessages([
                    'version' => 'This rule version already exists.',
                ]);
            }

            $ruleVersion = $rule->versions()->create([
                'company_id' => $rule->company_id,
                'version' => $version,
                'configuration' => $configuration,
                'status' => ComplianceRuleVersion::STATUS_DRAFT,
                'change_summary' => $summary,
            ]);

            $this->audit->record('compliance.rule_version_added', $rule, metadata: [
                'actor_id' => $actor->getKey(),
                'version' => $version,
            ]);

            return $ruleVersion->refresh();
        });
    }

    public function submitForReview(ComplianceRuleVersion $version, User $actor): ComplianceRuleVersion
    {
        return DB::transaction(function () use ($version, $actor): ComplianceRuleVersion {
            $version = $this->lockVersion($version);

            if ($version->status !== ComplianceRuleVersion::STATUS_DRAFT) {
                throw ValidationException::withMessages(['status' => 'Only a draft version can be submitted for review.']);
            }

            $version->forceFill([
                'status' => ComplianceRuleVersion::STATUS_REVIEWED,
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
            ])->save();

            $version->rule?->forceFill(['status' => ComplianceRule::STATUS_IN_REVIEW])->save();

            $this->audit->record('compliance.rule_version_submitted', $version->rule, metadata: [
                'actor_id' => $actor->getKey(),
                'version' => $version->version,
            ]);

            return $version->refresh();
        });
    }

    public function approveVersion(ComplianceRuleVersion $version, User $actor): ComplianceRuleVersion
    {
        return DB::transaction(function () use ($version, $actor): ComplianceRuleVersion {
            $version = $this->lockVersion($version);

            if ($version->status !== ComplianceRuleVersion::STATUS_REVIEWED) {
                throw ValidationException::withMessages(['status' => 'Only a reviewed version can be approved.']);
            }

            $version->forceFill([
                'status' => ComplianceRuleVersion::STATUS_APPROVED,
                'approved_by' => $actor->getKey(),
                'approved_at' => now(),
            ])->save();

            $version->rule?->forceFill([
                'status' => ComplianceRule::STATUS_APPROVED,
                'version' => $version->version,
            ])->save();

            $this->audit->record('compliance.rule_version_approved', $version->rule, metadata: [
                'actor_id' => $actor->getKey(),
                'version' => $version->version,
            ]);

            return $version->refresh();
        });
    }

    /**
     * Resolve the approved version effective on a given date.
     *
     * @return array{rule: ComplianceRule, version: ComplianceRuleVersion, configuration: array<string, mixed>}
     */
    public function resolve(string $code, ?string $sector = null, \DateTimeInterface|string|null $asOf = null): array
    {
        $asOf = $asOf === null
            ? now()->toDateString()
            : Carbon::parse($asOf)->toDateString();

        $rules = ComplianceRule::query()
            ->with(['versions' => fn ($query) => $query->where('status', ComplianceRuleVersion::STATUS_APPROVED)])
            ->where('code', $code)
            ->where('status', ComplianceRule::STATUS_APPROVED)
            ->get()
            ->filter(fn (ComplianceRule $rule) => $rule->isEffectiveOn($asOf))
            ->filter(fn (ComplianceRule $rule) => $sector === null
                || $rule->sector === null
                || $rule->sector === $sector)
            ->sortByDesc(fn (ComplianceRule $rule) => $rule->sector === $sector);

        if ($rules->isEmpty()) {
            throw ValidationException::withMessages([
                'code' => "No approved compliance rule '{$code}' is effective on {$asOf}.",
            ]);
        }

        $specific = $rules->where('sector', $sector);
        $candidates = $specific->isNotEmpty() ? $specific : $rules;

        if ($candidates->count() > 1) {
            throw ValidationException::withMessages([
                'code' => 'Multiple approved compliance rules conflict for this sector; expert review is required.',
            ]);
        }

        /** @var ComplianceRule $rule */
        $rule = $candidates->first();

        /** @var ComplianceRuleVersion|null $version */
        $version = $rule->versions
            ->where('version', $rule->version)
            ->first()
            ?? $rule->versions->sortByDesc('approved_at')->first();

        if ($version === null) {
            throw ValidationException::withMessages([
                'code' => "No approved configuration exists for compliance rule '{$code}'.",
            ]);
        }

        return [
            'rule' => $rule,
            'version' => $version,
            'configuration' => $version->configuration ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    protected function assertConfiguration(array $configuration): void
    {
        if ($configuration === []) {
            throw ValidationException::withMessages([
                'configuration' => 'A rule configuration is required.',
            ]);
        }

        array_walk_recursive($configuration, function ($value, $key): void {
            if (is_string($key) && in_array(strtolower($key), ['php', 'eval', 'script'], true)) {
                throw ValidationException::withMessages([
                    'configuration' => 'Rule configurations cannot contain executable code.',
                ]);
            }
        });
    }

    protected function lockVersion(ComplianceRuleVersion $version): ComplianceRuleVersion
    {
        return ComplianceRuleVersion::query()
            ->with('rule')
            ->whereKey($version->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }
}
