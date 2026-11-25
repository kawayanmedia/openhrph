<?php

namespace App\Jurisdictions\Philippines\Government\Legacy;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use KawayanMedia\LeaveForms\Contract\LeaveStoreInterface;
use KawayanMedia\LeaveForms\Support\LeaveDataset;

/**
 * Leave management write side on top of the application's own tables.
 *
 * | Dataset | Table | Notes |
 * | --- | --- | --- |
 * | `applications` | `leave_applications` | legacy columns already exist |
 * | `card` | `leave_card` | the CSC leave ledger |
 * | `cto` | `compensatory_timeoffs` | |
 * | `manual_log` | `manual_log` | |
 * | `dtr` | `timesheets` | shared with the DTR write side |
 * | `logs` | `hr_audit_events` | |
 */
final class LegacyLeaveStore extends LegacyStore implements LeaveStoreInterface
{
    private const APPLICATIONS = 'leave_applications';

    private const CARD = 'leave_card';

    private const CTO = 'compensatory_timeoffs';

    private const MANUAL_LOG = 'manual_log';

    private const TIMESHEETS = 'timesheets';

    private const AUDIT = 'hr_audit_events';

    public function rows(string $dataset, array $conditions = [], array $options = []): array
    {
        return $this->selectRows($this->table($dataset), $conditions, $options);
    }

    public function first(string $dataset, array $conditions = []): ?array
    {
        return $this->rows($dataset, $conditions, ['limit' => 1])[0] ?? null;
    }

    public function insert(string $dataset, array $values): int|string|null
    {
        if ($dataset === LeaveDataset::LOGS) {
            return $this->insertAuditRow($values);
        }

        if ($dataset === LeaveDataset::DTR) {
            $values = $this->withEmployeeNumber($values);
        }

        return $this->insertRow($this->table($dataset), $values);
    }

    public function update(string $dataset, array $values, array $conditions = []): int
    {
        return $this->updateRows($this->table($dataset), $values, $conditions);
    }

    public function delete(string $dataset, array $conditions = []): int
    {
        return $this->deleteRows($this->table($dataset), $conditions);
    }

    public function transaction(callable $operation): mixed
    {
        return $this->runTransaction($operation);
    }

    private function table(string $dataset): string
    {
        return match ($dataset) {
            LeaveDataset::APPLICATIONS => self::APPLICATIONS,
            LeaveDataset::CARD => self::CARD,
            LeaveDataset::CTO => self::CTO,
            LeaveDataset::MANUAL_LOG => self::MANUAL_LOG,
            LeaveDataset::DTR => self::TIMESHEETS,
            LeaveDataset::LOGS => self::AUDIT,
            default => throw new InvalidArgumentException(sprintf('Unknown leave dataset "%s".', $dataset)),
        };
    }

    /**
     * `timesheets.employee_number` is required and the leave services do not
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
}
