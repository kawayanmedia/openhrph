<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only punch ledger.
 *
 * The JSON `timesheets.logs` array stays as a fast per-day view, but every
 * punch now also lands here. Voiding a punch keeps the row for audit and the
 * JSON view is rebuilt from the remaining active punches.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_punches')) {
            return;
        }

        Schema::create('attendance_punches', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('timesheet_id')->nullable();
            $table->date('workday_date');
            $table->dateTime('punched_at');
            $table->unsignedTinyInteger('direction');
            $table->string('source', 32)->default('biometric');
            $table->string('device', 128)->nullable();
            $table->unsignedBigInteger('request_id')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->string('void_reason', 191)->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(
                ['company_id', 'employee_id', 'punched_at', 'direction'],
                'attendance_punches_unique',
            );
            $table->index(['company_id', 'workday_date', 'employee_id'], 'attendance_punches_workday_index');
            $table->index(['timesheet_id', 'punched_at'], 'attendance_punches_timesheet_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_punches');
    }
};
