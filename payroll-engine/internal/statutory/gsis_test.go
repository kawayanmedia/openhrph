package statutory

import (
	"testing"

	"github.com/kawayanmedia/openhrph-payroll-engine/internal/money"
)

// Expected values follow RA 8291 Sec. 5(a): 9% personal and 12% government
// share of the monthly compensation, with no salary credit brackets. Amounts
// above the Maximum AMC limit are billed to the member at 2%.
func TestComputeGSIS(t *testing.T) {
	cases := []struct {
		name     string
		cfg      GSISConfig
		monthly  float64
		employee float64
		employer float64
	}{
		{"regular member", DefaultGSISConfig(), 40000, 3600.00, 4800.00},
		{"no salary ceiling", DefaultGSISConfig(), 100000, 9000.00, 12000.00},
		{"zero compensation", DefaultGSISConfig(), 0, 0, 0},
		{"negative compensation", DefaultGSISConfig(), -500, 0, 0},
		{
			"above the maximum AMC limit",
			GSISConfig{EmployeeRate: 0.09, EmployerRate: 0.12, MaxAMCLimit: money.FromPesos(20000), AboveLimitEmployeeRate: 0.02},
			30000, 2000.00, 3600.00,
		},
		{
			"at the maximum AMC limit",
			GSISConfig{EmployeeRate: 0.09, EmployerRate: 0.12, MaxAMCLimit: money.FromPesos(20000), AboveLimitEmployeeRate: 0.02},
			20000, 1800.00, 2400.00,
		},
		{
			"judiciary life insurance only",
			GSISConfig{EmployeeRate: 0.03, EmployerRate: 0.03, MaxAMCLimit: 0, AboveLimitEmployeeRate: 0.03},
			30000, 900.00, 900.00,
		},
	}

	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			result := ComputeGSIS(money.FromPesos(tc.monthly), tc.cfg)

			if got, want := result.Employee, money.FromPesos(tc.employee); got != want {
				t.Errorf("employee share = %s, want %s", got, want)
			}

			if got, want := result.Employer, money.FromPesos(tc.employer); got != want {
				t.Errorf("employer share = %s, want %s", got, want)
			}

			if got, want := result.Total(), result.Employee.Add(result.Employer); got != want {
				t.Errorf("total = %s, want %s", got, want)
			}
		})
	}
}
