<?php

namespace App\Jurisdictions\Philippines\Government\Support;

use App\Sectors\Contracts\SectorReadiness;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Government-only payroll readiness checks.
 */
final class GovernmentReadiness implements SectorReadiness
{
    /**
     * @param  array{company_id: int|null, employees: \Illuminate\Support\Collection<int, \App\Models\Employee>}  $context
     * @return array<int, array<string, mixed>>
     */
    public function items(array $context): array
    {
        $covered = Schema::hasTable('statutory_gsis')
            && DB::table('statutory_gsis')->whereNull('company_id')->exists();

        return [
            [
                'key' => 'gsis-schedule',
                'title' => 'Load the GSIS premium schedule',
                'description' => 'This company is a government agency, so payroll remits GSIS instead of SSS at 9% employee and 12% employer.',
                'status' => $covered ? 'complete' : 'pending',
                'required' => true,
                'meta' => $covered ? 'GSIS schedule loaded' : 'No GSIS schedule imported',
                'action' => $covered ? null : [
                    'label' => 'Open payroll settings',
                    'url' => route('admin.settings', ['page' => 'payroll']),
                    'method' => null,
                ],
            ],
        ];
    }
}
