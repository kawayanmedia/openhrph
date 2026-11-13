<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the credentials used by time clock devices to push attendance logs.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            if (! Schema::hasColumn('devices', 'api_key')) {
                $table->string('api_key', 64)->nullable()->unique()->after('ip_address');
            }

            if (! Schema::hasColumn('devices', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('api_key');
            }

            if (! Schema::hasColumn('devices', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            if (Schema::hasColumn('devices', 'last_seen_at')) {
                $table->dropColumn('last_seen_at');
            }

            if (Schema::hasColumn('devices', 'is_active')) {
                $table->dropColumn('is_active');
            }

            if (Schema::hasColumn('devices', 'api_key')) {
                $table->dropUnique(['api_key']);
                $table->dropColumn('api_key');
            }
        });
    }
};
