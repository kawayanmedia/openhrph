<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for device protocol traffic and sync runs.
 *
 * High-frequency keepalives (`iclock/getrequest`) are never written here; only
 * pushes, file imports, sync summaries and failures, so an administrator can
 * see why a device is not delivering punches without drowning in noise.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_device_events')) {
            return;
        }

        Schema::create('attendance_device_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('device_id')->nullable();
            $table->string('serial_number', 64)->nullable();
            $table->string('protocol', 32)->nullable();
            $table->string('kind', 24);
            $table->string('endpoint', 191)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('records')->nullable();
            $table->unsignedInteger('accepted')->nullable();
            $table->unsignedInteger('duplicates')->nullable();
            $table->unsignedInteger('unmatched')->nullable();
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['company_id', 'occurred_at'], 'attendance_device_events_tenant_index');
            $table->index(['serial_number', 'occurred_at'], 'attendance_device_events_serial_index');
            $table->index(['device_id', 'occurred_at'], 'attendance_device_events_device_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_device_events');
    }
};
