<?php

namespace App\Sectors\Contracts;

/**
 * Extra readiness checklist items for a sector.
 *
 * The payroll readiness screen merges these with the shared items. A sector
 * with no extra prerequisites returns an empty list.
 */
interface SectorReadiness
{
    /**
     * @param  array{company_id: int|null, employees: \Illuminate\Support\Collection<int, \App\Models\Employee>}  $context
     * @return array<int, array<string, mixed>>
     */
    public function items(array $context): array;
}
