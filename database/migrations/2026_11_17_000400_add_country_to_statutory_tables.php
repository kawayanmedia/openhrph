<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Country scope for the legacy Philippine statutory tables.
 *
 * The rows keep their existing meaning; `country_code` defaults to `PH` so an
 * installation that has not run the installer behaves exactly as before. The
 * column lets a future country coexist in the same database and lets the
 * engine filter the schedule it is allowed to read.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = [
        'statutory_sss_brackets',
        'statutory_philhealth',
        'statutory_pagibig',
        'statutory_gsis',
        'statutory_tax_brackets',
        'statutory_min_wage',
        'statutory_de_minimis',
        'statutory_sources',
    ];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            if (! Schema::hasTable($name) || Schema::hasColumn($name, 'country_code')) {
                continue;
            }

            Schema::table($name, function (Blueprint $table): void {
                $table->string('country_code', 2)->default('PH')->index();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            if (! Schema::hasTable($name) || ! Schema::hasColumn($name, 'country_code')) {
                continue;
            }

            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn('country_code');
            });
        }
    }
};
