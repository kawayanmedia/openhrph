<?php

namespace App\Sectors\Contracts;

use Illuminate\Support\Collection;

/**
 * One remittance working paper a sector contributes.
 */
interface RemittanceForm
{
    public function key(): string;

    public function label(): string;

    /**
     * @param  Collection<int, \App\Models\PayrollDetail>  $details
     * @return array<int, array<int, string>>
     */
    public function rows(Collection $details): array;

    /**
     * @return array<int, string>
     */
    public function totalColumns(): array;
}
