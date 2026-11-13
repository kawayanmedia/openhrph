package payroll

import (
	"fmt"
	"strconv"
	"strings"
	"time"

	"github.com/kawayanmedia/openhrph-payroll-engine/internal/model"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/money"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/rules"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/statutory"
)

// ComputeThirteenthMonth computes the 13th-month pay run for one employee.
//
// The amount is 1/12 of the basic salary actually earned in the calendar year
// (PD 851 as amended by MO 28). The ₱90,000 exclusion is aggregated with the
// employee's other benefits for the year and only the excess is taxable.
func ComputeThirteenthMonth(cfg model.Config, employee model.Employee) (model.Payslip, error) {
	slip := model.Payslip{
		EmployeeID:   employee.ID,
		EmployeeNo:   employee.Number,
		EmployeeName: employee.Name,
		BasicMonthly: employee.MonthlyBasic,
	}

	daysPerYear := cfg.DaysPerYear
	if daysPerYear <= 0 {
		daysPerYear = 261
	}

	slip.DailyRate = employee.DailyRate
	if slip.DailyRate == 0 {
		slip.DailyRate = rules.DailyRate(employee.MonthlyBasic, daysPerYear)
	}

	amount := rules.ThirteenthMonth(employee.YTD.BasicEarned)
	if amount <= 0 {
		slip.Warnings = append(slip.Warnings, "no basic salary earned in the year; 13th month is zero")

		return slip, nil
	}

	taxable := incrementalBenefitExcess(amount, employee.YTD.OtherBenefits)

	slip.ThirteenthMonth = amount
	slip.PeriodBasic = amount
	slip.Gross = amount
	slip.NonTaxable = amount.Sub(taxable)
	slip.BenefitExempt = slip.NonTaxable
	slip.TaxableIncome = taxable

	brackets := annualBrackets(cfg)
	if len(brackets) > 0 && taxable > 0 {
		tax, err := statutory.ComputeTax(taxable, brackets)
		if err != nil {
			slip.Warnings = append(slip.Warnings, err.Error())
		} else {
			slip.WithholdingTax = tax
		}
	}

	slip.TotalDeductions = slip.WithholdingTax
	slip.NetPay = slip.Gross.Sub(slip.TotalDeductions)

	return slip, nil
}

// ComputeFinalPay computes a separation settlement: the final period's salary
// and statutory contributions, pro-rated 13th month, leave conversion and
// separation pay by cause.
func ComputeFinalPay(cfg model.Config, employee model.Employee) (model.Payslip, error) {
	regularCfg := cfg
	regularCfg.RunType = model.RunRegular

	slip, err := computeRegular(regularCfg, employee)
	if err != nil {
		return slip, err
	}

	daysPerYear := cfg.DaysPerYear
	if daysPerYear <= 0 {
		daysPerYear = 261
	}

	daily := slip.DailyRate

	// Pro-rated 13th month: 1/12 of the year's basic salary, less any
	// 13th-month pay already released.
	basicEarned := employee.YTD.BasicEarned.Add(slip.BasicEarned)
	thirteenth := rules.ThirteenthMonth(basicEarned).Sub(employee.YTD.ThirteenthMonthPaid)
	if thirteenth < 0 {
		thirteenth = 0
	}

	thirteenthTaxable := incrementalBenefitExcess(thirteenth, employee.YTD.OtherBenefits)

	// Leave conversion: unused leave days at the employee's daily rate.
	leaveDays := employee.LeaveBalanceDays
	if value, ok := cfg.Meta["leave_days"]; ok {
		if parsed, parseErr := strconv.ParseFloat(strings.TrimSpace(value), 64); parseErr == nil {
			leaveDays = parsed
		}
	}

	if strings.EqualFold(strings.TrimSpace(cfg.Meta["leave_conversion"]), "false") {
		leaveDays = 0
	}

	leave := money.Zero
	if leaveDays > 0 {
		leave = daily.Mul(leaveDays)
	}

	separation, separationTaxable := separationPay(cfg, employee, slip)

	gross := slip.Gross.Add(thirteenth).Add(leave).Add(separation)
	taxable := slip.TaxableIncome.Add(thirteenthTaxable).Add(leave)
	if separationTaxable {
		taxable = taxable.Add(separation)
	}

	tax := money.Zero
	if taxable > 0 {
		computed, taxErr := withholding(cfg, taxable, cfg.PeriodsPerMonth())
		if taxErr != nil {
			slip.Warnings = append(slip.Warnings, taxErr.Error())
		} else {
			tax = computed
		}
	}

	slip.ThirteenthMonth = thirteenth
	slip.LeaveConversion = leave
	slip.SeparationPay = separation
	slip.Gross = gross
	slip.TaxableIncome = taxable
	benefitExempt := thirteenth.Sub(thirteenthTaxable)
	slip.NonTaxable = slip.NonTaxable.Add(benefitExempt)
	slip.BenefitExempt = slip.BenefitExempt.Add(benefitExempt)
	if !separationTaxable {
		slip.NonTaxable = slip.NonTaxable.Add(separation)
	}

	slip.TotalDeductions = slip.TotalDeductions.Sub(slip.WithholdingTax).Add(tax)
	slip.WithholdingTax = tax
	slip.NetPay = slip.Gross.Sub(slip.TotalDeductions)

	if slip.NetPay < 0 {
		slip.Warnings = append(slip.Warnings,
			fmt.Sprintf("deductions exceed earnings by %s", slip.TotalDeductions.Sub(slip.Gross).String()))
		slip.NetPay = 0
	}

	return slip, nil
}

// ComputeAnnualAdjustment compares the tax withheld during the year with the
// tax on the annualised taxable income and returns the difference as a
// positive (additional withholding) or negative (refund) adjustment.
func ComputeAnnualAdjustment(cfg model.Config, employee model.Employee) (model.Payslip, error) {
	slip := model.Payslip{
		EmployeeID:   employee.ID,
		EmployeeNo:   employee.Number,
		EmployeeName: employee.Name,
		BasicMonthly: employee.MonthlyBasic,
	}

	brackets := annualBrackets(cfg)
	if len(brackets) == 0 || employee.YTD.TaxableIncome <= 0 {
		slip.Warnings = append(slip.Warnings, "no taxable income recorded for the year")

		return slip, nil
	}

	monthlyEquivalent := employee.YTD.TaxableIncome.Mul(1.0 / 12.0)
	monthlyTax, err := statutory.ComputeTax(monthlyEquivalent, brackets)
	if err != nil {
		return slip, err
	}

	annualTax := monthlyTax.Mul(12)
	adjustment := annualTax.Sub(employee.YTD.TaxWithheld)
	slip.AnnualAdjustment = adjustment
	slip.WithholdingTax = adjustment
	slip.TotalDeductions = adjustment
	slip.NetPay = adjustment.Mul(-1)
	slip.NonTaxable = 0

	return slip, nil
}

// incrementalBenefitExcess returns the portion of a benefit that exceeds the
// ₱90,000 annual exclusion after other benefits already received this year.
func incrementalBenefitExcess(amount, otherBenefits money.Centavos) money.Centavos {
	if amount <= 0 {
		return 0
	}

	priorExcess := money.Max(0, otherBenefits.Sub(rules.ThirteenthMonthExemption))
	totalExcess := money.Max(0, amount.Add(otherBenefits).Sub(rules.ThirteenthMonthExemption))

	return money.Max(0, totalExcess.Sub(priorExcess))
}

func annualBrackets(cfg model.Config) []statutory.TaxBracket {
	if len(cfg.AnnualTaxBrackets) > 0 {
		return cfg.AnnualTaxBrackets
	}

	return statutory.TRAINTaxBrackets(statutory.FrequencyMonthly)
}

// separationPay returns the separation pay for the run and whether it is
// taxable. Separation pay is exempt when the separation is due to sickness,
// death, physical disability or a cause beyond the employee's control
// (retrenchment, redundancy, closure).
func separationPay(cfg model.Config, employee model.Employee, slip model.Payslip) (money.Centavos, bool) {
	if value, ok := cfg.Meta["separation_pay"]; ok && strings.TrimSpace(value) != "" {
		amount := money.FromPesosString(value)

		return amount, separationIsTaxable(cfg.Meta["separation_reason"])
	}

	reason := strings.ToLower(strings.TrimSpace(cfg.Meta["separation_reason"]))
	monthsPerYear := 0.0

	switch reason {
	case "retrenchment", "redundancy", "closure", "business_closure":
		monthsPerYear = 1.0
	case "authorised_cause", "authorized_cause", "redundancy_half":
		monthsPerYear = 0.5
	default:
		return 0, false
	}

	years := yearsOfService(employee)
	if value, ok := cfg.Meta["years_of_service"]; ok {
		if parsed, err := strconv.ParseFloat(strings.TrimSpace(value), 64); err == nil {
			years = parsed
		}
	}

	if years <= 0 {
		return 0, false
	}

	amount := slip.BasicMonthly.Mul(years * monthsPerYear)

	return amount, separationIsTaxable(reason)
}

func separationIsTaxable(reason string) bool {
	switch strings.ToLower(strings.TrimSpace(reason)) {
	case "retrenchment", "redundancy", "closure", "business_closure",
		"illness", "death", "disability", "authorised_cause", "authorized_cause":
		return false
	default:
		return true
	}
}

func yearsOfService(employee model.Employee) float64 {
	start, err := time.Parse("2006-01-02", employee.HiredOn)
	if err != nil {
		return 0
	}

	end := time.Now()
	if employee.SeparatedOn != "" {
		if parsed, parseErr := time.Parse("2006-01-02", employee.SeparatedOn); parseErr == nil {
			end = parsed
		}
	}

	years := end.Sub(start).Hours() / 24 / 365.25
	if years < 0 {
		return 0
	}

	return years
}
