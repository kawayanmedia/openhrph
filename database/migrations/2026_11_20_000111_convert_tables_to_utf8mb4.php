<?php

use App\Support\Database\TableCharsets;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Databases that started from the retired SQL dumps still use latin1 or
 * utf8mb3. Rebuild every table with the character set the application
 * configures so the data and comparisons match the utf8mb4 tables Laravel
 * creates, including characters outside the Basic Multilingual Plane.
 */
return new class extends Migration
{
    public function up(): void
    {
        $charsets = app(TableCharsets::class);

        if (! $charsets->supportsUtf8mb4()) {
            return;
        }

        $converted = $charsets->convertAll();

        if ($converted !== []) {
            Log::info('Converted tables to '.$charsets->charset().'.', $converted);
        }
    }

    public function down(): void
    {
        // Irreversible: narrowing the charset would corrupt or drop characters.
    }
};
