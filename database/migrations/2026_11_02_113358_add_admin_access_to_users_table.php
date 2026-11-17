<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'group_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('group_id')->nullable()->after('company_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'group_id')) {
            return;
        }

        foreach (Schema::getIndexes('users') as $index) {
            if ($index['columns'] === ['group_id']) {
                Schema::table('users', function (Blueprint $table) use ($index) {
                    $table->dropIndex($index['name']);
                });
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('group_id');
        });
    }
};
