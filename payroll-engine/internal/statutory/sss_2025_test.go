package statutory

import (
	"testing"

	"github.com/kawayanmedia/openhrph-payroll-engine/internal/money"
)

// Expected values are transcribed from SSS Circular No. 2024-006 ("Schedule of
// SSS Contributions effective January 2025"), employer/employee table. Each
// case lists the monthly compensation, the salary credit the circular assigns
// to it, and the monthly employee/employer/EC amounts printed on that row.
func TestSSS2025ScheduleMatchesCircular(t *testing.T) {
	schedule := SSS2025Schedule()

	cases := []struct {
		salary float64
		msc    float64
		ee     float64
		er     float64
		ec     float64
	}{
		{1000, 5000, 250.00, 500.00, 10.00},
		{5000, 5000, 250.00, 500.00, 10.00},
		{5249.99, 5000, 250.00, 500.00, 10.00},
		{5250, 5500, 275.00, 550.00, 10.00},
		{5300, 5500, 275.00, 550.00, 10.00},
		{7000, 7000, 350.00, 700.00, 10.00},
		{14500, 14500, 725.00, 1450.00, 10.00},
		{14750, 15000, 750.00, 1500.00, 30.00},
		{20000, 20000, 1000.00, 2000.00, 30.00},
		{20300, 20500, 1025.00, 2050.00, 30.00},
		{30000, 30000, 1500.00, 3000.00, 30.00},
		{34750, 35000, 1750.00, 3500.00, 30.00},
		{100000, 35000, 1750.00, 3500.00, 30.00},
	}

	for _, tc := range cases {
		got, err := ComputeSSS(money.FromPesos(tc.salary), schedule)
		if err != nil {
			t.Fatalf("salary %v: %v", tc.salary, err)
		}

		if got.MSC != money.FromPesos(tc.msc) {
			t.Errorf("salary %v: MSC = %s, want %v", tc.salary, got.MSC, tc.msc)
		}

		if got.EmployeeShare() != money.FromPesos(tc.ee) {
			t.Errorf("salary %v: employee share = %s, want %v", tc.salary, got.EmployeeShare(), tc.ee)
		}

		if got.ER.Add(got.WISPER) != money.FromPesos(tc.er) {
			t.Errorf("salary %v: employer share = %s, want %v", tc.salary, got.ER.Add(got.WISPER), tc.er)
		}

		if got.EC != money.FromPesos(tc.ec) {
			t.Errorf("salary %v: EC = %s, want %v", tc.salary, got.EC, tc.ec)
		}
	}
}

func TestSSS2025WISPSplit(t *testing.T) {
	schedule := SSS2025Schedule()

	// ₱22,500 compensation: MSC ₱22,500 = ₱20,000 regular + ₱2,500 MPF.
	got, err := ComputeSSS(money.FromPesos(22500), schedule)
	if err != nil {
		t.Fatal(err)
	}

	if got.EE != money.FromPesos(1000) || got.WISPEE != money.FromPesos(125) {
		t.Fatalf("employee split = %s regular / %s WISP", got.EE, got.WISPEE)
	}

	if got.ER != money.FromPesos(2000) || got.WISPER != money.FromPesos(250) {
		t.Fatalf("employer split = %s regular / %s WISP", got.ER, got.WISPER)
	}

	// Total remittance for the row is ₱3,405.00.
	if got.Total() != money.FromPesos(3405) {
		t.Fatalf("total = %s, want 3405.00", got.Total())
	}
}

func TestSSS2025ScheduleCoversWholeRange(t *testing.T) {
	schedule := SSS2025Schedule()

	if first := schedule[0]; first.MSCFrom != 0 || first.MSCTo != money.FromPesos(5249.99) {
		t.Fatalf("first band = %s..%s, want 0..5249.99", first.MSCFrom, first.MSCTo)
	}

	last := schedule[len(schedule)-1]
	if last.MSCFrom != money.FromPesos(34750) || last.MSCTo != 0 || last.MSC != money.FromPesos(35000) {
		t.Fatalf("last band = %+v", last)
	}

	for i := 1; i < len(schedule); i++ {
		if schedule[i].MSCFrom != schedule[i-1].MSCTo+1 {
			t.Fatalf("gap between band %d (%s) and %d (%s)",
				i-1, schedule[i-1].MSCTo, i, schedule[i].MSCFrom)
		}
	}
}
