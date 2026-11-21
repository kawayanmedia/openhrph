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

	generic := isGenericConfig(cfg)

	// The ₱90,000 exclusion is a Philippine rule; a generic country taxes the
	// benefit with its own configured tax component.
	taxable := amount
	if !generic {
		taxable = incrementalBenefitExcess(amount, employee.YTD.OtherBenefits)
	}

	slip.ThirteenthMonth = amount
	slip.PeriodBasic = amount
	slip.Gross = amount
	slip.NonTaxable = amount.Sub(taxable)
	slip.BenefitExempt = slip.NonTaxable
	slip.TaxableIncome = taxable

	if generic {
		tax, err := genericTax(cfg, taxable)
		if err != nil {
			slip.Warnings = append(slip.Warnings, err.Error())
		} else {
			slip.WithholdingTax = tax
		}
	} else {
		brackets := annualBrackets(cfg)
		if len(brackets) > 0 && taxable > 0 {
			tax, err := statutory.ComputeTax(taxable, brackets)
			if err != nil {
				slip.Warnings = append(slip.Warnings, err.Error())
			} else {
				slip.WithholdingTax = tax
			}
		}
	}

	slip.TotalDeductions = slip.WithholdingTax
	slip.NetPay = slip.Gross.Sub(slip.TotalDeductions)
	appendGenericTaxLine(&slip, cfg)

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

	generic := isGenericConfig(cfg)
	thirteenthTaxable := thirteenth
	if !generic {
		thirteenthTaxable = incrementalBenefitExcess(thirteenth, employee.YTD.OtherBenefits)
	}

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
		var (
			computed money.Centavos
			taxErr   error
		)

		if generic {
			computed, taxErr = genericTax(cfg, taxable)
		} else {
			computed, taxErr = withholding(cfg, taxable, cfg.PeriodsPerMonth())
		}

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
	benefitExempt := money.Zero
	if !generic {
		benefitExempt = thirteenth.Sub(thirteenthTaxable)
	}
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

	appendGenericTaxLine(&slip, cfg)

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

	generic := isGenericConfig(cfg)
	brackets := annualBrackets(cfg)

	if (!generic && len(brackets) == 0) || employee.YTD.TaxableIncome <= 0 {
		slip.Warnings = append(slip.Warnings, "no taxable income recorded for the year")

		return slip, nil
	}

	monthlyEquivalent := employee.YTD.TaxableIncome.Mul(1.0 / 12.0)

	var (
		monthlyTax money.Centavos
		err        error
	)

	if generic {
		monthlyTax, err = genericTax(cfg, monthlyEquivalent)
	} else {
		monthlyTax, err = statutory.ComputeTax(monthlyEquivalent, brackets)
	}

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
	appendGenericTaxLine(&slip, cfg)

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

// appendGenericTaxLine names the country's tax component on a special run.
func appendGenericTaxLine(slip *model.Payslip, cfg model.Config) {
	if !isGenericConfig(cfg) || slip.WithholdingTax == 0 {
		return
	}

	for _, component := range cfg.Components {
		if strings.ToLower(strings.TrimSpace(component.Category)) != "tax" {
			continue
		}

		name := component.Name
		if name == "" {
			name = component.Code
		}

		slip.DeductionLines = append(slip.DeductionLines, model.DeductionLine{
			Code:      component.Code,
			Name:      name,
			Category:  "tax",
			Employee:  slip.WithholdingTax,
			Source:    model.SourceStatutory,
			SortOrder: component.SortOrder,
		})

		return
	}
}

func isGenericConfig(cfg model.Config) bool {
	driver := strings.ToLower(strings.TrimSpace(cfg.StatutoryDriver))

	return driver != "" && driver != "ph"
}

// genericTax applies the country's tax component to a one-off taxable amount.
func genericTax(cfg model.Config, amount money.Centavos) (money.Centavos, error) {
	for _, component := range cfg.Components {
		if strings.ToLower(strings.TrimSpace(component.Category)) != "tax" {
			continue
		}

		if amount < 0 {
			amount = 0
		}

		return statutory.ComputeGeneric(component, amount).Employee, nil
	}

	return 0, fmt.Errorf("no tax component is configured for this country")
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
	oneMonthMinimum := false

	switch reason {
	case "retrenchment", "redundancy", "closure", "business_closure",
		"authorised_cause", "authorized_cause":
		// Labor Code Art. 298: one month pay or one month pay per year of
		// service, whichever is higher.
		monthsPerYear = 1.0
		oneMonthMinimum = true
	case "illness", "disease", "sick":
		// Labor Code Art. 299: one-half month pay per year of service.
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

	if years < 0 {
		return 0, false
	}

	monthly := separationMonthlyPay(cfg, employee, slip)

	amount := monthly.Mul(years * monthsPerYear)
	if oneMonthMinimum && amount < monthly {
		amount = monthly
	}

	return amount, separationIsTaxable(reason)
}

// separationMonthlyPay uses the contracted monthly rate. A daily-paid employee
// has a daily rate in the monthly field, so annualise it first.
func separationMonthlyPay(cfg model.Config, employee model.Employee, slip model.Payslip) money.Centavos {
	if strings.EqualFold(strings.TrimSpace(employee.PaymentType), model.PaymentDaily) && employee.DailyRate > 0 {
		days := cfg.DaysPerYear
		if days <= 0 {
			days = 261
		}

		return employee.DailyRate.Mul(float64(days) / 12)
	}

	return slip.BasicMonthly
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

// yearsOfService counts whole years of service. A fraction of at least six
// months counts as one whole year (Labor Code Arts. 298-299).
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

	if end.Before(start) {
		return 0
	}

	years := end.Year() - start.Year()
	anniversary := start.AddDate(years, 0, 0)

	if anniversary.After(end) {
		years--
		anniversary = start.AddDate(years, 0, 0)
	}

	if !end.Before(anniversary.AddDate(0, 6, 0)) {
		years++
	}

	if years < 0 {
		return 0
	}

	return float64(years)
}
