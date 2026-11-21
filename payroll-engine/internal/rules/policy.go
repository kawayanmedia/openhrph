package rules

import (
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/money"
)

// Policy carries the country payroll earning rules that are not statutory.
// The zero value is completed by DefaultPolicy.
type Policy struct {
	Configured                   bool               `json:"-"`
	DayTypeMultipliers           map[string]float64 `json:"day_type_multipliers"`
	OvertimeMultiplier           float64            `json:"overtime_multiplier"`
	PremiumDayOvertimeMultiplier float64            `json:"premium_day_overtime_multiplier"`
	NightDifferentialRate        float64            `json:"night_differential_rate"`
	NightWindowStart             string             `json:"night_window_start"`
	NightWindowEnd               string             `json:"night_window_end"`
	ThirteenthMonthExempt        money.Centavos     `json:"-"`
	ThirteenthMonthExemptRaw     int64              `json:"thirteenth_month_exempt_centavos"`
	MinimumWageTaxExempt         bool               `json:"minimum_wage_tax_exempt"`
	DaysPerYearDefault           int                `json:"days_per_year_default"`
	HoursPerDayDefault           int                `json:"hours_per_day_default"`
	SeparationMonthsPerYear      map[string]float64 `json:"separation_months_per_year"`
	SpecialRuns                  []string           `json:"special_runs"`
}

// DefaultPolicy returns the Philippine baseline so a payroll without a
// snapshot keeps its historical result.
func DefaultPolicy() Policy {
	return Policy{
		DayTypeMultipliers:           DayMultipliers(),
		OvertimeMultiplier:           OvertimeMultiplier,
		PremiumDayOvertimeMultiplier: PremiumDayOvertimeMultiplier,
		NightDifferentialRate:        NightDifferentialRate,
		NightWindowStart:             "22:00",
		NightWindowEnd:               "06:00",
		ThirteenthMonthExempt:        money.FromPesos(90000),
		ThirteenthMonthExemptRaw:     int64(money.FromPesos(90000)),
		MinimumWageTaxExempt:         true,
		DaysPerYearDefault:           261,
		HoursPerDayDefault:           8,
		SeparationMonthsPerYear: map[string]float64{
			"retrenchment": 1, "redundancy": 1, "closure": 1, "business_closure": 1,
			"authorised_cause": 1, "authorized_cause": 1, "illness": 0.5, "disease": 0.5, "sick": 0.5,
		},
		SpecialRuns: []string{"regular", "thirteenth_month", "final_pay", "annual_adjustment"},
	}
}

// Normalize fills any missing entries with the PH baseline.
func (p Policy) Normalize() Policy {
	if !p.Configured {
		return DefaultPolicy()
	}

	d := DefaultPolicy()

	if len(p.DayTypeMultipliers) == 0 {
		p.DayTypeMultipliers = d.DayTypeMultipliers
	}
	if p.OvertimeMultiplier == 0 {
		p.OvertimeMultiplier = d.OvertimeMultiplier
	}
	if p.PremiumDayOvertimeMultiplier == 0 {
		p.PremiumDayOvertimeMultiplier = d.PremiumDayOvertimeMultiplier
	}
	if p.NightWindowStart == "" {
		p.NightWindowStart = d.NightWindowStart
	}
	if p.NightWindowEnd == "" {
		p.NightWindowEnd = d.NightWindowEnd
	}
	if p.ThirteenthMonthExempt == 0 && p.ThirteenthMonthExemptRaw > 0 {
		p.ThirteenthMonthExempt = money.Centavos(p.ThirteenthMonthExemptRaw)
	}
	if p.DaysPerYearDefault == 0 {
		p.DaysPerYearDefault = d.DaysPerYearDefault
	}
	if p.HoursPerDayDefault == 0 {
		p.HoursPerDayDefault = d.HoursPerDayDefault
	}
	if len(p.SeparationMonthsPerYear) == 0 {
		p.SeparationMonthsPerYear = d.SeparationMonthsPerYear
	}
	if len(p.SpecialRuns) == 0 {
		p.SpecialRuns = d.SpecialRuns
	}

	return p
}

// DayMultiplier returns the premium for the given day type.
func (p Policy) DayMultiplier(dayType string) float64 {
	key := normalize(dayType)

	if value, ok := p.DayTypeMultipliers[key]; ok {
		return value
	}

	return 1
}

// OvertimeMultiplierFor returns the multiplier on top of the day rate.
func (p Policy) OvertimeMultiplierFor(dayRate float64) float64 {
	if dayRate > 1 {
		return p.PremiumDayOvertimeMultiplier
	}

	return p.OvertimeMultiplier
}
