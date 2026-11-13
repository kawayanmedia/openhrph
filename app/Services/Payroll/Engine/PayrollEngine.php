<?php

namespace App\Services\Payroll\Engine;

use App\Models\Payroll;

interface PayrollEngine
{
    /**
     * Compute a payroll without persisting anything.
     */
    public function preview(Payroll $payroll): EngineResult;

    /**
     * Compute a payroll and persist the resulting details.
     */
    public function process(Payroll $payroll, bool $force = false): EngineResult;

    /**
     * The engine version, or null when the engine is unavailable.
     */
    public function version(): ?string;
}
