// Package payroll computes a payroll run from attendance and statutory rules.
package payroll

import (
	"fmt"
	"sort"
	"strconv"
	"strings"
	"time"

	"github.com/kawayanmedia/openhrph-payroll-engine/internal/model"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/money"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/rules"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/statutory"
)

// Compute produces the payslip for one employee.
func Compute(cfg model.Config, employee model.Employee) (model.Payslip, error) {
	cfg.Policy = cfg.Policy.Normalize()

	switch strings.ToLower(strings.TrimSpace(cfg.RunType)) {
	case model.RunThirteenthMonth:
		return ComputeThirteenthMonth(cfg, employee)
	case model.RunFinalPay:
		return ComputeFinalPay(cfg, employee)
	case model.RunAnnualAdjustment:
		return ComputeAnnualAdjustment(cfg, employee)
	default:
		return computeRegular(cfg, employee)
	}
}

func computeRegular(cfg model.Config, employee model.Employee) (model.Payslip, error) {
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

	hoursPerDay := cfg.HoursPerDay
	if hoursPerDay <= 0 {
		hoursPerDay = 8
	}

	monthlyPaid := strings.EqualFold(strings.TrimSpace(employee.PaymentType), model.PaymentMonthly)

	daily := employee.DailyRate
	if daily == 0 {
		daily = rules.DailyRate(employee.MonthlyBasic, daysPerYear)
	}

	// Daily-paid employees are contracted at a daily rate. Deriving their
	// hourly rate from the monthly field would treat the daily rate as a
	// month's pay and understate the wage by the days-per-year divisor.
	hourly := rules.HourlyRate(employee.MonthlyBasic, daysPerYear, hoursPerDay)
	if !monthlyPaid && employee.DailyRate > 0 {
		hourly = employee.DailyRate.Mul(1 / float64(hoursPerDay))
	}

	slip.DailyRate = daily
	periods := cfg.PeriodsPerMonth()
	if periods <= 0 {
		periods = 1
	}

	periodBasic := split(employee.MonthlyBasic, periods)
	slip.PeriodBasic = periodBasic

	// Fixed period basic for monthly-paid staff: the monthly rate divided by
	// the number of pays. Daily-paid staff earn only for attended days below.
	if monthlyPaid {
		slip.RegularPay = periodBasic
		slip.BasicEarned = periodBasic
	}

	if len(employee.Timesheets) == 0 {
		if cfg.PayWithoutTimesheet {
			slip.RegularPay = periodBasic
			slip.BasicEarned = slip.RegularPay
			slip.RegularHours = float64(hoursPerDay * periodDays(cfg, daysPerYear))
			slip.Warnings = append(slip.Warnings, "no attendance recorded; paid on basic salary")
		} else {
			slip.RegularPay = 0
			slip.BasicEarned = 0
			slip.Warnings = append(slip.Warnings, "no attendance recorded; nothing paid")
		}
	}

	for _, line := range employee.Timesheets {
		if line.LegacyHours {
			slip.Warnings = append(slip.Warnings,
				fmt.Sprintf("%s: hours reconstructed from the legacy premium-equivalent columns", line.Date))
		}

		if line.Absent {
			slip.AbsentDays++

			if monthlyPaid {
				slip.RegularPay = slip.RegularPay.Sub(daily)
				slip.BasicEarned = slip.BasicEarned.Sub(daily)
			}

			continue
		}

		leaveFraction := line.LeaveFraction
		if leaveFraction <= 0 || leaveFraction > 1 {
			leaveFraction = 1
		}

		if line.PaidLeave || rules.IsPaidDay(line.DayType) {
			if !monthlyPaid {
				paidLeave := daily.Mul(leaveFraction)
				slip.RegularPay = slip.RegularPay.Add(paidLeave)
				slip.BasicEarned = slip.BasicEarned.Add(paidLeave)
				slip.RegularHours += float64(hoursPerDay) * leaveFraction
			}

			continue
		}

		if rules.IsUnpaidDay(line.DayType) {
			if monthlyPaid {
				unpaid := daily.Mul(leaveFraction)
				slip.RegularPay = slip.RegularPay.Sub(unpaid)
				slip.BasicEarned = slip.BasicEarned.Sub(unpaid)
			}

			continue
		}

		if line.Hours <= 0 && line.Overtime <= 0 && line.Night <= 0 && line.NightOT <= 0 {
			continue
		}

		premium, note := entitledDayRate(employee.Benefits, line.DayType, line.Date, cfg.Policy)
		if note != "" {
			slip.Warnings = append(slip.Warnings, note)
		}

		hours := line.Hours
		undertimePay := money.Zero

		if cfg.ComputeUndertime && line.Undertime > 0 {
			paidHours := hours - line.Undertime
			if paidHours < 0 {
				paidHours = 0
			}

			if monthlyPaid {
				// The fixed period basic still pays the day, so remove the
				// undertime hours from it.
				undertimePay = hourly.Mul(line.Undertime * premium.dayRate)
				slip.RegularPay = slip.RegularPay.Sub(undertimePay)
				slip.BasicEarned = slip.BasicEarned.Sub(undertimePay)
			} else {
				undertimePay = hourly.Mul(line.Undertime * premium.dayRate)
			}

			slip.UndertimeHours += line.Undertime
			slip.UndertimePay = slip.UndertimePay.Add(undertimePay)

			hours = paidHours
		}

		regularPay := hourly.Mul(hours * premium.dayRate)
		if monthlyPaid {
			regularPay = hourly.Mul(hours * premium.premiumOnly)
		}

		// Article 87 requires the premium-day rate plus at least 30% for work
		// beyond eight hours on a rest day or holiday (169% to 507%).
		overtimeRate := premium.dayRate * cfg.Policy.OvertimeMultiplierFor(premium.dayRate)
		if !employee.Benefits.OvertimePay && line.Overtime > 0 {
			slip.Warnings = append(slip.Warnings,
				fmt.Sprintf("%s: overtime premium not applied (not entitled); ordinary rate used", line.Date))
			overtimeRate = premium.dayRate
		}

		overtimePay := hourly.Mul(line.Overtime * overtimeRate)

		nightRate := 0.0
		if employee.Benefits.NightDifferential {
			nightRate = premium.dayRate * cfg.Policy.NightDifferentialRate
		} else if line.Night > 0 {
			slip.Warnings = append(slip.Warnings,
				fmt.Sprintf("%s: night differential not applied (not entitled)", line.Date))
		}

		nightPay := hourly.Mul(line.Night * nightRate)

		nightOTPay := money.Zero
		if line.NightOT > 0 {
			base := premium.dayRate * cfg.Policy.OvertimeMultiplierFor(premium.dayRate)
			if !employee.Benefits.OvertimePay {
				base = premium.dayRate
			}

			differential := 0.0
			if employee.Benefits.NightDifferential {
				differential = cfg.Policy.NightDifferentialRate
			}

			nightOTPay = hourly.Mul(line.NightOT * base * differential)
		}

		if !monthlyPaid {
			slip.BasicEarned = slip.BasicEarned.Add(hourly.Mul(hours))
		}

		slip.RegularPay = slip.RegularPay.Add(regularPay)
		slip.OvertimePay = slip.OvertimePay.Add(overtimePay)
		slip.NightPay = slip.NightPay.Add(nightPay)
		slip.NightOTPay = slip.NightOTPay.Add(nightOTPay)

		slip.RegularHours += hours
		slip.OvertimeHours += line.Overtime
		slip.NightHours += line.Night + line.NightOT

		// Report only the premium component (already included in the totals
		// above) so the payslip does not show base pay twice.
		premiumPay := regularPay
		if !monthlyPaid {
			premiumPay = regularPay.Sub(hourly.Mul(hours))
		}

		switch {
		case rules.IsHoliday(line.DayType):
			slip.HolidayPay = slip.HolidayPay.Add(premiumPay)
		case rules.IsRestDay(line.DayType):
			slip.RestDayPay = slip.RestDayPay.Add(premiumPay)
		}
	}

	if len(employee.Timesheets) > 0 && slip.RegularHours == 0 && slip.OvertimeHours == 0 &&
		slip.NightHours == 0 && slip.AbsentDays == 0 && slip.UndertimeHours == 0 && !hasPaidLeave(employee.Timesheets) {
		slip.Warnings = append(slip.Warnings,
			"timesheets have no recorded hours; verify attendance before relying on this payroll")
	}

	if monthlyPaid && slip.RegularPay < 0 {
		slip.RegularPay = 0
	}

	if slip.BasicEarned < 0 {
		slip.BasicEarned = 0
	}

	// Unworked regular holidays (Article 94). Monthly-paid staff already have
	// the day inside their fixed period basic; daily-paid staff are paid 100%
	// of the daily rate when they worked or were on paid leave the day before.
	if !monthlyPaid && cfg.PayUnworkedRegularHolidays {
		for _, holiday := range uniqueHolidays(cfg.Holidays) {
			if !rules.IsRegularHoliday(holiday.Code) || workedOn(employee.Timesheets, holiday.Date) {
				continue
			}

			if !eligibleForUnworkedHoliday(employee.Timesheets, holiday.Date, cfg.PayWithoutTimesheet) {
				slip.Warnings = append(slip.Warnings,
					fmt.Sprintf("%s: unworked regular holiday unpaid (absent the preceding workday)", holiday.Date))

				continue
			}

			if !employee.Benefits.RegularHolidayPay {
				slip.Warnings = append(slip.Warnings,
					fmt.Sprintf("%s: unworked regular holiday not applied (not entitled)", holiday.Date))

				continue
			}

			slip.HolidayPay = slip.HolidayPay.Add(daily)
			slip.RegularPay = slip.RegularPay.Add(daily)
			slip.RegularHours += float64(hoursPerDay)
		}
	}

	// Attendance lines are paid as they are read, so an absent day has already
	// earned nothing. AbsentAmount stays on the slip as information for the
	// payslip and register; it must not be subtracted from the gross again.
	slip.AbsentAmount = rules.AbsenceDeduction(daily, slip.AbsentDays)

	// Additional earnings: allowances, compensations, bonuses and commissions.
	for _, earning := range employee.Allowances {
		slip.Allowances = slip.Allowances.Add(earning.Amount)
		slip.NonTaxable = slip.NonTaxable.Add(nonTaxablePart(cfg, employee, earning, &slip))
	}

	for _, earning := range employee.Compensations {
		slip.Compensations = append(slip.Compensations, earning)
		slip.Incentives = slip.Incentives.Add(earning.Amount)

		if !earning.Taxable {
			slip.NonTaxable = slip.NonTaxable.Add(earning.Amount)
		}
	}

	for _, earning := range employee.Bonuses {
		slip.Bonus = slip.Bonus.Add(earning.Amount)

		if exempt := benefitExemption(cfg, employee, earning, slip); exempt > 0 {
			slip.NonTaxable = slip.NonTaxable.Add(exempt)
			slip.BenefitExempt = slip.BenefitExempt.Add(exempt)
		}
	}

	for _, earning := range employee.Commissions {
		slip.Commission = slip.Commission.Add(earning.Amount)

		if !earning.Taxable {
			slip.NonTaxable = slip.NonTaxable.Add(earning.Amount)
			slip.BenefitExempt = slip.BenefitExempt.Add(earning.Amount)
		}
	}

	slip.Gross = slip.RegularPay.
		Add(slip.OvertimePay).
		Add(slip.NightPay).
		Add(slip.NightOTPay).
		Add(slip.Allowances).
		Add(slip.Incentives).
		Add(slip.Bonus).
		Add(slip.Commission)

	if slip.Gross < 0 {
		slip.Gross = 0
	}

	// No pay means no contributions; avoid producing a negative net pay.
	if slip.Gross == 0 {
		slip.Warnings = append(slip.Warnings, "no payable days in this period")
		slip.NetPay = 0
		slip.TaxableIncome = 0

		return slip, nil
	}

	// Statutory contributions. A `ph` installation uses the built-in agency
	// calculators; every other country reads its configured components.
	driver := strings.ToLower(strings.TrimSpace(cfg.StatutoryDriver))
	generic := driver != "" && driver != "ph"
	genericEmployeeTotal := money.Zero
	genericEmployerTotal := money.Zero
	genericTaxableReduction := money.Zero

	if !generic {
		// Each agency has its own contribution base so a company can follow the
		// agency circular without code changes.
		sssBase := contributionBase(cfg.SssBase, employee, slip, periods, cfg)
		philHealthBase := contributionBase(cfg.PhilHealthBase, employee, slip, periods, cfg)
		pagIBIGBase := contributionBase(cfg.PagIBIGBase, employee, slip, periods, cfg)
		gsisBase := contributionBase(cfg.GSISBase, employee, slip, periods, cfg)

		// A government agency remits GSIS instead of SSS: the two are mutually
		// exclusive. GSIS reuses the group's statutory cut-off schedule.
		if cfg.SocialInsurance == "gsis" {
			if divisor := statutory.Divisor(cfg.SSSSchedule, cfg.Frequency, cfg.Cutoff); divisor > 0 {
				gsis := statutory.ComputeGSIS(gsisBase, cfg.GSIS)
				slip.GSISEmployee = firstPart(gsis.Employee, divisor)
				slip.GSISEmployer = firstPart(gsis.Employer, divisor)
				// The total is the sum of the parts so the payroll columns always
				// reconcile to the remittance amount.
				slip.GSISTotal = slip.GSISEmployee.Add(slip.GSISEmployer)
			}
		} else if divisor := statutory.Divisor(cfg.SSSSchedule, cfg.Frequency, cfg.Cutoff); divisor > 0 {
			sss, err := statutory.ComputeSSS(sssBase, cfg.SSSBrackets)
			if err != nil {
				slip.Warnings = append(slip.Warnings, err.Error())
			} else {
				slip.SSSEmployee = firstPart(sss.EE, divisor)
				slip.SSSProvident = firstPart(sss.WISPEE, divisor)
				slip.SSSEmployer = firstPart(sss.ER, divisor)
				slip.SSSProvidentER = firstPart(sss.WISPER, divisor)
				slip.SSSEC = firstPart(sss.EC, divisor)
				// The total is the sum of the parts so employee and employer
				// amounts always reconcile to the per-agency columns.
				slip.SSSTotal = slip.SSSEmployee.
					Add(slip.SSSProvident).
					Add(slip.SSSEmployer).
					Add(slip.SSSProvidentER).
					Add(slip.SSSEC)
			}
		}

		if divisor := statutory.Divisor(cfg.PhilHealthSchedule, cfg.Frequency, cfg.Cutoff); divisor > 0 {
			ph := statutory.ComputePhilHealth(philHealthBase, cfg.PhilHealth)
			slip.PhilHealthEmployee = firstPart(ph.Employee, divisor)
			slip.PhilHealthEmployer = firstPart(ph.Employer, divisor)
		}

		if divisor := statutory.Divisor(cfg.PagIBIGSchedule, cfg.Frequency, cfg.Cutoff); divisor > 0 {
			pi := statutory.ComputePagIBIG(pagIBIGBase, cfg.PagIBIG)
			slip.PagIBIGEmployee = firstPart(pi.Employee, divisor)
			slip.PagIBIGEmployer = firstPart(pi.Employer, divisor)
		}
	}

	// Minimum wage earners are exempt on the statutory minimum wage and on the
	// holiday, overtime, night-shift and hazard pay attributable to it (RA 9504
	// as amended by RA 10963; BIR RR 10-2008).
	minWageExemption := money.Zero
	if cfg.Policy.MinimumWageTaxExempt && employee.IsMinimumWage && employee.MinimumWage > 0 {
		// A minimum-wage flag must be supported by the rate actually paid.
		// A stale or incorrect flag must not create a tax exemption.
		rate := employee.DailyRate
		if rate == 0 {
			rate = rules.DailyRate(employee.MonthlyBasic, daysPerYear)
		}

		if rate > employee.MinimumWage {
			slip.Warnings = append(slip.Warnings,
				fmt.Sprintf("minimum wage flag ignored: daily rate %s exceeds the applicable minimum wage %s", rate, employee.MinimumWage))
		} else {
			statutoryWage := minimumWagePay(cfg, employee, slip, daysPerYear, periods)
			premiums := slip.HolidayPay.
				Add(slip.RestDayPay).
				Add(slip.OvertimePay).
				Add(slip.NightPay).
				Add(slip.NightOTPay).
				Add(hazardPay(employee))
			minWageExemption = money.Min(slip.Gross, statutoryWage.Add(premiums))
			slip.MinimumWageExemption = minWageExemption
		}
	}

	if generic {
		computeGenericComponents(&slip, cfg, employee, periods, &genericEmployeeTotal, &genericEmployerTotal, &genericTaxableReduction, false, minWageExemption)
	}

	voluntaryTaxReduction := money.Zero

	for _, deduction := range employee.Deductions {
		amount := deductionAmount(employee, slip, periods, deduction, minWageExemption)
		slip.OtherDeductions = slip.OtherDeductions.Add(amount)

		if deduction.ReducesTaxableIncome {
			voluntaryTaxReduction = voluntaryTaxReduction.Add(amount)
		}

		code := deduction.Code
		if code == "" {
			code = fmt.Sprintf("deduction_%d", len(slip.DeductionItems))
		}

		slip.DeductionItems = append(slip.DeductionItems, model.DeductionLine{
			Code:                 code,
			Name:                 deduction.Name,
			Category:             "deduction",
			Employee:             amount,
			ReducesTaxableIncome: deduction.ReducesTaxableIncome,
			Source:               model.SourceDeduction,
			SortOrder:            500 + len(slip.DeductionItems),
		})
	}

	for _, loan := range employee.Loans {
		slip.Loans = slip.Loans.Add(loan.Amount)
		slip.OtherDeductions = slip.OtherDeductions.Add(loan.Amount)

		code := loan.Code
		if code == "" {
			code = fmt.Sprintf("loan_%d", len(slip.DeductionItems))
		}

		slip.DeductionItems = append(slip.DeductionItems, model.DeductionLine{
			Code:      code,
			Name:      loan.Name,
			Category:  "loan",
			Employee:  loan.Amount,
			Source:    model.SourceLoan,
			SortOrder: 800 + len(slip.DeductionItems),
		})
	}

	// Mandatory employee contributions and non-taxable benefits are exempt.
	slip.TaxableIncome = slip.Gross.
		Sub(slip.NonTaxable).
		Sub(minWageExemption).
		Sub(slip.SSSEmployee).
		Sub(slip.SSSProvident).
		Sub(slip.PhilHealthEmployee).
		Sub(slip.PagIBIGEmployee).
		Sub(slip.GSISEmployee).
		Sub(genericTaxableReduction).
		Sub(voluntaryTaxReduction)

	if slip.TaxableIncome < 0 {
		slip.TaxableIncome = 0
	}

	if generic {
		computeGenericComponents(&slip, cfg, employee, periods, &genericEmployeeTotal, &genericEmployerTotal, &genericTaxableReduction, true, minWageExemption)
	} else if statutory.Divisor(cfg.TaxSchedule, cfg.Frequency, cfg.Cutoff) > 0 {
		tax, err := withholding(cfg, slip.TaxableIncome, periods)
		if err != nil {
			slip.Warnings = append(slip.Warnings, err.Error())
		} else {
			slip.WithholdingTax = tax
		}
	}

	slip.TotalDeductions = slip.SSSEmployee.
		Add(slip.SSSProvident).
		Add(slip.PhilHealthEmployee).
		Add(slip.PagIBIGEmployee).
		Add(slip.GSISEmployee).
		Add(genericEmployeeTotal).
		Add(slip.WithholdingTax).
		Add(slip.OtherDeductions)

	slip.NetPay = slip.Gross.Sub(slip.TotalDeductions)

	// Deductions can never take an employee below zero; flag the shortfall so
	// payroll can review it instead of silently producing a negative payslip.
	if slip.NetPay < 0 {
		slip.Warnings = append(slip.Warnings,
			fmt.Sprintf("deductions exceed earnings by %s", slip.TotalDeductions.Sub(slip.Gross).String()))
		slip.NetPay = 0
	}

	slip.TotalEmployerCost = slip.SSSEmployer.
		Add(slip.SSSProvidentER).
		Add(slip.SSSEC).
		Add(slip.PhilHealthEmployer).
		Add(slip.PagIBIGEmployer).
		Add(slip.GSISEmployer).
		Add(genericEmployerTotal)

	// Compare against the wage order for this employee's region and industry;
	// the company-level figure is only a fallback and can belong to another
	// region.
	applicableMinimumWage := cfg.MinimumWage
	if employee.MinimumWage > 0 {
		applicableMinimumWage = employee.MinimumWage
	}

	if applicableMinimumWage > 0 && daily > 0 && daily < applicableMinimumWage {
		slip.Warnings = append(slip.Warnings,
			fmt.Sprintf("daily rate %s is below the minimum wage %s", daily, applicableMinimumWage))
	}

	return slip, nil
}

// computeGenericComponents evaluates the configured components in sort order.
// taxPass false handles contributions and levies; true handles the tax
// component against the finalised taxable income.
func computeGenericComponents(
	slip *model.Payslip,
	cfg model.Config,
	employee model.Employee,
	periods int,
	employeeTotal *money.Centavos,
	employerTotal *money.Centavos,
	taxableReduction *money.Centavos,
	taxPass bool,
	minWageExemption money.Centavos,
) {
	components := append([]statutory.GenericComponent(nil), cfg.Components...)

	sort.SliceStable(components, func(i, j int) bool {
		if components[i].SortOrder == components[j].SortOrder {
			return components[i].Code < components[j].Code
		}

		return components[i].SortOrder < components[j].SortOrder
	})

	for _, component := range components {
		category := strings.ToLower(strings.TrimSpace(component.Category))
		isTax := category == "tax"

		if taxPass != isTax {
			continue
		}

		divisor := statutory.Divisor(component.Schedule, cfg.Frequency, cfg.Cutoff)
		if divisor <= 0 {
			continue
		}

		base := genericBase(component.Base, employee, *slip, periods, minWageExemption, taxPass)
		result := statutory.ComputeGeneric(component, base)

		if strings.HasPrefix(strings.ToLower(strings.TrimSpace(component.Base)), "monthly_") {
			result = statutory.PerPeriod(result, divisor)
		}

		if isTax {
			slip.WithholdingTax = slip.WithholdingTax.Add(result.Employee)
		} else {
			*employeeTotal = employeeTotal.Add(result.Employee)
			*employerTotal = employerTotal.Add(result.Employer)

			if result.Taxable {
				*taxableReduction = taxableReduction.Add(result.Employee)
			}
		}

		name := component.Name
		if name == "" {
			name = component.Code
		}

		slip.DeductionLines = append(slip.DeductionLines, model.DeductionLine{
			Code:                 component.Code,
			Name:                 name,
			Category:             category,
			Base:                 result.Base,
			Employee:             result.Employee,
			Employer:             result.Employer,
			ReducesTaxableIncome: result.Taxable,
			Source:               model.SourceStatutory,
			SortOrder:            component.SortOrder,
		})
	}
}

// deductionAmount resolves a fixed or percentage-based employee deduction.
func deductionAmount(employee model.Employee, slip model.Payslip, periods int, deduction model.Earning, minWageExemption money.Centavos) money.Centavos {
	if strings.ToLower(strings.TrimSpace(deduction.Calculation)) != "percentage" || deduction.Rate <= 0 {
		if deduction.Amount < 0 {
			return 0
		}

		return deduction.Amount
	}

	base := deduction.Basis
	if base == "" {
		base = "period_basic"
	}

	baseAmount := genericBase(base, employee, slip, periods, minWageExemption, false)

	if strings.HasPrefix(strings.ToLower(strings.TrimSpace(base)), "monthly_") {
		if periods > 1 {
			baseAmount /= money.Centavos(periods)
		}
	}

	amount := baseAmount.Mul(deduction.Rate)

	return money.Max(amount, 0)
}

// genericBase returns the amount a component or percentage deduction uses.
func genericBase(base string, employee model.Employee, slip model.Payslip, periods int, minWageExemption money.Centavos, taxableResolved bool) money.Centavos {
	switch strings.ToLower(strings.TrimSpace(base)) {
	case "monthly_gross":
		if periods < 1 {
			periods = 1
		}

		return slip.Gross.Mul(float64(periods))
	case "period_gross", "gross":
		return slip.Gross
	case "period_basic":
		return slip.PeriodBasic
	case "taxable":
		if taxableResolved {
			return slip.TaxableIncome
		}

		value := slip.Gross.Sub(slip.NonTaxable).Sub(minWageExemption)
		if value < 0 {
			return 0
		}

		return value
	default:
		return employee.MonthlyBasic
	}
}

// StatutoryEmployeeTotal sums every employee-side statutory deduction,
// including generic country lines.
func StatutoryEmployeeTotal(slip model.Payslip) money.Centavos {
	total := slip.SSSEmployee.
		Add(slip.SSSProvident).
		Add(slip.PhilHealthEmployee).
		Add(slip.PagIBIGEmployee).
		Add(slip.GSISEmployee)

	for _, line := range slip.DeductionLines {
		if line.Source == model.SourceStatutory && line.Category != "tax" {
			total = total.Add(line.Employee)
		}
	}

	return total
}

// ComputeAll computes every employee of a run.
func ComputeAll(cfg model.Config, employees []model.Employee) ([]model.Payslip, error) {
	slips := make([]model.Payslip, 0, len(employees))

	for _, employee := range employees {
		slip, err := Compute(cfg, employee)
		if err != nil {
			return nil, fmt.Errorf("employee %d: %w", employee.ID, err)
		}

		slips = append(slips, slip)
	}

	return slips, nil
}

type dayPremium struct {
	dayRate     float64
	premiumOnly float64
}

// entitledDayRate returns the multiplier for the day, honouring the
// employee's benefit switches. A disabled switch falls back to the ordinary
// day rate and the reason is reported on the payslip.
func entitledDayRate(flags model.BenefitFlags, dayType string, date string, policy rules.Policy) (dayPremium, string) {
	multiplier := policy.DayMultiplier(dayType)
	entitled := true

	switch {
	case rules.IsRestDay(dayType):
		entitled = flags.RestDayPay
	case rules.IsRegularHoliday(dayType) && rules.IsSpecialHoliday(dayType):
		entitled = flags.RegularHolidaySpecialHolidayPay
	case rules.IsRegularHoliday(dayType):
		if multiplier >= 3.0 {
			entitled = flags.PremiumHolidayPay
		} else {
			entitled = flags.RegularHolidayPay
		}
	case rules.IsSpecialHoliday(dayType):
		entitled = flags.SpecialHolidayPay
	}

	if !entitled {
		return dayPremium{dayRate: 1, premiumOnly: 0},
			fmt.Sprintf("%s: premium for %s not applied (not entitled)", date, dayType)
	}

	return dayPremium{dayRate: multiplier, premiumOnly: multiplier - 1}, ""
}

func hasPaidLeave(lines []model.TimesheetLine) bool {
	for _, line := range lines {
		if line.PaidLeave || rules.IsPaidDay(line.DayType) {
			return true
		}
	}

	return false
}

func workedOn(lines []model.TimesheetLine, date string) bool {
	for _, line := range lines {
		if line.Date != date || line.Absent || rules.IsUnpaidDay(line.DayType) {
			continue
		}

		if line.Hours > 0 || line.Overtime > 0 || line.PaidLeave || rules.IsPaidDay(line.DayType) {
			return true
		}
	}

	return false
}

// eligibleForUnworkedHoliday checks the Article 94 condition: present or on
// paid leave on the work day immediately preceding the holiday.
func eligibleForUnworkedHoliday(lines []model.TimesheetLine, date string, payWithoutTimesheet bool) bool {
	if len(lines) == 0 {
		return payWithoutTimesheet
	}

	preceding := previousDate(date)
	if preceding == "" {
		return true
	}

	found := false
	for _, line := range lines {
		if line.Date != preceding {
			continue
		}

		found = true

		if line.Absent {
			return false
		}

		if line.PaidLeave || rules.IsPaidDay(line.DayType) || line.Hours > 0 || line.Overtime > 0 {
			return true
		}

		if rules.IsUnpaidDay(line.DayType) {
			return false
		}
	}

	// No record for the preceding day: assume present when attendance is
	// recorded for the period.
	return !found
}

func previousDate(date string) string {
	parsed, err := time.Parse("2006-01-02", date)
	if err != nil {
		return ""
	}

	return parsed.AddDate(0, 0, -1).Format("2006-01-02")
}

// nonTaxablePart returns the portion of an allowance that is not taxable.
// De minimis benefits are capped at their statutory ceiling for the period;
// anything above the remaining ceiling is taxable.
func nonTaxablePart(cfg model.Config, employee model.Employee, earning model.Earning, slip *model.Payslip) money.Centavos {
	if earning.Taxable {
		return 0
	}

	if earning.Kind == model.KindAllowance && employee.Benefits.DeMinimis {
		if benefit, ok := deMinimisBenefit(cfg, earning); ok {
			return deMinimisExemption(cfg, employee, earning, benefit, slip)
		}
	}

	return earning.Amount
}

func deMinimisBenefit(cfg model.Config, earning model.Earning) (model.DeMinimisBenefit, bool) {
	for _, benefit := range cfg.DeMinimis {
		if strings.EqualFold(benefit.Code, earning.Code) ||
			strings.EqualFold(benefit.Code, earning.Name) ||
			strings.EqualFold(benefit.Code, slug(earning.Name)) {
			return benefit, true
		}
	}

	return model.DeMinimisBenefit{}, false
}

// deMinimisExemption reports the exempt portion of one de minimis allowance.
// The ceiling applies to the cumulative amount for its own period (month,
// semester or year), so the exemption left is the ceiling less what prior
// payrolls in that period already exempted.
func deMinimisExemption(cfg model.Config, employee model.Employee, earning model.Earning, benefit model.DeMinimisBenefit, slip *model.Payslip) money.Centavos {
	if benefit.Basis == model.DeMinimisHourlyOT {
		return money.Min(earning.Amount, deMinimisHourlyOTCeiling(cfg, employee, benefit))
	}

	key := deMinimisUsageKey(cfg, employee, benefit)
	remaining := deMinimisRemaining(employee, slip, benefit, key)
	exempt := money.Min(earning.Amount, remaining)

	if exempt > 0 {
		if slip.DeMinimisExempt == nil {
			slip.DeMinimisExempt = map[string]money.Centavos{}
		}

		slip.DeMinimisExempt[key] = slip.DeMinimisExempt[key] + exempt
	}

	return exempt
}

// deMinimisRemaining is the exemption still available for a benefit in the
// payroll's period.
func deMinimisRemaining(employee model.Employee, slip *model.Payslip, benefit model.DeMinimisBenefit, key string) money.Centavos {
	used := employee.YTD.DeMinimis[key] + slip.DeMinimisExempt[key]

	return money.Max(0, benefit.Ceiling-used)
}

// deMinimisUsageKey is the ledger key for a benefit: its statutory code plus
// the calendar month, semester or year the ceiling applies to.
func deMinimisUsageKey(cfg model.Config, employee model.Employee, benefit model.DeMinimisBenefit) string {
	code := strings.ToLower(strings.TrimSpace(benefit.Code))
	if code == "" {
		code = "de_minimis"
	}

	if cfg.PayrollDate.IsZero() {
		year := employee.YTD.Year
		if year < 0 {
			year = 0
		}

		return code + "|" + strconv.Itoa(year)
	}

	period := cfg.PayrollDate.Format("2006-01")

	switch benefit.Basis {
	case model.DeMinimisAnnual:
		period = cfg.PayrollDate.Format("2006")
	case model.DeMinimisSemester:
		semester := 1
		if cfg.PayrollDate.Month() > 6 {
			semester = 2
		}

		period = fmt.Sprintf("%s-S%d", cfg.PayrollDate.Format("2006"), semester)
	}

	return code + "|" + period
}

// deMinimisHourlyOTCeiling is the meal-allowance ceiling for overtime and
// night-shift work: a percentage of the hourly minimum wage for each overtime
// hour in the period (RR 29-2025 sets 30%).
func deMinimisHourlyOTCeiling(cfg model.Config, employee model.Employee, benefit model.DeMinimisBenefit) money.Centavos {
	daysPerYear := cfg.DaysPerYear
	if daysPerYear <= 0 {
		daysPerYear = 261
	}

	hoursPerDay := cfg.HoursPerDay
	if hoursPerDay <= 0 {
		hoursPerDay = 8
	}

	hourly := rules.HourlyRate(employee.MonthlyBasic, daysPerYear, hoursPerDay)
	if employee.MinimumWage > 0 {
		hourly = employee.MinimumWage.Mul(1 / float64(hoursPerDay))
	}

	percentage := benefit.Percentage
	if percentage <= 0 {
		percentage = model.DeMinimisHourlyOTPercentage
	}

	return hourly.Mul(overtimeHours(employee.Timesheets) * float64(percentage) / 100)
}

func overtimeHours(lines []model.TimesheetLine) float64 {
	total := 0.0

	for _, line := range lines {
		total += line.Overtime
	}

	return total
}

func slug(value string) string {
	value = strings.ToLower(strings.TrimSpace(value))
	value = strings.ReplaceAll(value, "-", "_")
	value = strings.ReplaceAll(value, " ", "_")

	return value
}

// contributionBase selects what a statutory contribution is assessed on.
// benefitExemption reports the exempt part of one benefit payment. The
// PHP 90,000 exclusion for 13th-month pay and other benefits is a single
// annual amount shared by every benefit payment, so a payment is exempt only
// up to what the employee has left after earlier benefits. A country without
// the exclusion falls back to the line's own ceiling.
func benefitExemption(cfg model.Config, employee model.Employee, earning model.Earning, slip model.Payslip) money.Centavos {
	if earning.Taxable {
		return 0
	}

	lineCeiling := earning.Amount
	if earning.NonTaxableCeiling > 0 {
		lineCeiling = earning.NonTaxableCeiling
	}

	annualCeiling := cfg.Policy.ThirteenthMonthExempt
	if annualCeiling <= 0 {
		return money.Min(earning.Amount, lineCeiling)
	}

	remaining := money.Max(0, annualCeiling.Sub(employee.YTD.OtherBenefits).Sub(slip.BenefitExempt))

	return money.Min(earning.Amount, money.Min(lineCeiling, remaining))
}

func contributionBase(base string, employee model.Employee, slip model.Payslip, periods int, cfg model.Config) money.Centavos {
	switch strings.ToLower(strings.TrimSpace(base)) {
	case "gross":
		return slip.Gross.Mul(float64(periods))
	case "period_gross":
		return slip.Gross
	default:
		return monthlyContributionBase(cfg, employee)
	}
}

// uniqueHolidays drops duplicate date/code pairs. A duplicated reference row
// must never pay a second holiday, whatever the caller passed in: one day in
// the period earns one unworked-holiday pay.
func uniqueHolidays(holidays []model.Holiday) []model.Holiday {
	unique := make([]model.Holiday, 0, len(holidays))
	seen := make(map[string]bool, len(holidays))

	for _, holiday := range holidays {
		key := holiday.Date + "|" + holiday.Code

		if holiday.Date == "" || holiday.Code == "" || seen[key] {
			continue
		}

		seen[key] = true
		unique = append(unique, holiday)
	}

	return unique
}

// hazardPay reports the hazardous-work pay paid in the period. RA 9504 also
// exempts it for a minimum wage earner. The catalogue has no dedicated flag
// for hazardous-work pay, so the line is recognised by its code or name, the
// same way de minimis benefits are.
func hazardPay(employee model.Employee) money.Centavos {
	total := money.Zero

	for _, earning := range append(append([]model.Earning(nil), employee.Compensations...), employee.Allowances...) {
		label := strings.ToUpper(strings.TrimSpace(earning.Code)) + " " + strings.ToUpper(strings.TrimSpace(earning.Name))

		if earning.Amount > 0 && strings.Contains(label, "HAZARD") {
			total = total.Add(earning.Amount)
		}
	}

	return total
}

// monthlyContributionBase reports the monthly salary a contribution is
// assessed on. A daily-paid employee's MonthlyBasic holds the contracted daily
// rate, not a month's pay, so assessing SSS, PhilHealth and Pag-IBIG on it
// understates every contribution by the days-per-year divisor. The monthly
// equivalent of the daily rate is used instead; a stored figure above the daily
// rate is already a monthly salary and is kept.
func monthlyContributionBase(cfg model.Config, employee model.Employee) money.Centavos {
	if !strings.EqualFold(strings.TrimSpace(employee.PaymentType), model.PaymentDaily) {
		return employee.MonthlyBasic
	}

	if employee.MonthlyBasic > 0 && employee.MonthlyBasic > employee.DailyRate {
		return employee.MonthlyBasic
	}

	if employee.DailyRate <= 0 {
		return employee.MonthlyBasic
	}

	days := cfg.DaysPerYear
	if days <= 0 {
		days = 261
	}

	return employee.DailyRate.Mul(float64(days) / 12)
}

func minimumWagePay(cfg model.Config, employee model.Employee, slip model.Payslip, daysPerYear, periods int) money.Centavos {
	daily := employee.MinimumWage
	if daily == 0 {
		daily = cfg.MinimumWage
	}

	if daily == 0 {
		return 0
	}

	if strings.EqualFold(employee.PaymentType, model.PaymentMonthly) {
		monthlyMinimum := daily.Mul(float64(daysPerYear) / 12)

		return split(monthlyMinimum, periods)
	}

	// Daily-paid staff earn the minimum wage for each paid day in the period.
	// Cap the exemption at the regular pay actually earned (which includes
	// paid unworked holidays) so an overpayment above the statutory wage is
	// not exempt.
	statutory := daily.Mul(paidDays(cfg, employee))
	if slip.RegularPay < statutory {
		return slip.RegularPay
	}

	return statutory
}

// paidDays counts the days in the period that earn the daily minimum wage:
// attended days, paid leave, and paid unworked regular holidays.
func paidDays(cfg model.Config, employee model.Employee) float64 {
	days := 0.0

	for _, line := range employee.Timesheets {
		if line.Absent {
			continue
		}

		if line.PaidLeave || rules.IsPaidDay(line.DayType) {
			days++

			continue
		}

		if rules.IsUnpaidDay(line.DayType) {
			continue
		}

		if line.Hours > 0 {
			days++
		}
	}

	if cfg.PayUnworkedRegularHolidays {
		for _, holiday := range uniqueHolidays(cfg.Holidays) {
			if !rules.IsRegularHoliday(holiday.Code) || workedOn(employee.Timesheets, holiday.Date) {
				continue
			}

			if !eligibleForUnworkedHoliday(employee.Timesheets, holiday.Date, cfg.PayWithoutTimesheet) {
				continue
			}

			if !employee.Benefits.RegularHolidayPay {
				continue
			}

			days++
		}
	}

	return days
}

// withholding computes the tax for the period. When every contribution is
// taken every pay, the published tables for the frequency are the operative
// reference. With a non-uniform schedule (first/last pay only) the tax is
// computed on the monthly equivalent and allocated, so the two cut-offs
// reconcile to the monthly table.
func withholding(cfg model.Config, taxable money.Centavos, periods int) (money.Centavos, error) {
	if uniformSchedules(cfg) {
		return statutory.ComputeTax(taxable, cfg.TaxBrackets)
	}

	brackets := annualBrackets(cfg)
	if len(brackets) == 0 {
		return statutory.ComputeTax(taxable, cfg.TaxBrackets)
	}

	// A contribution taken only on one cut-off leaves this period's taxable
	// income unrepresentative of a month, so annualise the period and allocate
	// the annual tax back over the year's pays.
	partsPerYear := cfg.PeriodsPerYear()
	if partsPerYear <= 0 {
		partsPerYear = 1
	}

	annualTax, err := statutory.ComputeTax(taxable.Mul(float64(partsPerYear)), brackets)
	if err != nil {
		return 0, err
	}

	return firstPart(annualTax, partsPerYear), nil
}

func uniformSchedules(cfg model.Config) bool {
	for _, schedule := range []statutory.Schedule{
		cfg.SSSSchedule, cfg.PhilHealthSchedule, cfg.PagIBIGSchedule, cfg.TaxSchedule,
	} {
		if schedule != statutory.ScheduleEveryPay {
			return false
		}
	}

	return true
}

func split(amount money.Centavos, divisor int) money.Centavos {
	if divisor <= 1 {
		return amount
	}

	return money.Centavos(roundHalfUp(float64(amount) / float64(divisor)))
}

func firstPart(amount money.Centavos, parts int) money.Centavos {
	if parts <= 1 {
		return amount
	}

	return amount.Split(parts)[0]
}

func periodBasic(cfg model.Config, monthly money.Centavos) money.Centavos {
	if cfg.Frequency == statutory.FrequencySemiMonthly {
		return monthly.Half()
	}

	return monthly
}

func periodDays(cfg model.Config, daysPerYear int) int {
	days := daysPerYear / 12

	if cfg.Frequency == statutory.FrequencySemiMonthly {
		return days / 2
	}

	return days
}

func roundHalfUp(v float64) int64 {
	if v < 0 {
		return -int64(-v + 0.5)
	}

	return int64(v + 0.5)
}
