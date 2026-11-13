<?php

namespace App\Services\Government\Deductions;

use Ihrmis\Tax\Models\PhilHealth as PhilHealthModel;

class PhilHealth
{
    public $tables = null;

    protected $philHealth;

    public function __construct(PhilHealthModel $philHealth)
    {
        $this->philHealth = $philHealth;
    }
}
