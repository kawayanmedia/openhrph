<?php

namespace App\Services\Government\Deductions;

use Ihrmis\Tax\Models\Sss as SssModel;

class Sss
{
    public $tables = null;

    protected $sss;

    public function __construct(SssModel $sss)
    {
        $this->sss = $sss;
    }
}
