<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidate allowances, bonuses and commissions into the single
 * compensations catalogue and employees_compensations assignment table.
 *
 * The existing `compensations.group` column is reused as the earning category
 * (Allowance, Bonus, Commission or Compensation), so no new classifier column
 * is required.
 */
return new class extends Migration
{
    private const GROUP_ALLOWANCE = 'Allowance';

    private const GROUP_BONUS = 'Bonus';

    private const GROUP_COMMISSION = 'Commission';

    private const GROUP_COMPENSATION = 'Compensation';

    public function up(): void
    {
        $this->addConsolidatedColumns();
        $this->normaliseGroups();

        $allowanceMap = $this->copyAllowanceCatalog();
        $bonusMap = $this->copyBonusCatalog();
        $commissionMap = $this->copyCommissionCatalog();

        $this->copyAllowanceAssignments($allowanceMap);
        $this->copyBonusAssignments($bonusMap);
        $this->copyCommissionAssignments($commissionMap);
        $this->copyAllowanceRecurrings($allowanceMap);

        $this->dropLegacyTables();
    }

    public function down(): void
    {
        $this->recreateLegacyTables();

        $allowanceMap = $this->restoreAllowanceCatalog();
        $bonusMap = $this->restoreBonusCatalog();
        $commissionMap = $this->restoreCommissionCatalog();

        $this->restoreAllowanceAssignments($allowanceMap);
        $this->restoreBonusAssignments($bonusMap);
        $this->restoreCommissionAssignments($commissionMap);

        DB::table('employees_compensations')->whereNull('amount')->update(['amount' => 0]);

        Schema::table('employees_compensations', function (Blueprint $table): void {
            $table->integer('amount')->nullable(false)->change();
        });

        $this->dropConsolidatedColumns();
    }

    private function addConsolidatedColumns(): void
    {
        if (Schema::hasTable('compensations')) {
            Schema::table('compensations', function (Blueprint $table): void {
                if (! Schema::hasColumn('compensations', 'amount_basis')) {
                    $table->string('amount_basis', 32)->nullable();
                }

                if (! Schema::hasColumn('compensations', 'frequency')) {
                    $table->string('frequency', 32)->nullable();
                }

                if (! Schema::hasColumn('compensations', 'maximum_non_tax')) {
                    $table->integer('maximum_non_tax')->nullable();
                }
            });

            if (! Schema::hasIndex('compensations', ['company_id', 'group'])) {
                Schema::table('compensations', function (Blueprint $table): void {
                    $table->index(['company_id', 'group']);
                });
            }
        }

        if (Schema::hasTable('employees_compensations')) {
            Schema::table('employees_compensations', function (Blueprint $table): void {
                if (! Schema::hasColumn('employees_compensations', 'status')) {
                    $table->string('status', 32)->default('not_release');
                }

                $table->integer('amount')->nullable()->change();
            });

            if (! Schema::hasIndex('employees_compensations', ['company_id', 'status'])) {
                Schema::table('employees_compensations', function (Blueprint $table): void {
                    $table->index(['company_id', 'status']);
                });
            }
        }
    }

    /**
     * Give every existing catalogue row one of the four reserved group values.
     */
    private function normaliseGroups(): void
    {
        if (! Schema::hasTable('compensations')) {
            return;
        }

        $allowanceGroups = [
            'allowance',
            'representation',
            'transportation',
            'cost of living allowance',
            'fixed housing allowance',
            'de minimis benefits',
        ];

        $commissionGroups = ['commission'];

        $bonusGroups = ['bonus', '13th month pay'];

        $groups = DB::table('compensations')
            ->select('group')
            ->distinct()
            ->pluck('group');

        foreach ($groups as $group) {
            $normalised = strtolower(trim((string) $group));

            $target = match (true) {
                in_array($normalised, $allowanceGroups, true) => self::GROUP_ALLOWANCE,
                in_array($normalised, $commissionGroups, true) => self::GROUP_COMMISSION,
                in_array($normalised, $bonusGroups, true) => self::GROUP_BONUS,
                default => self::GROUP_COMPENSATION,
            };

            $query = DB::table('compensations');

            $group === null || $group === ''
                ? $query->whereNull('group')->orWhere('group', '')
                : $query->where('group', $group);

            $query->update(['group' => $target]);
        }
    }

    /**
     * @return array<int, int> legacy allowance id => compensation id
     */
    private function copyAllowanceCatalog(): array
    {
        if (! Schema::hasTable('allowances')) {
            return [];
        }

        $map = [];

        foreach (DB::table('allowances')->orderBy('id')->get() as $row) {
            $companyId = (int) ($row->company_id ?? 0);
            $code = 'ALLOW-'.$row->id;

            $existing = DB::table('compensations')
                ->where('company_id', $companyId)
                ->where('code', $code)
                ->first();

            if ($existing !== null) {
                $map[(int) $row->id] = (int) $existing->id;

                continue;
            }

            $map[(int) $row->id] = (int) DB::table('compensations')->insertGetId([
                'company_id' => $companyId,
                'code' => $code,
                'name' => $this->catalogueName($row->name ?? 'Allowance'),
                'tax_class_code' => null,
                'tax_class' => 'Allowance',
                'group' => self::GROUP_ALLOWANCE,
                'tax_option' => $row->tax_option ?? null,
                'is_taxable' => $this->taxableFromTaxOption((string) ($row->tax_option ?? '')),
                'amount' => (float) ($row->amount ?? 0),
                'status' => 'Active',
                'amount_basis' => null,
                'frequency' => null,
                'maximum_non_tax' => null,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }

        return $map;
    }

    /**
     * @return array<int, int> legacy bonus id => compensation id
     */
    private function copyBonusCatalog(): array
    {
        if (! Schema::hasTable('bonuses')) {
            return [];
        }

        $map = [];

        foreach (DB::table('bonuses')->orderBy('id')->get() as $row) {
            $companyId = (int) ($row->company_id ?? 0);
            $code = 'BONUS-'.$row->id;

            $existing = DB::table('compensations')
                ->where('company_id', $companyId)
                ->where('code', $code)
                ->first();

            if ($existing !== null) {
                $map[(int) $row->id] = (int) $existing->id;

                continue;
            }

            $taxable = (int) ($row->is_taxable ?? 1) === 1;

            $map[(int) $row->id] = (int) DB::table('compensations')->insertGetId([
                'company_id' => $companyId,
                'code' => $code,
                'name' => $this->catalogueName($row->name ?? 'Bonus'),
                'tax_class_code' => null,
                'tax_class' => 'Bonus',
                'group' => self::GROUP_BONUS,
                'tax_option' => $taxable ? 'fully_taxable' : 'fully_nontaxable',
                'is_taxable' => $taxable,
                'amount' => 0,
                'status' => 'Active',
                'amount_basis' => $row->amount_basis ?? null,
                'frequency' => $row->frequency ?? null,
                'maximum_non_tax' => $row->maximum_non_tax ?? null,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }

        return $map;
    }

    /**
     * @return array<int, int> legacy commission id => compensation id
     */
    private function copyCommissionCatalog(): array
    {
        if (! Schema::hasTable('commissions')) {
            return [];
        }

        $map = [];

        foreach (DB::table('commissions')->orderBy('id')->get() as $row) {
            $companyId = (int) ($row->company_id ?? 0);
            $code = 'COMM-'.$row->id;

            $existing = DB::table('compensations')
                ->where('company_id', $companyId)
                ->where('code', $code)
                ->first();

            if ($existing !== null) {
                $map[(int) $row->id] = (int) $existing->id;

                continue;
            }

            $taxable = (int) ($row->is_taxable ?? 1) === 1;

            $map[(int) $row->id] = (int) DB::table('compensations')->insertGetId([
                'company_id' => $companyId,
                'code' => $code,
                'name' => $this->catalogueName($row->name ?? 'Commission'),
                'tax_class_code' => null,
                'tax_class' => 'Commission',
                'group' => self::GROUP_COMMISSION,
                'tax_option' => $taxable ? 'fully_taxable' : 'fully_nontaxable',
                'is_taxable' => $taxable,
                'amount' => 0,
                'status' => 'Active',
                'amount_basis' => $row->amount_basis ?? null,
                'frequency' => null,
                'maximum_non_tax' => $row->maximum_non_tax ?? null,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $map
     */
    private function copyAllowanceAssignments(array $map): void
    {
        if (! Schema::hasTable('employees_allowances') || $map === []) {
            return;
        }

        foreach (DB::table('employees_allowances')->orderBy('id')->get() as $row) {
            $compensationId = $map[(int) $row->allowance_id] ?? null;

            if ($compensationId === null) {
                continue;
            }

            $compensation = DB::table('compensations')->where('id', $compensationId)->first();

            DB::table('employees_compensations')->insert([
                'company_id' => (int) ($row->company_id ?? $compensation->company_id ?? 0),
                'employee_id' => $row->employee_id,
                'compensation_id' => $compensationId,
                'is_special' => (int) ($row->is_special ?? 0),
                'release_date' => $row->release_date ?? null,
                'amount' => $row->amount ?? null,
                'valid_date_from' => $row->valid_date_from ?? null,
                'valid_date_to' => $row->valid_date_to ?? null,
                'is_recurring' => (int) ($row->is_recurring ?? 0),
                'frequency' => $row->frequency ?? null,
                'is_never_expires' => (int) ($row->is_never_expires ?? 0),
                'is_prorated' => (int) ($row->is_prorated ?? 0),
                'prorated_by' => $row->prorated_by ?? null,
                'on_paid_leave' => (int) ($row->on_paid_leave ?? 0),
                'on_unpaid_leave' => (int) ($row->on_unpaid_leave ?? 0),
                'is_taxable' => $this->taxableFromTaxOption((string) ($compensation->tax_option ?? '')),
                'is_active' => 1,
                'status' => 'not_release',
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    /**
     * @param  array<int, int>  $map
     */
    private function copyBonusAssignments(array $map): void
    {
        if (! Schema::hasTable('employees_bonuses') || $map === []) {
            return;
        }

        foreach (DB::table('employees_bonuses')->orderBy('id')->get() as $row) {
            $compensationId = $map[(int) ($row->bonus_id ?? 0)] ?? null;

            if ($compensationId === null) {
                continue;
            }

            $compensation = DB::table('compensations')->where('id', $compensationId)->first();

            DB::table('employees_compensations')->insert([
                'company_id' => (int) ($row->company_id ?? $compensation->company_id ?? 0),
                'employee_id' => $row->employee_id,
                'compensation_id' => $compensationId,
                'is_special' => 1,
                'release_date' => $row->release_date ?? null,
                'amount' => $row->amount ?? null,
                'valid_date_from' => null,
                'valid_date_to' => null,
                'is_recurring' => 0,
                'frequency' => null,
                'is_never_expires' => 0,
                'is_prorated' => 0,
                'prorated_by' => null,
                'on_paid_leave' => 0,
                'on_unpaid_leave' => 0,
                'is_taxable' => (int) ($compensation->is_taxable ?? 1),
                'is_active' => 1,
                'status' => $row->status ?: 'not_release',
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    /**
     * @param  array<int, int>  $map
     */
    private function copyCommissionAssignments(array $map): void
    {
        if (! Schema::hasTable('employees_commissions') || $map === []) {
            return;
        }

        foreach (DB::table('employees_commissions')->orderBy('id')->get() as $row) {
            $compensationId = $map[(int) ($row->commission_id ?? 0)] ?? null;

            if ($compensationId === null) {
                continue;
            }

            $compensation = DB::table('compensations')->where('id', $compensationId)->first();

            DB::table('employees_compensations')->insert([
                'company_id' => (int) ($row->company_id ?? $compensation->company_id ?? 0),
                'employee_id' => $row->employee_id,
                'compensation_id' => $compensationId,
                'is_special' => 1,
                'release_date' => $row->release_date ?? null,
                'amount' => $row->amount ?? null,
                'valid_date_from' => null,
                'valid_date_to' => null,
                'is_recurring' => 0,
                'frequency' => null,
                'is_never_expires' => 0,
                'is_prorated' => 0,
                'prorated_by' => null,
                'on_paid_leave' => 0,
                'on_unpaid_leave' => 0,
                'is_taxable' => (int) ($compensation->is_taxable ?? 1),
                'is_active' => 1,
                'status' => $row->status ?: 'not_release',
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    /**
     * @param  array<int, int>  $map
     */
    private function copyAllowanceRecurrings(array $map): void
    {
        if (! Schema::hasTable('employees_allowances_recurrings')
            || ! Schema::hasTable('employees_allowances')
            || $map === []) {
            return;
        }

        $allowances = DB::table('employees_allowances')
            ->get(['id', 'allowance_id'])
            ->keyBy('id');

        foreach (DB::table('employees_allowances_recurrings')->orderBy('id')->get() as $row) {
            $allowance = $allowances->get($row->employee_allowance_id);

            if ($allowance === null) {
                continue;
            }

            $compensationId = $map[(int) $allowance->allowance_id] ?? null;

            if ($compensationId === null) {
                continue;
            }

            $compensation = DB::table('compensations')->where('id', $compensationId)->first();

            DB::table('employees_compensations')->insert([
                'company_id' => (int) ($row->company_id ?? $compensation->company_id ?? 0),
                'employee_id' => $row->employee_id,
                'compensation_id' => $compensationId,
                'is_special' => 1,
                'release_date' => $row->release_date ?? null,
                'amount' => $row->amount ?? null,
                'valid_date_from' => null,
                'valid_date_to' => null,
                'is_recurring' => 0,
                'frequency' => null,
                'is_never_expires' => 0,
                'is_prorated' => 0,
                'prorated_by' => null,
                'on_paid_leave' => 0,
                'on_unpaid_leave' => 0,
                'is_taxable' => $this->taxableFromTaxOption((string) ($compensation->tax_option ?? '')),
                'is_active' => 1,
                'status' => $row->status ?: 'not_release',
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function dropLegacyTables(): void
    {
        foreach ([
            'employees_allowances_recurrings',
            'employees_allowances',
            'employees_bonuses',
            'employees_commissions',
            'allowances',
            'bonuses',
            'commissions',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function recreateLegacyTables(): void
    {
        if (! Schema::hasTable('allowances')) {
            Schema::create('allowances', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->string('name', 128)->nullable();
                $table->integer('amount')->nullable();
                $table->string('tax_option', 32)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('bonuses')) {
            Schema::create('bonuses', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->string('name', 128);
                $table->string('amount_basis', 32)->nullable();
                $table->string('frequency', 32)->nullable();
                $table->boolean('is_taxable')->default(false);
                $table->integer('maximum_non_tax')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('commissions')) {
            Schema::create('commissions', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->string('name', 128);
                $table->string('amount_basis', 32)->nullable();
                $table->boolean('is_taxable')->default(false);
                $table->integer('maximum_non_tax')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('employees_allowances')) {
            Schema::create('employees_allowances', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('employee_id')->nullable();
                $table->integer('allowance_id');
                $table->boolean('is_special')->default(true);
                $table->date('release_date')->nullable();
                $table->integer('amount');
                $table->date('valid_date_from')->nullable();
                $table->date('valid_date_to')->nullable();
                $table->boolean('is_recurring')->default(false);
                $table->string('frequency', 32)->nullable();
                $table->boolean('is_never_expires')->default(false);
                $table->boolean('is_prorated')->default(false);
                $table->string('prorated_by', 8)->nullable();
                $table->boolean('on_paid_leave')->default(false);
                $table->boolean('on_unpaid_leave')->default(false);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->unsignedBigInteger('company_id');
                $table->index(['employee_id', 'allowance_id']);
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('employees_allowances_recurrings')) {
            Schema::create('employees_allowances_recurrings', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('employee_id')->nullable();
                $table->integer('employee_allowance_id');
                $table->integer('allowance_id');
                $table->date('release_date')->nullable();
                $table->integer('amount');
                $table->string('status', 32)->nullable()->default('not_release');
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->unsignedBigInteger('company_id');
                $table->index(['employee_id', 'employee_allowance_id'], 'ear_employee_allowance_index');
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('employees_bonuses')) {
            Schema::create('employees_bonuses', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('employee_id')->nullable();
                $table->integer('bonus_id')->nullable();
                $table->integer('amount')->nullable();
                $table->date('release_date')->nullable();
                $table->string('status', 32)->default('not_release');
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->unsignedBigInteger('company_id');
                $table->index(['employee_id', 'bonus_id']);
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('employees_commissions')) {
            Schema::create('employees_commissions', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('employee_id')->nullable();
                $table->integer('commission_id')->nullable();
                $table->integer('amount')->nullable();
                $table->date('release_date')->nullable();
                $table->string('status', 32)->default('not_release');
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->unsignedBigInteger('company_id');
                $table->index(['employee_id', 'commission_id']);
                $table->index(['company_id']);
            });
        }
    }

    /**
     * @return array<int, int> compensation id => allowance id
     */
    private function restoreAllowanceCatalog(): array
    {
        $map = [];

        foreach (DB::table('compensations')->where('group', self::GROUP_ALLOWANCE)->orderBy('id')->get() as $row) {
            $map[(int) $row->id] = (int) DB::table('allowances')->insertGetId([
                'company_id' => $row->company_id,
                'name' => $row->name,
                'amount' => (int) ($row->amount ?? 0),
                'tax_option' => $row->tax_option,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }

        return $map;
    }

    /**
     * @return array<int, int> compensation id => bonus id
     */
    private function restoreBonusCatalog(): array
    {
        $map = [];

        foreach (DB::table('compensations')->where('group', self::GROUP_BONUS)->orderBy('id')->get() as $row) {
            $map[(int) $row->id] = (int) DB::table('bonuses')->insertGetId([
                'company_id' => $row->company_id,
                'name' => $row->name,
                'amount_basis' => $row->amount_basis,
                'frequency' => $row->frequency,
                'is_taxable' => (int) $row->is_taxable,
                'maximum_non_tax' => $row->maximum_non_tax,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }

        return $map;
    }

    /**
     * @return array<int, int> compensation id => commission id
     */
    private function restoreCommissionCatalog(): array
    {
        $map = [];

        foreach (DB::table('compensations')->where('group', self::GROUP_COMMISSION)->orderBy('id')->get() as $row) {
            $map[(int) $row->id] = (int) DB::table('commissions')->insertGetId([
                'company_id' => $row->company_id,
                'name' => $row->name,
                'amount_basis' => $row->amount_basis,
                'is_taxable' => (int) $row->is_taxable,
                'maximum_non_tax' => $row->maximum_non_tax,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $map
     */
    private function restoreAllowanceAssignments(array $map): void
    {
        $rows = DB::table('employees_compensations')
            ->whereIn('compensation_id', array_keys($map))
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            DB::table('employees_allowances')->insert([
                'employee_id' => $row->employee_id,
                'allowance_id' => $map[(int) $row->compensation_id],
                'is_special' => (int) ($row->is_special ?? 0),
                'release_date' => $row->release_date,
                'amount' => (int) ($row->amount ?? 0),
                'valid_date_from' => $row->valid_date_from,
                'valid_date_to' => $row->valid_date_to,
                'is_recurring' => (int) ($row->is_recurring ?? 0),
                'frequency' => $row->frequency,
                'is_never_expires' => (int) ($row->is_never_expires ?? 0),
                'is_prorated' => (int) ($row->is_prorated ?? 0),
                'prorated_by' => $row->prorated_by,
                'on_paid_leave' => (int) ($row->on_paid_leave ?? 0),
                'on_unpaid_leave' => (int) ($row->on_unpaid_leave ?? 0),
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
                'company_id' => $row->company_id,
            ]);
        }

        DB::table('employees_compensations')
            ->whereIn('compensation_id', array_keys($map))
            ->delete();
    }

    /**
     * @param  array<int, int>  $map
     */
    private function restoreBonusAssignments(array $map): void
    {
        $rows = DB::table('employees_compensations')
            ->whereIn('compensation_id', array_keys($map))
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            DB::table('employees_bonuses')->insert([
                'employee_id' => $row->employee_id,
                'bonus_id' => $map[(int) $row->compensation_id],
                'amount' => $row->amount,
                'release_date' => $row->release_date,
                'status' => $row->status ?: 'not_release',
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
                'company_id' => $row->company_id,
            ]);
        }

        DB::table('employees_compensations')
            ->whereIn('compensation_id', array_keys($map))
            ->delete();
    }

    /**
     * @param  array<int, int>  $map
     */
    private function restoreCommissionAssignments(array $map): void
    {
        $rows = DB::table('employees_compensations')
            ->whereIn('compensation_id', array_keys($map))
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            DB::table('employees_commissions')->insert([
                'employee_id' => $row->employee_id,
                'commission_id' => $map[(int) $row->compensation_id],
                'amount' => $row->amount,
                'release_date' => $row->release_date,
                'status' => $row->status ?: 'not_release',
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
                'company_id' => $row->company_id,
            ]);
        }

        DB::table('employees_compensations')
            ->whereIn('compensation_id', array_keys($map))
            ->delete();
    }

    private function dropConsolidatedColumns(): void
    {
        if (Schema::hasTable('employees_compensations')) {
            if (Schema::hasIndex('employees_compensations', ['company_id', 'status'])) {
                Schema::table('employees_compensations', function (Blueprint $table): void {
                    $table->dropIndex(['company_id', 'status']);
                });
            }

            if (Schema::hasColumn('employees_compensations', 'status')) {
                Schema::table('employees_compensations', function (Blueprint $table): void {
                    $table->dropColumn('status');
                });
            }
        }

        if (Schema::hasTable('compensations')) {
            if (Schema::hasIndex('compensations', ['company_id', 'group'])) {
                Schema::table('compensations', function (Blueprint $table): void {
                    $table->dropIndex(['company_id', 'group']);
                });
            }

            foreach (['amount_basis', 'frequency', 'maximum_non_tax'] as $column) {
                if (Schema::hasColumn('compensations', $column)) {
                    Schema::table('compensations', function (Blueprint $table) use ($column): void {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        DB::table('compensations')
            ->whereIn('group', [self::GROUP_ALLOWANCE, self::GROUP_BONUS, self::GROUP_COMMISSION])
            ->delete();
    }

    private function catalogueName(?string $name): string
    {
        return mb_substr(trim((string) $name), 0, 64) ?: 'Compensation';
    }

    private function taxableFromTaxOption(string $taxOption): bool
    {
        $option = strtolower(trim($taxOption));

        return ! str_contains($option, 'non') && ! str_contains($option, 'exempt');
    }
};
