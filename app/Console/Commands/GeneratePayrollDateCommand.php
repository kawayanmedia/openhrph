<?php

namespace App\Console\Commands;

use App\Models\PayrollDate;
use App\Models\PayrollGroup;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GeneratePayrollDateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll_date:generate--1';

    /**
     * Generate payroll date so that we dont need to run query and computation always
     *
     * @var string
     */
    protected $description = 'Generate payroll date so that we dont need to run query and computation always.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // We dont use this anymore.
        // We are using the command to generate payroll date `GeneratePayrollDateAction`

        return;
        $payrollGroups = PayrollGroup::all();

        foreach ($payrollGroups as $payrollGroup) {
            // Generate dates from 3 months before and
            // 3 months after the current system date
            $date = now();
            $dateTo = now();

            $period = \Carbon\CarbonPeriod::create($date->subMonth(3), '1 month', $dateTo->addMonth(3));

            // Once we get the months then we get the date of
            // payroll on every month
            foreach ($period as $dt) {
                $month = $dt->format('Y-m');
                if ($payrollGroup->frequency == PayrollGroup::FREQUENCY_SEMI_MONTHLY) {

                    $date1 = $this->getValidPayDate($payrollGroup, $month . '-' . $payrollGroup->first_pay_date);
                    $date2 = $this->getValidPayDate($payrollGroup, $month . '-' . $payrollGroup->second_pay_date);

                    $this->addValidPayDate($payrollGroup, $date1, 1);
                    $this->addValidPayDate($payrollGroup, $date2, 2);
                } elseif ($payrollGroup->frequency == PayrollGroup::FREQUENCY_MONTHLY) {
                    $date = $this->getValidPayDate($payrollGroup, $month . '-' . $payrollGroup->first_pay_date);

                    $this->addValidPayDate($payrollGroup, $date);
                }
            }

            $this->comment('okay na. genarated');
        }

        return Command::SUCCESS;
    }

    public function getValidPayDate($payrollGroup, $date)
    {
        $firstPayDate = Carbon::createFromFormat('Y-m-d', $date);

        // Check for non working day
        // Check first if holiday or weekend
        $isWeekendHoliday = false;
        if ($firstPayDate->isWeekend() or is_holiday($firstPayDate)) {
            $isWeekendHoliday = true;
        }

        $payDate = $firstPayDate;

        if ($isWeekendHoliday === true) {

            if ($payrollGroup->pay_date_non_working == PayrollGroup::NON_WORKING_DATE_BEFORE) {

                // Move backward then check for a date that is not holiday or weekend
                while ($payDate->isWeekend() or is_holiday($payDate->format('Y-m-d'))) {
                    $payDate->subDay();
                }

                return $payDate->format('Y-m-d');
            }

            if ($payrollGroup->pay_date_non_working == PayrollGroup::NON_WORKING_DATE_AFTER) {
                // Move forward then check for a date that is not holiday or weekend
                while ($payDate->isWeekend() or is_holiday($payDate->format('Y-m-d'))) {
                    $payDate->addDay();
                }

                return $payDate->format('Y-m-d');
            }

            if ($payrollGroup->pay_date_non_working == PayrollGroup::NON_WORKING_DATE_DATE) {
                return $payDate->format('Y-m-d');
            }
        }

        return $payDate->format('Y-m-d');
    }

    public function addValidPayDate($payrollGroup, $date, $firstSecond = null)
    {
        $payrollDate = PayrollDate::firstOrNew([
            'company_id' => $payrollGroup->company_id,
            'payroll_group_id' => $payrollGroup->id,
            'payroll_date' => $date,
        ]);

        $payrollDate->company_id = $payrollGroup->company_id;
        $payrollDate->first_second = $firstSecond;
        $payrollDate->save();
    }
}
