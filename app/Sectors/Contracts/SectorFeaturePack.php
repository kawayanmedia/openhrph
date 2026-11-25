<?php

namespace App\Sectors\Contracts;

use App\Livewire\Admin\Fields\Field;

/**
 * The screens, commands and reports a sector contributes.
 *
 * Core code composes the active pack instead of branching on the sector. A
 * sector without extra screens returns the NullFeaturePack.
 */
interface SectorFeaturePack
{
    /**
     * @return array<int, class-string>
     */
    public function adminResources(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function adminMenu(): array;

    /**
     * @return array<int, class-string>
     */
    public function commands(): array;

    /**
     * Form fields for employee records that only apply to this sector.
     *
     * @return array<int, Field>
     */
    public function employeeFields(): array;

    /**
     * @return array<int, class-string<SectorReadiness>>
     */
    public function readiness(): array;

    /**
     * @return array<string, class-string<RemittanceForm>>
     */
    public function remittanceForms(): array;

    /**
     * @return array<string, class-string<DocumentTemplate>>
     */
    public function documents(): array;

    /**
     * Ledger accounts contributed by the sector, keyed by account name.
     *
     * @return array<string, string>
     */
    public function journalAccounts(): array;
}
