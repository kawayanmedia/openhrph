<?php

namespace App\Jurisdictions\Philippines\Attendance\Dtr;

use App\Models\Employee;
use App\Models\Timesheet;
use KawayanMedia\DtrPdf\Adapter\NullTardinessStore;
use KawayanMedia\DtrPdf\Config\DtrLayout;
use KawayanMedia\DtrPdf\DtrGenerator;
use KawayanMedia\DtrPdf\Engine\DtrEngine;
use KawayanMedia\DtrPdf\Model\DtrReport;
use KawayanMedia\DtrPdf\Model\Employee as DtrEmployee;
use KawayanMedia\DtrPdf\Model\Period;
use KawayanMedia\DtrPdf\Model\RawDtrDay;
use KawayanMedia\DtrPdf\Renderer\DtrPdfRenderer;
use ReflectionClass;

/**
 * Wires the DTR engine to the application's attendance data.
 *
 * Tardiness uses {@see NullTardinessStore}: the payroll timesheet is the
 * authority on lateness, so printing a DTR never rewrites payroll data.
 */
final class DtrReportBuilder
{
    public function generator(): DtrGenerator
    {
        $engine = new DtrEngine(
            schedules: new ScheduleProvider,
            holidays: new HolidayProvider,
            leaveTypes: new LeaveTypeProvider,
            manualLogs: new ManualLogProvider,
            history: new DtrHistoryProvider,
            tardiness: new NullTardinessStore,
            settings: new SettingsProvider,
            signatories: new SignatoryProvider,
        );

        return new DtrGenerator($engine, new DtrPdfRenderer(DtrLayout::legacyDtr($this->templatePath())));
    }

    public function report(Employee $employee, Period $period): DtrReport
    {
        return $this->generator()->report($this->dtrEmployee($employee), $period, $this->rawDays($employee, $period));
    }

    /**
     * @return list<RawDtrDay>
     */
    public function rawDays(Employee $employee, Period $period): array
    {
        return $employee->timesheets()
            ->whereBetween('log_date', [$period->fromDate(), $period->toDate()])
            ->orderBy('log_date')
            ->get()
            ->map(fn (Timesheet $timesheet): RawDtrDay => new RawDtrDay(
                date: (string) $timesheet->log_date?->toDateString(),
                amIn: (string) $timesheet->am_login,
                amOut: (string) $timesheet->am_logout,
                pmIn: (string) $timesheet->pm_login,
                pmOut: (string) $timesheet->pm_logout,
                otIn: (string) $timesheet->ot_login,
                otOut: (string) $timesheet->ot_logout,
                leaveTypeId: (int) $timesheet->leave_type_id,
            ))
            ->all();
    }

    public function dtrEmployee(Employee $employee): DtrEmployee
    {
        return new DtrEmployee(
            id: (string) $employee->id,
            firstName: (string) $employee->first_name,
            middleName: (string) $employee->middle_name,
            lastName: (string) $employee->last_name,
            extension: (string) $employee->extension,
            shiftType: (int) ($employee->shift_type ?: 1),
            fridayExempted: (bool) $employee->friday_exempted,
            officeId: $employee->office_id === null ? null : (string) $employee->office_id,
            detailedOfficeId: $employee->detailed_office_id === null ? null : (string) $employee->detailed_office_id,
            assistantDepartmentHead: (bool) $employee->assistant_dept_head,
        );
    }

    public static function periodFor(int $year, int $month, int $fromDay, int $toDay): Period
    {
        return Period::monthly($year, $month, $fromDay, $toDay);
    }

    private function templatePath(): string
    {
        $configured = config('attendance.dtr_template');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $packagePath = dirname((string) (new ReflectionClass(DtrLayout::class))->getFileName(), 3);

        return $packagePath.'/resources/templates/dtr.pdf';
    }
}
