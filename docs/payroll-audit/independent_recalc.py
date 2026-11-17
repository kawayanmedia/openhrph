#!/usr/bin/env python3
"""Independent payroll calculation oracle.

This script recomputes the expected values used by the Go tests in
``payroll-engine`` from the published Philippine statutory tables and the
DOLE premium table. It deliberately shares no code with the payroll engine:
the arithmetic here is written from the circulars and the Labor Code, in
Decimal pesos rounded half-up at each money step.

Run it to reproduce the numbers quoted in
docs/payroll-audit/2026-11-payroll-compliance-audit.md section 7:

    python3 docs/payroll-audit/independent_recalc.py

Exit code 0 means every oracle value matches the value asserted in the Go
suite. The F-25 and F-26 scenarios are now enforced by the Go tests and should
match the law-based values below.
"""

from __future__ import annotations

from decimal import Decimal, ROUND_HALF_UP
from typing import Iterable

CENT = Decimal("0.01")
ZERO = Decimal("0.00")


def money(value: Decimal | int | str) -> Decimal:
    """Round a peso amount to centavos, half-up, the way payroll does."""
    return Decimal(value).quantize(CENT, rounding=ROUND_HALF_UP)


def mul(amount: Decimal, factor: Decimal | float | str) -> Decimal:
    return money(Decimal(amount) * Decimal(str(factor)))


def split(amount: Decimal, parts: int) -> Decimal:
    """First part of an amount divided into equal parts (remainder first)."""
    if parts <= 1:
        return money(amount)

    cents = int((Decimal(amount) * 100).to_integral_value(rounding=ROUND_HALF_UP))
    base, remainder = divmod(cents, parts)

    return money(Decimal(base + remainder) / 100)


# ---------------------------------------------------------------------------
# Statutory tables (transcribed from the official issuances)
# ---------------------------------------------------------------------------


def sss_2025_schedule() -> list[dict]:
    """SSS Circular No. 2024-006: MSC 5,000-35,000 in 500 steps.

    Each band runs from half a step below its salary credit to half a step
    less one centavo above it; the excess over the 20,000 regular MSC is
    billed as WISP/MPF. EC is 10 up to MSC 14,500 and 30 from MSC 15,000.
    """
    credits = [Decimal(5000)]
    while credits[-1] < Decimal(35000):
        credits.append(credits[-1] + Decimal(500))

    bands = []
    for credit in credits:
        if credit == Decimal(5000):
            low = Decimal(0)
            high = Decimal(5249.99)
        elif credit == Decimal(35000):
            low = Decimal(34750)
            high = None
        else:
            low = credit - Decimal(250)
            high = low + Decimal(499.99)

        regular = min(credit, Decimal(20000))
        wisp = max(ZERO, credit - Decimal(20000))

        bands.append(
            {
                "low": low,
                "high": high,
                "msc": credit,
                "ee": mul(regular, "0.05"),
                "er": mul(regular, "0.10"),
                "wisp_ee": mul(wisp, "0.05"),
                "wisp_er": mul(wisp, "0.10"),
                "ec": Decimal(30) if credit >= Decimal(15000) else Decimal(10),
            }
        )

    return bands


def sss(salary: Decimal) -> dict:
    for band in sss_2025_schedule():
        if salary >= band["low"] and (band["high"] is None or salary <= band["high"]):
            return band

    raise ValueError(f"no SSS band for {salary}")


def phil_health(salary: Decimal, rate="0.05", floor="10000", ceiling="100000") -> dict:
    base = min(max(salary, Decimal(floor)), Decimal(ceiling))
    premium = mul(base, rate)
    employee = money(premium / 2)

    return {"base": base, "premium": premium, "employee": employee, "employer": premium - employee}


def pag_ibig(salary: Decimal, max_fund="10000", low_threshold="1500") -> dict:
    base = min(salary, Decimal(max_fund))
    rate = Decimal("0.01") if salary <= Decimal(low_threshold) else Decimal("0.02")

    return {"base": base, "employee": mul(base, rate), "employer": mul(base, "0.02")}


TRAIN = {
    "monthly": [
        (Decimal(0), Decimal("20832.99"), Decimal(0), Decimal("0")),
        (Decimal("20833"), Decimal("33332.99"), Decimal(0), Decimal("0.15")),
        (Decimal("33333"), Decimal("66666.99"), Decimal("1875.00"), Decimal("0.20")),
        (Decimal("66667"), Decimal("166666.99"), Decimal("8541.80"), Decimal("0.25")),
        (Decimal("166667"), Decimal("666666.99"), Decimal("33541.80"), Decimal("0.30")),
        (Decimal("666667"), None, Decimal("183541.80"), Decimal("0.35")),
    ],
    "semi-monthly": [
        (Decimal(0), Decimal("10416.99"), Decimal(0), Decimal("0")),
        (Decimal("10417"), Decimal("16666.99"), Decimal(0), Decimal("0.15")),
        (Decimal("16667"), Decimal("33332.99"), Decimal("937.50"), Decimal("0.20")),
        (Decimal("33333"), Decimal("83332.99"), Decimal("4270.70"), Decimal("0.25")),
        (Decimal("83333"), Decimal("333332.99"), Decimal("16770.70"), Decimal("0.30")),
        (Decimal("333333"), None, Decimal("91770.70"), Decimal("0.35")),
    ],
}

# Annual table used for the 13th-month aggregation (TRAIN law, 2023 onwards).
TRAIN_ANNUAL = [
    (Decimal(0), Decimal("250000"), Decimal(0), Decimal("0")),
    (Decimal("250000.01"), Decimal("400000"), Decimal(0), Decimal("0.15")),
    (Decimal("400000.01"), Decimal("800000"), Decimal("22500"), Decimal("0.20")),
    (Decimal("800000.01"), Decimal("2000000"), Decimal("102500"), Decimal("0.25")),
    (Decimal("2000000.01"), Decimal("8000000"), Decimal("402500"), Decimal("0.30")),
    (Decimal("8000000.01"), None, Decimal("2202500"), Decimal("0.35")),
]


def tax(taxable: Decimal, table: Iterable) -> Decimal:
    for low, high, base, rate in table:
        if taxable >= low and (high is None or taxable <= high):
            return money(base + mul(max(ZERO, taxable - low), rate))

    raise ValueError(f"no tax band for {taxable}")


# ---------------------------------------------------------------------------
# Earning rules (Labor Code / DOLE handbook)
# ---------------------------------------------------------------------------

DAY_PREMIUM = {
    "regular": Decimal("1.00"),
    "rd": Decimal("1.30"),
    "sh": Decimal("1.30"),
    "rd_sh": Decimal("1.50"),
    "rh": Decimal("2.00"),
    "rd_rh": Decimal("2.60"),
    "2rh": Decimal("3.00"),
    "rd_2rh": Decimal("3.90"),
    "rh_sh": Decimal("2.60"),
    "rd_rh_sh": Decimal("3.00"),
}

OVERTIME = Decimal("1.25")
NIGHT = Decimal("0.10")
THIRTEENTH_EXEMPTION = Decimal("90000")


def daily_rate(monthly: Decimal, days_per_year: int = 261) -> Decimal:
    return mul(monthly, Decimal(12) / Decimal(days_per_year))


def hourly_rate(monthly: Decimal, days_per_year: int = 261, hours_per_day: int = 8) -> Decimal:
    return money(daily_rate(monthly, days_per_year) / Decimal(hours_per_day))


def thirteenth_month(basic_for_year: Decimal) -> Decimal:
    return money(basic_for_year / 12)


def benefit_excess(amount: Decimal, other_benefits: Decimal) -> Decimal:
    prior = max(ZERO, other_benefits - THIRTEENTH_EXEMPTION)

    return max(ZERO, max(ZERO, amount + other_benefits - THIRTEENTH_EXEMPTION) - prior)


# ---------------------------------------------------------------------------
# Scenarios mirroring the Go suite
# ---------------------------------------------------------------------------


def scenario_earnings_deductions_loan() -> dict:
    """TestAdditionalEarningsDeductionsAndLoansReconcile."""
    basic = Decimal("26100")
    period_basic = split(basic, 2)
    allowances = Decimal("2500") + Decimal("1000")
    gross = money(period_basic + allowances)

    sss_band = sss(basic)
    sss_ee = split(sss_band["ee"], 2)
    wisp_ee = split(sss_band["wisp_ee"], 2)
    ph = phil_health(basic)
    ph_ee = split(ph["employee"], 2)
    pi_ee = split(pag_ibig(basic)["employee"], 2)

    # Rice subsidy is capped at the 2,000 monthly de minimis ceiling.
    non_taxable = split(Decimal("2000"), 2)
    taxable = money(gross - non_taxable - sss_ee - wisp_ee - ph_ee - pi_ee)
    withholding = tax(taxable, TRAIN["semi-monthly"])
    other_deductions = money(Decimal("500") + Decimal("1000"))
    total_deductions = money(sss_ee + wisp_ee + ph_ee + pi_ee + withholding + other_deductions)

    return {
        "gross": gross,
        "non_taxable": non_taxable,
        "taxable": taxable,
        "withholding": withholding,
        "other_deductions": other_deductions,
        "total_deductions": total_deductions,
        "net": money(gross - total_deductions),
    }


def scenario_contribution_ceilings() -> dict:
    """TestContributionCeilingsInARun."""
    basic = Decimal("250000")
    gross = split(basic, 2)

    sss_band = sss(basic)
    sss_ee, wisp_ee = split(sss_band["ee"], 2), split(sss_band["wisp_ee"], 2)
    sss_er, wisp_er = split(sss_band["er"], 2), split(sss_band["wisp_er"], 2)
    ec = split(sss_band["ec"], 2)
    ph = phil_health(basic)
    ph_ee, ph_er = split(ph["employee"], 2), split(ph["employer"], 2)
    pi = pag_ibig(basic)
    pi_ee, pi_er = split(pi["employee"], 2), split(pi["employer"], 2)

    taxable = money(gross - sss_ee - wisp_ee - ph_ee - pi_ee)
    withholding = tax(taxable, TRAIN["semi-monthly"])
    total_deductions = money(sss_ee + wisp_ee + ph_ee + pi_ee + withholding)

    return {
        "gross": gross,
        "sss_ee": sss_ee,
        "wisp_ee": wisp_ee,
        "sss_er": sss_er,
        "wisp_er": wisp_er,
        "ec": ec,
        "philhealth_ee": ph_ee,
        "pagibig_ee": pi_ee,
        "taxable": taxable,
        "withholding": withholding,
        "total_deductions": total_deductions,
        "net": money(gross - total_deductions),
        "employer_cost": money(sss_er + wisp_er + ec + ph_er + pi_er),
    }


def scenario_thirteenth_aggregation() -> dict:
    """TestThirteenthMonthAggregatesOtherBenefits."""
    amount = thirteenth_month(Decimal("3600000"))
    taxable = benefit_excess(amount, Decimal("60000"))
    withholding = tax(taxable, TRAIN_ANNUAL)

    return {
        "thirteenth": amount,
        "taxable": taxable,
        "non_taxable": money(amount - taxable),
        "withholding": withholding,
        "net": money(amount - withholding),
    }


def scenario_night_overtime() -> dict:
    """TestNightOvertimePaysTheOvertimeBaseAndTheDifferential."""
    hourly = hourly_rate(Decimal("26100"))

    return {
        "overtime": mul(hourly, Decimal(2) * OVERTIME),
        "night": mul(hourly, Decimal(2) * NIGHT),
        "night_ot": mul(hourly, Decimal(2) * OVERTIME * NIGHT),
    }


def scenario_minimum_wage() -> dict:
    """F-25: exemption due on a ten-day period at the minimum wage."""
    daily = Decimal("640")
    wage_earned = money(daily * 10)
    hourly = money(daily / 8)
    premiums = money(mul(hourly, Decimal(2) * OVERTIME) + mul(hourly, Decimal(2) * NIGHT))

    return {"gross": money(wage_earned + premiums), "exemption": money(wage_earned + premiums)}


def scenario_final_pay_thirteenth() -> dict:
    """F-26: pro-rated 13th month on basic actually earned."""
    earned = Decimal("144000") + money(Decimal("1200") * 5)

    return {"earned_basic": earned, "thirteenth": thirteenth_month(earned)}


# ---------------------------------------------------------------------------
# Comparison harness
# ---------------------------------------------------------------------------

CASES = [
    ("earnings/deductions/loan", scenario_earnings_deductions_loan, {
        "gross": "16550.00",
        "non_taxable": "1000.00",
        "taxable": "14473.75",
        "withholding": "608.51",
        "other_deductions": "1500.00",
        "total_deductions": "3184.76",
        "net": "13365.24",
    }),
    ("contribution ceilings", scenario_contribution_ceilings, {
        "gross": "125000.00",
        "sss_ee": "500.00",
        "wisp_ee": "375.00",
        "sss_er": "1000.00",
        "wisp_er": "750.00",
        "ec": "15.00",
        "philhealth_ee": "1250.00",
        "pagibig_ee": "100.00",
        "taxable": "122775.00",
        "withholding": "28603.30",
        "total_deductions": "30828.30",
        "net": "94171.70",
        "employer_cost": "3115.00",
    }),
    ("13th-month aggregation", scenario_thirteenth_aggregation, {
        "thirteenth": "300000.00",
        "taxable": "270000.00",
        "non_taxable": "30000.00",
        "withholding": "3000.00",
        "net": "297000.00",
    }),
    ("night overtime", scenario_night_overtime, {
        "overtime": "375.00",
        "night": "30.00",
        "night_ot": "37.50",
    }),
    ("minimum wage exemption (F-25, fixed)", scenario_minimum_wage, {
        "gross": "6616.00",
        "exemption": "6616.00",
    }),
    ("final pay 13th month (F-26, fixed)", scenario_final_pay_thirteenth, {
        "earned_basic": "150000.00",
        "thirteenth": "12500.00",
    }),
]

ENGINE_GAPS = {}


def main() -> int:
    failures = 0

    for name, compute, expected in CASES:
        actual = compute()
        gaps = ENGINE_GAPS.get(name, {})

        print(f"\n{name}")
        for field, want in expected.items():
            got = actual[field]
            ok = got == Decimal(want)
            failures += 0 if ok else 1

            line = f"  {field:<20} oracle {got:>12}  expected {Decimal(want):>12}  {'OK' if ok else 'MISMATCH'}"

            if field in gaps:
                line += f"   engine now {Decimal(gaps[field]):>12}  (open finding)"

            print(line)

    print()
    if failures:
        print(f"{failures} oracle value(s) disagree with the Go suite.")
        return 1

    print("All oracle values match the Go suite.")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
