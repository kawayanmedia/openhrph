<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bring the de minimis ceilings up to BIR RR 29-2025 and record the percentage
 * the overtime/night meal allowance is measured at.
 */
return new class extends Migration
{
    /**
     * code => [name, ceiling in centavos, basis, percentage]
     *
     * @var array<string, array{0: string, 1: int, 2: string, 3: int}>
     */
    private const CEILINGS = [
        'rice' => ['Rice subsidy', 250000, 'monthly', 0],
        'clothing' => ['Uniform and clothing allowance', 800000, 'annual', 0],
        'medical' => ['Medical cash allowance to dependents', 200000, 'semester', 0],
        'laundry' => ['Laundry allowance', 40000, 'monthly', 0],
        'meal' => ['Meal allowance for overtime and night shift', 0, 'hourly_ot', 30],
        'medical_assistance' => ['Actual medical assistance', 1200000, 'annual', 0],
        'achievement_award' => ['Employee achievement awards', 1200000, 'annual', 0],
        'gifts' => ['Christmas and major anniversary gifts', 600000, 'annual', 0],
        'cba_productivity' => ['CBA and productivity incentive benefits', 1200000, 'annual', 0],
    ];

    /**
     * The RR 11-2018 ceilings, restored on rollback.
     *
     * @var array<string, array{0: string, 1: int, 2: string}>
     */
    private const PREVIOUS = [
        'rice' => ['Rice subsidy', 200000, 'monthly'],
        'clothing' => ['Uniform and clothing allowance', 600000, 'annual'],
        'medical' => ['Medical cash allowance to dependents', 150000, 'semester'],
        'laundry' => ['Laundry allowance', 30000, 'monthly'],
        'meal' => ['Meal allowance', 0, 'hourly_ot'],
        'medical_assistance' => ['Medical assistance', 1000000, 'annual'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('statutory_de_minimis')) {
            return;
        }

        if (! Schema::hasColumn('statutory_de_minimis', 'percentage')) {
            Schema::table('statutory_de_minimis', function (Blueprint $table): void {
                $table->unsignedTinyInteger('percentage')->nullable()->after('basis');
            });
        }

        $this->apply(self::CEILINGS);
    }

    public function down(): void
    {
        if (! Schema::hasTable('statutory_de_minimis')) {
            return;
        }

        $this->remove(['achievement_award', 'gifts', 'cba_productivity']);
        $this->apply(self::PREVIOUS);
    }

    /**
     * Update the effective company-wide row for each code, or insert it when the
     * deployment never had one.
     *
     * @param  array<string, array{0: string, 1: int, 2: string, 3?: int}>  $ceilings
     */
    private function apply(array $ceilings): void
    {
        $hasPercentage = Schema::hasColumn('statutory_de_minimis', 'percentage');

        foreach ($ceilings as $code => $row) {
            $values = [
                'name' => $row[0],
                'ceiling_centavos' => $row[1],
                'basis' => $row[2],
                'updated_at' => now(),
            ];

            if ($hasPercentage) {
                $values['percentage'] = $row[3] ?? 0;
            }

            $updated = DB::table('statutory_de_minimis')
                ->whereNull('company_id')
                ->where('code', $code)
                ->whereNull('effective_to')
                ->update($values);

            if ($updated > 0) {
                continue;
            }

            DB::table('statutory_de_minimis')->insert($values + [
                'company_id' => null,
                'code' => $code,
                'effective_from' => '2026-01-01',
                'effective_to' => null,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * @param  list<string>  $codes
     */
    private function remove(array $codes): void
    {
        DB::table('statutory_de_minimis')
            ->whereNull('company_id')
            ->whereIn('code', $codes)
            ->where('effective_from', '2026-01-01')
            ->delete();
    }
};
