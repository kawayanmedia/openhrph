<?php

namespace App\Jurisdictions\Philippines\Government;

use App\Jurisdictions\Philippines\Government\Admin\Resources\AppointmentAction;
use App\Jurisdictions\Philippines\Government\Admin\Resources\AppointmentDocument;
use App\Jurisdictions\Philippines\Government\Admin\Resources\AppointmentRecord;
use App\Jurisdictions\Philippines\Government\Admin\Resources\AssumptionRecord;
use App\Jurisdictions\Philippines\Government\Admin\Resources\HrmpsbCommittee;
use App\Jurisdictions\Philippines\Government\Admin\Resources\HrmpsbMember;
use App\Jurisdictions\Philippines\Government\Admin\Resources\LeaveCard;
use App\Jurisdictions\Philippines\Government\Admin\Resources\Nosa;
use App\Jurisdictions\Philippines\Government\Admin\Resources\PersonnelAction;
use App\Jurisdictions\Philippines\Government\Admin\Resources\PlantillaItem;
use App\Jurisdictions\Philippines\Government\Admin\Resources\PlantillaVersion;
use App\Jurisdictions\Philippines\Government\Admin\Resources\QualificationScreening;
use App\Jurisdictions\Philippines\Government\Admin\Resources\QualificationStandard;
use App\Jurisdictions\Philippines\Government\Admin\Resources\RecruitmentDeliberation;
use App\Jurisdictions\Philippines\Government\Admin\Resources\SalaryGrade;
use App\Jurisdictions\Philippines\Government\Admin\Resources\ServiceRecord;
use App\Jurisdictions\Philippines\Government\Console\Commands\GeneratePlantillaItemsCommand;
use App\Jurisdictions\Philippines\Government\Console\Commands\GenerateSalaryGradeCommand;
use App\Jurisdictions\Philippines\Government\Console\Commands\PayrollLoyaltyAwards;
use App\Jurisdictions\Philippines\Government\Console\Commands\PayrollStepIncrements;
use App\Jurisdictions\Philippines\Government\Console\Commands\PayrollTerminalLeave;
use App\Jurisdictions\Philippines\Government\Reports\CoaRegisterForm;
use App\Jurisdictions\Philippines\Government\Reports\GsisRemittanceForm;
use App\Jurisdictions\Philippines\Government\Support\EmployeeFields;
use App\Jurisdictions\Philippines\Government\Support\GovernmentReadiness;
use App\Sectors\Contracts\SectorFeaturePack;

/**
 * Public-sector screens, commands and reports for the Philippines.
 */
final class GovernmentFeaturePack implements SectorFeaturePack
{
    public function adminResources(): array
    {
        return [
            PlantillaVersion::class,
            PlantillaItem::class,
            QualificationStandard::class,
            QualificationScreening::class,
            AppointmentRecord::class,
            AppointmentDocument::class,
            AppointmentAction::class,
            AssumptionRecord::class,
            PersonnelAction::class,
            HrmpsbCommittee::class,
            HrmpsbMember::class,
            RecruitmentDeliberation::class,
            SalaryGrade::class,
            Nosa::class,
            ServiceRecord::class,
            LeaveCard::class,
        ];
    }

    public function adminMenu(): array
    {
        $path = __DIR__.'/Admin/menu.php';

        return is_file($path) ? (array) require $path : [];
    }

    public function commands(): array
    {
        return [
            GeneratePlantillaItemsCommand::class,
            GenerateSalaryGradeCommand::class,
            PayrollStepIncrements::class,
            PayrollLoyaltyAwards::class,
            PayrollTerminalLeave::class,
        ];
    }

    public function employeeFields(): array
    {
        return EmployeeFields::fields();
    }

    public function readiness(): array
    {
        return [GovernmentReadiness::class];
    }

    public function remittanceForms(): array
    {
        return [
            'gsis' => GsisRemittanceForm::class,
            'coa' => CoaRegisterForm::class,
        ];
    }

    public function documents(): array
    {
        return [];
    }

    public function journalAccounts(): array
    {
        return [
            'gsis_payable' => (string) config('payroll.accounts.gsis_payable', '2115'),
        ];
    }
}
