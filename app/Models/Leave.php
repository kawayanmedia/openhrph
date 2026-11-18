<?php

namespace App\Models;

use App\Enums\Sector;
use App\Models\Scopes\LeaveTypeCatalogueScope;
use App\Scopes\FilterScope;
use App\Sectors\Catalogues\LeaveTypeCatalogue;
use App\Sectors\SectorManager;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A leave type in the system-wide catalogue.
 *
 * Rows with a NULL company_id are shared by every company. A company-owned row
 * either overrides the shared row with the same name or is a custom type only
 * that company uses.
 */
class Leave extends Model
{
    use FilterScope;
    use SoftDeletes;

    protected $fillable = [];

    protected $table = 'leave_types';

    protected $casts = ['approved_date' => 'date'];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::addGlobalScope(new LeaveTypeCatalogueScope);

        static::created(function (Leave $model) {
            set_current_timestamp($model);
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Whether this row overrides a system leave type for one company.
     */
    public function isOverride(): bool
    {
        return $this->getAttribute('company_id') !== null;
    }

    public function scopeSystem(Builder $query): void
    {
        $query->whereNull($query->getModel()->qualifyColumn('company_id'));
    }

    public function scopeOverrides(Builder $query): void
    {
        $query->whereNotNull($query->getModel()->qualifyColumn('company_id'));
    }

    /**
     * The catalogue the active company sees: system rows, except the names it
     * overrides, plus its own rows.
     */
    public function scopeEffective(Builder $query): void
    {
        $tenantId = app(TenantContext::class)->id();
        $outer = $query->getModel()->getTable();

        if ($tenantId === null) {
            $query->whereNull($outer.'.company_id');

            return;
        }

        $query->where(function (Builder $query) use ($tenantId, $outer): void {
            $query->whereNotNull($outer.'.company_id')
                ->orWhere(function (Builder $query) use ($tenantId, $outer): void {
                    $query->whereNull($outer.'.company_id')
                        ->whereNotExists(function ($sub) use ($tenantId, $outer): void {
                            $sub->select(DB::raw(1))
                                ->from('leave_types as overrides')
                                ->whereRaw('UPPER(TRIM(overrides.name)) = UPPER(TRIM('.$outer.'.name))')
                                ->where('overrides.company_id', $tenantId)
                                ->whereNull('overrides.deleted_at');
                        });
                });
        });
    }

    /**
     * Restrict the catalogue to rows shared by both sectors plus the current
     * sector's own rows. Rows without a sector apply to both.
     */
    public function scopeForSector(Builder $query, ?Sector $sector = null): void
    {
        if (! Schema::hasColumn($query->getModel()->getTable(), 'sector')) {
            return;
        }

        $sector ??= app(SectorManager::class)->currentSector();

        $query->where(function (Builder $query) use ($sector): void {
            $query->whereNull('sector')->orWhere('sector', $sector->value);
        });
    }

    /**
     * Copy a system row into a company override, or return the existing one.
     */
    public static function overrideFor(int $companyId, self $system): self
    {
        $existing = self::withoutGlobalScope(LeaveTypeCatalogueScope::class)
            ->where('company_id', $companyId)
            ->whereRaw('UPPER(TRIM(name)) = ?', [LeaveTypeCatalogue::normalise((string) $system->name)])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $override = $system->replicate();
        $override->company_id = $companyId;
        $override->save();

        return $override;
    }

    /**
     * The leave types the active company can pick, keyed by id.
     *
     * @return array<int, string>
     */
    public static function selectOptions(): array
    {
        return self::query()
            ->effective()
            ->forSector()
            ->active()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
