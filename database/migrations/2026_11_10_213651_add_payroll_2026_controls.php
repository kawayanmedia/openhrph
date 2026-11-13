<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('statutory_sources') && ! Schema::hasColumn('statutory_sources', 'status')) {
            Schema::table('statutory_sources', function (Blueprint $table) {
                $table->string('status', 16)->default('verified')->after('effectivity');
            });
        }

        if (Schema::hasTable('statutory_min_wage')) {
            Schema::table('statutory_min_wage', function (Blueprint $table) {
                if (! Schema::hasColumn('statutory_min_wage', 'industry')) {
                    $table->string('industry', 64)->nullable()->after('region');
                }

                if (! Schema::hasColumn('statutory_min_wage', 'wage_order')) {
                    $table->string('wage_order', 64)->nullable()->after('industry');
                }

                if (! Schema::hasColumn('statutory_min_wage', 'effective_to')) {
                    $table->date('effective_to')->nullable()->after('effective_from');
                }
            });
        }

        if (Schema::hasTable('statutory_de_minimis') && ! Schema::hasColumn('statutory_de_minimis', 'basis')) {
            Schema::table('statutory_de_minimis', function (Blueprint $table) {
                $table->string('basis', 16)->default('monthly')->after('ceiling_centavos');
            });
        }

        if (Schema::hasTable('offices')) {
            Schema::table('offices', function (Blueprint $table) {
                if (! Schema::hasColumn('offices', 'region')) {
                    $table->string('region', 32)->nullable()->after('location');
                }

                if (! Schema::hasColumn('offices', 'industry')) {
                    $table->string('industry', 64)->nullable()->after('region');
                }
            });
        }

        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (! Schema::hasColumn('employees', 'region')) {
                    $table->string('region', 32)->nullable()->after('office_id');
                }

                if (! Schema::hasColumn('employees', 'industry')) {
                    $table->string('industry', 64)->nullable()->after('region');
                }
            });
        }

        if (Schema::hasTable('payrolls')) {
            Schema::table('payrolls', function (Blueprint $table) {
                if (! Schema::hasColumn('payrolls', 'processed_by')) {
                    $table->unsignedBigInteger('processed_by')->nullable()->after('processed_at');
                }

                if (! Schema::hasColumn('payrolls', 'reviewed_by')) {
                    $table->unsignedBigInteger('reviewed_by')->nullable()->after('processed_by');
                }

                if (! Schema::hasColumn('payrolls', 'reviewed_at')) {
                    $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
                }

                if (! Schema::hasColumn('payrolls', 'override_reason')) {
                    $table->text('override_reason')->nullable()->after('reviewed_at');
                }

                if (! Schema::hasColumn('payrolls', 'override_by')) {
                    $table->unsignedBigInteger('override_by')->nullable()->after('override_reason');
                }

                if (! Schema::hasColumn('payrolls', 'override_at')) {
                    $table->timestamp('override_at')->nullable()->after('override_by');
                }
            });
        }
    }

    public function down(): void
    {
        $columns = [
            'statutory_sources' => ['status'],
            'statutory_min_wage' => ['industry', 'wage_order', 'effective_to'],
            'statutory_de_minimis' => ['basis'],
            'offices' => ['region', 'industry'],
            'employees' => ['region', 'industry'],
            'payrolls' => ['processed_by', 'reviewed_by', 'reviewed_at', 'override_reason', 'override_by', 'override_at'],
        ];

        foreach ($columns as $table => $names) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) use ($names) {
                foreach ($names as $name) {
                    if (Schema::hasColumn($table->getTable(), $name)) {
                        $table->dropColumn($name);
                    }
                }
            });
        }
    }
};
