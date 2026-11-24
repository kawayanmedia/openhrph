<?php

namespace App\Services\Payroll;

use App\Models\Payroll;
use App\Models\PayrollRemittance;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Derives and tracks the statutory remittances for a payroll.
 */
final class RemittanceSchedule
{
    public function __construct(private readonly RemittanceReport $reports) {}

    /**
     * Create or refresh the pending remittance rows for a payroll.
     *
     * @return Collection<int, PayrollRemittance>
     */
    public function sync(Payroll $payroll): Collection
    {
        return app(TenantContext::class)->run((int) $payroll->company_id, function () use ($payroll): Collection {
            $summary = collect($this->reports->agencySummary($payroll))->keyBy('code');
            $sectorFormats = $this->reports->sectorFormats($payroll);
            $agencyCodes = array_keys((array) config('payroll.remittances.agencies', []));
            $allowed = array_values(array_intersect($sectorFormats, $agencyCodes));

            $keep = [];

            foreach ($summary as $code => $row) {
                if (! in_array($code, $allowed, true)) {
                    continue;
                }

                $remittance = PayrollRemittance::query()->firstOrNew([
                    'payroll_id' => $payroll->getKey(),
                    'agency' => $code,
                ]);

                if ($remittance->exists && $remittance->status !== PayrollRemittance::STATUS_PENDING) {
                    $keep[] = $remittance->getKey();

                    continue;
                }

                $remittance->fill([
                    'company_id' => $payroll->company_id,
                    'period_from' => $payroll->date_from,
                    'period_to' => $payroll->date_to,
                    'due_date' => $this->dueDate($payroll, $code),
                    'employee_amount' => $row['employee'],
                    'employer_amount' => $row['employer'],
                    'total_amount' => $row['total'],
                    'status' => PayrollRemittance::STATUS_PENDING,
                ]);

                $remittance->save();
                $keep[] = $remittance->getKey();
            }

            PayrollRemittance::query()
                ->where('payroll_id', $payroll->getKey())
                ->where('status', PayrollRemittance::STATUS_PENDING)
                ->when($keep !== [], fn ($query) => $query->whereNotIn('id', $keep))
                ->forceDelete();

            return PayrollRemittance::query()
                ->where('payroll_id', $payroll->getKey())
                ->orderBy('agency')
                ->get();
        });
    }

    public function markFiled(
        PayrollRemittance $remittance,
        string $reference,
        ?CarbonInterface $filedAt = null,
        ?int $userId = null,
    ): PayrollRemittance {
        $remittance->forceFill([
            'status' => PayrollRemittance::STATUS_FILED,
            'reference_number' => $reference !== '' ? $reference : $remittance->reference_number,
            'filed_at' => $filedAt ?? now(),
            'updated_by' => $userId,
        ])->save();

        return $remittance;
    }

    public function markPaid(
        PayrollRemittance $remittance,
        ?CarbonInterface $paidAt = null,
        ?string $reference = null,
        ?int $userId = null,
    ): PayrollRemittance {
        $remittance->forceFill([
            'status' => PayrollRemittance::STATUS_PAID,
            'reference_number' => $reference !== null && $reference !== '' ? $reference : $remittance->reference_number,
            'paid_at' => $paidAt ?? now(),
            'updated_by' => $userId,
        ])->save();

        return $remittance;
    }

    public function void(PayrollRemittance $remittance, ?int $userId = null): PayrollRemittance
    {
        $remittance->forceFill([
            'status' => PayrollRemittance::STATUS_VOID,
            'updated_by' => $userId,
        ])->save();

        return $remittance;
    }

    private function dueDate(Payroll $payroll, string $agency): ?CarbonInterface
    {
        $date = $payroll->payroll_date ?? $payroll->date_to;

        if ($date === null) {
            return null;
        }

        $days = (int) (config('payroll.remittances.due_days.'.$agency) ?? 15);

        return $date->copy()->addDays($days);
    }
}
