<?php

namespace App\Jurisdictions\Philippines\Government\Legacy;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use KawayanMedia\DtrPdf\Contract\DtrStoreInterface;
use KawayanMedia\DtrPdf\Support\DtrDataset;

/**
 * DTR write side on top of the application's own attendance tables.
 *
 * | Dataset | Table | Notes |
 * | --- | --- | --- |
 * | `dtr` | `timesheets` | the raw `am_login`, `pm_login`, `ot_login` … columns already exist |
 * | `tardiness` | `timesheets` | stored as `late_minutes`; log type is not kept |
 * | `leave_card` | `leave_card` | the CSC leave ledger |
 * | `logs` | `hr_audit_events` | tenant-scoped audit trail |
 *
 * Tardiness is stored in whole minutes and only ever raised: the payroll
 * timesheet is the authority on lateness, so a DTR edit must not lower it. The
 * per-log-type rows of the legacy `tardiness` table have no equivalent column.
 */
final class TimesheetDtrStore extends LegacyStore implements DtrStoreInterface
{
    private const TIMESHEETS = 'timesheets';

    private const LEAVE_CARD = 'leave_card';

    private const AUDIT = 'hr_audit_events';

    public function rows(string $dataset, array $conditions = [], array $options = []): array
    {
        return match ($dataset) {
            DtrDataset::DTR => $this->selectRows(self::TIMESHEETS, $conditions, $options),
            DtrDataset::TARDINESS => $this->tardinessRows($conditions),
            DtrDataset::LEAVE_CARD => $this->selectRows(self::LEAVE_CARD, $conditions, $options),
            DtrDataset::LOGS => $this->selectRows(self::AUDIT, $conditions, $options),
            default => throw new InvalidArgumentException(sprintf('Unknown DTR dataset "%s".', $dataset)),
        };
    }

    public function first(string $dataset, array $conditions = []): ?array
    {
        return $this->rows($dataset, $conditions, ['limit' => 1])[0] ?? null;
    }

    public function insert(string $dataset, array $values): void
    {
        match ($dataset) {
            DtrDataset::DTR => $this->insertTimesheet($values),
            DtrDataset::TARDINESS => $this->recordTardiness($values),
            DtrDataset::LEAVE_CARD => $this->insertRow(self::LEAVE_CARD, $values),
            DtrDataset::LOGS => $this->insertAudit($values),
            default => throw new InvalidArgumentException(sprintf('Unknown DTR dataset "%s".', $dataset)),
        };
    }

    public function update(string $dataset, array $values, array $conditions = []): int
    {
        return match ($dataset) {
            DtrDataset::DTR => $this->updateRows(self::TIMESHEETS, $values, $conditions),
            DtrDataset::TARDINESS => $this->recordTardiness($conditions + $values),
            DtrDataset::LEAVE_CARD => $this->updateRows(self::LEAVE_CARD, $values, $conditions),
            DtrDataset::LOGS => $this->updateRows(self::AUDIT, $values, $conditions),
            default => throw new InvalidArgumentException(sprintf('Unknown DTR dataset "%s".', $dataset)),
        };
    }

    public function delete(string $dataset, array $conditions = []): int
    {
        return match ($dataset) {
            DtrDataset::DTR => $this->deleteRows(self::TIMESHEETS, $conditions),
            // The payroll timesheet owns tardiness; a DTR edit never clears it.
            DtrDataset::TARDINESS => 0,
            DtrDataset::LEAVE_CARD => $this->deleteRows(self::LEAVE_CARD, $conditions),
            DtrDataset::LOGS => $this->deleteRows(self::AUDIT, $conditions),
            default => throw new InvalidArgumentException(sprintf('Unknown DTR dataset "%s".', $dataset)),
        };
    }

    public function transaction(callable $operation): mixed
    {
        return $this->runTransaction($operation);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    /**
     * @param  array<string, mixed>  $values
     * @return int the new timesheet id
     */
    private function insertTimesheet(array $values): int
    {
        return $this->insertRow(self::TIMESHEETS, $this->withEmployeeNumber($values));
    }

    /**
     * `timesheets.employee_number` is required and the DTR recorder does not
     * know it, so it is looked up from the employee record.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function withEmployeeNumber(array $values): array
    {
        if (($values['employee_number'] ?? null) !== null) {
            return $values;
        }

        $employeeId = $values['employee_id'] ?? null;

        if ($employeeId === null) {
            return $values;
        }

        $employeeNumber = DB::table('employees')
            ->where('company_id', $this->tenantId())
            ->where('id', $employeeId)
            ->value('employee_number');

        if ($employeeNumber !== null) {
            $values['employee_number'] = $employeeNumber;
        }

        return $values;
    }

    /**
     * @param  array<mixed>  $conditions
     * @return list<array<string, mixed>>
     */
    private function tardinessRows(array $conditions): array
    {
        $employeeId = $conditions['employee_id'] ?? null;
        $date = $conditions['date'] ?? null;

        if ($employeeId === null || $date === null) {
            return [];
        }

        $row = $this->selectFirst(self::TIMESHEETS, ['employee_id' => $employeeId, 'log_date' => $date]);
        $minutes = (int) ($row['late_minutes'] ?? 0);

        if ($row === null || $minutes <= 0) {
            return [];
        }

        return [[
            'id' => $row['id'] ?? null,
            'employee_id' => $employeeId,
            'office_id' => $row['office_id'] ?? null,
            'log_type' => (int) ($conditions['log_type'] ?? 0),
            'date' => $date,
            'seconds' => $minutes * 60,
        ]];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return int affected rows
     */
    private function recordTardiness(array $values): int
    {
        $employeeId = $values['employee_id'] ?? null;
        $date = $values['date'] ?? null;
        $seconds = (int) round((float) ($values['seconds'] ?? 0));

        if ($employeeId === null || $date === null || $seconds <= 0) {
            return 0;
        }

        $minutes = max(1, (int) ceil($seconds / 60));
        $existing = $this->selectFirst(self::TIMESHEETS, ['employee_id' => $employeeId, 'log_date' => $date]);

        if ($existing === null) {
            $this->insertRow(self::TIMESHEETS, $this->withEmployeeNumber([
                'employee_id' => $employeeId,
                'office_id' => $values['office_id'] ?? null,
                'log_date' => $date,
                'late_minutes' => $minutes,
            ]));

            return 1;
        }

        $current = (int) ($existing['late_minutes'] ?? 0);

        if ($current >= $minutes) {
            return 0;
        }

        return $this->updateRows(self::TIMESHEETS, ['late_minutes' => $minutes], [
            'employee_id' => $employeeId,
            'log_date' => $date,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return int the new audit row id
     */
    private function insertAudit(array $values): int
    {
        return $this->insertAuditRow($values);
    }
}
