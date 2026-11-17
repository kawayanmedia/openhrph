package statutory

import (
	"math"
	"strings"

	"github.com/kawayanmedia/openhrph-payroll-engine/internal/money"
)

// GenericComponent is one configured statutory deduction for a country that
// does not use a built-in driver.
type GenericComponent struct {
	Code                 string
	Name                 string
	Category             string
	AppliesTo            string
	Calculation          string
	Base                 string
	EmployeeRate         float64
	EmployerRate         float64
	EmployeeAmount       money.Centavos
	EmployerAmount       money.Centavos
	MinBase              money.Centavos
	MaxBase              money.Centavos
	MaxEmployee          money.Centavos
	MaxEmployer          money.Centavos
	Rounding             string
	Schedule             Schedule
	ReducesTaxableIncome bool
	SortOrder            int
	Bands                []GenericBand
}

// GenericBand is one bracket of a bracket-based component.
type GenericBand struct {
	From           money.Centavos
	To             money.Centavos
	Base           money.Centavos
	EmployeeRate   float64
	EmployerRate   float64
	EmployeeAmount money.Centavos
	EmployerAmount money.Centavos
}

// GenericResult is the split of a generic component for one pay period.
type GenericResult struct {
	Employee money.Centavos
	Employer money.Centavos
	Base     money.Centavos
	Taxable  bool
}

// ComputeGeneric calculates a percentage, bracket or fixed component.
func ComputeGeneric(component GenericComponent, base money.Centavos) GenericResult {
	if base < 0 {
		base = 0
	}

	var employee, employer money.Centavos

	switch strings.ToLower(strings.TrimSpace(component.Calculation)) {
	case "fixed":
		employee = component.EmployeeAmount
		employer = component.EmployerAmount
	case "bracket":
		employee, employer = computeGenericBracket(component, base)
	default:
		employee, employer = computeGenericPercentage(component, base)
	}

	switch strings.ToLower(strings.TrimSpace(component.AppliesTo)) {
	case "employer":
		employee = 0
	case "employee":
		employer = 0
	}

	employee = capAmount(employee, component.MaxEmployee)
	employer = capAmount(employer, component.MaxEmployer)

	return GenericResult{
		Employee: employee,
		Employer: employer,
		Base:     base,
		Taxable:  component.ReducesTaxableIncome,
	}
}

func computeGenericPercentage(component GenericComponent, base money.Centavos) (money.Centavos, money.Centavos) {
	if component.MinBase > 0 && base < component.MinBase {
		base = component.MinBase
	}

	if component.MaxBase > 0 && base > component.MaxBase {
		base = component.MaxBase
	}

	return roundGeneric(float64(base)*component.EmployeeRate, component.Rounding),
		roundGeneric(float64(base)*component.EmployerRate, component.Rounding)
}

func computeGenericBracket(component GenericComponent, base money.Centavos) (money.Centavos, money.Centavos) {
	for _, band := range component.Bands {
		if base < band.From {
			continue
		}

		if band.To > 0 && base > band.To {
			continue
		}

		excess := base - band.From
		if excess < 0 {
			excess = 0
		}

		employee := band.Base.
			Add(roundGeneric(float64(excess)*band.EmployeeRate, component.Rounding)).
			Add(band.EmployeeAmount)
		employer := band.Base.
			Add(roundGeneric(float64(excess)*band.EmployerRate, component.Rounding)).
			Add(band.EmployerAmount)

		return employee, employer
	}

	return 0, 0
}

// PerPeriod divides a monthly component across the payroll cut-offs.
func PerPeriod(result GenericResult, divisor int) GenericResult {
	if divisor <= 1 {
		return result
	}

	result.Employee /= money.Centavos(divisor)
	result.Employer /= money.Centavos(divisor)

	return result
}

func capAmount(amount, cap money.Centavos) money.Centavos {
	if cap > 0 && amount > cap {
		return cap
	}

	return amount
}

func roundGeneric(amount float64, rounding string) money.Centavos {
	switch strings.ToLower(strings.TrimSpace(rounding)) {
	case "floor", "down":
		return money.Centavos(math.Floor(amount))
	case "ceil", "up":
		return money.Centavos(math.Ceil(amount))
	default:
		return money.Centavos(math.Round(amount))
	}
}
