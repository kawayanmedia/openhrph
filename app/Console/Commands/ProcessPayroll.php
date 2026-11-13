<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollGroup;
use App\Services\Government\GovernmentDeduction;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessPayroll extends Command
{
    use GovernmentDeduction;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:payroll {payroll_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process payroll based on payroll group';

    public $payrollGroup;

    public $frequency = 'semi-monthly';

    public $employee;

    public $basicSalary;

    public $sssSelected;

    public $sssEmployeeShare;

    public $sssEmployeerShare;

    public $totalBonus = 0;

    public $totalTaxableBonus = 0;

    public $totalNonTaxableBonus = 0;

    public $totalCommission = 0;

    public $totalTaxableCommission = 0;

    public $totalNonTaxableCommission = 0;

    public $totalAllowance = 0;

    public $totalTaxableAllowance = 0;

    public $totalNonTaxableAllowance = 0;

    public $employeeDeductions = null;

    public $totalDeduction = 0;

    public $totalTaxableIncome = 0;

    public $totalNonTaxableIncome = 0;

    public $totalWithHoldingTax = 0;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $periodFrom = '2022-03-11';
        $periodTo = '2022-03-25';

        $periodFrom = '2022-03-26';
        $periodTo = '2022-04-10';

        DB::beginTransaction();

        $payrollId = $this->argument('payroll_id') ?? 1;

        $payroll = Payroll::withoutTenant()->find($payrollId);

        if ($payroll == null) {
            $this->comment('not exists');
            exit;
        }

        app(TenantContext::class)->set($payroll->company_id);

        try {
            $this->payrollGroup = PayrollGroup::with(
                'employees.bonuses',
                'employees.commissions',
                'employees.allowances',
                'employees.deductions',
                'employees.loans',
            )->find($payroll->payroll_group_id);

            $this->frequency = $this->payrollGroup->frequency;

            $groupGrossIncome = 0;
            $groupNetIncome = 0;

            $numbers = 100;

            foreach ($this->payrollGroup->employees as $employee) {
                // while($numbers > 0) {
                // $numbers --;

                // We need to query only the active data. For example get only the
                // active bonuses, commissions etc.
                $employee = Employee::with('bonuses', 'commissions', 'allowances', 'deductions', 'loans')->find(2);

                $this->comment('Name: '.$employee->last_name.', '.$employee->first_name);

                $this->employee = $employee;

                $basicSalary = $employee->basic_salary;

                $this->basicSalary = $basicSalary;

                // TODO We need to integrate how many days employee worked as well as absent days

                // https://mpm.ph/compute-employees-daily-rate/
                // https://mpm.ph/tax-accounting-software-service/
                // We devide salary by 2
                if ($this->frequency == PayrollGroup::FREQUENCY_SEMI_MONTHLY) {
                    $basicSalary = $basicSalary / 2;
                    // If work is Mondays to Fridays then
                    $dailyRate = ($employee->basic_salary * 12) / 261;
                }

                if ($this->frequency == PayrollGroup::FREQUENCY_MONTHLY) {
                    // If work is Mondays to Saturdays then
                    $dailyRate = ($employee->basic_salary * 12) / 313;
                }

                // Lets get the daily rate from employee
                $dailyRate = $employee->daily_rate;

                $hourlyRate = $dailyRate / 8;

                // Need to check if employee required a timesheet
                // so that we can deduct any absences
                // We may not need to check for any number of hours if
                // the employee belongs to monthly pay
                if ($employee->timesheet == true) {
                    // Lets assume we have absent
                    $basicSalary = $basicSalary - ($dailyRate * 3);
                }

                $mandatorydeductions = $this->getSSS() +
                                        $this->getSSSProvidentFund() +
                                        $this->getHDMF() +
                                        $this->getEmployeePhilHealth() +
                                        $this->getPhilHealthAdjustment();

                // We can have an option to deduct contributions on 2nd half
                // of the month
                $this->payrollGroup->tax_schedule;
                $this->payrollGroup->sss_schedule;
                $this->payrollGroup->phil_health_schedule;
                $this->payrollGroup->hdmf_schedule;

                if ($this->payrollGroup->sss_schedule === PayrollGroup::PAY_SCHEDULE_FIRSTPAY) {

                }

                if ($this->payrollGroup->sss_schedule === PayrollGroup::PAY_SCHEDULE_LASTPAY) {

                }

                // Taxable income
                // We deduct mandatory deductions from basic salary then add any
                // Taxable amount

                // We add all income like bonus, commissions, allowances etc - (deductions+loans+absent+late+etc)

                // Compute the bonus first
                $this->computeBonus($employee->bonuses);

                $this->computeCommission($employee->commissions);

                $this->computeTotalAllowance($employee->allowances);

                $this->computeTotalDeduction($employee->deductions);

                $this->computeTotalLoans($employee->loans);

                // $additionalIncome = $this->totalTaxableBonus +
                //                     $this->totalTaxableCommission +
                //                     $this->totalTaxableAllowance;

                // $grossIncome = ($basicSalary + $additionalIncome) - $this->totalDeduction; // may be we can add deductions for absent days
                $grossIncome = $basicSalary +
                                $this->totalBonus +
                                $this->totalCommission +
                                $this->totalAllowance;
                $groupGrossIncome += $grossIncome;

                // $taxableIncome = (($basicSalary - $mandatorydeductions) + $additionalIncome) - $this->totalDeduction;

                $taxableIncome = $grossIncome -
                    (
                        $this->totalNonTaxableBonus +
                        $this->totalNonTaxableCommission +
                        $this->totalNonTaxableAllowance +
                        $mandatorydeductions
                    );

                // $totalTax = 0;
                $taxOver = 0;

                $taxBracket = $this->getBracket($taxableIncome);

                // $this->totalWithHoldingTax += $taxBracket['prescribed'];

                // // If amount is over from minimun then x to over
                // if ($taxableIncome > $taxBracket['from']) {
                //     $decimal = floatval($taxBracket['over'].'%') / 100; // like 30% to 0.3
                //     $taxOver = ($taxableIncome - $taxBracket['from']) * $decimal;
                //     $this->totalWithHoldingTax += $taxOver;
                // }

                $this->comment('Basic Salary: '.$employee->basic_salary / 2);
                $this->comment('Daily Rate: '.$dailyRate);
                $this->comment('Total Bonus: '.$this->totalBonus);
                $this->comment('Total Commission: '.$this->totalCommission);
                $this->comment('Total Allowance: '.$this->totalAllowance);
                $this->comment('------GROSS INCOME-----: '.number_format($grossIncome, 2));

                $this->comment('Non Tax Bonus: '.$this->totalNonTaxableBonus);
                $this->comment('Non Tax Commission: '.$this->totalNonTaxableCommission);
                $this->comment('Non Tax Allowance: '.$this->totalNonTaxableAllowance);

                $this->comment('SSS: '.$this->getSSS());
                $this->comment('SSS Provident Fund: '.$this->getSSSProvidentFund());
                $this->comment('pag ibig / HDMF: '.$this->getHDMF());
                $this->comment('philhealth: '.$this->getPhilHealth());
                $this->comment('philhealth adjustment: '.$this->getPhilHealthAdjustment());

                // $this->comment('SSS: '.$tax->deduction->sssDeduction);
                // $this->comment('SSS Provident Fund: '.$tax->deduction->sssDeduction);
                // $this->comment('pag ibig / HDMF: '.$tax->deduction->pagibigDeduction);
                // $this->comment('philhealth: '.$tax->deduction->phDeduction);
                // $this->comment('philhealth adjustment: '.$tax->deduction->phDeduction);

                $this->comment('-------TAXABLE INCOME------: '.round($taxableIncome, 2));

                // $this->comment('mandatorydeductions: '.$mandatorydeductions);

                // $this->comment('Total Taxable Bonus: '.$this->totalTaxableBonus);
                // $this->comment('Total Taxable Commission: '.$this->totalTaxableCommission);
                // $this->comment('Total Allowance: '.$this->totalAllowance);
                // $this->comment('Total Taxable Allowance: '.$this->totalTaxableAllowance);
                // $this->comment('Total Deductions: '.$this->totalDeduction);

                $this->comment('Prescribed tax: '.$taxBracket['prescribed']);
                $this->comment('Over: '.$taxableIncome - $taxBracket['from']);
                $this->comment($taxBracket['over'].'%');
                $this->comment('Tax Over: '.$taxOver);

                $this->comment('totalTax: '.$this->totalWithHoldingTax);

                $this->comment('daily rate: '.$dailyRate);

                // TODO get me
                // $taxAmount = 9180.30;

                foreach ($this->employeeDeductions as $employeDeduction) {
                    $this->comment($employeDeduction['name'].': '.$employeDeduction['amount']);
                }

                $this->comment('-------NET PAY------: '.round(($taxableIncome + $this->totalNonTaxableIncome) - ($this->totalWithHoldingTax + $this->totalDeduction), 2));

                // dd('totalTax:  '.$this->totalWithHoldingTax);

                // dd($taxBracket);

                // Night Differential is taxable

                // Overtime is taxable

                $payrollDetail = PayrollDetail::firstOrNew([
                    'company_id' => $this->payrollGroup->company_id,
                    'payroll_id' => $payroll->id,
                    'employee_id' => $employee->id,
                ]);

                $payrollDetail->gross_income = round($grossIncome, 2);
                $payrollDetail->taxable_income = round($taxableIncome, 2);
                $payrollDetail->withholding_tax = round($this->totalWithHoldingTax, 2);

                $payrollDetail->sss_employee = $this->getEmployeeSSS();
                // $payrollDetail->sss_provident_employee = $tax->deduction->sssDeduction;
                $payrollDetail->sss_employer = $this->getEmployerSSS();
                // $payrollDetail->sss_provident_employer = $tax->deduction->sssDeduction;

                $payrollDetail->hdmf_employee = $this->getHDMF();
                $payrollDetail->hdmf_employer = $this->getHDMF();

                $payrollDetail->philhealth_employee = $this->getPhilHealth();
                $payrollDetail->philhealth_employer = $this->getPhilHealth();

                $employerDeductions = $this->getEmployerSSS() +
                                        $this->getSSSProvidentFund() +
                                        $this->getHDMF() +
                                        $this->getEmployerPhilHealth() +
                                        $this->getPhilHealthAdjustment();

                $payrollDetail->total_employee = $mandatorydeductions;
                $payrollDetail->total_employer = $employerDeductions;

                $payrollDetail->total_deductions = round($mandatorydeductions + $this->totalWithHoldingTax + $this->totalDeduction, 2);

                $payrollDetail->net_pay = round(($taxableIncome + $this->totalNonTaxableIncome) - ($this->totalWithHoldingTax + $this->totalDeduction), 2);

                $payrollDetail->save();

                $groupNetIncome += $payrollDetail->net_pay;

                $this->comment('Done');

                $this->resetAll();
                exit();
            }

            $payroll->gross_amount = round($groupGrossIncome);
            $payroll->net_amount = round($groupNetIncome);

            $payroll->save();

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            $this->comment('failed'.$th->getMessage());
        }
    }

    public function computeBonus($employeeBonuses = null)
    {
        if ($employeeBonuses == null) {
            return;
        }
        foreach ($employeeBonuses as $employeeBonus) {
            $bonuses[] = [
                'id' => $employeeBonus->id,
                'name' => $employeeBonus->bonus->name,
                'is_taxable' => $employeeBonus->bonus->is_taxable,
                'amount' => $employeeBonus->amount,
            ];
        }

        $bonuses = collect($bonuses);

        $this->totalBonus = $bonuses->sum('amount');

        $this->totalTaxableBonus = $bonuses->where('is_taxable', true)->sum('amount');

        $this->totalNonTaxableBonus = $bonuses->where('is_taxable', false)->sum('amount');

        $this->totalTaxableIncome += $this->totalTaxableBonus;
        $this->totalNonTaxableIncome += $this->totalNonTaxableBonus;
    }

    public function computeCommission($employeeCommissions = null)
    {
        if ($employeeCommissions == null) {
            return;
        }

        foreach ($employeeCommissions as $employeeCommission) {
            $commissions[] = [
                'id' => $employeeCommission->id,
                'name' => $employeeCommission->commission->name,
                'is_taxable' => $employeeCommission->commission->is_taxable,
                'amount' => $employeeCommission->amount,
            ];
        }

        $commissions = collect($commissions);

        $this->totalCommission = $commissions->sum('amount');

        $this->totalTaxableCommission = $commissions->where('is_taxable', true)->sum('amount');

        $this->totalNonTaxableCommission = $commissions->where('is_taxable', false)->sum('amount');

        $this->totalTaxableIncome += $this->totalTaxableCommission;
        $this->totalNonTaxableIncome += $this->totalNonTaxableCommission;
    }

    public function computeTotalAllowance($employeeAllowances = null)
    {
        if ($employeeAllowances == null) {
            return;
        }

        foreach ($employeeAllowances as $employeeAllowance) {
            $allowances[] = [
                'id' => $employeeAllowance->id,
                'name' => $employeeAllowance->allowance->name,
                'is_taxable' => $employeeAllowance->allowance->is_taxable,
                'amount' => $employeeAllowance->amount,
            ];
        }

        $allowances = collect($allowances);

        $this->totalAllowance = $allowances->sum('amount');

        $this->totalTaxableAllowance = $allowances->where('is_taxable', true)->sum('amount');

        $this->totalNonTaxableAllowance = $allowances->where('is_taxable', false)->sum('amount');

        $this->totalTaxableIncome += $this->totalTaxableAllowance;
        $this->totalNonTaxableIncome += $this->totalNonTaxableAllowance;
    }

    public function computeTotalDeduction($employeeDeductions = null)
    {
        $deductions = [];

        if ($employeeDeductions == null) {
            return;
        }

        foreach ($employeeDeductions as $employeeDeduction) {
            $deductions[] = [
                'id' => $employeeDeduction->id,
                'name' => $employeeDeduction->deduction->name,
                'amount' => $employeeDeduction->amount,
            ];
        }

        $deductions = collect($deductions);

        $this->employeeDeductions = $deductions;

        $this->totalDeduction = $deductions->sum('amount');
    }

    public function computeTotalLoans($employeeLoans = null)
    {
        $loans = [];

        if ($employeeLoans == null) {
            return;
        }

        foreach ($employeeLoans as $employeDeduction) {
            dd($employeDeduction);
            $loans[] = [
                'id' => $employeDeduction->id,
                'name' => $employeDeduction->deduction->name,
                'amount' => $employeDeduction->amount,
            ];
        }

        $loans = collect($loans);

        $this->employeeLoans = $loans;

        $this->totalDeduction = $loans->sum('amount');
    }

    public function getSSS()
    {
        // We can get the SSS based on employee
        // we can also get deduction amount in employee column
        $this->sssSelected = $this->getSSS2023($this->basicSalary);

        $this->sssEmployeeShare = $this->sssSelected['regular_ee'];
        $this->sssEmployeerShare = $this->sssSelected['regular_er'];

        if (is_semi_monthly_every_pay($this->payrollGroup)) {
            return $this->sssEmployeeShare / 2;
        }

        return $this->sssEmployeeShare;

        return 900;
    }

    public function getEmployeeSSS()
    {
        // If semi monthly and everypay
        // then we should devide the deductions into 2
        if (is_semi_monthly_every_pay($this->payrollGroup)) {
            return $this->sssSelected['regular_ee'] / 2;
        }

        return $this->sssSelected['regular_ee'];
    }

    public function getEmployerSSS()
    {
        // If semi monthly and everypay
        // then we should devide the deductions into 2
        if (is_semi_monthly_every_pay($this->payrollGroup)) {
            return $this->sssSelected['regular_er'] / 2;
        }

        return $this->sssSelected['regular_er'];
    }

    public function getSSSProvidentFund()
    {
        return 225;
    }

    public function getHDMF()
    {
        // not changed for 2023
        // If semi monthly and everypay
        // then we should devide the deductions into 2
        if (is_semi_monthly_every_pay($this->payrollGroup, 'hdmf_schedule')) {
            return 100 / 2;
        }

        return 100;
    }

    public function getPhilHealth()
    {
        // For 2024, 2025
        $amount = $this->basicSalary * 0.050;

        // For 2022
        $amount = $this->basicSalary * 0.040;

        // For 2023
        $amount = $this->basicSalary * 0.045;

        // For example the amount is 2000 then we devide the contribution to 2
        // 1000 from employee and 1000 from employer
        return $amount / 2;

        return 1000;
    }

    public function getPhilHealthAdjustment()
    {
        return 0;
    }

    public function getEmployeePhilHealth()
    {
        // If semi monthly and everypay
        // then we should devide the deductions into 2
        if (is_semi_monthly_every_pay($this->payrollGroup, 'phil_health_schedule')) {
            return $this->getPhilHealth() / 2;
        }

        return $this->getPhilHealth();
    }

    public function getEmployerPhilHealth()
    {
        // If semi monthly and everypay
        // then we should devide the deductions into 2
        if (is_semi_monthly_every_pay($this->payrollGroup, 'phil_health_schedule')) {
            return $this->getPhilHealth() / 2;
        }

        return $this->getPhilHealth();
    }

    public function taxTable($year = 2023)
    {
        if ($year == 2022) {
            return $this->taxTable2022();
        }

        return $this->taxTable2023Up();
    }

    public function resetAll()
    {
        $this->totalTaxableIncome = 0;
        $this->totalNonTaxableIncome = 0;
    }

    public function getBracket($taxableAmount)
    {
        // https://www.bir.gov.ph/images/bir_files/internal_communications_2/RMCs/2018/WT%20table.pdf
        // Lets cache the tax table instead
        // https://www.bir.gov.ph/index.php/tax-information/withholding-tax.html#wt03
        // https://www.bir.gov.ph/index.php/tax-information/withholding-tax.html#wt10

        $taxTable = $this->taxTable();

        $taxTables = $taxTable[PayrollGroup::FREQUENCY_MONTHLY];

        // We devide salary by 2
        if ($this->frequency == PayrollGroup::FREQUENCY_SEMI_MONTHLY) {
            $taxTables = $taxTable[PayrollGroup::FREQUENCY_SEMI_MONTHLY];
        }

        $firstBracket = $taxTables[0];
        $lastBracket = $taxTables[5];

        $taxBracket = null;

        $this->totalWithHoldingTax = 0;

        foreach ($taxTables as $table) {
            $taxBracket = $table;
            // If no tax
            if ($taxableAmount <= floatval($taxTables[0]['from'])) {
                break;
            }

            // If greater than the last bracket
            if ($taxableAmount >= floatval($table['from']) and $taxableAmount <= floatval($table['to'])) {
                break;
            }
        }

        $this->totalWithHoldingTax += $taxBracket['prescribed'];

        // If amount is over from minimun then x to over
        if ($taxableAmount > $taxBracket['from']) {
            $decimal = floatval($taxBracket['over'].'%') / 100; // like 30% to 0.3
            $taxOver = ($taxableAmount - $taxBracket['from']) * $decimal;
            $this->totalWithHoldingTax += $taxOver;
        }

        return $taxBracket;
    }
}
