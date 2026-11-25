<?php

namespace App\Sectors\Support;

use App\Sectors\Contracts\SectorFeaturePack;

/**
 * A sector that contributes no extra screens, commands or reports.
 */
final class NullFeaturePack implements SectorFeaturePack
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
        return [];
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
