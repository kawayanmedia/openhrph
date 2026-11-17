package statutory

import (
	"testing"

	"github.com/kawayanmedia/openhrph-payroll-engine/internal/money"
)

func TestComputeGenericPercentageFloorAndCap(t *testing.T) {
	component := GenericComponent{
		Calculation:          "percentage",
		EmployeeRate:         0.05,
		EmployerRate:         0.05,
		MinBase:              money.FromPesos(10000),
		MaxBase:              money.FromPesos(20000),
		MaxEmployee:          money.FromPesos(900),
		MaxEmployer:          money.FromPesos(900),
		AppliesTo:            "both",
		ReducesTaxableIncome: true,
	}

	below := ComputeGeneric(component, money.FromPesos(5000))
	above := ComputeGeneric(component, money.FromPesos(50000))

	if below.Employee != money.FromPesos(500) {
		t.Fatalf("floor employee = %s, want 500.00", below.Employee)
	}

	if above.Employee != money.FromPesos(900) || above.Employer != money.FromPesos(900) {
		t.Fatalf("cap = %s/%s, want 900.00/900.00", above.Employee, above.Employer)
	}
}

func TestComputeGenericBracketAddsRateOnExcess(t *testing.T) {
	component := GenericComponent{
		Calculation: "bracket",
		AppliesTo:   "employee",
		Bands: []GenericBand{
			{From: 0, To: money.FromPesos(9999.99)},
			{From: money.FromPesos(10000), EmployeeRate: 0.10},
		},
	}

	result := ComputeGeneric(component, money.FromPesos(14250))

	if result.Employee != money.FromPesos(425) {
		t.Fatalf("tax = %s, want 425.00", result.Employee)
	}

	if result.Employer != 0 {
		t.Fatalf("employer = %s, want 0", result.Employer)
	}
}

func TestComputeGenericFixedAndPerPeriod(t *testing.T) {
	result := ComputeGeneric(GenericComponent{
		Calculation:    "fixed",
		AppliesTo:      "both",
		EmployeeAmount: money.FromPesos(100),
		EmployerAmount: money.FromPesos(200),
	}, 0)

	split := PerPeriod(result, 2)

	if split.Employee != money.FromPesos(50) || split.Employer != money.FromPesos(100) {
		t.Fatalf("fixed split = %s/%s, want 50.00/100.00", split.Employee, split.Employer)
	}
}
