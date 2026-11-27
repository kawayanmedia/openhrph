<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turns `devices` into a protocol-aware device registry.
 *
 * Push devices (ADMS/iClock) are identified by serial number and an optional
 * push key; pull devices store their LAN host, port and encrypted credentials.
 * The sync columns let the scheduled sync and the on-prem agent resume where
 * they left off without re-reading the whole device log.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('devices')) {
            return;
        }

        Schema::table('devices', function (Blueprint $table): void {
            if (! Schema::hasColumn('devices', 'serial_number')) {
                $table->string('serial_number', 64)->nullable()->after('name');
            }

            if (! Schema::hasColumn('devices', 'protocol')) {
                $table->string('protocol', 32)->default('generic_json')->after('serial_number');
            }

            if (! Schema::hasColumn('devices', 'port')) {
                $table->unsignedSmallInteger('port')->nullable()->after('ip_address');
            }

            if (! Schema::hasColumn('devices', 'timezone')) {
                $table->string('timezone', 64)->nullable()->after('port');
            }

            if (! Schema::hasColumn('devices', 'push_key')) {
                $table->string('push_key', 64)->nullable()->after('api_key');
            }

            if (! Schema::hasColumn('devices', 'credentials')) {
                $table->text('credentials')->nullable()->after('push_key');
            }

            if (! Schema::hasColumn('devices', 'config')) {
                $table->json('config')->nullable()->after('credentials');
            }

            if (! Schema::hasColumn('devices', 'office_id')) {
                $table->unsignedBigInteger('office_id')->nullable()->after('company_id');
            }

            if (! Schema::hasColumn('devices', 'sync_enabled')) {
                $table->boolean('sync_enabled')->default(false)->after('is_active');
            }

            if (! Schema::hasColumn('devices', 'sync_interval_minutes')) {
                $table->unsignedSmallInteger('sync_interval_minutes')->default(5)->after('sync_enabled');
            }

            if (! Schema::hasColumn('devices', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('last_seen_at');
            }

            if (! Schema::hasColumn('devices', 'last_sync_status')) {
                $table->string('last_sync_status', 16)->nullable()->after('last_synced_at');
            }

            if (! Schema::hasColumn('devices', 'last_sync_message')) {
                $table->text('last_sync_message')->nullable()->after('last_sync_status');
            }

            if (! Schema::hasColumn('devices', 'last_pull_cursor')) {
                $table->string('last_pull_cursor', 64)->nullable()->after('last_sync_message');
            }
        });

        $this->addIndexes();
    }

    public function down(): void
    {
        if (! Schema::hasTable('devices')) {
            return;
        }

        $this->dropIndexes();

        Schema::table('devices', function (Blueprint $table): void {
            foreach ([
                'serial_number',
                'protocol',
                'port',
                'timezone',
                'push_key',
                'credentials',
                'config',
                'office_id',
                'sync_enabled',
                'sync_interval_minutes',
                'last_synced_at',
                'last_sync_status',
                'last_sync_message',
                'last_pull_cursor',
            ] as $column) {
                if (Schema::hasColumn('devices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function addIndexes(): void
    {
        $indexes = collect(Schema::getIndexes('devices'))->pluck('name')->all();

        Schema::table('devices', function (Blueprint $table) use ($indexes): void {
            if (! in_array('devices_company_serial_unique', $indexes, true)) {
                $table->unique(['company_id', 'serial_number'], 'devices_company_serial_unique');
            }

            if (! in_array('devices_protocol_sync_index', $indexes, true)) {
                $table->index(['company_id', 'protocol', 'sync_enabled'], 'devices_protocol_sync_index');
            }

            if (! in_array('devices_office_index', $indexes, true)) {
                $table->index(['office_id'], 'devices_office_index');
            }
        });
    }

    private function dropIndexes(): void
    {
        $indexes = collect(Schema::getIndexes('devices'))->pluck('name')->all();

        Schema::table('devices', function (Blueprint $table) use ($indexes): void {
            if (in_array('devices_company_serial_unique', $indexes, true)) {
                $table->dropUnique('devices_company_serial_unique');
            }

            if (in_array('devices_protocol_sync_index', $indexes, true)) {
                $table->dropIndex('devices_protocol_sync_index');
            }

            if (in_array('devices_office_index', $indexes, true)) {
                $table->dropIndex('devices_office_index');
            }
        });
    }
};
