<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Government\PlantillaItem;
use App\Models\Office;
use Database\Seeders\Concerns\SeedsDemoTenant;
use Illuminate\Database\Seeder;

class PlantillaItemSeeder extends Seeder
{
    use SeedsDemoTenant;

    public function run(): void
    {
        $this->runForDemoTenant(function (Company $company): void {
            $offices = Office::query()
                ->where('company_id', $company->getKey())
                ->orderBy('id')
                ->get()
                ->keyBy('code');

            $headOffice = $offices->get('HQ') ?? $offices->first();

            if ($headOffice === null) {
                return;
            }

            $branchOffice = $offices->get('BR1') ?? $headOffice;

            $items = [
                [$headOffice, 'HQ-HRM-001', 'Human Resources Manager', 22, 1],
                [$headOffice, 'HQ-PAY-001', 'Payroll Accountant', 18, 1],
                [$headOffice, 'HQ-ADM-001', 'Administrative Assistant', 8, 1],
                [$headOffice, 'HQ-ADM-002', 'Administrative Officer', 11, 1],
                [$headOffice, 'HQ-IT-001', 'Information Technology Officer', 19, 1],
                [$branchOffice, 'BR1-DEV-001', 'Software Developer', 18, 1],
                [$branchOffice, 'BR1-ADM-001', 'Administrative Aide', 4, 1],
                [$branchOffice, 'BR1-FIN-001', 'Finance Officer', 16, 1],
            ];

            foreach ($items as $sortOrder => [$office, $itemNumber, $positionTitle, $salaryGrade, $step]) {
                $this->upsertModel(
                    PlantillaItem::class,
                    [
                        'company_id' => $company->getKey(),
                        'office_id' => $office->getKey(),
                        'item_number' => $itemNumber,
                    ],
                    [
                        'position_title' => $positionTitle,
                        'salary_grade' => $salaryGrade,
                        'step' => $step,
                        'sort_order' => $sortOrder + 1,
                        'have_budget' => true,
                    ],
                );
            }
        });
    }
}
