package rules

import "testing"

// The timesheets table stores the short day-type codes used by the rate table
// (rates_new): regular, rd, sh, rh, rd-sh, rd-rh, 2rh, rd-2rh, rh-sh,
// rd-rh-sh, pl, upl. Premiums below follow DOLE Handbook on Workers'
// Statutory Benefits (2023), guide computation table on pages 24-25.
func TestDayMultiplierForStoredCodes(t *testing.T) {
	cases := map[string]float64{
		"regular":  1.00,
		"rd":       1.30, // rest day work: 130%
		"sh":       1.30, // special non-working day work: 130%
		"rh":       2.00, // regular holiday work: 200%
		"rd-sh":    1.50, // special day falling on a rest day: 150%
		"rd-rh":    2.60, // regular holiday falling on a rest day: 260%
		"2rh":      3.00, // double regular holiday: 300%
		"rd-2rh":   3.90, // double regular holiday on a rest day: 390%
		"rh-sh":    2.60, // regular holiday and special day: 260%
		"rd-rh-sh": 3.00, // regular + special holiday on a rest day: 300%
	}

	for dayType, want := range cases {
		if got := DayMultiplier(dayType); got != want {
			t.Errorf("DayMultiplier(%q) = %v, want %v", dayType, got, want)
		}
	}
}

func TestStoredCodeClassification(t *testing.T) {
	holidays := []string{"sh", "rh", "rd-sh", "rd-rh", "2rh", "rd-2rh", "rh-sh", "rd-rh-sh"}
	for _, dayType := range holidays {
		if !IsHoliday(dayType) {
			t.Errorf("IsHoliday(%q) = false, want true", dayType)
		}
	}

	if IsHoliday("rd") || IsHoliday("regular") {
		t.Error("rest day and ordinary day must not be classified as holidays")
	}

	if !IsRestDay("rd") {
		t.Error(`IsRestDay("rd") = false, want true`)
	}

	if !IsPaidDay("pl") {
		t.Error(`IsPaidDay("pl") = false, want true`)
	}

	if !IsUnpaidDay("upl") {
		t.Error(`IsUnpaidDay("upl") = false, want true`)
	}
}
