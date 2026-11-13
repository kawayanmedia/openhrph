package payroll

import (
	"reflect"
	"strings"
	"testing"

	"github.com/kawayanmedia/openhrph-payroll-engine/internal/model"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/money"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/rules"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/statutory"
)

// Tests in this file close the coverage gaps recorded in
// docs/payroll-audit/2026-11-payroll-compliance-audit.md section 7.
//
// Every expected value is derived by hand from the law or from the published
// statutory table (the derivation is in the comment above each assertion).
// None of them is taken from engine output. Tests that document a defect or
// an unresolved policy question call t.Skip with the expected and observed
// values, so the suite stays green while the gap stays visible.

func regularDayLines(startDay, count int) []model.TimesheetLine {
	lines := make([]model.TimesheetLine, 0, count)

	for day := startDay; day < startDay+count; day++ {
		lines = append(lines, model.TimesheetLine{
			Date:    "2026-01-" + padDay(day),
			DayType: rules.DayRegular,
			Hours:   8,
		})
	}

	return lines
}

// TestAdditionalEarningsDeductionsAndLoansReconcile covers F-02: allowances,
// de minimis ceilings, other deductions and loans must reach gross, taxable
// income, total deductions and net pay exactly once each.
func TestAdditionalEarningsDeductionsAndLoansReconcile(t *testing.T) {
	cfg := semiMonthlyConfig()
	cfg.DeMinimis = []model.DeMinimisBenefit{{Code: "rice", Ceiling: money.FromPesos(2000)}}

	employee := model.Employee{
		ID:           41,
		Number:       "E-0041",
		Name:         "Allowance Employee",
		MonthlyBasic: money.FromPesos(26100),
		PaymentType:  model.PaymentMonthly,
		Benefits:     allBenefits(),
		Timesheets:   regularDayLines(1, 11),
		Allowances: []model.Earning{
			{Code: "rice", Name: "Rice subsidy", Kind: model.KindAllowance, Amount: money.FromPesos(2500)},
			{Code: "transpo", Name: "Transport allowance", Kind: model.KindAllowance, Amount: money.FromPesos(1000), Taxable: true},
		},
		Deductions: []model.Earning{
			{Code: "union", Name: "Union dues", Kind: model.KindDeduction, Amount: money.FromPesos(500)},
		},
		Loans: []model.Earning{
			{Code: "sss_loan", Name: "SSS salary loan", Kind: model.KindLoan, Amount: money.FromPesos(1000)},
		},
	}

	slip, err := Compute(cfg, employee)
	if err != nil {
		t.Fatal(err)
	}

	// Monthly-paid: fixed half-month basic 26,100 / 2 = 13,050.
	// Gross = 13,050 basic + 1,000 taxable allowance + 2,500 rice subsidy.
	if slip.Allowances != money.FromPesos(3500) {
		t.Fatalf("allowances = %s, want 3500.00", slip.Allowances)
	}

	if slip.Gross != money.FromPesos(16550) {
		t.Fatalf("gross = %s, want 16550.00", slip.Gross)
	}

	// Rice subsidy is capped at the 2,000 monthly de minimis ceiling, so the
	// semi-monthly cut-off exempts 1,000 of it.
	if slip.NonTaxable != money.FromPesos(1000) {
		t.Fatalf("non-taxable = %s, want 1000.00", slip.NonTaxable)
	}

	// 26,100 -> MSC 26,000: SSS 5% x 20,000 = 1,000 and WISP 5% x 6,000 = 300,
	// each halved for the semi-monthly cut-off.
	if slip.SSSEmployee != money.FromPesos(500) || slip.SSSProvident != money.FromPesos(150) {
		t.Fatalf("sss split = %s / %s, want 500.00 / 150.00", slip.SSSEmployee, slip.SSSProvident)
	}

	// PhilHealth 5% x 26,100 = 1,305 premium, 652.50 employee, halved.
	// Pag-IBIG 2% x 10,000 cap = 200 employee, halved.
	if slip.PhilHealthEmployee != money.FromPesos(326.25) || slip.PagIBIGEmployee != money.FromPesos(100) {
		t.Fatalf("philhealth/pagibig = %s / %s", slip.PhilHealthEmployee, slip.PagIBIGEmployee)
	}

	// Taxable = 16,550 - 1,000 exempt - 500 - 150 - 326.25 - 100 = 14,473.75.
	if slip.TaxableIncome != money.FromPesos(14473.75) {
		t.Fatalf("taxable income = %s, want 14473.75", slip.TaxableIncome)
	}

	// 15% x (14,473.75 - 10,417) = 608.5125 -> 608.51.
	if slip.WithholdingTax != money.FromPesos(608.51) {
		t.Fatalf("withholding tax = %s, want 608.51", slip.WithholdingTax)
	}

	// Loans are part of other deductions and must be counted exactly once.
	if slip.Loans != money.FromPesos(1000) {
		t.Fatalf("loans = %s, want 1000.00", slip.Loans)
	}

	if slip.OtherDeductions != money.FromPesos(1500) {
		t.Fatalf("other deductions = %s, want 1500.00 (500 deduction + 1,000 loan)", slip.OtherDeductions)
	}

	// 500 + 150 + 326.25 + 100 + 608.51 + 1,500 = 3,184.76.
	if slip.TotalDeductions != money.FromPesos(3184.76) {
		t.Fatalf("total deductions = %s, want 3184.76", slip.TotalDeductions)
	}

	if slip.NetPay != money.FromPesos(13365.24) {
		t.Fatalf("net pay = %s, want 13365.24", slip.NetPay)
	}
}

// TestContributionCeilingsInARun proves the ceilings are applied inside a
// payroll run, not only in the statutory package.
func TestContributionCeilingsInARun(t *testing.T) {
	cfg := semiMonthlyConfig()

	slip, err := Compute(cfg, model.Employee{
		ID:           42,
		MonthlyBasic: money.FromPesos(250000),
		PaymentType:  model.PaymentMonthly,
		Benefits:     allBenefits(),
		Timesheets:   regularDayLines(1, 11),
	})
	if err != nil {
		t.Fatal(err)
	}

	// Fixed half-month basic 125,000.
	if slip.Gross != money.FromPesos(125000) {
		t.Fatalf("gross = %s, want 125000.00", slip.Gross)
	}

	// SSS MSC is capped at 35,000: 5% x 20,000 = 1,000 regular plus
	// 5% x 15,000 = 750 WISP, halved -> 500 + 375.
	if slip.SSSEmployee != money.FromPesos(500) || slip.SSSProvident != money.FromPesos(375) {
		t.Fatalf("sss split = %s / %s, want 500.00 / 375.00", slip.SSSEmployee, slip.SSSProvident)
	}

	// Employer: 1,000 + 750 WISP + 15 EC (30 halved).
	if slip.SSSEmployer != money.FromPesos(1000) || slip.SSSProvidentER != money.FromPesos(750) || slip.SSSEC != money.FromPesos(15) {
		t.Fatalf("sss employer split = %s / %s / %s", slip.SSSEmployer, slip.SSSProvidentER, slip.SSSEC)
	}

	// PhilHealth is capped at a 100,000 base: 5% = 5,000 premium, 2,500
	// employee share, halved -> 1,250.
	if slip.PhilHealthEmployee != money.FromPesos(1250) {
		t.Fatalf("philhealth employee = %s, want 1250.00", slip.PhilHealthEmployee)
	}

	// Pag-IBIG stays at the 200 employee maximum, halved -> 100.
	if slip.PagIBIGEmployee != money.FromPesos(100) {
		t.Fatalf("pagibig employee = %s, want 100.00", slip.PagIBIGEmployee)
	}

	// Taxable = 125,000 - 500 - 375 - 1,250 - 100 = 122,775.
	// Semi-monthly 83,333-333,332.99 band is 30%: 16,770.70 +
	// 30% x (122,775 - 83,333) = 28,603.30.
	if slip.TaxableIncome != money.FromPesos(122775) {
		t.Fatalf("taxable income = %s, want 122775.00", slip.TaxableIncome)
	}

	if slip.WithholdingTax != money.FromPesos(28603.30) {
		t.Fatalf("withholding tax = %s, want 28603.30", slip.WithholdingTax)
	}

	// 500 + 375 + 1,250 + 100 + 28,603.30 = 30,828.30; net = 94,171.70.
	if slip.TotalDeductions != money.FromPesos(30828.30) {
		t.Fatalf("total deductions = %s, want 30828.30", slip.TotalDeductions)
	}

	if slip.NetPay != money.FromPesos(94171.70) {
		t.Fatalf("net pay = %s, want 94171.70", slip.NetPay)
	}

	// Employer cost = 1,000 + 750 + 15 + 1,250 + 100 = 3,115.
	if slip.TotalEmployerCost != money.FromPesos(3115) {
		t.Fatalf("total employer cost = %s, want 3115.00", slip.TotalEmployerCost)
	}
}

// annualTRAINBrackets is the published BIR annual withholding table for
// 2023 onwards, transcribed as an independent reference for the tests.
func annualTRAINBrackets() []statutory.TaxBracket {
	return []statutory.TaxBracket{
		{From: money.Centavos(0), To: money.Centavos(25000000), BaseTax: 0, Rate: 0},
		{From: money.Centavos(25000001), To: money.Centavos(40000000), BaseTax: 0, Rate: 0.15},
		{From: money.Centavos(40000001), To: money.Centavos(80000000), BaseTax: money.Centavos(2250000), Rate: 0.20},
		{From: money.Centavos(80000001), To: money.Centavos(200000000), BaseTax: money.Centavos(10250000), Rate: 0.25},
		{From: money.Centavos(200000001), To: money.Centavos(800000000), BaseTax: money.Centavos(40250000), Rate: 0.30},
		{From: money.Centavos(800000001), To: 0, BaseTax: money.Centavos(220250000), Rate: 0.35},
	}
}

// TestThirteenthMonthAggregatesOtherBenefits covers F-09: the 90,000
// exclusion is a combined ceiling for 13th-month pay and other benefits.
func TestThirteenthMonthAggregatesOtherBenefits(t *testing.T) {
	cfg := semiMonthlyConfig()
	cfg.RunType = model.RunThirteenthMonth
	cfg.AnnualTaxBrackets = annualTRAINBrackets()

	slip, err := Compute(cfg, model.Employee{
		ID:           43,
		MonthlyBasic: money.FromPesos(300000),
		YTD: model.YtdTotals{
			BasicEarned:   money.FromPesos(3600000),
			OtherBenefits: money.FromPesos(60000),
		},
	})
	if err != nil {
		t.Fatal(err)
	}

	// 3,600,000 / 12 = 300,000.
	if slip.ThirteenthMonth != money.FromPesos(300000) {
		t.Fatalf("13th month = %s, want 300000.00", slip.ThirteenthMonth)
	}

	// Combined benefits 300,000 + 60,000 = 360,000; excess over 90,000 is
	// 270,000, so only 30,000 of this run stays exempt.
	if slip.TaxableIncome != money.FromPesos(270000) {
		t.Fatalf("taxable income = %s, want 270000.00", slip.TaxableIncome)
	}

	if slip.NonTaxable != money.FromPesos(30000) {
		t.Fatalf("non-taxable = %s, want 30000.00", slip.NonTaxable)
	}

	// Annual table: 15% x (270,000 - 250,000) = 3,000.
	if slip.WithholdingTax != money.FromPesos(3000) {
		t.Fatalf("withholding tax = %s, want 3000.00", slip.WithholdingTax)
	}

	if slip.NetPay != money.FromPesos(297000) {
		t.Fatalf("net pay = %s, want 297000.00", slip.NetPay)
	}
}

// TestThirteenthMonthExclusionBoundary checks the exact 90,000 ceiling.
func TestThirteenthMonthExclusionBoundary(t *testing.T) {
	cfg := semiMonthlyConfig()
	cfg.RunType = model.RunThirteenthMonth
	cfg.AnnualTaxBrackets = annualTRAINBrackets()

	// 1,080,000 / 12 = 90,000 exactly: fully exempt.
	slip, err := Compute(cfg, model.Employee{
		ID:           44,
		MonthlyBasic: money.FromPesos(90000),
		YTD:          model.YtdTotals{BasicEarned: money.FromPesos(1080000)},
	})
	if err != nil {
		t.Fatal(err)
	}

	if slip.ThirteenthMonth != money.FromPesos(90000) || slip.TaxableIncome != 0 || slip.WithholdingTax != 0 {
		t.Fatalf("13th at the ceiling = %s, taxable %s, tax %s; want 90000.00, 0, 0",
			slip.ThirteenthMonth, slip.TaxableIncome, slip.WithholdingTax)
	}

	// The same 90,000 with 90,000 of other benefits is fully taxable.
	slip, err = Compute(cfg, model.Employee{
		ID:           44,
		MonthlyBasic: money.FromPesos(90000),
		YTD: model.YtdTotals{
			BasicEarned:   money.FromPesos(1080000),
			OtherBenefits: money.FromPesos(90000),
		},
	})
	if err != nil {
		t.Fatal(err)
	}

	if slip.TaxableIncome != money.FromPesos(90000) || slip.NonTaxable != 0 {
		t.Fatalf("combined benefits at 180,000: taxable %s, non-taxable %s; want 90000.00 and 0",
			slip.TaxableIncome, slip.NonTaxable)
	}
}

// TestMinimumWageExemptionCoversTheWholePeriod covers F-08 for daily-paid
// minimum wage earners.
//
// PENDING: the engine exempts one day of minimum wage (640) plus the period's
// premiums (216), instead of the minimum wage actually earned in the period
// (10 days x 640 = 6,400) plus premiums. That leaves 5,136 of statutory
// minimum wage taxable. See finding F-25 in the payroll audit.
func TestMinimumWageExemptionCoversTheWholePeriod(t *testing.T) {
	cfg := semiMonthlyConfig()
	cfg.MinimumWage = money.FromPesos(640)

	employee := model.Employee{
		ID:            45,
		MonthlyBasic:  money.FromPesos(13920), // 640 x 261 / 12
		DailyRate:     money.FromPesos(640),
		PaymentType:   model.PaymentDaily,
		IsMinimumWage: true,
		MinimumWage:   money.FromPesos(640),
		Benefits:      allBenefits(),
		Timesheets:    regularDayLines(1, 10),
	}

	employee.Timesheets = append(employee.Timesheets,
		model.TimesheetLine{Date: "2026-01-20", DayType: rules.DayRegular, Overtime: 2},
		model.TimesheetLine{Date: "2026-01-21", DayType: rules.DayRegular, Night: 2},
	)

	slip, err := Compute(cfg, employee)
	if err != nil {
		t.Fatal(err)
	}

	// Basic 6,400 + OT 80 x 2 x 125% (200) + night 80 x 2 x 10% (16).
	if slip.Gross != money.FromPesos(6616) {
		t.Fatalf("gross = %s, want 6616.00", slip.Gross)
	}

	// The exemption must cover the statutory wage and the premiums
	// attributable to it, so nothing is left taxable.
	if slip.MinimumWageExemption != money.FromPesos(6616) {
		t.Fatalf("minimum wage exemption = %s, want 6616.00", slip.MinimumWageExemption)
	}

	if slip.TaxableIncome != 0 {
		t.Fatalf("taxable income = %s, want 0", slip.TaxableIncome)
	}
}

// TestFinalPayProratesThirteenthMonthOnEarnedBasic covers F-10 for a
// daily-paid separation.
//
// PENDING: the pro-rated 13th month adds the notional half-month basic
// (13,050) instead of the basic actually earned in the final period (6,000),
// so 13,087.50 is paid instead of 12,500.00. See finding F-26 in the payroll
// audit.
func TestFinalPayProratesThirteenthMonthOnEarnedBasic(t *testing.T) {
	cfg := semiMonthlyConfig()
	cfg.RunType = model.RunFinalPay
	cfg.Meta = map[string]string{
		"leave_conversion": "false",
		"separation_pay":   "0",
	}

	slip, err := Compute(cfg, model.Employee{
		ID:           46,
		MonthlyBasic: money.FromPesos(26100),
		DailyRate:    money.FromPesos(1200),
		PaymentType:  model.PaymentDaily,
		Benefits:     allBenefits(),
		HiredOn:      "2025-03-01",
		SeparatedOn:  "2026-01-15",
		YTD:          model.YtdTotals{BasicEarned: money.FromPesos(144000)},
		Timesheets:   regularDayLines(1, 5),
	})
	if err != nil {
		t.Fatal(err)
	}

	// Final period basic: 5 days x 1,200 = 6,000.
	if slip.BasicEarned != money.FromPesos(6000) {
		t.Fatalf("final period basic = %s, want 6000.00", slip.BasicEarned)
	}

	// (144,000 earned year to date + 6,000 final period) / 12 = 12,500.
	if slip.ThirteenthMonth != money.FromPesos(12500) {
		t.Fatalf("pro-rated 13th month = %s, want 12500.00", slip.ThirteenthMonth)
	}
}

// TestNewHireProrationIsUnresolvedPolicy records that a monthly-paid employee
// hired mid-period receives the full period basic, with no proration.
//
// PENDING: proration for a mid-period hire is a company policy the application
// does not yet define (audit section 10). The test is skipped rather than
// asserting an invented rule.
func TestNewHireProrationIsUnresolvedPolicy(t *testing.T) {
	t.Skip("pending policy: a monthly-paid hire on 2026-01-16 with 10 attended days receives the full 13,050.00 period basic; proration is not defined")

	cfg := semiMonthlyConfig()

	slip, err := Compute(cfg, model.Employee{
		ID:           47,
		MonthlyBasic: money.FromPesos(26100),
		PaymentType:  model.PaymentMonthly,
		Benefits:     allBenefits(),
		HiredOn:      "2026-01-16",
		Timesheets:   regularDayLines(16, 10),
	})
	if err != nil {
		t.Fatal(err)
	}

	// A prorated reading would pay 10 days x 1,200 = 12,000.
	if slip.RegularPay != money.FromPesos(12000) {
		t.Fatalf("prorated basic = %s, want 12000.00", slip.RegularPay)
	}
}

// TestRepeatedRunsAreDeterministicAndDoNotDoubleChargeLoans covers the
// duplicate-run requirement at the engine boundary: the engine is stateless,
// so re-running the same input must produce the same payslip and charge each
// loan once per run.
func TestRepeatedRunsAreDeterministicAndDoNotDoubleChargeLoans(t *testing.T) {
	cfg := semiMonthlyConfig()

	employee := model.Employee{
		ID:           48,
		MonthlyBasic: money.FromPesos(26100),
		PaymentType:  model.PaymentMonthly,
		Benefits:     allBenefits(),
		Timesheets:   regularDayLines(1, 11),
		Loans: []model.Earning{
			{Code: "sss_loan", Name: "SSS salary loan", Kind: model.KindLoan, Amount: money.FromPesos(1000)},
		},
	}

	first, err := Compute(cfg, employee)
	if err != nil {
		t.Fatal(err)
	}

	second, err := Compute(cfg, employee)

	if err != nil {
		t.Fatal(err)
	}

	if !reflect.DeepEqual(first, second) {
		t.Fatalf("re-running the same input produced a different payslip:\nfirst  %+v\nsecond %+v", first, second)
	}

	if first.Loans != money.FromPesos(1000) {
		t.Fatalf("loans = %s, want 1000.00 charged once per run", first.Loans)
	}

	if first.OtherDeductions != money.FromPesos(1000) {
		t.Fatalf("other deductions = %s, want 1000.00 for the single loan", first.OtherDeductions)
	}
}

// TestMinimumWageFlagIsIgnoredWhenTheRateExceedsTheMinimum covers F-29: a
// stale minimum-wage flag must not create a tax exemption for a high earner.
func TestMinimumWageFlagIsIgnoredWhenTheRateExceedsTheMinimum(t *testing.T) {
	cfg := semiMonthlyConfig()
	cfg.MinimumWage = money.FromPesos(640)

	slip, err := Compute(cfg, model.Employee{
		ID:            47,
		MonthlyBasic:  money.FromPesos(100000),
		DailyRate:     money.FromPesos(4597.70),
		PaymentType:   model.PaymentMonthly,
		IsMinimumWage: true,
		MinimumWage:   money.FromPesos(640),
		Benefits:      allBenefits(),
		Timesheets:    regularDayLines(1, 11),
	})
	if err != nil {
		t.Fatal(err)
	}

	if slip.MinimumWageExemption != 0 {
		t.Fatalf("minimum wage exemption = %s, want 0 for a rate above the minimum", slip.MinimumWageExemption)
	}

	found := false
	for _, warning := range slip.Warnings {
		if strings.Contains(warning, "minimum wage flag ignored") {
			found = true
			break
		}
	}

	if !found {
		t.Fatalf("expected a minimum-wage warning, got %v", slip.Warnings)
	}
}

// TestDeMinimisAnnualClothingCeiling covers F-37: the clothing ceiling is
// P6,000 per annum, so a semi-monthly period exempts P250.00 (6,000 / 24).
func TestDeMinimisAnnualClothingCeiling(t *testing.T) {
	cfg := semiMonthlyConfig()
	cfg.DeMinimis = []model.DeMinimisBenefit{
		{Code: "clothing", Ceiling: money.FromPesos(6000), Basis: model.DeMinimisAnnual},
	}

	slip, err := Compute(cfg, model.Employee{
		ID:           48,
		MonthlyBasic: money.FromPesos(26100),
		PaymentType:  model.PaymentMonthly,
		Benefits:     allBenefits(),
		Timesheets:   regularDayLines(1, 11),
		Allowances: []model.Earning{{
			Code: "clothing", Name: "Uniform and clothing allowance",
			Kind: model.KindAllowance, Amount: money.FromPesos(6000),
		}},
	})
	if err != nil {
		t.Fatal(err)
	}

	if slip.NonTaxable != money.FromPesos(250) {
		t.Fatalf("non-taxable clothing = %s, want 250.00", slip.NonTaxable)
	}
}

// TestDeMinimisSemesterMedicalCeiling covers F-37: the medical cash ceiling is
// P1,500 per semester, i.e. P250.00 per month or P125.00 per semi-monthly pay.
func TestDeMinimisSemesterMedicalCeiling(t *testing.T) {
	cfg := semiMonthlyConfig()
	cfg.DeMinimis = []model.DeMinimisBenefit{
		{Code: "medical", Ceiling: money.FromPesos(1500), Basis: model.DeMinimisSemester},
	}

	slip, err := Compute(cfg, model.Employee{
		ID:           49,
		MonthlyBasic: money.FromPesos(26100),
		PaymentType:  model.PaymentMonthly,
		Benefits:     allBenefits(),
		Timesheets:   regularDayLines(1, 11),
		Allowances: []model.Earning{{
			Code: "medical", Name: "Medical cash allowance to dependents",
			Kind: model.KindAllowance, Amount: money.FromPesos(500),
		}},
	})
	if err != nil {
		t.Fatal(err)
	}

	if slip.NonTaxable != money.FromPesos(125) {
		t.Fatalf("non-taxable medical = %s, want 125.00", slip.NonTaxable)
	}
}

// TestDeMinimisHourlyMealCeiling covers F-37: the overtime/night meal
// allowance is exempt up to 25% of the basic hourly rate. At P26,100 monthly
// the hourly rate is P150.00, so 2 OT hours exempt P75.00.
func TestDeMinimisHourlyMealCeiling(t *testing.T) {
	cfg := semiMonthlyConfig()
	cfg.DeMinimis = []model.DeMinimisBenefit{
		{Code: "meal", Ceiling: 0, Basis: model.DeMinimisHourlyOT},
	}

	lines := regularDayLines(1, 10)
	lines = append(lines, model.TimesheetLine{
		Date: "2026-01-20", DayType: rules.DayRegular, Overtime: 2,
	})

	slip, err := Compute(cfg, model.Employee{
		ID:           50,
		MonthlyBasic: money.FromPesos(26100),
		PaymentType:  model.PaymentMonthly,
		Benefits:     allBenefits(),
		Timesheets:   lines,
		Allowances: []model.Earning{{
			Code: "meal", Name: "Meal allowance",
			Kind: model.KindAllowance, Amount: money.FromPesos(500),
		}},
	})
	if err != nil {
		t.Fatal(err)
	}

	if slip.NonTaxable != money.FromPesos(75) {
		t.Fatalf("non-taxable meal = %s, want 75.00", slip.NonTaxable)
	}
}
