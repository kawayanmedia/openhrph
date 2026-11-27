<?php

namespace App\Jurisdictions\Philippines\Government\Models;

use App\Models\OrganizationUnit;
use App\Models\Position;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use KawayanMedia\Government\Models\PlantillaItem as BasePlantillaItem;

/**
 * Plantilla item with version, position and organization links.
 *
 * Extends the package model so existing government screens keep working.
 */
class PlantillaItem extends BasePlantillaItem
{
    public const STATUS_AUTHORIZED = 'authorized';

    public const STATUS_VACANT = 'vacant';

    public const STATUS_FILLED = 'filled';

    public const STATUS_ABOLISHED = 'abolished';

    public const STATUSES = [
        self::STATUS_AUTHORIZED,
        self::STATUS_VACANT,
        self::STATUS_FILLED,
        self::STATUS_ABOLISHED,
    ];

    protected $fillable = [
        'company_id',
        'office_id',
        'employee_id',
        'item_number',
        'position_title',
        'salary_grade',
        'step',
        'have_budget',
        'is_vacant',
        'annual_salary_authorized',
        'annual_salary_actual',
        'appointment_date',
        'plantilla_version_id',
        'position_id',
        'organization_unit_id',
        'funding_source',
        'status',
        'effective_from',
        'effective_until',
    ];

    protected $casts = [
        'is_vacant' => 'boolean',
        'have_budget' => 'boolean',
        'salary_grade' => 'integer',
        'step' => 'integer',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'appointment_date' => 'date',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(PlantillaVersion::class, 'plantilla_version_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    /**
     * @return HasMany<AppointmentRecord, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(AppointmentRecord::class, 'plantilla_item_id');
    }

    public function isOccupied(): bool
    {
        return $this->employee_id !== null || $this->status === self::STATUS_FILLED;
    }
}
