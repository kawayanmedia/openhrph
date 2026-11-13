// Package money provides exact centavo arithmetic.
//
// All payroll amounts are handled as integer centavos. Workers are paid in
// centavos; nothing in the engine uses floats for stored money, which keeps
// totals reproducible and avoids rounding drift across runs.
package money

import (
	"fmt"
	"math"
	"strconv"
	"strings"
)

// Centavos is a monetary amount in hundredths of a peso.
type Centavos int64

// Zero is the additive identity.
const Zero Centavos = 0

// FromPesos converts a peso amount to centavos using half-up rounding.
func FromPesos(pesos float64) Centavos {
	return Centavos(math.Round(pesos * 100))
}

// FromPesosString parses a decimal string such as "1234.56" into centavos.
// Empty or invalid input yields zero.
func FromPesosString(value string) Centavos {
	value = strings.TrimSpace(value)
	if value == "" {
		return 0
	}

	value = strings.ReplaceAll(value, ",", "")

	parsed, err := strconv.ParseFloat(value, 64)
	if err != nil {
		return 0
	}

	return FromPesos(parsed)
}

// Pesos returns the amount as a float, for reporting only.
func (c Centavos) Pesos() float64 {
	return float64(c) / 100
}

// String renders the amount with two decimals, e.g. "1234.56".
func (c Centavos) String() string {
	sign := ""
	value := int64(c)
	if value < 0 {
		sign = "-"
		value = -value
	}

	return fmt.Sprintf("%s%d.%02d", sign, value/100, value%100)
}

// Add returns c+other.
func (c Centavos) Add(other Centavos) Centavos { return c + other }

// Sub returns c-other.
func (c Centavos) Sub(other Centavos) Centavos { return c - other }

// Mul returns c scaled by factor using half-up rounding.
func (c Centavos) Mul(factor float64) Centavos {
	return Centavos(math.Round(float64(c) * factor))
}

// Split divides the amount into parts using half-up rounding on the first
// part so the parts always add back to the original amount.
func (c Centavos) Split(parts int) []Centavos {
	if parts <= 0 {
		return nil
	}

	if parts == 1 {
		return []Centavos{c}
	}

	base := c / Centavos(parts)
	remainder := c - base*Centavos(parts)

	out := make([]Centavos, parts)
	for i := range out {
		out[i] = base
	}

	if remainder != 0 {
		out[0] += remainder
	}

	return out
}

// Half returns exactly half of the amount, rounded half-up.
func (c Centavos) Half() Centavos {
	return Centavos(math.Round(float64(c) / 2))
}

// ClampTo limits the amount to the inclusive range [low, high].
func (c Centavos) ClampTo(low, high Centavos) Centavos {
	if high > 0 && c > high {
		c = high
	}

	if c < low {
		c = low
	}

	return c
}

// Min returns the smaller amount.
func Min(a, b Centavos) Centavos {
	if a < b {
		return a
	}

	return b
}

// Max returns the larger amount.
func Max(a, b Centavos) Centavos {
	if a > b {
		return a
	}

	return b
}
