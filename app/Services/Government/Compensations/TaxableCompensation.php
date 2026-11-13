<?php

namespace App\Services\Government\Compensations;

class TaxableCompensation
{
    public $user;

    public function __construct()
    {
    }

    public function get($user)
    {
        $amount = 0;

        foreach ($user->compensations as $compensation) {
            $amount += $compensation->amount;
        }

        return $amount;
    }
}
