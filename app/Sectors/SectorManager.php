<?php

namespace App\Sectors;

use App\Enums\Sector;
use App\Jurisdictions\Support\JurisdictionManager;
use App\Models\Company;
use App\Sectors\Contracts\SectorFeaturePack;
use App\Sectors\Contracts\SectorPolicy;
use App\Sectors\Support\NullFeaturePack;
use App\Sectors\Support\SectorProfile;
use App\Support\Installation\Installation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves the active sector policy.
 *
 * Resolution order:
 *  1. the installation setting written by `openhr:install`;
 *  2. the legacy `IS_GOVERNMENT` environment flag;
 *  3. the configured default (Private).
 *
 * Payroll resolves per company so legacy rows with a `companies.type` keep
 * working. The installer pins every company to the installation sector, so the
 * two normally agree.
 */
final class SectorManager
{
    /**
     * @var array<string, SectorPolicy>
     */
    private array $policies = [];

    public function __construct(private readonly JurisdictionManager $jurisdictions) {}

    public function current(): SectorPolicy
    {
        return $this->for($this->resolveSector());
    }

    public function currentSector(): Sector
    {
        return $this->current()->sector();
    }

    public function for(Sector|string|null $sector): SectorPolicy
    {
        $resolved = match (true) {
            $sector instanceof Sector => $sector,
            is_string($sector) => Sector::tryFromValue($sector) ?? $this->resolveSector(),
            default => $this->resolveSector(),
        };

        return $this->policies[$resolved->value] ??= $this->make($resolved);
    }

    public function forCompany(Company|int|null $company): SectorPolicy
    {
        if ($company === null) {
            return $this->current();
        }

        $type = is_int($company)
            ? $this->companyType($company)
            : $company->type;

        $sector = is_string($type) ? Sector::tryFromValue($type) : null;

        return $sector === null ? $this->current() : $this->for($sector);
    }

    public function isGovernment(Company|int|string|Sector|null $subject = null): bool
    {
        if ($subject instanceof Company || is_int($subject)) {
            return $this->forCompany($subject)->sector()->isGovernment();
        }

        if ($subject instanceof Sector || is_string($subject)) {
            return $this->for($subject)->sector()->isGovernment();
        }

        return $this->current()->sector()->isGovernment();
    }

    public function supports(string $feature, Company|int|Sector|string|null $subject = null): bool
    {
        return $this->policyForSubject($subject)->supports($feature);
    }

    public function showsMenu(string $key, Company|int|Sector|string|null $subject = null): bool
    {
        return $this->policyForSubject($subject)->showsMenu($key);
    }

    /**
     * @return array<string, SectorPolicy>
     */
    public function all(): array
    {
        $policies = [];

        foreach (Sector::cases() as $sector) {
            $policies[$sector->value] = $this->for($sector);
        }

        return $policies;
    }

    public function reset(): void
    {
        $this->policies = [];
    }

    private function policyForSubject(Company|int|Sector|string|null $subject): SectorPolicy
    {
        if ($subject instanceof Company || is_int($subject)) {
            return $this->forCompany($subject);
        }

        if ($subject instanceof Sector || is_string($subject)) {
            return $this->for($subject);
        }

        return $this->current();
    }

    private function make(Sector $sector): SectorPolicy
    {
        $profile = SectorProfile::fromConfig(
            $sector,
            $this->jurisdictions->current()->sectorProfile($sector),
        );

        $pack = $this->featurePack($sector);

        return match ($sector) {
            Sector::Government => new GovernmentSector($profile, $pack),
            Sector::Private => new PrivateSector($profile, $pack),
        };
    }

    private function featurePack(Sector $sector): SectorFeaturePack
    {
        $class = $this->jurisdictions->current()->sectorFeaturePack($sector);

        if (is_string($class) && class_exists($class) && is_subclass_of($class, SectorFeaturePack::class)) {
            return new $class;
        }

        return new NullFeaturePack;
    }

    private function resolveSector(): Sector
    {
        if ($sector = Installation::sectorEnum()) {
            return $sector;
        }

        if ((bool) config('app.is_government', false)) {
            return Sector::Government;
        }

        return Sector::tryFromValue((string) config('sectors.default', 'Private')) ?? Sector::Private;
    }

    private function companyType(int $companyId): ?string
    {
        if (! Schema::hasTable('companies') || ! Schema::hasColumn('companies', 'type')) {
            return null;
        }

        $type = DB::table('companies')->where('id', $companyId)->value('type');

        return is_string($type) ? $type : null;
    }
}
