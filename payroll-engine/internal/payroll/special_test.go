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
