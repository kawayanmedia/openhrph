package payroll

import (
	"testing"

	"github.com/kawayanmedia/openhrph-payroll-engine/internal/model"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/money"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/rules"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/statutory"
)

func TestNightOvertimePaysTheOvertimeBaseAndTheDifferential(t *testing.T) {
	cfg := semiMonthlyConfig()

	// ₱26,100 monthly = ₱1,200 daily / ₱150 hourly.
	slip, err := Compute(cfg, model.Employee{
		ID:           21,
		MonthlyBasic: money.FromPesos(26100),
		Benefits:     allBenefits(),
		Timesheets: []model.TimesheetLine{{
			Date:     "2026-01-05",
			DayType:  "regular",
			Overtime: 2, // actual_ot (0) + actual_nt_ot (2)
			Night:    2, // actual_nt (4) - actual_nt_ot (2)
			NightOT:  2,
		}},
	})
	if err != nil {
		t.Fatal(err)
	}

	// Overtime: 150 x 2 x 125% = 375.00.
	if slip.OvertimePay != money.FromPesos(375) {
		t.Fatalf("overtime pay = %s, want 375.00", slip.OvertimePay)
	}

	// Ordinary night: 150 x 2 x 10% = 30.00.
	if slip.NightPay != money.FromPesos(30) {
		t.Fatalf("night pay = %s, want 30.00", slip.NightPay)
	}

	// Night-OT differential: 150 x 2 x 125% x 10% = 37.50.
	if slip.NightOTPay != money.FromPesos(37.50) {
		t.Fatalf("night OT pay = %s, want 37.50", slip.NightOTPay)
	}
}

func TestMonthlyPaidStaffReceiveAFixedPeriodBasic(t *testing.T) {
	cfg := semiMonthlyConfig()

	build := func(days int) model.Employee {
		employee := model.Employee{
			ID:           22,
			MonthlyBasic: money.FromPesos(26100),
			PaymentType:  model.PaymentMonthly,
			Benefits:     allBenefits(),
			Timesheets:   nil,
		}

		for day := 1; day <= days; day++ {
			employee.Timesheets = append(employee.Timesheets, model.TimesheetLine{
				Date:    "2026-01-" + padDay(day),
				DayType: "regular",
				Hours:   8,
			})
		}

		return employee
	}

	for _, days := range []int{10, 11, 12} {
		slip, err := Compute(cfg, build(days))
		if err != nil {
			t.Fatal(err)
		}

		// Fixed half-month rate, independent of the working-day count.
		if slip.RegularPay != money.FromPesos(13050) {
			t.Fatalf("%d days: regular pay = %s, want 13050.00", days, slip.RegularPay)
		}
	}
}

func TestMonthlyPaidPremiumsPayOnlyThePremiumPortion(t *testing.T) {
	cfg := semiMonthlyConfig()

	slip, err := Compute(cfg, model.Employee{
		ID:           23,
		MonthlyBasic: money.FromPesos(26100),
		PaymentType:  model.PaymentMonthly,
		Benefits:     allBenefits(),
		Timesheets: []model.TimesheetLine{
			{Date: "2026-01-03", DayType: "rd", Hours: 8},
			{Date: "2026-01-05", DayType: "rh", Hours: 8},
		},
	})
	if err != nil {
		t.Fatal(err)
	}

	// Fixed 13,050 + rest-day premium 360 (30%) + holiday premium 1,200 (100%).
	if slip.RegularPay != money.FromPesos(14610) {
		t.Fatalf("regular pay = %s, want 14610.00", slip.RegularPay)
	}

	if slip.RestDayPay != money.FromPesos(360) {
		t.Fatalf("rest day pay = %s, want 360.00", slip.RestDayPay)
	}

	if slip.HolidayPay != money.FromPesos(1200) {
		t.Fatalf("holiday pay = %s, want 1200.00", slip.HolidayPay)
	}
}

func TestMonthlyPaidAbsenceReducesTheFixedBasic(t *testing.T) {
	cfg := semiMonthlyConfig()

	slip, err := Compute(cfg, model.Employee{
		ID:           24,
		MonthlyBasic: money.FromPesos(26100),
		PaymentType:  model.PaymentMonthly,
		Benefits:     allBenefits(),
		Timesheets: []model.TimesheetLine{
			{Date: "2026-01-03", DayType: "regular", Hours: 8},
			{Date: "2026-01-04", DayType: "regular", Absent: true},
		},
	})
	if err != nil {
		t.Fatal(err)
	}

	if slip.RegularPay != money.FromPesos(11850) {
		t.Fatalf("regular pay = %s, want 11850.00", slip.RegularPay)
	}

	if slip.BasicEarned != money.FromPesos(11850) {
		t.Fatalf("basic earned = %s, want 11850.00", slip.BasicEarned)
	}
}

func TestDailyPaidUnworkedRegularHoliday(t *testing.T) {
	cfg := semiMonthlyConfig()
	cfg.Holidays = []model.Holiday{{Date: "2026-01-02", Code: "rh"}}

	employee := model.Employee{
		ID:           25,
		MonthlyBasic: money.FromPesos(26100),
		PaymentType:  model.PaymentDaily,
		Benefits:     allBenefits(),
		Timesheets: []model.TimesheetLine{
			{Date: "2026-01-01", DayType: "regular", Hours: 8},
		},
	}

	slip, err := Compute(cfg, employee)
	if err != nil {
		t.Fatal(err)
	}

	// 1,200 for 1 January plus 1,200 for the unworked regular holiday.
	if slip.RegularPay != money.FromPesos(2400) {
		t.Fatalf("regular pay = %s, want 2400.00", slip.RegularPay)
	}

	if slip.HolidayPay != money.FromPesos(1200) {
		t.Fatalf("holiday pay = %s, want 1200.00", slip.HolidayPay)
	}
}

func TestDailyPaidUnworkedHolidayRequiresPresenceBeforeIt(t *testing.T) {
	cfg := semiMonthlyConfig()
	cfg.Holidays = []model.Holiday{{Date: "2026-01-02", Code: "rh"}}

	slip, err := Compute(cfg, model.Employee{
		ID:           26,
		MonthlyBasic: money.FromPesos(26100),
		PaymentType:  model.PaymentDaily,
		Benefits:     allBenefits(),
		Timesheets: []model.TimesheetLine{
			{Date: "2026-01-01", DayType: "regular", Absent: true},
		},
	})
	if err != nil {
		t.Fatal(err)
	}

	if slip.HolidayPay != 0 || slip.RegularPay != 0 {
		t.Fatalf("unworked holiday should be unpaid when absent the day before, got %s / %s",
			slip.HolidayPay, slip.RegularPay)
	}
}

func TestBenefitSwitchesSuppressPremiums(t *testing.T) {
	cfg := semiMonthlyConfig()

	slip, err := Compute(cfg, model.Employee{
		ID:           27,
		MonthlyBasic: money.FromPesos(26100),
		PaymentType:  model.PaymentDaily,
		Benefits:     model.BenefitFlags{}, // nothing configured
		Timesheets: []model.TimesheetLine{
			{Date: "2026-01-03", DayType: "rd", Hours: 8, Overtime: 2},
		},
	})
	if err != nil {
		t.Fatal(err)
	}

	// Ordinary rate only: 1,200 + 2 x 150 (no OT premium).
	if slip.RegularPay != money.FromPesos(1200) {
		t.Fatalf("regular pay = %s, want 1200.00", slip.RegularPay)
	}

	if slip.OvertimePay != money.FromPesos(300) {
		t.Fatalf("overtime pay = %s, want 300.00", slip.OvertimePay)
	}

	if len(slip.Warnings) == 0 {
		t.Fatal("expected warnings for suppressed premiums")
	}
}

func TestStatutoryPartsReconcileAcrossCutoffs(t *testing.T) {
	cfg := semiMonthlyConfig()

	slip, err := Compute(cfg, model.Employee{
		ID:           28,
		MonthlyBasic: money.FromPesos(20300),
		PaymentType:  model.PaymentMonthly,
		Benefits:     allBenefits(),
		Timesheets:   []model.TimesheetLine{{Date: "2026-01-03", DayType: "regular", Hours: 8}},
	})
	if err != nil {
		t.Fatal(err)
	}

	if slip.SSSTotal != slip.SSSEmployee.Add(slip.SSSProvident).
		Add(slip.SSSEmployer).Add(slip.SSSProvidentER).Add(slip.SSSEC) {
		t.Fatalf("sss total %s does not reconcile to its parts", slip.SSSTotal)
	}
}

func TestMinimumWageEarnerExemption(t *testing.T) {
	cfg := semiMonthlyConfig()
	cfg.MinimumWage = money.FromPesos(600)

	slip, err := Compute(cfg, model.Employee{
		ID:            29,
		MonthlyBasic:  money.FromPesos(9000),
		DailyRate:     money.FromPesos(600),
		PaymentType:   model.PaymentDaily,
		IsMinimumWage: true,
		MinimumWage:   money.FromPesos(600),
		Benefits:      allBenefits(),
		Timesheets: []model.TimesheetLine{
			{Date: "2026-01-03", DayType: "regular", Hours: 8},
		},
	})
	if err != nil {
		t.Fatal(err)
	}

	if slip.MinimumWageExemption == 0 {
		t.Fatal("expected the statutory minimum wage to be exempt")
	}

	if slip.WithholdingTax != 0 {
		t.Fatalf("withholding tax = %s, want 0", slip.WithholdingTax)
	}
}

func TestThirteenthMonthUsesTheAnnualBasicAndNinetyThousandExclusion(t *testing.T) {
	cfg := semiMonthlyConfig()
	cfg.RunType = model.RunThirteenthMonth

	slip, err := Compute(cfg, model.Employee{
		ID:           30,
		MonthlyBasic: money.FromPesos(26100),
		YTD:          model.YtdTotals{BasicEarned: money.FromPesos(261000)},
	})
	if err != nil {
		t.Fatal(err)
	}

	// 261,000 / 12 = 21,750, below the 90,000 exclusion.
	if slip.ThirteenthMonth != money.FromPesos(21750) {
		t.Fatalf("13th month = %s, want 21750.00", slip.ThirteenthMonth)
	}

	if slip.WithholdingTax != 0 {
		t.Fatalf("13th month below the exclusion should not be taxed, got %s", slip.WithholdingTax)
	}

	if slip.NetPay != money.FromPesos(21750) {
		t.Fatalf("net pay = %s, want 21750.00", slip.NetPay)
	}
}

func TestThirteenthMonthTaxesOnlyTheExcess(t *testing.T) {
	cfg := semiMonthlyConfig()
	cfg.RunType = model.RunThirteenthMonth

	slip, err := Compute(cfg, model.Employee{
		ID:           31,
		MonthlyBasic: money.FromPesos(120000),
		YTD:          model.YtdTotals{BasicEarned: money.FromPesos(1440000)},
	})
	if err != nil {
		t.Fatal(err)
	}

	// 120,000 - 90,000 = 30,000 taxable.
	if slip.ThirteenthMonth != money.FromPesos(120000) {
		t.Fatalf("13th month = %s, want 120000.00", slip.ThirteenthMonth)
	}

	if slip.TaxableIncome != money.FromPesos(30000) {
		t.Fatalf("taxable income = %s, want 30000.00", slip.TaxableIncome)
	}

	if slip.WithholdingTax == 0 {
		t.Fatal("expected withholding tax on the excess")
	}
}

func TestFinalPayIncludesLeaveConversionAndSeparationPay(t *testing.T) {
	cfg := semiMonthlyConfig()
	cfg.RunType = model.RunFinalPay
	cfg.Frequency = statutory.FrequencyMonthly
	cfg.Meta = map[string]string{
		"separation_reason": "retrenchment",
		"years_of_service":  "5",
	}

	slip, err := Compute(cfg, model.Employee{
		ID:               32,
		MonthlyBasic:     money.FromPesos(26100),
		PaymentType:      model.PaymentMonthly,
		Benefits:         allBenefits(),
		HiredOn:          "2021-01-01",
		SeparatedOn:      "2026-01-01",
		LeaveBalanceDays: 5,
		YTD:              model.YtdTotals{BasicEarned: money.FromPesos(261000)},
	})
	if err != nil {
		t.Fatal(err)
	}

	// Leave conversion: 5 days x 1,200 = 6,000.
	if slip.LeaveConversion != money.FromPesos(6000) {
		t.Fatalf("leave conversion = %s, want 6000.00", slip.LeaveConversion)
	}

	// Retrenchment: 1 month per year x 5 years = 130,500.
	if slip.SeparationPay != money.FromPesos(130500) {
		t.Fatalf("separation pay = %s, want 130500.00", slip.SeparationPay)
	}

	if slip.ThirteenthMonth == 0 {
		t.Fatal("expected a pro-rated 13th month")
	}
}

func padDay(day int) string {
	if day < 10 {
		return "0" + string(rune('0'+day))
	}

	return string(rune('0'+day/10)) + string(rune('0'+day%10))
}

func TestStoredDayCodesRemainClassified(t *testing.T) {
	for code, want := range map[string]float64{
		"rd":       1.30,
		"sh":       1.30,
		"rh":       2.00,
		"rd-sh":    1.50,
		"rd-rh":    2.60,
		"2rh":      3.00,
		"rd-2rh":   3.90,
		"rh-sh":    2.60,
		"rd-rh-sh": 3.00,
		"regular":  1.00,
	} {
		if got := rules.DayMultiplier(code); got != want {
			t.Fatalf("DayMultiplier(%q) = %v, want %v", code, got, want)
		}
	}
}

func TestWithholdingUsesMonthlyEquivalentForNonUniformSchedules(t *testing.T) {
	cfg := semiMonthlyConfig()

	uniform, err := withholding(cfg, money.FromPesos(20000), 2)
	if err != nil {
		t.Fatal(err)
	}

	cfg.SSSSchedule = statutory.ScheduleFirstPay

	nonUniform, err := withholding(cfg, money.FromPesos(20000), 2)
	if err != nil {
		t.Fatal(err)
	}

	monthlyTax, err := statutory.ComputeTax(money.FromPesos(40000), statutory.TRAINTaxBrackets(statutory.FrequencyMonthly))
	if err != nil {
		t.Fatal(err)
	}

	if nonUniform != monthlyTax.Split(2)[0] {
		t.Fatalf("non-uniform withholding = %s, want %s", nonUniform, monthlyTax.Split(2)[0])
	}

	if uniform == nonUniform {
		t.Fatal("expected the semi-monthly and monthly-equivalent calculations to differ")
	}
}

func TestDeMinimisCeilingCapsTheExemptAllowance(t *testing.T) {
	cfg := semiMonthlyConfig()
	cfg.DeMinimis = []model.DeMinimisBenefit{{Code: "rice", Ceiling: money.FromPesos(2000)}}

	slip, err := Compute(cfg, model.Employee{
		ID:           33,
		MonthlyBasic: money.FromPesos(26100),
		PaymentType:  model.PaymentDaily,
		Benefits:     model.BenefitFlags{DeMinimis: true},
		Allowances: []model.Earning{{
			Code:    "rice",
			Name:    "Rice subsidy",
			Kind:    model.KindAllowance,
			Amount:  money.FromPesos(3000),
			Taxable: false,
		}},
		Timesheets: []model.TimesheetLine{{Date: "2026-01-03", DayType: "regular", Hours: 8}},
	})
	if err != nil {
		t.Fatal(err)
	}

	// The ceiling is monthly (2,000), so a semi-monthly cut-off exempts 1,000
	// of the 3,000 allowance; the remaining 2,000 is taxable.
	if slip.NonTaxable != money.FromPesos(1000) {
		t.Fatalf("non-taxable = %s, want 1000.00", slip.NonTaxable)
	}
}
