<?php

namespace App\Services\Organization;

use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Location;
use App\Models\Office;
use App\Models\OrganizationUnit;
use App\Services\Hr\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Maintains the organization tree, including the materialized `path` used for
 * descendant queries and the bridge to legacy department/office rows.
 */
class OrganizationUnitService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): OrganizationUnit
    {
        return DB::transaction(function () use ($attributes): OrganizationUnit {
            $unit = OrganizationUnit::create($attributes);

            $unit->refresh();

            $this->audit->record('organization_unit.created', $unit, after: $this->snapshot($unit));

            return $unit;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(OrganizationUnit $unit, array $attributes): OrganizationUnit
    {
        return DB::transaction(function () use ($unit, $attributes): OrganizationUnit {
            $before = $this->snapshot($unit);
            $oldPath = $unit->path;
            $oldDepth = (int) $unit->depth;
            $parentChanged = array_key_exists('parent_id', $attributes)
                && (int) $attributes['parent_id'] !== (int) $unit->parent_id;

            if ($parentChanged) {
                $this->guardParent($unit, $attributes['parent_id'] === null ? null : (int) $attributes['parent_id']);
            }

            $unit->fill($attributes);
            $unit->save();

            if ($parentChanged) {
                $parentPath = $unit->parent_id === null
                    ? ''
                    : (string) OrganizationUnit::withoutTenant()->find($unit->parent_id)?->path;

                $unit->forceFill([
                    'path' => ltrim($parentPath.'/'.$unit->getKey(), '/'),
                ])->saveQuietly();

                $this->rebuildBranch($unit, $oldPath, $oldDepth);
            }

            $unit->refresh();

            $this->audit->record(
                'organization_unit.updated',
                $unit,
                before: $before,
                after: $this->snapshot($unit),
            );

            return $unit;
        });
    }

    public function move(OrganizationUnit $unit, ?OrganizationUnit $parent): OrganizationUnit
    {
        return $this->update($unit, ['parent_id' => $parent?->getKey()]);
    }

    public function delete(OrganizationUnit $unit): void
    {
        if ($unit->children()->exists()) {
            throw ValidationException::withMessages([
                'organization_unit' => 'Move or delete child units before deleting this unit.',
            ]);
        }

        if ($unit->employees()->exists()) {
            throw ValidationException::withMessages([
                'organization_unit' => 'Reassign employees before deleting this unit.',
            ]);
        }

        DB::transaction(function () use ($unit): void {
            $before = $this->snapshot($unit);
            $unit->delete();

            $this->audit->record('organization_unit.deleted', $unit, before: $before);
        });
    }

    /**
     * Ensure a legacy department or office has a matching unit and link.
     */
    public function syncLegacy(Department|Office $legacy): OrganizationUnit
    {
        if ($legacy->organization_unit_id !== null) {
            return OrganizationUnit::findOrFail($legacy->organization_unit_id);
        }

        return DB::transaction(function () use ($legacy): OrganizationUnit {
            $unit = OrganizationUnit::create([
                'company_id' => $legacy->company_id,
                'code' => $legacy->code,
                'name' => $legacy->name,
                'unit_type' => $legacy instanceof Department
                    ? OrganizationUnit::TYPE_DEPARTMENT
                    : OrganizationUnit::TYPE_OFFICE,
            ]);

            $legacy->forceFill(['organization_unit_id' => $unit->getKey()])->save();

            return $unit->refresh();
        });
    }

    /**
     * Create or reuse a location for a company.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createLocation(array $attributes): Location
    {
        return DB::transaction(function () use ($attributes): Location {
            $location = Location::create($attributes);

            $this->audit->record('location.created', $location, after: $this->snapshot($location));

            return $location;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createCostCenter(array $attributes): CostCenter
    {
        return DB::transaction(function () use ($attributes): CostCenter {
            $costCenter = CostCenter::create($attributes);

            $this->audit->record('cost_center.created', $costCenter, after: $this->snapshot($costCenter));

            return $costCenter;
        });
    }

    protected function guardParent(OrganizationUnit $unit, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($parentId === (int) $unit->getKey()) {
            throw ValidationException::withMessages([
                'parent_id' => 'A unit cannot be its own parent.',
            ]);
        }

        $parent = OrganizationUnit::find($parentId);

        if ($parent === null) {
            throw ValidationException::withMessages([
                'parent_id' => 'The selected parent unit does not exist.',
            ]);
        }

        if ($unit->isAncestorOf($parent)) {
            throw ValidationException::withMessages([
                'parent_id' => 'A unit cannot be moved under one of its own descendants.',
            ]);
        }
    }

    /**
     * Rewrite the path/depth of the moved unit and everything below it.
     */
    protected function rebuildBranch(OrganizationUnit $unit, ?string $oldPath, int $oldDepth): void
    {
        $unit->refresh();
        $newPath = (string) $unit->path;
        $newDepth = (int) $unit->depth;

        if ($oldPath === null || $oldPath === $newPath) {
            return;
        }

        $depthDelta = $newDepth - $oldDepth;

        $descendants = OrganizationUnit::withoutTenant()
            ->where('company_id', $unit->company_id)
            ->where('path', 'like', $oldPath.'/%')
            ->get();

        foreach ($descendants as $descendant) {
            $suffix = substr((string) $descendant->path, strlen($oldPath));

            DB::table('organization_units')
                ->where('id', $descendant->getKey())
                ->update([
                    'path' => $newPath.$suffix,
                    'depth' => max(0, (int) $descendant->depth + $depthDelta),
                ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(object $model): array
    {
        return collect($model->getAttributes())
            ->except(['created_at', 'updated_at', 'deleted_at'])
            ->all();
    }
}
