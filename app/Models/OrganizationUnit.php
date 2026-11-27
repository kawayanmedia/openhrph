<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

/**
 * Canonical organizational unit tree.
 *
 * `path` is a slash-separated list of ancestor ids ending with this unit,
 * which makes descendant queries a single indexed prefix match.
 */
class OrganizationUnit extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const TYPE_OFFICE = 'office';

    public const TYPE_DEPARTMENT = 'department';

    public const TYPE_DIVISION = 'division';

    public const TYPE_SECTION = 'section';

    public const TYPE_UNIT = 'unit';

    public const TYPE_TEAM = 'team';

    public const TYPES = [
        self::TYPE_OFFICE,
        self::TYPE_DEPARTMENT,
        self::TYPE_DIVISION,
        self::TYPE_SECTION,
        self::TYPE_UNIT,
        self::TYPE_TEAM,
    ];

    protected $fillable = [
        'company_id',
        'parent_id',
        'code',
        'name',
        'unit_type',
        'path',
        'depth',
        'manager_employee_id',
        'location_id',
        'cost_center_id',
        'effective_from',
        'effective_until',
        'is_active',
    ];

    protected $casts = [
        'depth' => 'integer',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (OrganizationUnit $unit): void {
            $unit->depth = $unit->parent_id === null
                ? 0
                : (int) (static::withoutGlobalScopes()
                    ->where('company_id', $unit->company_id)
                    ->find($unit->parent_id)?->depth ?? 0) + 1;
        });

        static::created(function (OrganizationUnit $unit): void {
            $parentPath = $unit->parent_id === null
                ? ''
                : (string) (static::withoutGlobalScopes()
                    ->where('company_id', $unit->company_id)
                    ->find($unit->parent_id)?->path ?? '');

            $unit->forceFill([
                'path' => ltrim($parentPath.'/'.$unit->getKey(), '/'),
            ])->saveQuietly();
        });

        static::updated(function (OrganizationUnit $unit): void {
            if (! $unit->wasChanged('parent_id')) {
                return;
            }

            $oldPath = (string) $unit->getOriginal('path');
            $oldDepth = (int) $unit->getOriginal('depth');
            $parentPath = $unit->parent_id === null
                ? ''
                : (string) (static::withoutGlobalScopes()
                    ->where('company_id', $unit->company_id)
                    ->find($unit->parent_id)?->path ?? '');
            $newPath = ltrim($parentPath.'/'.$unit->getKey(), '/');

            if ($oldPath === $newPath) {
                return;
            }

            $unit->forceFill(['path' => $newPath])->saveQuietly();

            $depthDelta = (int) $unit->depth - $oldDepth;

            static::withoutGlobalScopes()
                ->where('company_id', $unit->company_id)
                ->where('path', 'like', $oldPath.'/%')
                ->get()
                ->each(function (OrganizationUnit $descendant) use ($oldPath, $newPath, $depthDelta): void {
                    $descendant->forceFill([
                        'path' => $newPath.substr((string) $descendant->path, strlen($oldPath)),
                        'depth' => max(0, (int) $descendant->depth + $depthDelta),
                    ])->saveQuietly();
                });
        });

        static::deleting(function (OrganizationUnit $unit): void {
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
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<OrganizationUnit, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * @return HasMany<Position, $this>
     */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /**
     * @return array<int, int>
     */
    public function descendantIds(): array
    {
        return static::query()
            ->where('id', '!=', $this->getKey())
            ->where(function (Builder $query): void {
                $query->where('path', 'like', $this->path.'/%');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function isAncestorOf(OrganizationUnit $unit): bool
    {
        return str_starts_with((string) $unit->path, (string) $this->path.'/');
    }
}
