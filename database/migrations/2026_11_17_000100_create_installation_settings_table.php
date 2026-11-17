<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Installation-wide settings.
 *
 * Unlike `settings` these rows are not tenant-scoped: they describe the
 * installation itself (sector, country, currency, locale and timezone) and are
 * shared by every company in the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('installation_settings')) {
            Schema::create('installation_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('key', 64)->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_settings');
    }
};
