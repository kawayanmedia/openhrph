<?php

namespace App\Services\Payroll;

use App\Models\BankFileTemplate;
use App\Models\CompanyBank;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;

/**
 * Renders a payroll bank-credit file from a configurable template.
 */
final class BankFileGenerator
{
    /**
     * The values a template column may read.
     *
     * @var array<string, string>
     */
    public const SOURCES = [
        'employee_number' => 'Employee number',
        'employee_name' => 'Employee full name',
        'last_name' => 'Last name',
        'first_name' => 'First name',
        'bank_name' => 'Bank name',
        'bank_branch' => 'Bank branch',
        'account_name' => 'Account name',
        'account_number' => 'Account number',
        'amount' => 'Net pay',
        'company_name' => 'Company name',
        'company_code' => 'Company code',
        'payment_date' => 'Payment date',
        'period_from' => 'Period from',
        'period_to' => 'Period to',
        'payroll_date' => 'Payroll date',
        'reference' => 'Payroll reference',
    ];

    /**
     * @return array{
     *     headers: array<int, string>,
     *     rows: array<int, array<int, string>>,
     *     total: int,
     *     included: int,
     *     excluded: int,
     *     missing: array<int, array{employee_number: string, name: string, reason: string}>,
     *     duplicates: array<int, string>,
     *     tokens: array<string, string>
     * }
     */
    public function build(
        Payroll $payroll,
        BankFileTemplate $template,
        ?CompanyBank $debitAccount = null,
        ?string $paymentDate = null,
    ): array {
        return app(TenantContext::class)->run((int) $payroll->company_id, function () use ($payroll, $template, $debitAccount, $paymentDate): array {
            $details = $payroll->payrollDetails()
                ->with(['employee.bank'])
                ->orderBy('id')
                ->get();

            $tokens = $this->tokens($payroll, $debitAccount, $paymentDate);
            $columns = $this->columns($template);

            $rows = [];
            $missing = [];
            $excluded = 0;
            $total = 0;
            $accounts = [];

            foreach ($details as $detail) {
                $employee = $detail->employee;
                $netPay = (int) $detail->getRawOriginal('net_pay');

                if ($employee === null || $netPay <= 0) {
                    $excluded++;

                    continue;
                }

                if (strtolower((string) ($employee->pay_mode ?? 'bank')) !== 'bank') {
                    $excluded++;

                    continue;
                }

                $accountNumber = trim((string) $employee->bank_account_number);

                if ($accountNumber === '') {
                    $missing[] = [
                        'employee_number' => (string) $employee->employee_number,
                        'name' => trim($employee->first_name.' '.$employee->last_name),
                        'reason' => 'No bank account number',
                    ];

                    continue;
                }

                $accounts[$accountNumber] = ($accounts[$accountNumber] ?? 0) + 1;

                $values = $this->values($payroll, $detail, $template, $paymentDate);
                $row = [];

                foreach ($columns as $column) {
                    $row[] = $values[(string) ($column['source'] ?? '')] ?? '';
                }

                $rows[] = $row;
                $total += $netPay;
            }

            $duplicates = array_keys(array_filter($accounts, fn (int $count): bool => $count > 1));

            return [
                'headers' => array_map(fn (array $column): string => (string) ($column['label'] ?? $column['source'] ?? ''), $columns),
                'rows' => $rows,
                'total' => $total,
                'included' => count($rows),
                'excluded' => $excluded,
                'missing' => $missing,
                'duplicates' => $duplicates,
                'tokens' => $tokens,
            ];
        });
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function columns(BankFileTemplate $template): array
    {
        return collect($template->columns ?? [])
            ->filter(fn ($column): bool => is_array($column) && isset($column['source']))
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function values(Payroll $payroll, PayrollDetail $detail, BankFileTemplate $template, ?string $paymentDate): array
    {
        $employee = $detail->employee;
        $netPay = (int) $detail->getRawOriginal('net_pay');
        $date = $paymentDate !== null && $paymentDate !== '' ? Carbon::parse($paymentDate) : ($payroll->payroll_date ?? now());
        $dateFormat = $template->date_format ?: 'Ymd';

        return [
            'employee_number' => (string) $employee?->employee_number,
            'employee_name' => trim(($employee?->first_name ?? '').' '.($employee?->last_name ?? '')),
            'last_name' => (string) $employee?->last_name,
            'first_name' => (string) $employee?->first_name,
            'bank_name' => (string) ($employee?->bank?->name ?? ''),
            'bank_branch' => (string) ($employee?->bank_branch ?? ''),
            'account_name' => (string) ($employee?->bank_account_name ?: trim(($employee?->first_name ?? '').' '.($employee?->last_name ?? ''))),
            'account_number' => (string) $employee?->bank_account_number,
            'amount' => $template->amount_in_centavos
                ? (string) $netPay
                : number_format($netPay / 100, 2, '.', ''),
            'company_name' => (string) ($payroll->company?->name ?? ''),
            'company_code' => (string) ($payroll->company?->code ?? ''),
            'payment_date' => $date->format($dateFormat),
            'period_from' => $payroll->date_from?->format($dateFormat) ?? '',
            'period_to' => $payroll->date_to?->format($dateFormat) ?? '',
            'payroll_date' => $payroll->payroll_date?->format($dateFormat) ?? '',
            'reference' => 'PAYROLL-'.$payroll->getKey(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function tokens(Payroll $payroll, ?CompanyBank $debitAccount, ?string $paymentDate): array
    {
        $date = $paymentDate !== null && $paymentDate !== '' ? Carbon::parse($paymentDate) : ($payroll->payroll_date ?? now());

        return [
            '{{company_name}}' => (string) ($payroll->company?->name ?? ''),
            '{{company_code}}' => (string) ($payroll->company?->code ?? ''),
            '{{company_account}}' => (string) ($debitAccount?->account_number ?? ''),
            '{{company_bank}}' => (string) ($debitAccount?->name ?? ''),
            '{{payment_date}}' => $date->format('Ymd'),
            '{{payroll_date}}' => $payroll->payroll_date?->format('Ymd') ?? '',
            '{{reference}}' => 'PAYROLL-'.$payroll->getKey(),
            '{{period_from}}' => $payroll->date_from?->format('Y-m-d') ?? '',
            '{{period_to}}' => $payroll->date_to?->format('Y-m-d') ?? '',
        ];
    }

    /**
     * Render the complete file contents.
     *
     * @param  array{headers: array<int, string>, rows: array<int, array<int, string>>, total: int, included: int}  $result
     */
    public function csv(BankFileTemplate $template, array $result): string
    {
        $delimiter = $template->delimiter !== '' ? $template->delimiter : ',';
        $tokens = $result['tokens'] ?? [];
        $lines = [];
        $headerRows = $this->templateRows($template->header_rows);
        $footerRows = $this->templateRows($template->footer_rows);

        foreach ($headerRows as $headerRow) {
            $lines[] = $this->replaceTokens(is_array($headerRow) ? $headerRow : [$headerRow], $tokens);
        }

        if ($headerRows === []) {
            $lines[] = $result['headers'];
        }

        $lines = array_merge($lines, $result['rows']);

        if ($template->include_control_total && $footerRows === []) {
            $amount = $template->amount_in_centavos
                ? (string) $result['total']
                : number_format($result['total'] / 100, 2, '.', '');

            $lines[] = ['TOTAL', (string) $result['included'], $amount];
        }

        foreach ($footerRows as $footerRow) {
            $lines[] = $this->replaceTokens(is_array($footerRow) ? $footerRow : [$footerRow], $tokens);
        }

        $stream = fopen('php://temp', 'r+');

        foreach ($lines as $line) {
            fputcsv($stream, array_map(fn ($value): string => (string) $value, $line), $delimiter);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return (string) $csv;
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, string>  $tokens
     * @return array<int, string>
     */
    private function replaceTokens(array $row, array $tokens): array
    {
        return array_map(
            fn ($value): string => str_replace(array_keys($tokens), array_values($tokens), (string) $value),
            $row,
        );
    }

    /**
     * @return array<int, mixed>
     */
    private function templateRows(mixed $rows): array
    {
        return is_array($rows) ? array_values($rows) : [];
    }
}
