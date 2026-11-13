// Package payroll computes a payroll run from attendance and statutory rules.
package payroll

import (
	"fmt"
	"strings"
	"time"

	"github.com/kawayanmedia/openhrph-payroll-engine/internal/model"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/money"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/rules"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/statutory"
)

// Compute produces the payslip for one employee.
func Compute(cfg model.Config, employee model.Employee) (model.Payslip, error) {
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

	daily := employee.DailyRate
	if daily == 0 {
		daily = rules.DailyRate(employee.MonthlyBasic, daysPerYear)
	}

	hourly := rules.HourlyRate(employee.MonthlyBasic, daysPerYear, hoursPerDay)
	slip.DailyRate = daily

	monthlyPaid := strings.EqualFold(strings.TrimSpace(employee.PaymentType), model.PaymentMonthly)
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

		if line.PaidLeave || rules.IsPaidDay(line.DayType) {
			if !monthlyPaid {
				slip.RegularPay = slip.RegularPay.Add(daily)
				slip.BasicEarned = slip.BasicEarned.Add(daily)
				slip.RegularHours += float64(hoursPerDay)
			}

			continue
		}

		if rules.IsUnpaidDay(line.DayType) {
			if monthlyPaid {
				slip.RegularPay = slip.RegularPay.Sub(daily)
				slip.BasicEarned = slip.BasicEarned.Sub(daily)
			}

			continue
		}

		if line.Hours <= 0 && line.Overtime <= 0 && line.Night <= 0 && line.NightOT <= 0 {
			continue
		}

		premium, note := entitledDayRate(employee.Benefits, line.DayType, line.Date)
		if note != "" {
			slip.Warnings = append(slip.Warnings, note)
		}

		regularPay := hourly.Mul(line.Hours * premium.dayRate)
		if monthlyPaid {
			regularPay = hourly.Mul(line.Hours * premium.premiumOnly)
		}

		overtimeRate := premium.dayRate * rules.OvertimeMultiplier
		if !employee.Benefits.OvertimePay && line.Overtime > 0 {
			slip.Warnings = append(slip.Warnings,
				fmt.Sprintf("%s: overtime premium not applied (not entitled); ordinary rate used", line.Date))
			overtimeRate = premium.dayRate
		}

		overtimePay := hourly.Mul(line.Overtime * overtimeRate)

		nightRate := 0.0
		if employee.Benefits.NightDifferential {
			nightRate = premium.dayRate * rules.NightDifferentialRate
		} else if line.Night > 0 {
			slip.Warnings = append(slip.Warnings,
				fmt.Sprintf("%s: night differential not applied (not entitled)", line.Date))
		}

		nightPay := hourly.Mul(line.Night * nightRate)

		nightOTPay := money.Zero
		if line.NightOT > 0 {
			base := premium.dayRate * rules.OvertimeMultiplier
			if !employee.Benefits.OvertimePay {
				base = premium.dayRate
			}

			differential := 0.0
			if employee.Benefits.NightDifferential {
				differential = rules.NightDifferentialRate
			}

			nightOTPay = hourly.Mul(line.NightOT * base * differential)
		}

		if !monthlyPaid {
			slip.BasicEarned = slip.BasicEarned.Add(hourly.Mul(line.Hours))
		}

		slip.RegularPay = slip.RegularPay.Add(regularPay)
		slip.OvertimePay = slip.OvertimePay.Add(overtimePay)
		slip.NightPay = slip.NightPay.Add(nightPay)
		slip.NightOTPay = slip.NightOTPay.Add(nightOTPay)

		slip.RegularHours += line.Hours
		slip.OvertimeHours += line.Overtime
		slip.NightHours += line.Night + line.NightOT

		// Report only the premium component (already included in the totals
		// above) so the payslip does not show base pay twice.
		premiumPay := regularPay
		if !monthlyPaid {
			premiumPay = regularPay.Sub(hourly.Mul(line.Hours))
		}

		switch {
		case rules.IsHoliday(line.DayType):
			slip.HolidayPay = slip.HolidayPay.Add(premiumPay)
		case rules.IsRestDay(line.DayType):
			slip.RestDayPay = slip.RestDayPay.Add(premiumPay)
		}
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
		for _, holiday := range cfg.Holidays {
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
		slip.NonTaxable = slip.NonTaxable.Add(nonTaxablePart(cfg, employee, earning, periods))
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

		if ceiling := earning.NonTaxableCeiling; ceiling > 0 {
			exempt := money.Min(earning.Amount, ceiling)
			slip.NonTaxable = slip.NonTaxable.Add(exempt)
			slip.BenefitExempt = slip.BenefitExempt.Add(exempt)
		} else if !earning.Taxable {
			slip.NonTaxable = slip.NonTaxable.Add(earning.Amount)
			slip.BenefitExempt = slip.BenefitExempt.Add(earning.Amount)
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

	// Statutory contributions. Each agency has its own contribution base so a
	// company can follow the agency circular without code changes.
	sssBase := contributionBase(cfg.SssBase, employee, slip, periods)
	philHealthBase := contributionBase(cfg.PhilHealthBase, employee, slip, periods)
	pagIBIGBase := contributionBase(cfg.PagIBIGBase, employee, slip, periods)

	if divisor := statutory.Divisor(cfg.SSSSchedule, cfg.Frequency, cfg.Cutoff); divisor > 0 {
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

	for _, deduction := range employee.Deductions {
		slip.OtherDeductions = slip.OtherDeductions.Add(deduction.Amount)
	}

	for _, loan := range employee.Loans {
		slip.Loans = slip.Loans.Add(loan.Amount)
		slip.OtherDeductions = slip.OtherDeductions.Add(loan.Amount)
	}

	// Minimum wage earners are exempt on the statutory minimum wage and on
	// the holiday, overtime and night-shift pay attributable to it (RA 9504
	// as amended by RA 10963).
	minWageExemption := money.Zero
	if employee.IsMinimumWage && employee.MinimumWage > 0 {
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
				Add(slip.NightOTPay)
			minWageExemption = money.Min(slip.Gross, statutoryWage.Add(premiums))
			slip.MinimumWageExemption = minWageExemption
		}
	}

	// Mandatory employee contributions and non-taxable benefits are exempt.
	slip.TaxableIncome = slip.Gross.
		Sub(slip.NonTaxable).
		Sub(minWageExemption).
		Sub(slip.SSSEmployee).
		Sub(slip.SSSProvident).
		Sub(slip.PhilHealthEmployee).
		Sub(slip.PagIBIGEmployee)

	if slip.TaxableIncome < 0 {
		slip.TaxableIncome = 0
	}

	if statutory.Divisor(cfg.TaxSchedule, cfg.Frequency, cfg.Cutoff) > 0 {
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
		Add(slip.PagIBIGEmployer)

	if cfg.MinimumWage > 0 && daily > 0 && daily < cfg.MinimumWage {
		slip.Warnings = append(slip.Warnings,
			fmt.Sprintf("daily rate %s is below the minimum wage %s", daily, cfg.MinimumWage))
	}

	return slip, nil
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
func entitledDayRate(flags model.BenefitFlags, dayType string, date string) (dayPremium, string) {
	multiplier := rules.DayMultiplier(dayType)
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
// De minimis benefits are capped at their statutory ceiling and basis;
// anything above the ceiling is taxable.
func nonTaxablePart(cfg model.Config, employee model.Employee, earning model.Earning, periods int) money.Centavos {
	if earning.Taxable {
		return 0
	}

	if earning.Kind == model.KindAllowance && employee.Benefits.DeMinimis {
		if benefit, ok := deMinimisBenefit(cfg, earning); ok {
			return deMinimisExemption(cfg, employee, earning, benefit, periods)
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

func deMinimisExemption(cfg model.Config, employee model.Employee, earning model.Earning, benefit model.DeMinimisBenefit, periods int) money.Centavos {
	periodsPerMonth := periods
	if periodsPerMonth <= 0 {
		periodsPerMonth = 1
	}

	switch benefit.Basis {
	case model.DeMinimisAnnual:
		return money.Min(earning.Amount, split(benefit.Ceiling, periodsPerMonth*12))
	case model.DeMinimisSemester:
		perMonth := split(benefit.Ceiling, 6)

		return money.Min(earning.Amount, firstPart(perMonth, periodsPerMonth))
	case model.DeMinimisHourlyOT:
		daysPerYear := cfg.DaysPerYear
		if daysPerYear <= 0 {
			daysPerYear = 261
		}

		hoursPerDay := cfg.HoursPerDay
		if hoursPerDay <= 0 {
			hoursPerDay = 8
		}

		hourly := rules.HourlyRate(employee.MonthlyBasic, daysPerYear, hoursPerDay)
		ceiling := hourly.Mul(overtimeHours(employee.Timesheets) * 0.25)

		return money.Min(earning.Amount, ceiling)
	default:
		return money.Min(earning.Amount, firstPart(benefit.Ceiling, periodsPerMonth))
	}
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
func contributionBase(base string, employee model.Employee, slip model.Payslip, periods int) money.Centavos {
	switch strings.ToLower(strings.TrimSpace(base)) {
	case "gross":
		return slip.Gross.Mul(float64(periods))
	case "period_gross":
		return slip.Gross
	default:
		return employee.MonthlyBasic
	}
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
		for _, holiday := range cfg.Holidays {
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

	brackets := cfg.AnnualTaxBrackets
	if len(brackets) == 0 {
		brackets = statutory.TRAINTaxBrackets(statutory.FrequencyMonthly)
	}

	monthlyTax, err := statutory.ComputeTax(taxable.Mul(float64(periods)), brackets)
	if err != nil {
		return 0, err
	}

	return firstPart(monthlyTax, periods), nil
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
