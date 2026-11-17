<?php

namespace App\Observers;

use App\Models\PayrollDetail;
use App\Models\PayrollYearDate;

class PayrollDetailObserver
{
    /**
     * Handle the Employee "created" event.
     *
     * @return void
     */
    public function created(PayrollDetail $payrollDetail)
    {
        $this->updateRecord($payrollDetail);
    }

    /**
     * Handle the Employee "updated" event.
     *
     * @return void
     */
    public function updated(PayrollDetail $payrollDetail)
    {
        $this->updateRecord($payrollDetail);
    }

    /**
     * Handle the Employee "deleted" event.
     *
     * @return void
     */
    public function deleted(PayrollDetail $payrollDetail)
    {
        //
    }

    /**
     * Handle the Employee "restored" event.
     *
     * @return void
     */
    public function restored(PayrollDetail $payrollDetail)
    {
        //
    }

    /**
     * Handle the Employee "force deleted" event.
     *
     * @return void
     */
    public function forceDeleted(PayrollDetail $payrollDetail)
    {
        //
    }

    public function updateRecord(PayrollDetail $payrollDetail)
    {
        if ($payrollDetail->year == null) {
            return;
        }

        // TODO make sure that the payroll is final before
        // we update the year to date details
        // $payrollDetail->payroll

        // We could also put this on queue
        // so that it wont take so long to process
        // on users side

        $yearToDate = PayrollYearDate::firstOrNew([
            'company_id' => $payrollDetail->company_id,
            'employee_id' => $payrollDetail->employee_id,
            'year' => $payrollDetail->year,
        ]);

        $details = PayrollDetail::query()
            ->where('employee_id', $payrollDetail->employee_id)
            ->get();

        $yearToDate->taxable_gross = $details->sum('gross_income');
        $yearToDate->tax = $details->sum('withholding_tax');

        $yearToDate->sss = $details->sum('sss_employee_total');
        $yearToDate->philhealth = $details->sum('philhealth_employee');
        $yearToDate->hdmf = $details->sum('hdmf_employee');

        $yearToDate->gross_income = $details->sum('gross_income');
        $yearToDate->non_taxable = $details->sum('non_taxable_income');

        $yearToDate->save();
    }
}
