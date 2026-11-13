// Package statutory implements Philippine statutory contributions and taxes.
//
// Every calculator is pure: rates and brackets are supplied by the caller
// (originally loaded from the versioned statutory tables), so historical runs
// remain reproducible after a rate change.
package statutory

import (
	"errors"
	"fmt"
	"strings"

	"github.com/kawayanmedia/openhrph-payroll-engine/internal/money"
)

// Schedule expresses when a deduction is taken.
type Schedule string

const (
	ScheduleEveryPay Schedule = "every_pay"
	ScheduleFirstPay Schedule = "first_pay"
	ScheduleLastPay  Schedule = "last_pay"
)

// Frequency is the payroll group's pay frequency.
type Frequency string

const (
	FrequencyMonthly     Frequency = "monthly"
	FrequencySemiMonthly Frequency = "semi-monthly"
)

// NormalizeSchedule maps the values stored on payroll groups to a Schedule.
// Legacy rows use variants such as "everypay" or numeric cut-off ids.
func NormalizeSchedule(value string) Schedule {
	normalized := strings.ToLower(strings.TrimSpace(value))
	normalized = strings.ReplaceAll(normalized, "-", "_")
	normalized = strings.ReplaceAll(normalized, " ", "_")

	switch normalized {
	case "", "every_pay", "everypay", "0":
		return ScheduleEveryPay
	case "first_pay", "firstpay", "1":
		return ScheduleFirstPay
	case "last_pay", "lastpay", "2":
		return ScheduleLastPay
	default:
		return Schedule(normalized)
	}
}

// Divisor reports how many parts of a monthly amount fall in this run.
// A zero divisor means the deduction does not apply to this cut-off.
func Divisor(schedule Schedule, frequency Frequency, cutoff string) int {
	switch schedule {
	case "", ScheduleEveryPay:
		if frequency == FrequencySemiMonthly {
			return 2
		}

		return 1
	case ScheduleFirstPay, ScheduleLastPay:
		if string(schedule) == cutoff {
			return 1
		}

		return 0
	default:
		if string(schedule) == cutoff {
			return 1
		}

		return 0
	}
}

// SSSBracket is one row of the SSS contribution schedule for a monthly salary
// credit (MSC) band. Amounts are monthly centavos.
type SSSBracket struct {
	MSCFrom money.Centavos
	MSCTo   money.Centavos // zero means unbounded
	MSC     money.Centavos // salary credit billed for the band
	EE      money.Centavos
	ER      money.Centavos
	EC      money.Centavos
	WISPEE  money.Centavos
	WISPER  money.Centavos
}

// SSSResult is the monthly SSS split.
type SSSResult struct {
	MSC    money.Centavos
	EE     money.Centavos
	ER     money.Centavos
	EC     money.Centavos
	WISPEE money.Centavos
	WISPER money.Centavos
}

// EmployeeShare is the employee's monthly contribution including WISP/MPF.
func (r SSSResult) EmployeeShare() money.Centavos { return r.EE.Add(r.WISPEE) }

// EmployerShare is the employer's monthly contribution including EC and WISP.
func (r SSSResult) EmployerShare() money.Centavos { return r.ER.Add(r.EC).Add(r.WISPER) }

// Total is the full monthly remittance.
func (r SSSResult) Total() money.Centavos { return r.EmployeeShare().Add(r.EmployerShare()) }

// ErrNoBracket is returned when a salary falls outside every configured band.
var ErrNoBracket = errors.New("no statutory bracket matches the salary")

// ComputeSSS selects the monthly bracket containing the salary and returns it.
func ComputeSSS(monthlyBasic money.Centavos, brackets []SSSBracket) (SSSResult, error) {
	if len(brackets) == 0 {
		return SSSResult{}, fmt.Errorf("sss: %w", ErrNoBracket)
	}

	for _, bracket := range brackets {
		if monthlyBasic >= bracket.MSCFrom && (bracket.MSCTo == 0 || monthlyBasic <= bracket.MSCTo) {
			return SSSResult{
				MSC:    bracket.MSC,
				EE:     bracket.EE,
				ER:     bracket.ER,
				EC:     bracket.EC,
				WISPEE: bracket.WISPEE,
				WISPER: bracket.WISPER,
			}, nil
		}
	}

	return SSSResult{}, fmt.Errorf("sss: %w (basic %s)", ErrNoBracket, monthlyBasic)
}

// SSS2025Bracket builds one row of the 2025 schedule from its salary credit.
// The 2025 schedule is 15% total: 5% employee and 10% employer, with the
// regular MSC capped at ₱20,000 and the excess handled by WISP/MPF.
func SSS2025Bracket(from, to, msc money.Centavos) SSSBracket {
	regular := money.Min(msc, money.FromPesos(20000))
	wisp := money.Centavos(0)

	if msc > money.FromPesos(20000) {
		wisp = msc.Sub(money.FromPesos(20000))
	}

	return SSSBracket{
		MSCFrom: from,
		MSCTo:   to,
		MSC:     msc,
		EE:      regular.Mul(0.05),
		ER:      regular.Mul(0.10),
		EC:      sssEmployeesCompensation(msc),
		WISPEE:  wisp.Mul(0.05),
		WISPER:  wisp.Mul(0.10),
	}
}

// sssEmployeesCompensation returns the employer EC contribution for a salary
// credit: ₱10 up to ₱14,500 and ₱30 from ₱15,000 (SSS Circular No. 2024-006).
func sssEmployeesCompensation(msc money.Centavos) money.Centavos {
	if msc >= money.FromPesos(15000) {
		return money.FromPesos(30)
	}

	return money.FromPesos(10)
}

// SSS2025Schedule generates the 2025 SSS schedule: salary credit from ₱5,000
// to ₱35,000 in ₱500 steps. The circular bills each band from half a step
// below its credit (₱5,250-₱5,749.99 is credited ₱5,500), with anything below
// ₱5,250 billed at the ₱5,000 floor.
func SSS2025Schedule() []SSSBracket {
	step := money.FromPesos(500)
	halfStep := money.FromPesos(250)
	floor := money.FromPesos(5000)
	ceiling := money.FromPesos(35000)

	brackets := []SSSBracket{
		SSS2025Bracket(0, floor.Add(halfStep).Sub(1), floor),
	}

	for msc := floor.Add(step); msc < ceiling; msc += step {
		from := msc.Sub(halfStep)
		brackets = append(brackets, SSS2025Bracket(from, from.Add(step).Sub(1), msc))
	}

	brackets = append(brackets, SSS2025Bracket(ceiling.Sub(halfStep), 0, ceiling))

	return brackets
}

// PhilHealthConfig describes the premium schedule in force.
type PhilHealthConfig struct {
	Rate    float64        // premium rate, e.g. 0.05
	Floor   money.Centavos // minimum monthly basic
	Ceiling money.Centavos // maximum monthly basic
}

// DefaultPhilHealthConfig is the 5% schedule with a ₱10,000 floor and
// ₱100,000 ceiling.
func DefaultPhilHealthConfig() PhilHealthConfig {
	return PhilHealthConfig{
		Rate:    0.05,
		Floor:   money.FromPesos(10000),
		Ceiling: money.FromPesos(100000),
	}
}

// PhilHealthResult is the monthly premium split.
type PhilHealthResult struct {
	Base     money.Centavos
	Premium  money.Centavos
	Employee money.Centavos
	Employer money.Centavos
}

// ComputePhilHealth splits the premium 50/50 between employee and employer.
func ComputePhilHealth(monthlyBasic money.Centavos, cfg PhilHealthConfig) PhilHealthResult {
	base := monthlyBasic.ClampTo(cfg.Floor, cfg.Ceiling)
	premium := base.Mul(cfg.Rate)
	employee := premium.Half()

	return PhilHealthResult{
		Base:     base,
		Premium:  premium,
		Employee: employee,
		Employer: premium.Sub(employee),
	}
}

// PagIBIGConfig describes the HDMF contribution schedule.
type PagIBIGConfig struct {
	Rate          float64
	LowRate       float64
	LowThreshold  money.Centavos
	MaxFundSalary money.Centavos
}

// DefaultPagIBIGConfig is the 2% schedule capped at a ₱10,000 fund salary
// (₱200 maximum on each side).
func DefaultPagIBIGConfig() PagIBIGConfig {
	return PagIBIGConfig{
		Rate:          0.02,
		LowRate:       0.01,
		LowThreshold:  money.FromPesos(1500),
		MaxFundSalary: money.FromPesos(10000),
	}
}

// PagIBIGResult is the monthly HDMF split.
type PagIBIGResult struct {
	Base     money.Centavos
	Employee money.Centavos
	Employer money.Centavos
}

// ComputePagIBIG applies the fund-salary cap before computing the shares.
func ComputePagIBIG(monthlyBasic money.Centavos, cfg PagIBIGConfig) PagIBIGResult {
	base := money.Min(monthlyBasic, cfg.MaxFundSalary)

	rate := cfg.Rate
	if monthlyBasic <= cfg.LowThreshold && cfg.LowRate > 0 {
		rate = cfg.LowRate
	}

	employee := base.Mul(rate)
	employer := base.Mul(cfg.Rate)

	return PagIBIGResult{Base: base, Employee: employee, Employer: employer}
}

// TaxBracket is one withholding-tax band for a pay frequency.
type TaxBracket struct {
	From    money.Centavos
	To      money.Centavos // zero means unlimited
	BaseTax money.Centavos
	Rate    float64
}

// ComputeTax returns the withholding tax for a taxable amount.
func ComputeTax(taxable money.Centavos, brackets []TaxBracket) (money.Centavos, error) {
	if len(brackets) == 0 {
		return 0, fmt.Errorf("tax: %w", ErrNoBracket)
	}

	for _, bracket := range brackets {
		if taxable >= bracket.From && (bracket.To == 0 || taxable <= bracket.To) {
			if taxable <= bracket.From {
				return bracket.BaseTax, nil
			}

			return bracket.BaseTax.Add(taxable.Sub(bracket.From).Mul(bracket.Rate)), nil
		}
	}

	return 0, fmt.Errorf("tax: %w (taxable %s)", ErrNoBracket, taxable)
}

// TRAINTaxBrackets is the BIR withholding table effective 2023 onwards.
// Values follow the published BIR tables (RR 11-2018 as amended by the TRAIN
// law) rather than being derived by division, so they match payroll reports
// to the centavo.
func TRAINTaxBrackets(f Frequency) []TaxBracket {
	if f == FrequencySemiMonthly {
		return []TaxBracket{
			{From: 0, To: money.FromPesos(10416.99), BaseTax: 0, Rate: 0},
			{From: money.FromPesos(10417), To: money.FromPesos(16666.99), BaseTax: 0, Rate: 0.15},
			{From: money.FromPesos(16667), To: money.FromPesos(33332.99), BaseTax: money.FromPesos(937.50), Rate: 0.20},
			{From: money.FromPesos(33333), To: money.FromPesos(83332.99), BaseTax: money.FromPesos(4270.70), Rate: 0.25},
			{From: money.FromPesos(83333), To: money.FromPesos(333332.99), BaseTax: money.FromPesos(16770.70), Rate: 0.30},
			{From: money.FromPesos(333333), To: 0, BaseTax: money.FromPesos(91770.70), Rate: 0.35},
		}
	}

	return []TaxBracket{
		{From: 0, To: money.FromPesos(20832.99), BaseTax: 0, Rate: 0},
		{From: money.FromPesos(20833), To: money.FromPesos(33332.99), BaseTax: 0, Rate: 0.15},
		{From: money.FromPesos(33333), To: money.FromPesos(66666.99), BaseTax: money.FromPesos(1875.00), Rate: 0.20},
		{From: money.FromPesos(66667), To: money.FromPesos(166666.99), BaseTax: money.FromPesos(8541.80), Rate: 0.25},
		{From: money.FromPesos(166667), To: money.FromPesos(666666.99), BaseTax: money.FromPesos(33541.80), Rate: 0.30},
		{From: money.FromPesos(666667), To: 0, BaseTax: money.FromPesos(183541.80), Rate: 0.35},
	}
}

// MinimumWage is a regional minimum wage row.
type MinimumWage struct {
	Region      string
	Amount      money.Centavos
	Effectivity string
}

// BelowMinimum reports whether a daily rate is below the given minimum wage.
func BelowMinimum(dailyRate money.Centavos, wage MinimumWage) bool {
	return wage.Amount > 0 && dailyRate < wage.Amount
}
