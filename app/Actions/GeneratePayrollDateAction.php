<?php

namespace App\Actions;

use App\Models\PayrollDate;
use App\Models\PayrollGroup;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;
use Psy\ExecutionLoop\Listener;

class GeneratePayrollDateAction extends BaseAction
{
    use AsAction;

    public string $commandSignature = 'payroll_date:generate {payroll_group_id?}';

    public string $commandDescription = 'Generate payroll date so that we dont need to run query and computation always.';

    public function handle(PayrollGroup $payrollGroup, $isUpdate = false)
    {
        if ($payrollGroup == null) {
            return;
        }

        if ($isUpdate) {
            // Remove all open payroll dates
            PayrollDate::where('payroll_group_id', $payrollGroup->id)
                ->where('status', PayrollDate::STATUS_OPEN)
                ->delete();
        }

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

        return Command::SUCCESS;
    }

    public function asCommand(Command $command)
    {
        $payrollGroup = PayrollGroup::find($command->argument('payroll_group_id'));

        if ($payrollGroup == null) {

            $payrollGroups = PayrollGroup::all();

            foreach ($payrollGroups as $payrollGroup) {
                $this->handle($payrollGroup);

                $command->comment('okay na. genarated');
            }
        } else {
            $this->handle($payrollGroup);
        }

        // $command->comment('okay na. genarated from action.');
    }

    // public function asListener(Listener $event): void
    // {
    //     $this->handle($event->source, $event->destination);
    // }

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
        // TODO just return if the date is more than 3 months
        $payrollDate = PayrollDate::firstOrNew([
            'company_id' => $payrollGroup->company_id,
            'payroll_group_id' => $payrollGroup->id,
            'payroll_date' => $date,
        ]);

        $thisMonth = Carbon::createFromFormat('Y-m-d', $date);
        $lastMonth = Carbon::createFromFormat('Y-m-d', $date);

        $payrollDate->first_second = $firstSecond;

        $attendanceDateStart = null;
        $attendanceDateEnd = null;

        if ($payrollGroup->frequency == PayrollGroup::FREQUENCY_SEMI_MONTHLY) {

            // Get the attendance coverage
            if ($payrollDate->first_second == PayrollDate::FIRST_CUTOFF) {
                // Need to move one month backward to get the month
                $lastMonth->subMonth();

                $attendanceDateStart = $lastMonth->format('Y-m-') . $payrollGroup->second_cut_off + 1;
                $attendanceDateEnd = $thisMonth->format('Y-m-') . $payrollGroup->first_cut_off;
            }

            if ($payrollDate->first_second == PayrollDate::SECOND_CUTOFF) {
                $attendanceDateStart = $lastMonth->format('Y-m-') . $payrollGroup->first_cut_off + 1;
                $attendanceDateEnd = $thisMonth->format('Y-m-') . $payrollGroup->second_cut_off;
            }
        } elseif ($payrollGroup->frequency == PayrollGroup::FREQUENCY_MONTHLY) {
            $attendanceDateStart = $thisMonth->format('Y-m-') . '1';
            $attendanceDateEnd = $thisMonth->format('Y-m-t');
        }

        $payrollDate->attendance_date_start = $attendanceDateStart;
        $payrollDate->attendance_date_end = $attendanceDateEnd;
        $payrollDate->save();
    }

    // We can add this as controller route
    // Route::post('/leaves', GeneratePayrollDateAction::class)->middleware('auth');
    public function asController(Request $request)
    {
        $this->handle();

        return redirect()->back();
    }
}
