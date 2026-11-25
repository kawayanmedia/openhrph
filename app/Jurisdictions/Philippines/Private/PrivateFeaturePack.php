<?php

namespace App\Jurisdictions\Philippines\Private;

use App\Sectors\Contracts\SectorFeaturePack;

/**
 * Private-sector screens and fields for the Philippines.
 */
final class PrivateFeaturePack implements SectorFeaturePack
{
    public function adminResources(): array
    {
        return [];
    }

    public function adminMenu(): array
    {
        return [];
    }

    public function commands(): array
    {
        return [];
    }

    public function employeeFields(): array
    {
        return EmployeeFields::fields();
    }

    public function readiness(): array
    {
        return [];
    }

    public function remittanceForms(): array
    {
        return [];
    }

    public function documents(): array
    {
        return [];
    }

    public function journalAccounts(): array
    {
        return [];
    }
}
