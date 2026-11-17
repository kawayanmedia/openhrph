package rules

import (
	"testing"

	"github.com/kawayanmedia/openhrph-payroll-engine/internal/money"
)

func TestDayMultiplier(t *testing.T) {
	cases := map[string]float64{
		DayRegular:            1.00,
		DayRest:               1.30,
		DaySpecialHoliday:     1.30,
		DayRestSpecialHoliday: 1.50,
		DayRegularHoliday:     2.00,
		DayRestRegularHoliday: 2.60,
		"Regular Day":         1.00,
		"":                    1.00,
	}

	for dayType, want := range cases {
		if got := DayMultiplier(dayType); got != want {
			t.Fatalf("DayMultiplier(%q) = %v, want %v", dayType, got, want)
		}
	}
}

func TestDailyAndHourlyRate(t *testing.T) {
	monthly := money.FromPesos(26100)

	if got := DailyRate(monthly, 261); got != money.FromPesos(1200) {
		t.Fatalf("daily rate = %s, want 1200.00", got)
	}

	if got := HourlyRate(monthly, 261, 8); got != money.FromPesos(150) {
		t.Fatalf("hourly rate = %s, want 150.00", got)
	}
}

func TestLinePayPremiums(t *testing.T) {
	hourly := money.FromPesos(100)

	if got := LinePay(hourly, 8, DayRegular, false); got != money.FromPesos(800) {
		t.Fatalf("regular pay = %s", got)
	}

	if got := LinePay(hourly, 2, DayRegular, true); got != money.FromPesos(250) {
		t.Fatalf("overtime pay = %s", got)
	}

	if got := LinePay(hourly, 8, DayRegularHoliday, false); got != money.FromPesos(1600) {
		t.Fatalf("regular holiday pay = %s", got)
	}

	if got := LinePay(hourly, 1, DayRestSpecialHoliday, true); got != money.FromPesos(187.50) {
		t.Fatalf("rest+special OT pay = %s", got)
	}
}

func TestNightDifferential(t *testing.T) {
	hourly := money.FromPesos(100)

	if got := NightDifferential(hourly, 8, DayRegular); got != money.FromPesos(80) {
		t.Fatalf("night differential = %s", got)
	}

	if got := NightDifferential(hourly, 0, DayRegular); got != 0 {
		t.Fatalf("zero hours should pay nothing")
	}
}

func TestThirteenthMonth(t *testing.T) {
	if got := ThirteenthMonth(money.FromPesos(120000)); got != money.FromPesos(10000) {
		t.Fatalf("13th month = %s", got)
	}

	exempt, taxable := SplitThirteenthMonth(money.FromPesos(100000))
	if exempt != money.FromPesos(90000) || taxable != money.FromPesos(10000) {
		t.Fatalf("13th month split = %s / %s", exempt, taxable)
	}

	exempt, taxable = SplitThirteenthMonth(money.FromPesos(45000))
	if exempt != money.FromPesos(45000) || taxable != 0 {
		t.Fatalf("fully exempt split = %s / %s", exempt, taxable)
	}
}
