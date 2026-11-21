package payroll

import (
	"testing"

	"github.com/kawayanmedia/openhrph-payroll-engine/internal/model"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/money"
)

// Labor Code Art. 298: authorized causes pay one month per year of service or
// one month, whichever is higher; a fraction of at least six months counts as
// one whole year.
func TestSeparationPayAuthorizedCause(t *testing.T) {
	cases := []struct {
		name       string
		hired      string
		separated  string
		reason     string
		want       money.Centavos
	}{
		{"four years two months", "2016-01-01", "2020-03-01", "redundancy", money.FromPesos(120000)},
		{"four years seven months rounds up", "2016-01-01", "2020-08-01", "retrenchment", money.FromPesos(150000)},
		{"under six months gets one month", "2020-01-01", "2020-06-01", "closure", money.FromPesos(30000)},
		{"illness is half a month per year", "2016-01-01", "2020-08-01", "illness", money.FromPesos(75000)},
		{"just cause pays nothing", "2016-01-01", "2020-08-01", "just_cause", money.Zero},
	}

	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			cfg := semiMonthlyConfig()
			cfg.RunType = model.RunFinalPay
			cfg.PayWithoutTimesheet = false
			cfg.Meta = map[string]string{"separation_reason": tc.reason}

			slip, err := Compute(cfg, model.Employee{
				ID:           1,
				MonthlyBasic: money.FromPesos(30000),
				Benefits:     allBenefits(),
				HiredOn:      tc.hired,
				SeparatedOn:  tc.separated,
			})
			if err != nil {
				t.Fatal(err)
			}

			if slip.SeparationPay != tc.want {
				t.Fatalf("separation pay = %s, want %s", slip.SeparationPay, tc.want)
			}
		})
	}
}

// RA 7641: 22.5 days per year of service, minimum five years.
func TestRetirementPay(t *testing.T) {
	cfg := semiMonthlyConfig()
	cfg.RunType = model.RunFinalPay
	cfg.PayWithoutTimesheet = false
	cfg.Meta = map[string]string{"separation_reason": "retirement"}

	employee := model.Employee{
		ID:           1,
		MonthlyBasic: money.FromPesos(26100), // PKR 1,200 daily
		Benefits:     allBenefits(),
		HiredOn:      "2016-01-01",
		SeparatedOn:  "2026-01-01",
	}

	slip, err := Compute(cfg, employee)
	if err != nil {
		t.Fatal(err)
	}

	// 10 years x 22.5 days x PKR 1,200 = PKR 270,000.
	if slip.SeparationPay != money.FromPesos(270000) {
		t.Fatalf("retirement pay = %s, want 270000.00", slip.SeparationPay)
	}

	employee.SeparatedOn = "2020-01-01" // four years

	slip, err = Compute(cfg, employee)
	if err != nil {
		t.Fatal(err)
	}

	if slip.SeparationPay != money.Zero {
		t.Fatalf("retirement pay = %s, want 0 before five years", slip.SeparationPay)
	}
}

// RR 29-2025: up to 12 days of monetized unused VL for a private employee is
// non-taxable; the excess is taxable.
func TestFinalPayLeaveConversionTaxSplit(t *testing.T) {
	cfg := semiMonthlyConfig()
	cfg.RunType = model.RunFinalPay
	cfg.PayWithoutTimesheet = false
	cfg.Meta = map[string]string{
		"separation_reason": "just_cause",
		"leave_days":        "15",
	}

	slip, err := Compute(cfg, model.Employee{
		ID:           1,
		PaymentType:  model.PaymentMonthly,
		MonthlyBasic: money.FromPesos(26100), // PKR 1,200 daily
		Benefits:     allBenefits(),
		HiredOn:      "2016-01-01",
		SeparatedOn:  "2026-01-01",
	})
	if err != nil {
		t.Fatal(err)
	}

	if slip.LeaveConversion != money.FromPesos(18000) {
		t.Fatalf("leave conversion = %s, want 18000.00", slip.LeaveConversion)
	}

	// 12 days exempt = 14,400; 3 days taxable = 3,600.
	if slip.NonTaxable != money.FromPesos(14400) {
		t.Fatalf("non-taxable = %s, want 14400.00", slip.NonTaxable)
	}

	if slip.TaxableIncome != money.FromPesos(3600) {
		t.Fatalf("taxable income = %s, want 3600.00", slip.TaxableIncome)
	}
}
