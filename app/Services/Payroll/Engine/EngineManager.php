<?php

namespace App\Services\Payroll\Engine;

/**
 * Resolves the configured payroll engine.
 */
class EngineManager
{
    public function driver(): string
    {
        return (string) config('payroll.engine.driver', 'go');
    }

    public function engine(): PayrollEngine
    {
        return $this->driver() === 'php'
            ? new PhpPayrollEngine
            : app(GoPayrollEngine::class);
    }
}
