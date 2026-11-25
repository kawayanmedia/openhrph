<?php

namespace App\Sectors\Contracts;

use App\Models\Employee;
use Illuminate\Support\Collection;

/**
 * Extra readiness checklist items for a sector.
 *
 * The payroll readiness screen merges these with the shared items. A sector
 * with no extra prerequisites returns an empty list.
 */
interface SectorReadiness
{
    /**
     * @param  array{company_id: int|null, employees: Collection<int, Employee>}  $context
     * @return array<int, array<string, mixed>>
     */
    public function items(array $context): array;
}
