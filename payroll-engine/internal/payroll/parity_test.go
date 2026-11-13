package payroll

// Parity corpus.
//
// The payroll engine has two runtimes: the Go binary (production) and the PHP
// driver in app/Services/Payroll/Computation (fallback). They must produce the
// same payslip for the same input, so the scenarios below are computed by this
// package and written to testdata/parity as the contract both sides assert
// against.
//
//	PARITY_WRITE=1 go test ./internal/payroll -run TestParityCorpus
//
// rewrites the corpus after an intentional engine change. Without PARITY_WRITE
// the test only verifies that the engine still reproduces the committed files.

import (
	"encoding/json"
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/kawayanmedia/openhrph-payroll-engine/internal/model"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/money"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/statutory"
)

const parityDir = "testdata/parity"

type fxBenefitFlags struct {
	RestDayPay                      bool `json:"rest_day_pay"`
	RegularHolidayPay               bool `json:"regular_holiday_pay"`
	SpecialHolidayPay               bool `json:"special_holiday_pay"`
	PremiumHolidayPay               bool `json:"premium_holiday_pay"`
	RegularHolidaySpecialHolidayPay bool `json:"regular_holiday_special_holiday_pay"`
	OvertimePay                     bool `json:"overtime_pay"`
	NightDifferential               bool `json:"night_differential"`
	DeMinimis                       bool `json:"deminimis"`
}

type fxYTD struct {
	Year                int   `json:"year"`
	BasicEarned         int64 `json:"basic_earned"`
	GrossEarned         int64 `json:"gross_earned"`
	TaxableIncome       int64 `json:"taxable_income"`
	TaxWithheld         int64 `json:"tax_withheld"`
	Contributions       int64 `json:"contributions"`
	ThirteenthMonthPaid int64 `json:"thirteenth_month_paid"`
	OtherBenefits       int64 `json:"other_benefits"`
}

type fxTimesheet struct {
	Date        string  `json:"date"`
	DayType     string  `json:"day_type"`
	Hours       float64 `json:"hours"`
	Overtime    float64 `json:"overtime"`
	Night       float64 `json:"night"`
	NightOT     float64 `json:"night_ot"`
	Absent      bool    `json:"absent"`
	Leave       bool    `json:"leave"`
	PaidLeave   bool    `json:"paid_leave"`
	LegacyHours bool    `json:"legacy_hours"`
}

type fxEarning struct {
	Code              string `json:"code"`
	Name              string `json:"name"`
	Kind              string `json:"kind"`
	Amount            int64  `json:"amount"`
	Taxable           bool   `json:"taxable"`
	NonTaxableCeiling int64  `json:"non_taxable_ceiling"`
	Prorated          bool   `json:"prorated"`
	ProratedBy        string `json:"prorated_by"`
}

type fxHoliday struct {
	Date string `json:"date"`
	Code string `json:"code"`
}

type fxDeMinimis struct {
	Code    string `json:"code"`
	Ceiling int64  `json:"ceiling"`
	Basis   string `json:"basis"`
}

type fxSssBracket struct {
	MSCFrom int64 `json:"msc_from"`
	MSCTo   int64 `json:"msc_to"`
	MSC     int64 `json:"msc"`
	EE      int64 `json:"ee"`
	ER      int64 `json:"er"`
	EC      int64 `json:"ec"`
	WISPEE  int64 `json:"wisp_ee"`
	WISPER  int64 `json:"wisp_er"`
}

type fxPhilHealth struct {
	Rate    float64 `json:"rate"`
	Floor   int64   `json:"floor"`
	Ceiling int64   `json:"ceiling"`
}

type fxPagIbig struct {
	Rate          float64 `json:"rate"`
	LowRate       float64 `json:"low_rate"`
	LowThreshold  int64   `json:"low_threshold"`
	MaxFundSalary int64   `json:"max_fund_salary"`
}

type fxTaxBracket struct {
	From    int64   `json:"from"`
	To      int64   `json:"to"`
	BaseTax int64   `json:"base_tax"`
	Rate    float64 `json:"rate"`
}

type fxMinimumWage struct {
	Region        string `json:"region"`
	Industry      string `json:"industry"`
	WageOrder     string `json:"wage_order"`
	Amount        int64  `json:"amount"`
	EffectiveFrom string `json:"effective_from"`
	EffectiveTo   string `json:"effective_to"`
}

type fxConfig struct {
	Frequency                  string            `json:"frequency"`
	Cutoff                     string            `json:"cutoff"`
	RunType                    string            `json:"run_type"`
	Meta                       map[string]string `json:"meta"`
	DaysPerYear                int               `json:"days_per_year"`
	HoursPerDay                int               `json:"hours_per_day"`
	SSSSchedule                string            `json:"sss_schedule"`
	PhilHealthSchedule         string            `json:"philhealth_schedule"`
	PagIBIGSchedule            string            `json:"pagibig_schedule"`
	TaxSchedule                string            `json:"tax_schedule"`
	SSSBrackets                []fxSssBracket    `json:"sss_brackets"`
	PhilHealth                 fxPhilHealth      `json:"philhealth"`
	PagIBIG                    fxPagIbig         `json:"pagibig"`
	TaxBrackets                []fxTaxBracket    `json:"tax_brackets"`
	AnnualTaxBrackets          []fxTaxBracket    `json:"annual_tax_brackets"`
	MinimumWage                int64             `json:"minimum_wage"`
	MinimumWages               []fxMinimumWage   `json:"minimum_wages"`
	DefaultRegion              string            `json:"default_region"`
	DefaultIndustry            string            `json:"default_industry"`
	SSSBase                    string            `json:"sss_base"`
	PhilHealthBase             string            `json:"philhealth_base"`
	PagIBIGBase                string            `json:"pagibig_base"`
	DeMinimis                  []fxDeMinimis     `json:"de_minimis"`
	Holidays                   []fxHoliday       `json:"holidays"`
	PayWithoutTimesheet        bool              `json:"pay_without_timesheet"`
	PayUnworkedRegularHolidays bool              `json:"pay_unworked_regular_holidays"`
}

type fxEmployee struct {
	ID               int64          `json:"id"`
	Region           string         `json:"region"`
	Industry         string         `json:"industry"`
	Number           string         `json:"number"`
	Name             string         `json:"name"`
	MonthlyBasic     int64          `json:"monthly_basic"`
	DailyRate        int64          `json:"daily_rate"`
	TaxStatus        string         `json:"tax_status"`
	Dependents       int            `json:"dependents"`
	PaymentType      string         `json:"payment_type"`
	IsMondayFriday   bool           `json:"is_monday_friday"`
	IsMinimumWage    bool           `json:"is_minimum_wage"`
	MinimumWage      int64          `json:"minimum_wage"`
	Benefits         fxBenefitFlags `json:"benefits"`
	HiredOn          string         `json:"hired_on"`
	SeparatedOn      string         `json:"separated_on"`
	LeaveBalanceDays float64        `json:"leave_balance_days"`
	YTD              fxYTD          `json:"ytd"`
	Timesheets       []fxTimesheet  `json:"timesheets"`
	Allowances       []fxEarning    `json:"allowances"`
	Compensations    []fxEarning    `json:"compensations"`
	Bonuses          []fxEarning    `json:"bonuses"`
	Commissions      []fxEarning    `json:"commissions"`
	Deductions       []fxEarning    `json:"deductions"`
	Loans            []fxEarning    `json:"loans"`
}

type fxPayslip struct {
	EmployeeID         int64       `json:"employee_id"`
	EmployeeNo         string      `json:"employee_no"`
	EmployeeName       string      `json:"employee_name"`
	BasicMonthly       int64       `json:"basic_monthly"`
	DailyRate          int64       `json:"daily_rate"`
	PeriodBasic        int64       `json:"period_basic"`
	BasicEarned        int64       `json:"basic_earned"`
	RegularHours       float64     `json:"regular_hours"`
	RegularPay         int64       `json:"regular_pay"`
	OvertimeHours      float64     `json:"overtime_hours"`
	OvertimePay        int64       `json:"overtime_pay"`
	NightHours         float64     `json:"night_hours"`
	NightPay           int64       `json:"night_pay"`
	NightOTPay         int64       `json:"night_ot_pay"`
	HolidayPay         int64       `json:"holiday_pay"`
	RestDayPay         int64       `json:"rest_day_pay"`
	AbsentDays         float64     `json:"absent_days"`
	AbsentAmount       int64       `json:"absent_amount"`
	Allowances         int64       `json:"allowances"`
	Incentives         int64       `json:"incentives"`
	Compensations      []fxEarning `json:"compensations"`
	Bonus              int64       `json:"bonus"`
	Commission         int64       `json:"commission"`
	ThirteenthMonth    int64       `json:"thirteenth_month"`
	LeaveConversion    int64       `json:"leave_conversion"`
	SeparationPay      int64       `json:"separation_pay"`
	AnnualAdjustment   int64       `json:"annual_adjustment"`
	Gross              int64       `json:"gross"`
	SSSEmployee        int64       `json:"sss_employee"`
	SSSProvident       int64       `json:"sss_provident"`
	SSSEmployer        int64       `json:"sss_employer"`
	SSSProvidentER     int64       `json:"sss_provident_er"`
	SSSEC              int64       `json:"sss_ec"`
	SSSTotal           int64       `json:"sss_total"`
	PhilHealthEmployee int64       `json:"philhealth_employee"`
	PhilHealthEmployer int64       `json:"philhealth_employer"`
	PagIBIGEmployee    int64       `json:"pagibig_employee"`
	PagIBIGEmployer    int64       `json:"pagibig_employer"`
	TaxableIncome      int64       `json:"taxable_income"`
	NonTaxable         int64       `json:"non_taxable"`
	BenefitExempt      int64       `json:"benefit_exempt"`
	MinimumWageExempt  int64       `json:"minimum_wage_exemption"`
	WithholdingTax     int64       `json:"withholding_tax"`
	OtherDeductions    int64       `json:"other_deductions"`
	Loans              int64       `json:"loans"`
	TotalDeductions    int64       `json:"total_deductions"`
	TotalEmployerCost  int64       `json:"total_employer_cost"`
	NetPay             int64       `json:"net_pay"`
	Warnings           []string    `json:"warnings"`
}

type fxFixture struct {
	Name     string     `json:"name"`
	RunType  string     `json:"run_type"`
	Config   fxConfig   `json:"config"`
	Employee fxEmployee `json:"employee"`
	Expected fxPayslip  `json:"expected"`
}

func money64(value money.Centavos) int64 { return int64(value) }

func fromMoney(value int64) money.Centavos { return money.Centavos(value) }

func toFxConfig(cfg model.Config) fxConfig {
	out := fxConfig{
		Frequency:                  string(cfg.Frequency),
		Cutoff:                     cfg.Cutoff,
		RunType:                    cfg.RunType,
		Meta:                       cfg.Meta,
		DaysPerYear:                cfg.DaysPerYear,
		HoursPerDay:                cfg.HoursPerDay,
		SSSSchedule:                string(cfg.SSSSchedule),
		PhilHealthSchedule:         string(cfg.PhilHealthSchedule),
		PagIBIGSchedule:            string(cfg.PagIBIGSchedule),
		TaxSchedule:                string(cfg.TaxSchedule),
		PhilHealth:                 fxPhilHealth{Rate: cfg.PhilHealth.Rate, Floor: money64(cfg.PhilHealth.Floor), Ceiling: money64(cfg.PhilHealth.Ceiling)},
		PagIBIG:                    fxPagIbig{Rate: cfg.PagIBIG.Rate, LowRate: cfg.PagIBIG.LowRate, LowThreshold: money64(cfg.PagIBIG.LowThreshold), MaxFundSalary: money64(cfg.PagIBIG.MaxFundSalary)},
		MinimumWage:                money64(cfg.MinimumWage),
		DefaultRegion:              cfg.DefaultRegion,
		DefaultIndustry:            cfg.DefaultIndustry,
		SSSBase:                    cfg.SssBase,
		PhilHealthBase:             cfg.PhilHealthBase,
		PagIBIGBase:                cfg.PagIBIGBase,
		PayWithoutTimesheet:        cfg.PayWithoutTimesheet,
		PayUnworkedRegularHolidays: cfg.PayUnworkedRegularHolidays,
	}

	out.SSSBrackets = []fxSssBracket{}
	for _, bracket := range cfg.SSSBrackets {
		out.SSSBrackets = append(out.SSSBrackets, fxSssBracket{
			MSCFrom: money64(bracket.MSCFrom), MSCTo: money64(bracket.MSCTo), MSC: money64(bracket.MSC),
			EE: money64(bracket.EE), ER: money64(bracket.ER), EC: money64(bracket.EC),
			WISPEE: money64(bracket.WISPEE), WISPER: money64(bracket.WISPER),
		})
	}

	out.TaxBrackets = toFxBrackets(cfg.TaxBrackets)
	out.AnnualTaxBrackets = toFxBrackets(cfg.AnnualTaxBrackets)

	out.MinimumWages = []fxMinimumWage{}
	for _, wage := range cfg.MinimumWages {
		out.MinimumWages = append(out.MinimumWages, fxMinimumWage{
			Region: wage.Region, Industry: wage.Industry, WageOrder: wage.WageOrder,
			Amount: money64(wage.Amount), EffectiveFrom: wage.EffectiveFrom, EffectiveTo: wage.EffectiveTo,
		})
	}

	out.DeMinimis = []fxDeMinimis{}
	for _, benefit := range cfg.DeMinimis {
		out.DeMinimis = append(out.DeMinimis, fxDeMinimis{Code: benefit.Code, Ceiling: money64(benefit.Ceiling), Basis: benefit.Basis})
	}

	out.Holidays = []fxHoliday{}
	for _, holiday := range cfg.Holidays {
		out.Holidays = append(out.Holidays, fxHoliday{Date: holiday.Date, Code: holiday.Code})
	}

	return out
}

func toFxBrackets(brackets []statutory.TaxBracket) []fxTaxBracket {
	out := []fxTaxBracket{}
	for _, bracket := range brackets {
		out = append(out, fxTaxBracket{
			From: money64(bracket.From), To: money64(bracket.To),
			BaseTax: money64(bracket.BaseTax), Rate: bracket.Rate,
		})
	}

	return out
}

func fromFxConfig(cfg fxConfig) model.Config {
	out := model.Config{
		Frequency:                  statutory.Frequency(cfg.Frequency),
		Cutoff:                     cfg.Cutoff,
		RunType:                    cfg.RunType,
		Meta:                       cfg.Meta,
		DaysPerYear:                cfg.DaysPerYear,
		HoursPerDay:                cfg.HoursPerDay,
		SSSSchedule:                statutory.Schedule(cfg.SSSSchedule),
		PhilHealthSchedule:         statutory.Schedule(cfg.PhilHealthSchedule),
		PagIBIGSchedule:            statutory.Schedule(cfg.PagIBIGSchedule),
		TaxSchedule:                statutory.Schedule(cfg.TaxSchedule),
		PhilHealth:                 statutory.PhilHealthConfig{Rate: cfg.PhilHealth.Rate, Floor: fromMoney(cfg.PhilHealth.Floor), Ceiling: fromMoney(cfg.PhilHealth.Ceiling)},
		PagIBIG:                    statutory.PagIBIGConfig{Rate: cfg.PagIBIG.Rate, LowRate: cfg.PagIBIG.LowRate, LowThreshold: fromMoney(cfg.PagIBIG.LowThreshold), MaxFundSalary: fromMoney(cfg.PagIBIG.MaxFundSalary)},
		MinimumWage:                fromMoney(cfg.MinimumWage),
		DefaultRegion:              cfg.DefaultRegion,
		DefaultIndustry:            cfg.DefaultIndustry,
		SssBase:                    cfg.SSSBase,
		PhilHealthBase:             cfg.PhilHealthBase,
		PagIBIGBase:                cfg.PagIBIGBase,
		PayWithoutTimesheet:        cfg.PayWithoutTimesheet,
		PayUnworkedRegularHolidays: cfg.PayUnworkedRegularHolidays,
	}

	for _, bracket := range cfg.SSSBrackets {
		out.SSSBrackets = append(out.SSSBrackets, statutory.SSSBracket{
			MSCFrom: fromMoney(bracket.MSCFrom), MSCTo: fromMoney(bracket.MSCTo), MSC: fromMoney(bracket.MSC),
			EE: fromMoney(bracket.EE), ER: fromMoney(bracket.ER), EC: fromMoney(bracket.EC),
			WISPEE: fromMoney(bracket.WISPEE), WISPER: fromMoney(bracket.WISPER),
		})
	}

	for _, bracket := range cfg.TaxBrackets {
		out.TaxBrackets = append(out.TaxBrackets, statutory.TaxBracket{
			From: fromMoney(bracket.From), To: fromMoney(bracket.To),
			BaseTax: fromMoney(bracket.BaseTax), Rate: bracket.Rate,
		})
	}

	for _, bracket := range cfg.AnnualTaxBrackets {
		out.AnnualTaxBrackets = append(out.AnnualTaxBrackets, statutory.TaxBracket{
			From: fromMoney(bracket.From), To: fromMoney(bracket.To),
			BaseTax: fromMoney(bracket.BaseTax), Rate: bracket.Rate,
		})
	}

	for _, wage := range cfg.MinimumWages {
		out.MinimumWages = append(out.MinimumWages, model.MinimumWage{
			Region: wage.Region, Industry: wage.Industry, WageOrder: wage.WageOrder,
			Amount: fromMoney(wage.Amount), EffectiveFrom: wage.EffectiveFrom, EffectiveTo: wage.EffectiveTo,
		})
	}

	for _, benefit := range cfg.DeMinimis {
		out.DeMinimis = append(out.DeMinimis, model.DeMinimisBenefit{
			Code: benefit.Code, Ceiling: fromMoney(benefit.Ceiling), Basis: benefit.Basis,
		})
	}

	for _, holiday := range cfg.Holidays {
		out.Holidays = append(out.Holidays, model.Holiday{Date: holiday.Date, Code: holiday.Code})
	}

	return out
}

func toFxEmployee(employee model.Employee) fxEmployee {
	out := fxEmployee{
		ID: employee.ID, Region: employee.Region, Industry: employee.Industry,
		Number: employee.Number, Name: employee.Name,
		MonthlyBasic: money64(employee.MonthlyBasic), DailyRate: money64(employee.DailyRate),
		TaxStatus: employee.TaxStatus, Dependents: employee.Dependents,
		PaymentType: employee.PaymentType, IsMondayFriday: employee.IsMondayFriday,
		IsMinimumWage: employee.IsMinimumWage, MinimumWage: money64(employee.MinimumWage),
		Benefits: fxBenefitFlags{
			RestDayPay:                      employee.Benefits.RestDayPay,
			RegularHolidayPay:               employee.Benefits.RegularHolidayPay,
			SpecialHolidayPay:               employee.Benefits.SpecialHolidayPay,
			PremiumHolidayPay:               employee.Benefits.PremiumHolidayPay,
			RegularHolidaySpecialHolidayPay: employee.Benefits.RegularHolidaySpecialHolidayPay,
			OvertimePay:                     employee.Benefits.OvertimePay,
			NightDifferential:               employee.Benefits.NightDifferential,
			DeMinimis:                       employee.Benefits.DeMinimis,
		},
		HiredOn: employee.HiredOn, SeparatedOn: employee.SeparatedOn,
		LeaveBalanceDays: employee.LeaveBalanceDays,
		YTD: fxYTD{
			Year: employee.YTD.Year, BasicEarned: money64(employee.YTD.BasicEarned),
			GrossEarned: money64(employee.YTD.GrossEarned), TaxableIncome: money64(employee.YTD.TaxableIncome),
			TaxWithheld: money64(employee.YTD.TaxWithheld), Contributions: money64(employee.YTD.Contributions),
			ThirteenthMonthPaid: money64(employee.YTD.ThirteenthMonthPaid), OtherBenefits: money64(employee.YTD.OtherBenefits),
		},
		Timesheets:    []fxTimesheet{},
		Allowances:    toFxEarnings(employee.Allowances),
		Compensations: toFxEarnings(employee.Compensations),
		Bonuses:       toFxEarnings(employee.Bonuses),
		Commissions:   toFxEarnings(employee.Commissions),
		Deductions:    toFxEarnings(employee.Deductions),
		Loans:         toFxEarnings(employee.Loans),
	}

	for _, line := range employee.Timesheets {
		out.Timesheets = append(out.Timesheets, fxTimesheet{
			Date: line.Date, DayType: line.DayType, Hours: line.Hours, Overtime: line.Overtime,
			Night: line.Night, NightOT: line.NightOT, Absent: line.Absent,
			Leave: line.Leave, PaidLeave: line.PaidLeave, LegacyHours: line.LegacyHours,
		})
	}

	return out
}

func fromFxEmployee(employee fxEmployee) model.Employee {
	out := model.Employee{
		ID: employee.ID, Region: employee.Region, Industry: employee.Industry,
		Number: employee.Number, Name: employee.Name,
		MonthlyBasic: fromMoney(employee.MonthlyBasic), DailyRate: fromMoney(employee.DailyRate),
		TaxStatus: employee.TaxStatus, Dependents: employee.Dependents,
		PaymentType: employee.PaymentType, IsMondayFriday: employee.IsMondayFriday,
		IsMinimumWage: employee.IsMinimumWage, MinimumWage: fromMoney(employee.MinimumWage),
		Benefits: model.BenefitFlags{
			RestDayPay:                      employee.Benefits.RestDayPay,
			RegularHolidayPay:               employee.Benefits.RegularHolidayPay,
			SpecialHolidayPay:               employee.Benefits.SpecialHolidayPay,
			PremiumHolidayPay:               employee.Benefits.PremiumHolidayPay,
			RegularHolidaySpecialHolidayPay: employee.Benefits.RegularHolidaySpecialHolidayPay,
			OvertimePay:                     employee.Benefits.OvertimePay,
			NightDifferential:               employee.Benefits.NightDifferential,
			DeMinimis:                       employee.Benefits.DeMinimis,
		},
		HiredOn: employee.HiredOn, SeparatedOn: employee.SeparatedOn,
		LeaveBalanceDays: employee.LeaveBalanceDays,
		YTD: model.YtdTotals{
			Year: employee.YTD.Year, BasicEarned: fromMoney(employee.YTD.BasicEarned),
			GrossEarned: fromMoney(employee.YTD.GrossEarned), TaxableIncome: fromMoney(employee.YTD.TaxableIncome),
			TaxWithheld: fromMoney(employee.YTD.TaxWithheld), Contributions: fromMoney(employee.YTD.Contributions),
			ThirteenthMonthPaid: fromMoney(employee.YTD.ThirteenthMonthPaid), OtherBenefits: fromMoney(employee.YTD.OtherBenefits),
		},
		Allowances:    fromFxEarnings(employee.Allowances),
		Compensations: fromFxEarnings(employee.Compensations),
		Bonuses:       fromFxEarnings(employee.Bonuses),
		Commissions:   fromFxEarnings(employee.Commissions),
		Deductions:    fromFxEarnings(employee.Deductions),
		Loans:         fromFxEarnings(employee.Loans),
	}

	for _, line := range employee.Timesheets {
		out.Timesheets = append(out.Timesheets, model.TimesheetLine{
			Date: line.Date, DayType: line.DayType, Hours: line.Hours, Overtime: line.Overtime,
			Night: line.Night, NightOT: line.NightOT, Absent: line.Absent,
			Leave: line.Leave, PaidLeave: line.PaidLeave, LegacyHours: line.LegacyHours,
		})
	}

	return out
}

func toFxEarnings(earnings []model.Earning) []fxEarning {
	out := []fxEarning{}
	for _, earning := range earnings {
		out = append(out, fxEarning{
			Code: earning.Code, Name: earning.Name, Kind: earning.Kind,
			Amount: money64(earning.Amount), Taxable: earning.Taxable,
			NonTaxableCeiling: money64(earning.NonTaxableCeiling),
			Prorated:          earning.Prorated, ProratedBy: earning.ProratedBy,
		})
	}

	return out
}

func fromFxEarnings(earnings []fxEarning) []model.Earning {
	out := []model.Earning{}
	for _, earning := range earnings {
		out = append(out, model.Earning{
			Code: earning.Code, Name: earning.Name, Kind: earning.Kind,
			Amount: fromMoney(earning.Amount), Taxable: earning.Taxable,
			NonTaxableCeiling: fromMoney(earning.NonTaxableCeiling),
			Prorated:          earning.Prorated, ProratedBy: earning.ProratedBy,
		})
	}

	return out
}

func toFxPayslip(slip model.Payslip) fxPayslip {
	warnings := slip.Warnings
	if warnings == nil {
		warnings = []string{}
	}

	return fxPayslip{
		EmployeeID: slip.EmployeeID, EmployeeNo: slip.EmployeeNo, EmployeeName: slip.EmployeeName,
		BasicMonthly: money64(slip.BasicMonthly), DailyRate: money64(slip.DailyRate),
		PeriodBasic: money64(slip.PeriodBasic), BasicEarned: money64(slip.BasicEarned),
		RegularHours: slip.RegularHours, RegularPay: money64(slip.RegularPay),
		OvertimeHours: slip.OvertimeHours, OvertimePay: money64(slip.OvertimePay),
		NightHours: slip.NightHours, NightPay: money64(slip.NightPay), NightOTPay: money64(slip.NightOTPay),
		HolidayPay: money64(slip.HolidayPay), RestDayPay: money64(slip.RestDayPay),
		AbsentDays: slip.AbsentDays, AbsentAmount: money64(slip.AbsentAmount),
		Allowances: money64(slip.Allowances), Incentives: money64(slip.Incentives),
		Compensations: toFxEarnings(slip.Compensations),
		Bonus:         money64(slip.Bonus), Commission: money64(slip.Commission),
		ThirteenthMonth: money64(slip.ThirteenthMonth), LeaveConversion: money64(slip.LeaveConversion),
		SeparationPay: money64(slip.SeparationPay), AnnualAdjustment: money64(slip.AnnualAdjustment),
		Gross:       money64(slip.Gross),
		SSSEmployee: money64(slip.SSSEmployee), SSSProvident: money64(slip.SSSProvident),
		SSSEmployer: money64(slip.SSSEmployer), SSSProvidentER: money64(slip.SSSProvidentER),
		SSSEC: money64(slip.SSSEC), SSSTotal: money64(slip.SSSTotal),
		PhilHealthEmployee: money64(slip.PhilHealthEmployee), PhilHealthEmployer: money64(slip.PhilHealthEmployer),
		PagIBIGEmployee: money64(slip.PagIBIGEmployee), PagIBIGEmployer: money64(slip.PagIBIGEmployer),
		TaxableIncome: money64(slip.TaxableIncome), NonTaxable: money64(slip.NonTaxable),
		BenefitExempt: money64(slip.BenefitExempt), MinimumWageExempt: money64(slip.MinimumWageExemption),
		WithholdingTax: money64(slip.WithholdingTax), OtherDeductions: money64(slip.OtherDeductions),
		Loans: money64(slip.Loans), TotalDeductions: money64(slip.TotalDeductions),
		TotalEmployerCost: money64(slip.TotalEmployerCost), NetPay: money64(slip.NetPay),
		Warnings: warnings,
	}
}

func baseConfig() model.Config {
	return model.Config{
		Frequency:                  statutory.FrequencySemiMonthly,
		Cutoff:                     string(statutory.ScheduleLastPay),
		RunType:                    model.RunRegular,
		Meta:                       map[string]string{},
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
		DefaultRegion:              "NCR",
		DefaultIndustry:            "NON-AGRICULTURE",
		SssBase:                    "basic",
		PhilHealthBase:             "basic",
		PagIBIGBase:                "basic",
		PayWithoutTimesheet:        true,
		PayUnworkedRegularHolidays: true,
	}
}

func monthlyEmployee() model.Employee {
	return model.Employee{
		ID: 501, Number: "E-0501", Name: "MONTHLY EMPLOYEE",
		MonthlyBasic: money.FromPesos(30000), PaymentType: model.PaymentMonthly,
		Benefits: allBenefits(),
	}
}

func dailyEmployee() model.Employee {
	return model.Employee{
		ID: 502, Number: "E-0502", Name: "DAILY EMPLOYEE",
		MonthlyBasic: money.FromPesos(22000), DailyRate: money.FromPesos(1000),
		PaymentType: model.PaymentDaily,
		Benefits:    allBenefits(),
	}
}

func parityScenarios() []struct {
	name     string
	cfg      model.Config
	employee model.Employee
} {
	cfg := baseConfig()

	// 1. Monthly-paid employee with a full period of ordinary days.
	fullMonth := monthlyEmployee()
	for day := 1; day <= 15; day++ {
		fullMonth.Timesheets = append(fullMonth.Timesheets, model.TimesheetLine{
			Date: dateOf(2026, 3, day), DayType: "regular", Hours: 8,
		})
	}

	// 2. Daily-paid employee: ordinary days, rest day, overtime and night.
	daily := dailyEmployee()
	daily.Timesheets = []model.TimesheetLine{
		{Date: "2026-03-02", DayType: "regular", Hours: 8},
		{Date: "2026-03-03", DayType: "regular", Hours: 8, Overtime: 2},
		{Date: "2026-03-07", DayType: "rd", Hours: 8},
		{Date: "2026-03-08", DayType: "regular", Hours: 8, Night: 2, Overtime: 1, NightOT: 1},
		{Date: "2026-03-09", DayType: "absent"},
		{Date: "2026-03-10", DayType: "regular", Hours: 4, LegacyHours: true},
	}

	// 3. Every premium day type for a monthly-paid employee.
	premium := monthlyEmployee()
	premium.Timesheets = []model.TimesheetLine{
		{Date: "2026-04-01", DayType: "rd", Hours: 8},
		{Date: "2026-04-02", DayType: "sh", Hours: 8},
		{Date: "2026-04-03", DayType: "rh", Hours: 8},
		{Date: "2026-04-04", DayType: "rd_sh", Hours: 8},
		{Date: "2026-04-05", DayType: "rd_rh", Hours: 8},
		{Date: "2026-04-06", DayType: "2rh", Hours: 8},
		{Date: "2026-04-07", DayType: "rd_2rh", Hours: 8},
		{Date: "2026-04-08", DayType: "rh_sh", Hours: 8},
		{Date: "2026-04-09", DayType: "rd_rh_sh", Hours: 8},
	}

	// 4. The same days with every premium switch disabled.
	notEntitled := monthlyEmployee()
	notEntitled.Benefits = model.BenefitFlags{}
	notEntitled.Timesheets = append([]model.TimesheetLine{}, premium.Timesheets...)
	notEntitled.Timesheets = append(notEntitled.Timesheets,
		model.TimesheetLine{Date: "2026-04-10", DayType: "regular", Hours: 8, Overtime: 2, Night: 2, NightOT: 1})

	// 5. No attendance, paid on basic.
	noTimesheetPaid := monthlyEmployee()

	// 6. No attendance, nothing paid.
	unpaidCfg := baseConfig()
	unpaidCfg.PayWithoutTimesheet = false
	noTimesheetUnpaid := monthlyEmployee()

	// 7. Absence and paid leave for both payment types.
	absent := dailyEmployee()
	absent.Timesheets = []model.TimesheetLine{
		{Date: "2026-05-04", DayType: "regular", Hours: 8},
		{Date: "2026-05-05", DayType: "absent"},
		{Date: "2026-05-06", DayType: "upl"},
		{Date: "2026-05-07", DayType: "pl"},
		{Date: "2026-05-08", DayType: "regular", Hours: 8},
	}

	// 8. Allowances with de minimis ceilings.
	deMinimisCfg := baseConfig()
	deMinimisCfg.DeMinimis = []model.DeMinimisBenefit{
		{Code: "rice", Ceiling: money.FromPesos(2000), Basis: model.DeMinimisMonthly},
		{Code: "medical", Ceiling: money.FromPesos(10000), Basis: model.DeMinimisAnnual},
		{Code: "laundry", Ceiling: money.FromPesos(3600), Basis: model.DeMinimisSemester},
		{Code: "meal_ot", Ceiling: money.FromPesos(100), Basis: model.DeMinimisHourlyOT},
	}
	allowances := monthlyEmployee()
	allowances.Timesheets = fullMonth.Timesheets
	allowances.Allowances = []model.Earning{
		{Code: "rice", Name: "Rice Allowance", Kind: model.KindAllowance, Amount: money.FromPesos(2500), Taxable: false},
		{Code: "medical", Name: "Medical Allowance", Kind: model.KindAllowance, Amount: money.FromPesos(1500), Taxable: false},
		{Code: "laundry", Name: "Laundry Allowance", Kind: model.KindAllowance, Amount: money.FromPesos(400), Taxable: false},
		{Code: "transport", Name: "Transport Allowance", Kind: model.KindAllowance, Amount: money.FromPesos(1000), Taxable: true},
	}

	// 9. Bonuses, commissions and compensations.
	earnings := monthlyEmployee()
	earnings.Timesheets = fullMonth.Timesheets
	earnings.Bonuses = []model.Earning{
		{Code: "signing", Name: "Signing Bonus", Kind: model.KindBonus, Amount: money.FromPesos(20000), NonTaxableCeiling: money.FromPesos(5000)},
	}
	earnings.Commissions = []model.Earning{
		{Code: "sales", Name: "Sales Commission", Kind: model.KindCommission, Amount: money.FromPesos(4000), Taxable: false},
	}
	earnings.Compensations = []model.Earning{
		{Code: "internet", Name: "Internet", Kind: model.KindCompensation, Amount: money.FromPesos(1200), Taxable: true},
		{Code: "meal", Name: "Meal", Kind: model.KindCompensation, Amount: money.FromPesos(800), Taxable: false},
	}

	// 10. Deductions and loans exceeding earnings (net-pay floor).
	deductions := dailyEmployee()
	deductions.Timesheets = []model.TimesheetLine{
		{Date: "2026-06-01", DayType: "regular", Hours: 8},
		{Date: "2026-06-02", DayType: "regular", Hours: 8},
	}
	deductions.Deductions = []model.Earning{
		{Code: "union", Name: "Union Dues", Kind: model.KindDeduction, Amount: money.FromPesos(50000)},
	}
	deductions.Loans = []model.Earning{
		{Code: "sssloan", Name: "SSS Loan", Kind: model.KindLoan, Amount: money.FromPesos(15000)},
	}

	// 11. Contributions assessed on the period gross.
	grossCfg := baseConfig()
	grossCfg.SssBase = "period_gross"
	grossCfg.PhilHealthBase = "gross"
	grossCfg.PagIBIGBase = "gross"
	gross := monthlyEmployee()
	gross.Timesheets = fullMonth.Timesheets

	// 12. First-pay-only schedule: contributions and tax split unevenly.
	firstPayCfg := baseConfig()
	firstPayCfg.Cutoff = string(statutory.ScheduleFirstPay)
	firstPayCfg.SSSSchedule = statutory.ScheduleFirstPay
	firstPayCfg.PhilHealthSchedule = statutory.ScheduleFirstPay
	firstPayCfg.PagIBIGSchedule = statutory.ScheduleFirstPay
	firstPayCfg.TaxSchedule = statutory.ScheduleFirstPay
	firstPay := monthlyEmployee()
	firstPay.Timesheets = fullMonth.Timesheets

	// 13. Monthly frequency paid once a month.
	monthlyCfg := baseConfig()
	monthlyCfg.Frequency = statutory.FrequencyMonthly
	monthlyCfg.SSSBrackets = statutory.SSS2025Schedule()
	monthlyCfg.TaxBrackets = statutory.TRAINTaxBrackets(statutory.FrequencyMonthly)
	monthlyPay := monthlyEmployee()
	for day := 1; day <= 20; day++ {
		monthlyPay.Timesheets = append(monthlyPay.Timesheets, model.TimesheetLine{
			Date: dateOf(2026, 7, day), DayType: "regular", Hours: 8,
		})
	}

	// 14. Minimum-wage earner exempt from tax.
	minWageCfg := baseConfig()
	minWageCfg.MinimumWage = money.FromPesos(755)
	minWage := dailyEmployee()
	minWage.DailyRate = money.FromPesos(755)
	minWage.MonthlyBasic = money.FromPesos(755 * 26)
	minWage.IsMinimumWage = true
	minWage.MinimumWage = money.FromPesos(755)
	minWage.Timesheets = []model.TimesheetLine{
		{Date: "2026-08-03", DayType: "regular", Hours: 8},
		{Date: "2026-08-04", DayType: "regular", Hours: 8, Overtime: 2},
		{Date: "2026-08-05", DayType: "regular", Hours: 8, Night: 2},
	}

	// 15. Stale minimum-wage flag: the rate paid exceeds the minimum.
	staleCfg := baseConfig()
	staleCfg.MinimumWage = money.FromPesos(755)
	stale := dailyEmployee()
	stale.IsMinimumWage = true
	stale.MinimumWage = money.FromPesos(755)
	stale.Timesheets = []model.TimesheetLine{
		{Date: "2026-08-03", DayType: "regular", Hours: 8},
		{Date: "2026-08-04", DayType: "regular", Hours: 8},
	}

	// 16. Unworked regular holiday: paid, then unpaid because of the absence
	// on the preceding workday.
	holidayCfg := baseConfig()
	holidayCfg.Holidays = []model.Holiday{{Date: "2026-09-07", Code: "rh"}, {Date: "2026-09-14", Code: "rh"}}
	holiday := dailyEmployee()
	holiday.Timesheets = []model.TimesheetLine{
		{Date: "2026-09-04", DayType: "regular", Hours: 8},
		{Date: "2026-09-11", DayType: "absent"},
		{Date: "2026-09-15", DayType: "regular", Hours: 8},
	}

	// 17. 13th month below the ₱90,000 exclusion.
	thirteenthCfg := baseConfig()
	thirteenthCfg.RunType = model.RunThirteenthMonth
	thirteenthCfg.PayWithoutTimesheet = false
	thirteenth := monthlyEmployee()
	thirteenth.YTD = model.YtdTotals{Year: 2026, BasicEarned: money.FromPesos(180000), OtherBenefits: money.FromPesos(20000)}

	// 18. 13th month crossing the exclusion with other benefits.
	thirteenthTaxableCfg := baseConfig()
	thirteenthTaxableCfg.RunType = model.RunThirteenthMonth
	thirteenthTaxableCfg.PayWithoutTimesheet = false
	thirteenthTaxable := monthlyEmployee()
	thirteenthTaxable.YTD = model.YtdTotals{Year: 2026, BasicEarned: money.FromPesos(1080000), OtherBenefits: money.FromPesos(60000)}

	// 19. Final pay for a retrenchment.
	finalCfg := baseConfig()
	finalCfg.RunType = model.RunFinalPay
	finalCfg.PayWithoutTimesheet = false
	finalCfg.Meta = map[string]string{
		"separation_reason": "retrenchment",
		"years_of_service":  "5",
	}
	final := monthlyEmployee()
	final.HiredOn = "2021-03-01"
	final.SeparatedOn = "2026-03-15"
	final.LeaveBalanceDays = 7.5
	final.YTD = model.YtdTotals{Year: 2026, BasicEarned: money.FromPesos(90000), ThirteenthMonthPaid: money.FromPesos(2000)}
	final.Timesheets = []model.TimesheetLine{
		{Date: "2026-03-02", DayType: "regular", Hours: 8},
		{Date: "2026-03-03", DayType: "regular", Hours: 8},
	}

	// 20. Annual adjustment producing a refund.
	annualCfg := baseConfig()
	annualCfg.RunType = model.RunAnnualAdjustment
	annualCfg.PayWithoutTimesheet = false
	annual := monthlyEmployee()
	annual.YTD = model.YtdTotals{Year: 2026, TaxableIncome: money.FromPesos(600000), TaxWithheld: money.FromPesos(60000)}

	return []struct {
		name     string
		cfg      model.Config
		employee model.Employee
	}{
		{"regular_semimonthly_monthly_employee", cfg, fullMonth},
		{"regular_semimonthly_daily_employee", cfg, daily},
		{"regular_premium_day_types_monthly", cfg, premium},
		{"regular_premiums_not_entitled", cfg, notEntitled},
		{"regular_no_timesheet_paid", cfg, noTimesheetPaid},
		{"regular_no_timesheet_unpaid", unpaidCfg, noTimesheetUnpaid},
		{"regular_absence_and_paid_leave", cfg, absent},
		{"regular_de_minimis_allowances", deMinimisCfg, allowances},
		{"regular_bonus_commission_compensation", cfg, earnings},
		{"regular_deductions_exceed_earnings", cfg, deductions},
		{"regular_contribution_base_gross", grossCfg, gross},
		{"regular_schedule_first_pay_only", firstPayCfg, firstPay},
		{"regular_monthly_frequency", monthlyCfg, monthlyPay},
		{"regular_minimum_wage_exempt", minWageCfg, minWage},
		{"regular_minimum_wage_flag_stale", staleCfg, stale},
		{"regular_unworked_regular_holiday", holidayCfg, holiday},
		{"thirteenth_month_below_exclusion", thirteenthCfg, thirteenth},
		{"thirteenth_month_above_exclusion", thirteenthTaxableCfg, thirteenthTaxable},
		{"final_pay_retrenchment", finalCfg, final},
		{"annual_adjustment_refund", annualCfg, annual},
	}
}

func dateOf(year int, month int, day int) string {
	return time.Date(year, time.Month(month), day, 0, 0, 0, 0, time.UTC).Format("2006-01-02")
}

func TestParityCorpus(t *testing.T) {
	write := os.Getenv("PARITY_WRITE") == "1"

	if write {
		if err := os.MkdirAll(parityDir, 0o755); err != nil {
			t.Fatalf("create corpus directory: %v", err)
		}
	}

	for _, scenario := range parityScenarios() {
		scenario := scenario

		t.Run(scenario.name, func(t *testing.T) {
			slip, err := Compute(scenario.cfg, scenario.employee)
			if err != nil {
				t.Fatalf("compute: %v", err)
			}

			fixture := fxFixture{
				Name:     scenario.name,
				RunType:  scenario.cfg.RunType,
				Config:   toFxConfig(scenario.cfg),
				Employee: toFxEmployee(scenario.employee),
				Expected: toFxPayslip(slip),
			}

			encoded, err := json.MarshalIndent(fixture, "", "  ")
			if err != nil {
				t.Fatalf("encode fixture: %v", err)
			}

			encoded = append(encoded, '\n')
			path := filepath.Join(parityDir, scenario.name+".json")

			if write {
				if err := os.WriteFile(path, encoded, 0o644); err != nil {
					t.Fatalf("write fixture: %v", err)
				}

				return
			}

			committed, err := os.ReadFile(path)
			if err != nil {
				t.Fatalf("read fixture %s: run PARITY_WRITE=1 go test ./internal/payroll -run TestParityCorpus to create it", path)
			}

			if string(committed) != string(encoded) {
				t.Fatalf("fixture %s is stale; run PARITY_WRITE=1 go test ./internal/payroll -run TestParityCorpus", path)
			}
		})
	}
}
