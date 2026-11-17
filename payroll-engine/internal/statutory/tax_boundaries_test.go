package statutory

import (
	"testing"

	"github.com/kawayanmedia/openhrph-payroll-engine/internal/money"
)

// TestTRAINTaxBandEdges checks both ends of every band of the BIR withholding
// table (RR 11-2018, as amended by the TRAIN law).
//
// Expected values are computed from the published bracket definition
// (base tax + rate x excess over the bracket floor), not from the engine:
//
//	monthly, ₱33,332.99 -> 15% x (33,332.99 - 20,833.00) = 1,874.9985 -> 1,875.00
//	monthly, ₱66,666.99 -> 1,875.00 + 20% x (66,666.99 - 33,333.00) = 8,541.798 -> 8,541.80
//	monthly, ₱166,666.99 -> 8,541.80 + 25% x (166,666.99 - 66,667.00) = 33,541.7975 -> 33,541.80
//	monthly, ₱666,666.99 -> 33,541.80 + 30% x (666,666.99 - 166,667.00) = 183,541.797 -> 183,541.80
//	monthly, ₱1,000,000.00 -> 183,541.80 + 35% x (1,000,000 - 666,667) = 300,208.35
func TestTRAINTaxBandEdges(t *testing.T) {
	cases := []struct {
		frequency Frequency
		taxable   money.Centavos
		want      money.Centavos
	}{
		// Monthly table.
		{FrequencyMonthly, money.FromPesos(0), money.FromPesos(0)},
		{FrequencyMonthly, money.FromPesos(20832.99), money.FromPesos(0)},
		{FrequencyMonthly, money.FromPesos(20833), money.FromPesos(0)},
		{FrequencyMonthly, money.FromPesos(33332.99), money.FromPesos(1875)},
		{FrequencyMonthly, money.FromPesos(33333), money.FromPesos(1875)},
		{FrequencyMonthly, money.FromPesos(66666.99), money.FromPesos(8541.80)},
		{FrequencyMonthly, money.FromPesos(66667), money.FromPesos(8541.80)},
		{FrequencyMonthly, money.FromPesos(166666.99), money.FromPesos(33541.80)},
		{FrequencyMonthly, money.FromPesos(666666.99), money.FromPesos(183541.80)},
		{FrequencyMonthly, money.FromPesos(1000000), money.FromPesos(300208.35)},

		// Semi-monthly table.
		{FrequencySemiMonthly, money.FromPesos(10416.99), money.FromPesos(0)},
		{FrequencySemiMonthly, money.FromPesos(10417), money.FromPesos(0)},
		{FrequencySemiMonthly, money.FromPesos(16666.99), money.FromPesos(937.50)},
		{FrequencySemiMonthly, money.FromPesos(33332.99), money.FromPesos(4270.70)},
		{FrequencySemiMonthly, money.FromPesos(83332.99), money.FromPesos(16770.70)},
		{FrequencySemiMonthly, money.FromPesos(333332.99), money.FromPesos(91770.70)},
		{FrequencySemiMonthly, money.FromPesos(400000), money.FromPesos(115104.15)},
	}

	for _, tc := range cases {
		got, err := ComputeTax(tc.taxable, TRAINTaxBrackets(tc.frequency))
		if err != nil {
			t.Fatalf("%s tax(%s): %v", tc.frequency, tc.taxable, err)
		}

		if got != tc.want {
			t.Errorf("%s tax(%s) = %s, want %s", tc.frequency, tc.taxable, got, tc.want)
		}
	}
}

// TestTRAINTaxBandsAreContinuous proves the table has no jump or gap at the
// band boundaries: the tax on the last centavo of a band must equal the base
// tax of the next band.
func TestTRAINTaxBandsAreContinuous(t *testing.T) {
	for _, frequency := range []Frequency{FrequencyMonthly, FrequencySemiMonthly} {
		brackets := TRAINTaxBrackets(frequency)

		for i := 0; i < len(brackets)-1; i++ {
			upper, next := brackets[i], brackets[i+1]

			if upper.To == 0 {
				t.Fatalf("%s: band %d is open-ended before the last band", frequency, i)
			}

			if next.From != upper.To+1 {
				t.Errorf("%s: gap between band %d (%s) and %d (%s)",
					frequency, i, upper.To, i+1, next.From)
			}

			atEdge, err := ComputeTax(upper.To, brackets)
			if err != nil {
				t.Fatalf("%s: tax(%s): %v", frequency, upper.To, err)
			}

			nextBase, err := ComputeTax(next.From, brackets)
			if err != nil {
				t.Fatalf("%s: tax(%s): %v", frequency, next.From, err)
			}

			if atEdge != nextBase {
				t.Errorf("%s: tax drops or jumps at %s (edge %s, next band %s)",
					frequency, upper.To, atEdge, nextBase)
			}
		}
	}
}

// TestContributionCeilingEdges pins the floor and ceiling of the PhilHealth
// and Pag-IBIG schedules, where a one-centavo change in the salary must not
// change the contribution beyond the cap.
func TestContributionCeilingEdges(t *testing.T) {
	philHealth := DefaultPhilHealthConfig()

	atFloor := ComputePhilHealth(money.FromPesos(10000), philHealth)
	if atFloor.Base != money.FromPesos(10000) || atFloor.Employee != money.FromPesos(250) {
		t.Errorf("PhilHealth at the floor = %+v, want base 10000.00 and employee 250.00", atFloor)
	}

	belowFloor := ComputePhilHealth(money.FromPesos(5000), philHealth)
	if belowFloor.Premium != atFloor.Premium {
		t.Errorf("PhilHealth below the floor should use the floor premium, got %s want %s",
			belowFloor.Premium, atFloor.Premium)
	}

	atCeiling := ComputePhilHealth(money.FromPesos(100000), philHealth)
	aboveCeiling := ComputePhilHealth(money.FromPesos(500000), philHealth)

	if atCeiling.Employee != money.FromPesos(2500) {
		t.Errorf("PhilHealth at the ceiling employee = %s, want 2500.00", atCeiling.Employee)
	}

	if aboveCeiling.Premium != atCeiling.Premium {
		t.Errorf("PhilHealth above the ceiling premium = %s, want %s",
			aboveCeiling.Premium, atCeiling.Premium)
	}

	pagibig := DefaultPagIBIGConfig()

	// 1% applies up to and including the low threshold, 2% above it.
	if low := ComputePagIBIG(money.FromPesos(1500), pagibig); low.Employee != money.FromPesos(15) {
		t.Errorf("Pag-IBIG at the low threshold = %s, want 15.00", low.Employee)
	}

	if above := ComputePagIBIG(money.FromPesos(1500.01), pagibig); above.Employee != money.FromPesos(30) {
		t.Errorf("Pag-IBIG just above the low threshold = %s, want 30.00", above.Employee)
	}
}
