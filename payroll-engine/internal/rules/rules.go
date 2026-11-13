// Package rules implements payroll earning rules that are not statutory:
// day-type premiums, overtime, night differential, absence handling and
// 13th-month accrual.
package rules

import (
	"strings"

	"github.com/kawayanmedia/openhrph-payroll-engine/internal/money"
)

// Day type keys mirror the values stored on timesheets.
const (
	DayRegular                          = "regular"
	DayRest                             = "rest_day"
	DaySpecialHoliday                   = "special_holiday"
	DayRegularHoliday                   = "regular_holiday"
	DayRestSpecialHoliday               = "rest_day_special_holiday"
	DayRestRegularHoliday               = "rest_day_regular_holiday"
	DayDoubleHoliday                    = "double_holiday"
	DayRestDoubleHoliday                = "rest_day_double_holiday"
	DayRegularHolidaySpecialHoliday     = "regular_holiday_special_holiday"
	DayRestRegularHolidaySpecialHoliday = "rest_day_regular_holiday_special_holiday"
	DayPaidLeave                        = "paid_leave"
	DayUnpaidLeave                      = "unpaid_leave"
	DayAbsent                           = "absent"
	DayUndertime                        = "undertime"
)

// storedDayCodes maps the codes the timesheet screens write to timesheets.day_type
// (and rates_new.code) to the canonical keys above. Without this mapping every
// premium day falls through to the ordinary-day multiplier.
var storedDayCodes = map[string]string{
	"rd":       DayRest,
	"sh":       DaySpecialHoliday,
	"rh":       DayRegularHoliday,
	"rd_sh":    DayRestSpecialHoliday,
	"rd_rh":    DayRestRegularHoliday,
	"2rh":      DayDoubleHoliday,
	"rd_2rh":   DayRestDoubleHoliday,
	"rh_sh":    DayRegularHolidaySpecialHoliday,
	"rd_rh_sh": DayRestRegularHolidaySpecialHoliday,
	"pl":       DayPaidLeave,
	"upl":      DayUnpaidLeave,
}

// OvertimeMultiplier is the Labor Code overtime premium (125% of the day rate).
const OvertimeMultiplier = 1.25

// NightDifferentialRate is the 10% premium for work between 22:00 and 06:00.
const NightDifferentialRate = 0.10

// DayMultiplier returns the premium for work performed on the given day type.
func DayMultiplier(dayType string) float64 {
	switch normalize(dayType) {
	case DayRest:
		return 1.30
	case DaySpecialHoliday:
		return 1.30
	case DayRestSpecialHoliday:
		return 1.50
	case DayRegularHoliday:
		return 2.00
	case DayRestRegularHoliday:
		return 2.60
	case DayDoubleHoliday:
		return 3.00
	case DayRestDoubleHoliday:
		return 3.90
	case DayRegularHolidaySpecialHoliday:
		return 2.60
	case DayRestRegularHolidaySpecialHoliday:
		return 3.00
	default:
		return 1.00
	}
}

// IsHoliday reports whether the day type is a holiday (regular or special).
func IsHoliday(dayType string) bool {
	switch normalize(dayType) {
	case DaySpecialHoliday, DayRegularHoliday, DayRestSpecialHoliday, DayRestRegularHoliday,
		DayDoubleHoliday, DayRestDoubleHoliday, DayRegularHolidaySpecialHoliday,
		DayRestRegularHolidaySpecialHoliday:
		return true
	default:
		return false
	}
}

// IsRestDay reports whether the day type is a rest day without a holiday.
func IsRestDay(dayType string) bool {
	switch normalize(dayType) {
	case DayRest:
		return true
	default:
		return false
	}
}

// IsRegularHoliday reports whether the day type includes a regular holiday.
func IsRegularHoliday(dayType string) bool {
	switch normalize(dayType) {
	case DayRegularHoliday, DayRestRegularHoliday, DayDoubleHoliday,
		DayRestDoubleHoliday, DayRegularHolidaySpecialHoliday,
		DayRestRegularHolidaySpecialHoliday:
		return true
	default:
		return false
	}
}

// IsSpecialHoliday reports whether the day type includes a special holiday.
func IsSpecialHoliday(dayType string) bool {
	switch normalize(dayType) {
	case DaySpecialHoliday, DayRestSpecialHoliday, DayRegularHolidaySpecialHoliday,
		DayRestRegularHolidaySpecialHoliday:
		return true
	default:
		return false
	}
}

// PremiumPortion returns only the premium part of the day multiplier
// (e.g. 0.30 for a rest day). Monthly-paid employees already receive the
// ordinary day in their fixed period basic, so only this portion is paid on
// top of it.
func PremiumPortion(dayType string) float64 {
	return DayMultiplier(dayType) - 1
}

// IsPaidDay reports whether the day type earns pay without hours (paid leave).
func IsPaidDay(dayType string) bool { return normalize(dayType) == DayPaidLeave }

// IsUnpaidDay reports day types that never earn pay.
func IsUnpaidDay(dayType string) bool {
	switch normalize(dayType) {
	case DayUnpaidLeave, DayAbsent:
		return true
	default:
		return false
	}
}

func normalize(dayType string) string {
	key := strings.ToLower(strings.TrimSpace(dayType))
	key = strings.ReplaceAll(key, "-", "_")
	key = strings.ReplaceAll(key, " ", "_")

	if canonical, ok := storedDayCodes[key]; ok {
		return canonical
	}

	return key
}

// DailyRate derives the daily rate from a monthly salary.
func DailyRate(monthly money.Centavos, daysPerYear int) money.Centavos {
	if daysPerYear <= 0 {
		daysPerYear = 261
	}

	return monthly.Mul(12 / float64(daysPerYear))
}

// HourlyRate derives the hourly rate from a monthly salary.
func HourlyRate(monthly money.Centavos, daysPerYear, hoursPerDay int) money.Centavos {
	if hoursPerDay <= 0 {
		hoursPerDay = 8
	}

	return DailyRate(monthly, daysPerYear).Mul(1 / float64(hoursPerDay))
}

// LinePay computes the pay for a number of hours at a day-type premium,
// optionally with the overtime premium applied on top.
func LinePay(hourly money.Centavos, hours float64, dayType string, overtime bool) money.Centavos {
	if hours <= 0 {
		return 0
	}

	rate := DayMultiplier(dayType)
	if overtime {
		rate *= OvertimeMultiplier
	}

	return hourly.Mul(hours * rate)
}

// NightDifferential computes the 10% premium on night hours worked.
func NightDifferential(hourly money.Centavos, hours float64, dayType string) money.Centavos {
	if hours <= 0 {
		return 0
	}

	return hourly.Mul(hours * DayMultiplier(dayType) * NightDifferentialRate)
}

// AbsenceDeduction computes the pay lost for unpaid days.
func AbsenceDeduction(daily money.Centavos, days float64) money.Centavos {
	if days <= 0 {
		return 0
	}

	return daily.Mul(days)
}

// ThirteenthMonth returns 1/12 of the total basic salary earned in the year.
func ThirteenthMonth(totalBasicForYear money.Centavos) money.Centavos {
	return money.Centavos(roundHalfUp(float64(totalBasicForYear) / 12))
}

// ThirteenthMonthExemption is the tax-free ceiling for 13th-month pay and
// other benefits (₱90,000 under the TRAIN law).
const ThirteenthMonthExemption = money.Centavos(9000000)

// SplitThirteenthMonth separates the exempt and taxable portions of 13th-month
// pay and other benefits.
func SplitThirteenthMonth(amount money.Centavos) (exempt, taxable money.Centavos) {
	if amount <= ThirteenthMonthExemption {
		return amount, 0
	}

	return ThirteenthMonthExemption, amount.Sub(ThirteenthMonthExemption)
}

func roundHalfUp(v float64) int64 {
	if v < 0 {
		return -int64(-v + 0.5)
	}

	return int64(v + 0.5)
}
