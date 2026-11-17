package payroll

import (
	"testing"

	"github.com/kawayanmedia/openhrph-payroll-engine/internal/model"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/money"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/rules"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/statutory"
)

func allBenefits() model.BenefitFlags {
	return model.BenefitFlags{
		RestDayPay:                      true,
		RegularHolidayPay:               true,
		SpecialHolidayPay:               true,
		PremiumHolidayPay:               true,
		RegularHolidaySpecialHolidayPay: true,
		OvertimePay:                     true,
		NightDifferential:               true,
		DeMinimis:                       true,
	}
}

func semiMonthlyConfig() model.Config {
	return model.Config{
		Frequency:                  statutory.FrequencySemiMonthly,
		Cutoff:                     "first_pay",
		DaysPerYear:                261,
		HoursPerDay:                8,
		SSSSchedule:                statutory.ScheduleEveryPay,
		PhilHealthSchedule:         statutory.ScheduleEveryPay,
		PagIBIGSchedule:            statutory.ScheduleEveryPay,
		TaxSchedule:                statutory.ScheduleEveryPay,
		SSSBrackets:                statutory.SSS2025Schedule(),
		PhilHealth:                 statutory.DefaultPhilHealthConfig(),
		PagIBIG:                    statutory.DefaultPagIBIGConfig(),
		TaxBrackets:                statutory.TRAINTaxBrackets(statutory.FrequencySemiMonthly),
		AnnualTaxBrackets:          statutory.TRAINTaxBrackets(statutory.FrequencyMonthly),
		PayUnworkedRegularHolidays: true,
	}
}

func TestComputeSemiMonthlyWithOvertimeAndNight(t *testing.T) {
	cfg := semiMonthlyConfig()
	cfg.MinimumWage = 0

	employee := model.Employee{
		ID:           7,
		Number:       "E-0007",
		Name:         "Juan Dela Cruz",
		MonthlyBasic: money.FromPesos(30000),
		Benefits:     allBenefits(),
	}

	// Eleven regular days of 8 hours, two overtime hours and two night hours.
	for day := 0; day < 11; day++ {
		employee.Timesheets = append(employee.Timesheets, model.TimesheetLine{
			Date:    "2026-01-0" + string(rune('1'+day%9)),
			DayType: rules.DayRegular,
			Hours:   8,
		})
	}

	employee.Timesheets = append(employee.Timesheets, model.TimesheetLine{
		Date:     "2026-01-20",
		DayType:  rules.DayRegular,
		Overtime: 2,
	})

	employee.Timesheets = append(employee.Timesheets, model.TimesheetLine{
		Date:    "2026-01-21",
		DayType: rules.DayRegular,
		Night:   2,
	})

	slip, err := Compute(cfg, employee)
	if err != nil {
		t.Fatal(err)
	}

	if slip.RegularHours != 88 {
		t.Fatalf("regular hours = %v, want 88", slip.RegularHours)
	}

	if slip.RegularPay != money.FromPesos(15172.08) {
		t.Fatalf("regular pay = %s, want 15172.08", slip.RegularPay)
	}

	if slip.OvertimePay != money.FromPesos(431.03) {
		t.Fatalf("overtime pay = %s, want 431.03", slip.OvertimePay)
	}

	if slip.NightPay != money.FromPesos(34.48) {
		t.Fatalf("night pay = %s, want 34.48", slip.NightPay)
	}

	if slip.Gross != money.FromPesos(15637.59) {
		t.Fatalf("gross = %s, want 15637.59", slip.Gross)
	}

	// SSS ₱30,000 MSC: ₱1,000 regular + ₱500 WISP employee share, halved.
	if slip.SSSEmployee != money.FromPesos(500) || slip.SSSProvident != money.FromPesos(250) {
		t.Fatalf("sss split = %s / %s", slip.SSSEmployee, slip.SSSProvident)
	}

	// PhilHealth: 5% of ₱30,000 = ₱1,500 premium, ₱750 employee share, halved.
	if slip.PhilHealthEmployee != money.FromPesos(375) || slip.PhilHealthEmployer != money.FromPesos(375) {
		t.Fatalf("philhealth split = %s / %s", slip.PhilHealthEmployee, slip.PhilHealthEmployer)
	}

	// Pag-IBIG: 2% of the ₱10,000 cap = ₱200 employee share, halved.
	if slip.PagIBIGEmployee != money.FromPesos(100) {
		t.Fatalf("pagibig employee = %s, want 100.00", slip.PagIBIGEmployee)
	}

	if slip.TaxableIncome != money.FromPesos(14412.59) {
		t.Fatalf("taxable income = %s, want 14412.59", slip.TaxableIncome)
	}

	if slip.WithholdingTax != money.FromPesos(599.34) {
		t.Fatalf("withholding tax = %s, want 599.34", slip.WithholdingTax)
	}

	if slip.NetPay != money.FromPesos(13813.25) {
		t.Fatalf("net pay = %s, want 13813.25", slip.NetPay)
	}

	if slip.TotalEmployerCost == 0 {
		t.Fatal("employer cost should include SSS/PhilHealth/Pag-IBIG employer shares")
	}
}

func TestComputeWithoutTimesheet(t *testing.T) {
	cfg := semiMonthlyConfig()

	slip, err := Compute(cfg, model.Employee{
		ID:           8,
		MonthlyBasic: money.FromPesos(20000),
	})
	if err != nil {
		t.Fatal(err)
	}

	if slip.RegularPay != 0 || slip.NetPay != 0 {
		t.Fatalf("expected no pay without attendance, got %s / %s", slip.RegularPay, slip.NetPay)
	}

	if len(slip.Warnings) == 0 {
		t.Fatal("expected a warning about missing attendance")
	}

	cfg.PayWithoutTimesheet = true
	slip, err = Compute(cfg, model.Employee{ID: 8, MonthlyBasic: money.FromPesos(20000)})
	if err != nil {
		t.Fatal(err)
	}

	if slip.RegularPay != money.FromPesos(10000) {
		t.Fatalf("basic half-month pay = %s, want 10000.00", slip.RegularPay)
	}
}

func TestComputeAbsenceAndPaidLeave(t *testing.T) {
	cfg := semiMonthlyConfig()

	slip, err := Compute(cfg, model.Employee{
		ID:           9,
		MonthlyBasic: money.FromPesos(26100),
		Timesheets: []model.TimesheetLine{
			{Date: "2026-01-01", DayType: rules.DayRegular, Hours: 8},
			{Date: "2026-01-02", DayType: rules.DayRegular, Absent: true},
			{Date: "2026-01-03", DayType: rules.DayPaidLeave},
		},
	})
	if err != nil {
		t.Fatal(err)
	}

	if slip.AbsentDays != 1 {
		t.Fatalf("absent days = %v, want 1", slip.AbsentDays)
	}

	// daily rate 1200: one worked day + one paid leave day. The absent day was
	// never paid, so it must not be deducted a second time from the earnings.
	if slip.RegularPay != money.FromPesos(2400) {
		t.Fatalf("regular pay = %s, want 2400.00", slip.RegularPay)
	}

	if slip.Gross != money.FromPesos(2400) {
		t.Fatalf("gross = %s, want 2400.00", slip.Gross)
	}

	// The lost wage is still reported for payslip and register transparency.
	if slip.AbsentAmount != money.FromPesos(1200) {
		t.Fatalf("absence amount = %s, want 1200.00", slip.AbsentAmount)
	}
}

func TestComputePremiumsForStoredDayCodes(t *testing.T) {
	cfg := semiMonthlyConfig()

	// ₱26,100 monthly = ₱1,200 daily / ₱150 hourly.
	slip, err := Compute(cfg, model.Employee{
		ID:           11,
		MonthlyBasic: money.FromPesos(26100),
		Benefits:     allBenefits(),
		Timesheets: []model.TimesheetLine{
			{Date: "2026-01-05", DayType: "regular", Hours: 8},
			{Date: "2026-01-10", DayType: "rd", Hours: 8},
			{Date: "2026-01-12", DayType: "rh", Hours: 8},
			{Date: "2026-01-17", DayType: "rd", Overtime: 2},
			{Date: "2026-01-24", DayType: "sh", Hours: 8},
		},
	})
	if err != nil {
		t.Fatal(err)
	}

	// Ordinary 1,200 + rest day 1,560 + regular holiday 2,400 + special 1,560.
	if slip.RegularPay != money.FromPesos(6720) {
		t.Fatalf("regular pay = %s, want 6720.00", slip.RegularPay)
	}

	// Rest-day overtime: 150 x 2 x 130% x 125% = 487.50.
	if slip.OvertimePay != money.FromPesos(487.50) {
		t.Fatalf("overtime pay = %s, want 487.50", slip.OvertimePay)
	}

	// Premium components only: rest day 30% = 360, holiday premium
	// (100% regular + 30% special) = 1,560.
	if slip.RestDayPay != money.FromPesos(360) {
		t.Fatalf("rest day pay = %s, want 360.00", slip.RestDayPay)
	}

	if slip.HolidayPay != money.FromPesos(1560) {
		t.Fatalf("holiday pay = %s, want 1560.00", slip.HolidayPay)
	}
}

func TestStatutoryScheduleRespectsCutoff(t *testing.T) {
	cfg := semiMonthlyConfig()
	cfg.SSSSchedule = statutory.ScheduleFirstPay

	// On the last pay the SSS deduction must not be taken.
	cfg.Cutoff = "last_pay"

	slip, err := Compute(cfg, model.Employee{
		ID:           10,
		MonthlyBasic: money.FromPesos(30000),
		Timesheets:   []model.TimesheetLine{{DayType: rules.DayRegular, Hours: 8}},
	})
	if err != nil {
		t.Fatal(err)
	}

	if slip.SSSEmployee != 0 {
		t.Fatalf("sss should not apply on last pay, got %s", slip.SSSEmployee)
	}
}
