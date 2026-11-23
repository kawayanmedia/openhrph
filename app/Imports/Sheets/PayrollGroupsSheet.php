<?php

namespace App\Imports\Sheets;

use App\Actions\GeneratePayrollDateAction;
use App\Imports\Workbook\Column;
use App\Imports\Workbook\ColumnType;
use App\Imports\Workbook\ReferenceIndex;
use App\Imports\Workbook\SheetDefinition;
use App\Models\PayrollGroup;
use Illuminate\Database\Eloquent\Model;

class PayrollGroupsSheet extends SheetDefinition
{
    private const FREQUENCIES = ['monthly', 'semi-monthly'];

    private const PAYMENT_SCHEDULES = ['First Pay', 'Last Pay', 'Every Pay'];

    private const NON_WORKING_DATES = [
        'Pay date before non working day',
        'Pay date after non working day',
        'Remain as schedule pay date',
    ];

    public function key(): string
    {
        return 'payroll-groups';
    }

    public function title(): string
    {
        return 'Payroll Groups';
    }

    public function aliases(): array
    {
        return ['Payroll Group'];
    }

    /**
     * @return class-string<Model>
     */
    public function model(): string
    {
        return PayrollGroup::class;
    }

    public function keyColumns(): array
    {
        return ['name'];
    }

    public function referenceType(): ?string
    {
        return 'payroll_group';
    }

    public function referenceNameColumn(): ?string
    {
        return 'name';
    }

    public function columns(): array
    {
        return [
            new Column(
                key: 'name',
                heading: 'Name',
                type: ColumnType::Text,
                required: true,
                rules: ['max:32'],
                aliases: ['Payroll Group'],
                example: 'Semi-Monthly',
                help: 'Unique payroll group name used by employees.',
            ),
            new Column(
                key: 'frequency',
                heading: 'Frequency',
                type: ColumnType::Text,
                required: true,
                rules: ['max:32'],
                options: self::FREQUENCIES,
                example: 'semi-monthly',
                help: 'monthly or semi-monthly.',
            ),
            new Column(
                key: 'days_year',
                heading: 'Days per Year',
                type: ColumnType::Integer,
                rules: ['integer', 'between:1,366'],
                example: '261',
                help: 'Working days per year: 261 for Monday–Friday, 313 for Monday–Saturday.',
            ),
            new Column(
                key: 'hours_day',
                heading: 'Hours per Day',
                type: ColumnType::Integer,
                rules: ['integer', 'between:1,24'],
                example: '8',
                help: 'The required number of hours per day.',
            ),
            new Column(
                key: 'is_default',
                heading: 'Default',
                type: ColumnType::Boolean,
                options: ['Yes', 'No'],
                example: 'Yes',
                help: 'The default payroll group is assigned to new employees.',
            ),
            new Column(
                key: 'compute_tardiness',
                heading: 'Compute Tardiness',
                type: ColumnType::Boolean,
                options: ['Yes', 'No'],
                example: 'Yes',
            ),
            new Column(
                key: 'compute_overbreak',
                heading: 'Compute Overbreak',
                type: ColumnType::Boolean,
                options: ['Yes', 'No'],
                example: 'No',
            ),
            new Column(
                key: 'compute_undertime',
                heading: 'Compute Undertime',
                type: ColumnType::Boolean,
                options: ['Yes', 'No'],
                example: 'Yes',
            ),
            new Column(
                key: 'first_cut_off',
                heading: 'First Cut-off',
                type: ColumnType::Integer,
                rules: ['integer', 'between:1,31'],
                example: '10',
                help: 'Day of the month (1–31). Required for every payroll group.',
            ),
            new Column(
                key: 'first_pay_date',
                heading: 'First Pay Date',
                type: ColumnType::Integer,
                rules: ['integer', 'between:1,31'],
                example: '15',
                help: 'Day of the month (1–31). Required for every payroll group.',
            ),
            new Column(
                key: 'second_cut_off',
                heading: 'Second Cut-off',
                type: ColumnType::Integer,
                rules: ['integer', 'between:1,31'],
                example: '25',
                help: 'Semi-monthly groups only. Leave blank for monthly groups.',
            ),
            new Column(
                key: 'second_pay_date',
                heading: 'Second Pay Date',
                type: ColumnType::Integer,
                rules: ['integer', 'between:1,31'],
                example: '30',
                help: 'Semi-monthly groups only. Leave blank for monthly groups.',
            ),
            new Column(
                key: 'pay_date_non_working',
                heading: 'Pay Date on Non-Working Day',
                type: ColumnType::Text,
                rules: ['max:64'],
                options: self::NON_WORKING_DATES,
                example: 'Pay date before non working day',
                help: 'What happens when a pay date falls on a weekend or holiday.',
            ),
            new Column(
                key: 'sss_schedule',
                heading: 'SSS Schedule',
                type: ColumnType::Text,
                rules: ['max:32'],
                options: self::PAYMENT_SCHEDULES,
                example: 'Every Pay',
                help: 'When the SSS contribution is deducted.',
            ),
            new Column(
                key: 'phil_health_schedule',
                heading: 'PhilHealth Schedule',
                type: ColumnType::Text,
                rules: ['max:32'],
                options: self::PAYMENT_SCHEDULES,
                example: 'Every Pay',
                help: 'When the PhilHealth contribution is deducted.',
            ),
            new Column(
                key: 'hdmf_schedule',
                heading: 'HDMF Schedule',
                type: ColumnType::Text,
                rules: ['max:32'],
                options: self::PAYMENT_SCHEDULES,
                example: 'Every Pay',
                help: 'When the HDMF contribution is deducted.',
            ),
        ];
    }

    public function sampleRows(): array
    {
        return [
            [
                'name' => 'Semi-Monthly',
                'frequency' => 'semi-monthly',
                'days_year' => '261',
                'hours_day' => '8',
                'is_default' => 'Yes',
                'compute_tardiness' => 'Yes',
                'compute_overbreak' => 'No',
                'compute_undertime' => 'Yes',
                'first_cut_off' => '10',
                'first_pay_date' => '15',
                'second_cut_off' => '25',
                'second_pay_date' => '30',
                'pay_date_non_working' => 'Pay date before non working day',
                'sss_schedule' => 'Every Pay',
                'phil_health_schedule' => 'Every Pay',
                'hdmf_schedule' => 'Every Pay',
            ],
        ];
    }

    public function validateValues(array $values, int $row, callable $add): void
    {
        $frequency = $this->frequency((string) $values['frequency']);

        if ($frequency === null) {
            $add('frequency', 'Frequency must be monthly or semi-monthly.');

            return;
        }

        $creating = $this->findExisting($values) === null;

        if (isset($values['pay_date_non_working']) && $this->nonWorkingDate((string) $values['pay_date_non_working']) === null) {
            $add('pay_date_non_working', 'Choose Pay date before non working day, Pay date after non working day or Remain as schedule pay date.');
        }

        foreach (['sss_schedule', 'phil_health_schedule', 'hdmf_schedule'] as $key) {
            if (isset($values[$key]) && $this->paymentSchedule((string) $values[$key]) === null) {
                $add($key, 'Choose First Pay, Last Pay or Every Pay.');
            }
        }

        if ($frequency === PayrollGroup::FREQUENCY_MONTHLY) {
            if ($creating && ! isset($values['first_cut_off'])) {
                $add('first_cut_off', 'First Cut-off is required for a monthly payroll group.');
            }

            if ($creating && ! isset($values['first_pay_date'])) {
                $add('first_pay_date', 'First Pay Date is required for a monthly payroll group.');
            }

            if (isset($values['second_cut_off']) || isset($values['second_pay_date'])) {
                $add(
                    isset($values['second_cut_off']) ? 'second_cut_off' : 'second_pay_date',
                    'Monthly payroll groups do not use a second cut-off or second pay date. Leave both blank.',
                );
            }

            return;
        }

        if ($creating) {
            $required = [
                'first_cut_off' => 'First Cut-off',
                'first_pay_date' => 'First Pay Date',
                'second_cut_off' => 'Second Cut-off',
                'second_pay_date' => 'Second Pay Date',
            ];

            foreach ($required as $key => $label) {
                if (! isset($values[$key])) {
                    $add($key, $label.' is required for a semi-monthly payroll group.');
                }
            }
        }

        $firstCutOff = $values['first_cut_off'] ?? null;
        $secondCutOff = $values['second_cut_off'] ?? null;

        if ($firstCutOff !== null && $secondCutOff !== null && $firstCutOff >= $secondCutOff) {
            $add('second_cut_off', 'Second Cut-off must be later than First Cut-off.');
        }

        $firstPayDate = $values['first_pay_date'] ?? null;
        $secondPayDate = $values['second_pay_date'] ?? null;

        if ($firstPayDate !== null && $secondPayDate !== null && $firstPayDate >= $secondPayDate) {
            $add('second_pay_date', 'Second Pay Date must be later than First Pay Date.');
        }
    }

    public function fill(Model $model, array $values, ReferenceIndex $references, bool $creating): void
    {
        if ($creating) {
            $model->setAttribute('days_year', PayrollGroup::NUMBER_WORKING_DAYS_MONFRI);
            $model->setAttribute('hours_day', 8);
            $model->setAttribute('is_default', false);
            $model->setAttribute('compute_tardiness', false);
            $model->setAttribute('compute_overbreak', false);
            $model->setAttribute('compute_undertime', false);
            $model->setAttribute('pay_date_non_working', PayrollGroup::NON_WORKING_DATE_DATE);
            $model->setAttribute('tax_schedule', PayrollGroup::PAY_SCHEDULE_EVERYPAY);
            $model->setAttribute('sss_schedule', PayrollGroup::PAY_SCHEDULE_EVERYPAY);
            $model->setAttribute('phil_health_schedule', PayrollGroup::PAY_SCHEDULE_EVERYPAY);
            $model->setAttribute('hdmf_schedule', PayrollGroup::PAY_SCHEDULE_EVERYPAY);
        }

        $this->setValues($model, $values, [
            'name' => 'name',
            'days_year' => 'days_year',
            'hours_day' => 'hours_day',
            'is_default' => 'is_default',
            'compute_tardiness' => 'compute_tardiness',
            'compute_overbreak' => 'compute_overbreak',
            'compute_undertime' => 'compute_undertime',
            'first_cut_off' => 'first_cut_off',
            'first_pay_date' => 'first_pay_date',
            'second_cut_off' => 'second_cut_off',
            'second_pay_date' => 'second_pay_date',
        ]);

        $frequency = $this->frequency((string) $values['frequency']);

        if ($frequency !== null) {
            $model->setAttribute('frequency', $frequency);
        }

        if ($frequency === PayrollGroup::FREQUENCY_MONTHLY) {
            $model->setAttribute('second_cut_off', null);
            $model->setAttribute('second_pay_date', null);
        }

        if (isset($values['pay_date_non_working'])) {
            $nonWorkingDate = $this->nonWorkingDate((string) $values['pay_date_non_working']);

            if ($nonWorkingDate !== null) {
                $model->setAttribute('pay_date_non_working', $nonWorkingDate);
            }
        }

        foreach (['sss_schedule', 'phil_health_schedule', 'hdmf_schedule'] as $key) {
            if (! isset($values[$key])) {
                continue;
            }

            $schedule = $this->paymentSchedule((string) $values[$key]);

            if ($schedule !== null) {
                $model->setAttribute($key, $schedule);
            }
        }
    }

    public function afterSave(Model $model, array $values, bool $creating): void
    {
        if ($model->getAttribute('is_default')) {
            PayrollGroup::query()->whereKeyNot($model->getKey())->update(['is_default' => false]);
        }

        $frequency = $model->getAttribute('frequency');

        if (! in_array($frequency, [PayrollGroup::FREQUENCY_MONTHLY, PayrollGroup::FREQUENCY_SEMI_MONTHLY], true)) {
            return;
        }

        if ($model->getAttribute('first_pay_date') === null) {
            return;
        }

        if ($frequency === PayrollGroup::FREQUENCY_SEMI_MONTHLY && $model->getAttribute('second_pay_date') === null) {
            return;
        }

        GeneratePayrollDateAction::run($model, ! $creating);
    }

    private function frequency(string $value): ?string
    {
        return match (strtolower(str_replace('_', '-', trim($value)))) {
            'monthly' => PayrollGroup::FREQUENCY_MONTHLY,
            'semi-monthly', 'semi monthly', 'semimonthly' => PayrollGroup::FREQUENCY_SEMI_MONTHLY,
            default => null,
        };
    }

    private function paymentSchedule(string $value): ?string
    {
        return match (strtolower(trim($value))) {
            'first_pay', 'first pay' => PayrollGroup::PAY_SCHEDULE_FIRSTPAY,
            'last_pay', 'last pay' => PayrollGroup::PAY_SCHEDULE_LASTPAY,
            'every_pay', 'every pay' => PayrollGroup::PAY_SCHEDULE_EVERYPAY,
            default => null,
        };
    }

    private function nonWorkingDate(string $value): ?string
    {
        return match (strtolower(trim($value))) {
            'pay_date_before', 'pay date before non working day', 'before' => PayrollGroup::NON_WORKING_DATE_BEFORE,
            'pay_date_after', 'pay date after non working day', 'after' => PayrollGroup::NON_WORKING_DATE_AFTER,
            'pay_date', 'remain as schedule pay date', 'as scheduled' => PayrollGroup::NON_WORKING_DATE_DATE,
            default => null,
        };
    }
}
