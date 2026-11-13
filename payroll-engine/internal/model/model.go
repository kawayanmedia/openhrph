// Package model holds the data the payroll engine works with.
package model

import (
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/money"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/statutory"
)

// Payment types used by employees.payment_type.
const (
	PaymentMonthly = "monthly"
	PaymentDaily   = "daily"
)

// Payroll run types stored on payrolls.type.
const (
	RunRegular          = "regular"
	RunThirteenthMonth  = "thirteenth_month"
	RunFinalPay         = "final_pay"
	RunAnnualAdjustment = "annual_adjustment"
)

// Earning kinds.
const (
	KindAllowance    = "allowance"
	KindCompensation = "compensation"
	KindBonus        = "bonus"
	KindCommission   = "commission"
	KindDeduction    = "deduction"
	KindLoan         = "loan"
)

// TimesheetLine is one attendance day for an employee.
//
// The hour buckets are disjoint: Hours are ordinary hours, Overtime is hours
// paid at the overtime premium (including night-OT hours) and Night is night
// hours that are not overtime. NightOT is the subset of Overtime worked at
// night, used to add the 10% differential on top of the overtime rate.
type TimesheetLine struct {
	Date      string
	DayType   string
	Hours     float64
	Overtime  float64
	Night     float64
	NightOT   float64
	Absent    bool
	Leave     bool
	PaidLeave bool

	// LegacyHours is true when the hours were reconstructed from the legacy
	// premium-equivalent columns (regular/ot/nt) because actual_* was empty.
	LegacyHours bool
}

// Earning is an additional earning or deduction line.
type Earning struct {
	Code              string
	Name              string
	Kind              string
	Amount            money.Centavos
	Taxable           bool
	NonTaxableCeiling money.Centavos
	Prorated          bool
	ProratedBy        string
}

// BenefitFlags are the employee-specific entitlements from employees.
type BenefitFlags struct {
	RestDayPay                      bool
	RegularHolidayPay               bool
	SpecialHolidayPay               bool
	PremiumHolidayPay               bool
	RegularHolidaySpecialHolidayPay bool
	OvertimePay                     bool
	NightDifferential               bool
	DeMinimis                       bool
}

// Holiday is a company holiday within the payroll period.
type Holiday struct {
	Date string
	Code string
}

// De minimis ceiling bases.
const (
	DeMinimisMonthly  = "monthly"
	DeMinimisAnnual   = "annual"
	DeMinimisSemester = "semester"
	DeMinimisHourlyOT = "hourly_ot"
)

// DeMinimisBenefit is one non-taxable benefit ceiling.
type DeMinimisBenefit struct {
	Code    string
	Ceiling money.Centavos
	Basis   string
}

// YtdTotals is the employee's year-to-date ledger for a calendar year.
type YtdTotals struct {
	Year                int
	BasicEarned         money.Centavos
	GrossEarned         money.Centavos
	TaxableIncome       money.Centavos
	TaxWithheld         money.Centavos
	Contributions       money.Centavos
	ThirteenthMonthPaid money.Centavos
	OtherBenefits       money.Centavos
}

// MinimumWage is one effective-dated regional/industry wage order.
type MinimumWage struct {
	Region        string
	Industry      string
	WageOrder     string
	Amount        money.Centavos
	EffectiveFrom string
	EffectiveTo   string
}

// Employee is the payroll input for one person.
type Employee struct {
	ID           int64
	Region       string
	Industry     string
	Number       string
	Name         string
	MonthlyBasic money.Centavos
	DailyRate    money.Centavos
	TaxStatus    string
	Dependents   int

	PaymentType    string
	IsMondayFriday bool
	IsMinimumWage  bool
	MinimumWage    money.Centavos
	Benefits       BenefitFlags

	HiredOn          string
	SeparatedOn      string
	LeaveBalanceDays float64
	YTD              YtdTotals

	Timesheets    []TimesheetLine
	Allowances    []Earning
	Compensations []Earning
	Bonuses       []Earning
	Commissions   []Earning
	Deductions    []Earning
	Loans         []Earning
}

// Config is everything the computation needs that is not employee-specific.
type Config struct {
	Frequency statutory.Frequency
	Cutoff    string

	// RunType selects the computation: regular, thirteenth_month, final_pay
	// or annual_adjustment. Meta carries run-specific inputs (separation
	// reason, years of service, annual adjustment period).
	RunType string
	Meta    map[string]string

	DaysPerYear int
	HoursPerDay int

	SSSSchedule        statutory.Schedule
	PhilHealthSchedule statutory.Schedule
	PagIBIGSchedule    statutory.Schedule
	TaxSchedule        statutory.Schedule

	SSSBrackets       []statutory.SSSBracket
	PhilHealth        statutory.PhilHealthConfig
	PagIBIG           statutory.PagIBIGConfig
	TaxBrackets       []statutory.TaxBracket
	AnnualTaxBrackets []statutory.TaxBracket

	MinimumWage     money.Centavos
	MinimumWages    []MinimumWage
	DefaultRegion   string
	DefaultIndustry string

	// ContributionBase selects what SSS/PhilHealth/Pag-IBIG are assessed on:
	// "basic" (employees.basic_salary) or "gross" (period gross).
	SssBase        string
	PhilHealthBase string
	PagIBIGBase    string

	DeMinimis []DeMinimisBenefit

	Holidays []Holiday

	// PayWithoutTimesheet pays the period's basic salary when the employee has
	// no attendance rows, instead of treating the period as absent.
	PayWithoutTimesheet bool

	// PayUnworkedRegularHolidays pays 100% of the daily rate for a regular
	// holiday that was not worked (Article 94).
	PayUnworkedRegularHolidays bool
}

// PeriodsPerYear returns how many pays a frequency produces in a year.
func (c Config) PeriodsPerYear() int {
	if c.Frequency == statutory.FrequencyMonthly {
		return 12
	}

	return 24
}

// PeriodsPerMonth returns how many pays a frequency produces in a month.
func (c Config) PeriodsPerMonth() int {
	if c.Frequency == statutory.FrequencyMonthly {
		return 1
	}

	return 2
}

// Payslip is the computed result for one employee.
type Payslip struct {
	EmployeeID   int64
	EmployeeNo   string
	EmployeeName string

	BasicMonthly money.Centavos
	DailyRate    money.Centavos
	PeriodBasic  money.Centavos
	BasicEarned  money.Centavos

	RegularHours     float64
	RegularPay       money.Centavos
	OvertimeHours    float64
	OvertimePay      money.Centavos
	NightHours       float64
	NightPay         money.Centavos
	NightOTPay       money.Centavos
	HolidayPay       money.Centavos
	RestDayPay       money.Centavos
	AbsentDays       float64
	AbsentAmount     money.Centavos
	Allowances       money.Centavos
	Incentives       money.Centavos
	Compensations    []Earning
	Bonus            money.Centavos
	Commission       money.Centavos
	ThirteenthMonth  money.Centavos
	LeaveConversion  money.Centavos
	SeparationPay    money.Centavos
	AnnualAdjustment money.Centavos
	Gross            money.Centavos

	SSSEmployee        money.Centavos
	SSSProvident       money.Centavos // employee WISP/MPF
	SSSEmployer        money.Centavos
	SSSProvidentER     money.Centavos
	SSSEC              money.Centavos
	SSSTotal           money.Centavos
	PhilHealthEmployee money.Centavos
	PhilHealthEmployer money.Centavos
	PagIBIGEmployee    money.Centavos
	PagIBIGEmployer    money.Centavos

	TaxableIncome        money.Centavos
	NonTaxable           money.Centavos
	BenefitExempt        money.Centavos
	MinimumWageExemption money.Centavos
	WithholdingTax       money.Centavos

	OtherDeductions   money.Centavos
	Loans             money.Centavos
	TotalDeductions   money.Centavos
	TotalEmployerCost money.Centavos
	NetPay            money.Centavos

	Warnings []string
}
