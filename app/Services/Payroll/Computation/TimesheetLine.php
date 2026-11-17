<?php

namespace App\Services\Payroll\Computation;

/**
 * One attendance day for an employee.
 *
 * The hour buckets are disjoint: Hours are ordinary hours, Overtime is hours
 * paid at the overtime premium (including night-OT hours) and Night is night
 * hours that are not overtime. NightOT is the subset of Overtime worked at
 * night, used to add the 10% differential on top of the overtime rate.
 */
final class TimesheetLine
{
    public function __construct(
        public string $date = '',
        public string $dayType = '',
        public float $hours = 0.0,
        public float $overtime = 0.0,
        public float $night = 0.0,
        public float $nightOT = 0.0,
        public bool $absent = false,
        public bool $leave = false,
        public bool $paidLeave = false,
        public bool $legacyHours = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            date: (string) ($data['date'] ?? ''),
            dayType: (string) ($data['day_type'] ?? ''),
            hours: (float) ($data['hours'] ?? 0),
            overtime: (float) ($data['overtime'] ?? 0),
            night: (float) ($data['night'] ?? 0),
            nightOT: (float) ($data['night_ot'] ?? 0),
            absent: (bool) ($data['absent'] ?? false),
            leave: (bool) ($data['leave'] ?? false),
            paidLeave: (bool) ($data['paid_leave'] ?? false),
            legacyHours: (bool) ($data['legacy_hours'] ?? false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'day_type' => $this->dayType,
            'hours' => $this->hours,
            'overtime' => $this->overtime,
            'night' => $this->night,
            'night_ot' => $this->nightOT,
            'absent' => $this->absent,
            'leave' => $this->leave,
            'paid_leave' => $this->paidLeave,
            'legacy_hours' => $this->legacyHours,
        ];
    }
}
