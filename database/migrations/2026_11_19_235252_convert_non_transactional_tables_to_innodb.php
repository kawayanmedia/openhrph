<?php

use App\Support\Database\TableEngines;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Databases that started from the retired SQL dumps still contain MyISAM
 * tables. MySQL rejects writes to non-transactional tables inside a transaction
 * when GTID consistency is enforced, and MyISAM ignores rollbacks, so every
 * table has to be rebuilt with InnoDB.
 */
return new class extends Migration
{
    public function up(): void
    {
        $engines = app(TableEngines::class);

        if (! $engines->supportsInnoDb()) {
            return;
        }

        $converted = $engines->convertAll();

        if ($converted !== []) {
            Log::info('Converted non-transactional tables to InnoDB.', $converted);
        }
    }

    public function down(): void
    {
        // Irreversible: restoring MyISAM would make the tables non-transactional again.
    }
};
