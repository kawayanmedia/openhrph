<?php

namespace App\Services\Payroll\Computation;

/**
 * An employee's year-to-date ledger for a calendar year.
 */
final class YtdTotals
{
    public function __construct(
        public int $year = 0,
        public int $basicEarned = 0,
        public int $grossEarned = 0,
        public int $taxableIncome = 0,
        public int $taxWithheld = 0,
        public int $contributions = 0,
        public int $thirteenthMonthPaid = 0,
        public int $otherBenefits = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            year: (int) ($data['year'] ?? 0),
            basicEarned: (int) ($data['basic_earned'] ?? 0),
            grossEarned: (int) ($data['gross_earned'] ?? 0),
            taxableIncome: (int) ($data['taxable_income'] ?? 0),
            taxWithheld: (int) ($data['tax_withheld'] ?? 0),
            contributions: (int) ($data['contributions'] ?? 0),
            thirteenthMonthPaid: (int) ($data['thirteenth_month_paid'] ?? 0),
            otherBenefits: (int) ($data['other_benefits'] ?? 0),
        );
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'year' => $this->year,
            'basic_earned' => $this->basicEarned,
            'gross_earned' => $this->grossEarned,
            'taxable_income' => $this->taxableIncome,
            'tax_withheld' => $this->taxWithheld,
            'contributions' => $this->contributions,
            'thirteenth_month_paid' => $this->thirteenthMonthPaid,
            'other_benefits' => $this->otherBenefits,
        ];
    }
}
