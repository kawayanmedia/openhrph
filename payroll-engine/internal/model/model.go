// Package model holds the data the payroll engine works with.
package model

import (
	"time"

	"github.com/kawayanmedia/openhrph-payroll-engine/internal/money"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/rules"
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
	Undertime float64
	Absent    bool
	Leave     bool
	PaidLeave bool

	// LeaveFraction is the part of the day covered by leave: 1 for a whole
	// day and 0.5 for a half-day. Zero is treated as a whole day.
	LeaveFraction float64

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

	// Deduction configuration from the catalogue. A percentage deduction uses
	// Rate and Basis; a fixed deduction uses Amount.
	Calculation          string
	Rate                 float64
	Basis                string
	ReducesTaxableIncome bool
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

// DeMinimisHourlyOTPercentage is the default share of the hourly minimum wage
// allowed as a non-taxable overtime/night-shift meal allowance (RR 29-2025).
const DeMinimisHourlyOTPercentage = 30

// DeMinimisBenefit is one non-taxable benefit ceiling.
//
// Percentage applies to the hourly OT basis: the meal allowance ceiling is that
// percentage of the hourly minimum wage (RR 29-2025 sets 30%).
type DeMinimisBenefit struct {
	Code       string
	Ceiling    money.Centavos
	Basis      string
	Percentage int
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

	// DeMinimis is the exemption already used this month, semester or year,
	// keyed by "{code}|{period}".
	DeMinimis map[string]money.Centavos
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

	// IsGovernment marks a GSIS-covered agency. Government companies remit
	// GSIS instead of SSS (RA 8291); the two are mutually exclusive.
	IsGovernment bool

	// Sector is "Government" or "Private". IsGovernment is kept for parity
	// with older fixture files.
	Sector string

	// SocialInsurance selects the mandatory scheme: "sss" (private) or "gsis"
	// (government). It replaces IsGovernment as the computation switch.
	SocialInsurance string

	// LeaveConversionTaxableDays caps the tax-exempt portion of monetized
	// leave. A nil value means the whole conversion is exempt.
	LeaveConversionTaxableDays *int

	DaysPerYear int
	HoursPerDay int

	SSSSchedule        statutory.Schedule
	PhilHealthSchedule statutory.Schedule
	PagIBIGSchedule    statutory.Schedule
	TaxSchedule        statutory.Schedule

	SSSBrackets       []statutory.SSSBracket
	PhilHealth        statutory.PhilHealthConfig
	GSIS              statutory.GSISConfig
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
	GSISBase       string

	DeMinimis []DeMinimisBenefit

	Holidays []Holiday

	// PayWithoutTimesheet pays the period's basic salary when the employee has
	// no attendance rows, instead of treating the period as absent.
	PayWithoutTimesheet bool

	// PayUnworkedRegularHolidays pays 100% of the daily rate for a regular
	// holiday that was not worked (Article 94).
	PayUnworkedRegularHolidays bool

	// ComputeUndertime deducts approved undertime hours from pay. The
	// payroll group controls this switch.
	ComputeUndertime bool

	// CountryCode and StatutoryDriver select the statutory rule set. `ph`
	// uses the built-in agency calculators; any other driver reads Components.
	CountryCode     string
	StatutoryDriver string
	Components      []statutory.GenericComponent

	// Policy carries the country's non-statutory earning rules. It is loaded
	// from the payroll's snapshot so a historical run stays reproducible.
	Policy rules.Policy

	// PayrollDate keys the de minimis ledger to the calendar month, semester
	// or year the ceiling applies to.
	PayrollDate time.Time
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
	UndertimeHours   float64
	UndertimePay     money.Centavos
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
	GSISEmployee       money.Centavos
	GSISEmployer       money.Centavos
	GSISTotal          money.Centavos

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

	DeductionLines []DeductionLine
	DeductionItems []DeductionLine

	// DeMinimisExempt is the exemption this payslip consumed, keyed by
	// "{code}|{period}". It is written to the ledger and kept out of the
	// parity fixture shape.
	DeMinimisExempt map[string]money.Centavos
}

// DeductionLine is one country-neutral deduction line for a payslip.
type DeductionLine struct {
	Code                 string
	Name                 string
	Category             string
	Base                 money.Centavos
	Employee             money.Centavos
	Employer             money.Centavos
	ReducesTaxableIncome bool
	Source               string
	SortOrder            int
}

// Deduction line sources.
const (
	SourceStatutory = "statutory"
	SourceDeduction = "deduction"
	SourceLoan      = "loan"
)
