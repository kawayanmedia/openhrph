<?php

namespace App\Console\Commands;

use App\Support\Installation\Installation;
use Illuminate\Console\Command;

/**
 * Base class for commands that implement one sector's rules.
 *
 * The command is registered for every installation but refuses to run when the
 * active sector does not match, so a Private installation never executes a
 * Government rule (and vice versa).
 */
abstract class SectorCommand extends Command
{
    protected string $sector = 'Government';

    protected function ensureSector(): bool
    {
        $policy = Installation::sectorPolicy();

        if (strcasecmp($policy->sector()->value, $this->sector) === 0) {
            return true;
        }

        $this->error("This command is a {$this->sector} rule. This installation is {$policy->label()}.");

        return false;
    }
}
