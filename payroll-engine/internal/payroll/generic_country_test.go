package payroll

import (
	"testing"

	"github.com/kawayanmedia/openhrph-payroll-engine/internal/model"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/money"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/rules"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/statutory"
)

func genericCountryConfig() model.Config {
	cfg := semiMonthlyConfig()
	cfg.Cutoff = "last_pay"
	cfg.StatutoryDriver = "generic"
	cfg.CountryCode = "XX"
	cfg.MinimumWage = 0
	cfg.Components = []statutory.GenericComponent{
		{
			Code:                 "social",
			Name:                 "Social insurance",
			Category:             "contribution",
			AppliesTo:            "both",
			Calculation:          "percentage",
			Base:                 "monthly_basic",
			EmployeeRate:         0.05,
			EmployerRate:         0.05,
			ReducesTaxableIncome: true,
			Schedule:             statutory.ScheduleEveryPay,
			SortOrder:            10,
		},
		{
			Code:        "income_tax",
			Name:        "Income tax",
			Category:    "tax",
			AppliesTo:   "employee",
			Calculation: "bracket",
			Base:        "taxable",
			Schedule:    statutory.ScheduleEveryPay,
			SortOrder:   900,
			Bands: []statutory.GenericBand{
				{From: 0, To: money.FromPesos(9999.99)},
				{From: money.FromPesos(10000), EmployeeRate: 0.10},
			},
		},
	}

	return cfg
}

func TestComputeGenericCountryFromComponents(t *testing.T) {
	cfg := genericCountryConfig()

	employee := model.Employee{
		ID:           501,
		Number:       "E-0501",
		Name:         "Generic Employee",
		MonthlyBasic: money.FromPesos(30000),
		PaymentType:  model.PaymentMonthly,
		Benefits:     allBenefits(),
	}

	for day := 1; day <= 15; day++ {
		employee.Timesheets = append(employee.Timesheets, model.TimesheetLine{
			Date:    "2026-03-" + twoDigits(day),
			DayType: rules.DayRegular,
			Hours:   8,
		})
	}

	slip, err := Compute(cfg, employee)
	if err != nil {
		t.Fatal(err)
	}

	if slip.Gross != money.FromPesos(15000) {
		t.Fatalf("gross = %s, want 15000.00", slip.Gross)
	}

	// 5% of the ₱30,000 monthly basic, divided across two cut-offs.
	if len(slip.DeductionLines) != 2 {
		t.Fatalf("deduction lines = %d, want 2", len(slip.DeductionLines))
	}

	if slip.DeductionLines[0].Code != "social" || slip.DeductionLines[0].Employee != money.FromPesos(750) {
		t.Fatalf("social line = %+v", slip.DeductionLines[0])
	}

	if slip.WithholdingTax != money.FromPesos(425) {
		t.Fatalf("tax = %s, want 425.00", slip.WithholdingTax)
	}

	if slip.TotalDeductions != money.FromPesos(1175) {
		t.Fatalf("deductions = %s, want 1175.00", slip.TotalDeductions)
	}

	if slip.NetPay != money.FromPesos(13825) {
		t.Fatalf("net = %s, want 13825.00", slip.NetPay)
	}

	if slip.TotalEmployerCost != money.FromPesos(750) {
		t.Fatalf("employer cost = %s, want 750.00", slip.TotalEmployerCost)
	}
}

func twoDigits(value int) string {
	if value < 10 {
		return "0" + string(rune('0'+value))
	}

	return string(rune('0'+value/10)) + string(rune('0'+value%10))
}
