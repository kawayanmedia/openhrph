<?php

namespace App\Jurisdictions\Philippines\Government\Services;

use App\Jurisdictions\Philippines\Government\Models\AppointmentRecord;
use App\Jurisdictions\Philippines\Government\Models\PlantillaItem;
use App\Jurisdictions\Philippines\Government\Models\PlantillaVersion;
use App\Models\User;
use App\Services\Hr\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Plantilla version lifecycle and item occupancy.
 *
 * Approved versions are immutable. Items only change while their version is
 * draft; occupancy changes go through the appointment service.
 */
class PlantillaService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createVersion(array $attributes): PlantillaVersion
    {
        return DB::transaction(function () use ($attributes): PlantillaVersion {
            $companyId = (int) ($attributes['company_id'] ?? app(TenantContext::class)->id());

            $exists = PlantillaVersion::query()
                ->where('company_id', $companyId)
                ->where('version', (string) $attributes['version'])
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'version' => 'This plantilla version already exists for the organization.',
                ]);
            }

            $version = PlantillaVersion::create([
                ...$attributes,
                'company_id' => $companyId,
                'status' => $attributes['status'] ?? PlantillaVersion::STATUS_DRAFT,
            ]);

            $this->audit->record('plantilla.version_created', $version, after: $version->getAttributes());

            return $version->refresh();
        });
    }

    public function approveVersion(PlantillaVersion $version, User $actor, ?string $reference = null): PlantillaVersion
    {
        return DB::transaction(function () use ($version, $actor, $reference): PlantillaVersion {
            $version = PlantillaVersion::withoutTenant()->whereKey($version->getKey())->lockForUpdate()->firstOrFail();

            if (! $version->canTransitionTo(PlantillaVersion::STATUS_APPROVED)) {
                throw ValidationException::withMessages([
                    'status' => 'Only a draft plantilla version can be approved.',
                ]);
            }

            $before = $version->getAttributes();

            $version->forceFill([
                'status' => PlantillaVersion::STATUS_APPROVED,
                'approval_reference' => $reference ?? $version->approval_reference,
                'approved_by' => $actor->getKey(),
                'approved_at' => now(),
            ])->save();

            $this->audit->record('plantilla.version_approved', $version, before: $before, after: $version->getAttributes());

            return $version->refresh();
        });
    }

    public function retireVersion(PlantillaVersion $version, User $actor): PlantillaVersion
    {
        return DB::transaction(function () use ($version, $actor): PlantillaVersion {
            $version = PlantillaVersion::withoutTenant()->whereKey($version->getKey())->lockForUpdate()->firstOrFail();

            if (! $version->canTransitionTo(PlantillaVersion::STATUS_RETIRED)) {
                throw ValidationException::withMessages([
                    'status' => 'Only an approved plantilla version can be retired.',
                ]);
            }

            $version->forceFill(['status' => PlantillaVersion::STATUS_RETIRED])->save();

            $this->audit->record('plantilla.version_retired', $version, metadata: ['actor_id' => $actor->getKey()]);

            return $version->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function addItem(PlantillaVersion $version, array $attributes): PlantillaItem
    {
        return DB::transaction(function () use ($version, $attributes): PlantillaItem {
            if ($version->status !== PlantillaVersion::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'plantilla_version_id' => 'Items can only be added to a draft plantilla version.',
                ]);
            }

            $itemNumber = (string) $attributes['item_number'];

            $exists = PlantillaItem::query()
                ->where('company_id', $version->company_id)
                ->where('plantilla_version_id', $version->getKey())
                ->where('item_number', $itemNumber)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'item_number' => 'This item number already exists in the plantilla version.',
                ]);
            }

            $item = PlantillaItem::create([
                ...$attributes,
                'company_id' => $version->company_id,
                'plantilla_version_id' => $version->getKey(),
                'status' => $attributes['status'] ?? PlantillaItem::STATUS_AUTHORIZED,
                'is_vacant' => $attributes['is_vacant'] ?? true,
            ]);

            $this->audit->record('plantilla.item_created', $item, after: $item->getAttributes());

            return $item->refresh();
        });
    }

    /**
     * Ensure no other employee/active appointment occupies the item.
     */
    public function assertItemAvailable(PlantillaItem $item, ?int $employeeId = null, int|array|null $ignoreAppointmentIds = null): void
    {
        $ignoreIds = array_values(array_filter(array_map('intval', (array) $ignoreAppointmentIds)));
        if ($item->employee_id !== null && (int) $item->employee_id !== (int) $employeeId) {
            throw ValidationException::withMessages([
                'plantilla_item_id' => 'This plantilla item is already occupied.',
            ]);
        }

        $conflict = AppointmentRecord::withoutTenant()
            ->where('company_id', $item->company_id)
            ->where('plantilla_item_id', $item->getKey())
            ->whereNotIn('status', [
                AppointmentRecord::STATUS_CANCELLED,
                AppointmentRecord::STATUS_RETURNED,
            ])
            ->when($ignoreIds !== [], fn ($query) => $query->whereNotIn('id', $ignoreIds))
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'plantilla_item_id' => 'Another active appointment already references this plantilla item.',
            ]);
        }
    }

    public function occupy(PlantillaItem $item, AppointmentRecord $appointment): PlantillaItem
    {
        $before = $item->getAttributes();

        $item->forceFill([
            'employee_id' => $appointment->employee_id,
            'is_vacant' => false,
            'status' => PlantillaItem::STATUS_FILLED,
            'appointment_date' => $appointment->effectivity_date?->toDateString(),
        ])->save();

        $this->audit->record('plantilla.item_filled', $item, before: $before, after: $item->getAttributes(), metadata: [
            'appointment_id' => $appointment->getKey(),
        ]);

        return $item->refresh();
    }
}
