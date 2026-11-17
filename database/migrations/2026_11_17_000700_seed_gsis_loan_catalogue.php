<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GSIS loans are salary deductions, not statutory contributions. Existing
 * government installations get the three GSIS loan programs in their loan
 * catalogue so an administrator can attach an amortization to an employee.
 */
return new class extends Migration
{
    /**
     * @var list<array{code: string, name: string}>
     */
    private array $loans = [
        ['code' => 'GSIS-POLICY', 'name' => 'GSIS Policy Loan'],
        ['code' => 'GSIS-EMERGENCY', 'name' => 'GSIS Emergency Loan'],
        ['code' => 'GSIS-REAL-ESTATE', 'name' => 'GSIS Real Estate Loan'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('companies') || ! Schema::hasTable('loans')) {
            return;
        }

        $now = now();

        $companyIds = DB::table('companies')
            ->whereRaw("lower(trim(type)) = 'government'")
            ->pluck('id');

        foreach ($companyIds as $companyId) {
            foreach ($this->loans as $loan) {
                $exists = DB::table('loans')
                    ->where('company_id', $companyId)
                    ->where('code', $loan['code'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('loans')->insert([
                    'company_id' => $companyId,
                    'loan_agency' => 'GSIS',
                    'code' => $loan['code'],
                    'name' => $loan['name'],
                    'is_default' => true,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('loans')) {
            return;
        }

        DB::table('loans')
            ->where('loan_agency', 'GSIS')
            ->whereIn('code', array_column($this->loans, 'code'))
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('employees_loans')
                    ->whereColumn('employees_loans.loan_id', 'loans.id');
            })
            ->delete();
    }
};
