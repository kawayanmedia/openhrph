package statutory

import (
	"testing"

	"github.com/kawayanmedia/openhrph-payroll-engine/internal/money"
)

func TestSSS2025ScheduleBands(t *testing.T) {
	schedule := SSS2025Schedule()

	// SSS Circular No. 2024-006 lists 61 rows: MSC ₱5,000, then ₱5,500 to
	// ₱34,500 in ₱500 steps, then ₱35,000.
	if len(schedule) != 61 {
		t.Fatalf("expected 61 bands, got %d", len(schedule))
	}

	// Below the floor is billed at ₱5,000: 5% EE, 10% ER, ₱10 EC.
	low, err := ComputeSSS(money.FromPesos(3000), schedule)
	if err != nil {
		t.Fatal(err)
	}

	if low.MSC != money.FromPesos(5000) || low.EE != money.FromPesos(250) || low.ER != money.FromPesos(500) || low.EC != money.FromPesos(10) {
		t.Fatalf("floor band wrong: %+v", low)
	}

	// ₱20,000 salary credit: no WISP yet.
	mid, err := ComputeSSS(money.FromPesos(20000), schedule)
	if err != nil {
		t.Fatal(err)
	}

	if mid.EE != money.FromPesos(1000) || mid.ER != money.FromPesos(2000) || mid.WISPEE != 0 {
		t.Fatalf("₱20,000 band wrong: %+v", mid)
	}

	// ₱30,000 salary credit: regular ₱20,000 + ₱10,000 WISP.
	high, err := ComputeSSS(money.FromPesos(30000), schedule)
	if err != nil {
		t.Fatal(err)
	}

	if high.EE != money.FromPesos(1000) || high.WISPEE != money.FromPesos(500) {
		t.Fatalf("WISP wrong: %+v", high)
	}

	if high.EmployeeShare() != money.FromPesos(1500) {
		t.Fatalf("employee share = %s, want 1500.00", high.EmployeeShare())
	}

	// Above the ceiling the credit stays at ₱35,000.
	top, err := ComputeSSS(money.FromPesos(90000), schedule)
	if err != nil {
		t.Fatal(err)
	}

	if top.MSC != money.FromPesos(35000) || top.EmployeeShare() != money.FromPesos(1750) {
		t.Fatalf("ceiling wrong: %+v", top)
	}
}

func TestPhilHealthSplitAndLimits(t *testing.T) {
	cfg := DefaultPhilHealthConfig()

	low := ComputePhilHealth(money.FromPesos(5000), cfg)
	if low.Premium != money.FromPesos(500) || low.Employee != money.FromPesos(250) || low.Employer != money.FromPesos(250) {
		t.Fatalf("floor premium wrong: %+v", low)
	}

	mid := ComputePhilHealth(money.FromPesos(30000), cfg)
	if mid.Premium != money.FromPesos(1500) || mid.Employee != money.FromPesos(750) {
		t.Fatalf("mid premium wrong: %+v", mid)
	}

	high := ComputePhilHealth(money.FromPesos(250000), cfg)
	if high.Premium != money.FromPesos(5000) || high.Employee != money.FromPesos(2500) {
		t.Fatalf("ceiling premium wrong: %+v", high)
	}
}

func TestPagIBIGCap(t *testing.T) {
	cfg := DefaultPagIBIGConfig()

	low := ComputePagIBIG(money.FromPesos(1000), cfg)
	if low.Employee != money.FromPesos(10) {
		t.Fatalf("low salary should use 1%%: %+v", low)
	}

	mid := ComputePagIBIG(money.FromPesos(5000), cfg)
	if mid.Employee != money.FromPesos(100) || mid.Employer != money.FromPesos(100) {
		t.Fatalf("2%% wrong: %+v", mid)
	}

	high := ComputePagIBIG(money.FromPesos(25000), cfg)
	if high.Base != money.FromPesos(10000) || high.Employee != money.FromPesos(200) || high.Employer != money.FromPesos(200) {
		t.Fatalf("fund salary cap wrong: %+v", high)
	}
}

func TestTRAINTaxBrackets(t *testing.T) {
	semi := TRAINTaxBrackets(FrequencySemiMonthly)

	cases := []struct {
		taxable money.Centavos
		want    money.Centavos
	}{
		{money.FromPesos(8000), 0},
		{money.FromPesos(10417), 0},
		{money.FromPesos(16667), money.FromPesos(937.50)},
		{money.FromPesos(33333), money.FromPesos(4270.70)},
		{money.FromPesos(83333), money.FromPesos(16770.70)},
		{money.FromPesos(333333), money.FromPesos(91770.70)},
	}

	for _, tc := range cases {
		got, err := ComputeTax(tc.taxable, semi)
		if err != nil {
			t.Fatalf("ComputeTax(%s): %v", tc.taxable, err)
		}

		if got != tc.want {
			t.Fatalf("tax(%s) = %s, want %s", tc.taxable, got, tc.want)
		}
	}

	monthly := TRAINTaxBrackets(FrequencyMonthly)
	got, err := ComputeTax(money.FromPesos(20833), monthly)
	if err != nil || got != 0 {
		t.Fatalf("monthly exemption: %v %s", err, got)
	}
}

func TestDivisor(t *testing.T) {
	if d := Divisor(ScheduleEveryPay, FrequencySemiMonthly, "first_pay"); d != 2 {
		t.Fatalf("every pay semi-monthly divisor = %d", d)
	}

	if d := Divisor(ScheduleEveryPay, FrequencyMonthly, "first_pay"); d != 1 {
		t.Fatalf("monthly divisor = %d", d)
	}

	if d := Divisor(ScheduleFirstPay, FrequencySemiMonthly, "first_pay"); d != 1 {
		t.Fatalf("first pay divisor = %d", d)
	}

	if d := Divisor(ScheduleFirstPay, FrequencySemiMonthly, "last_pay"); d != 0 {
		t.Fatalf("non-matching cut-off divisor = %d", d)
	}
}
