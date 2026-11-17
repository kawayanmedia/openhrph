<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Optional tooling tables present in the existing database (Nova, Pulse, Telescope, Reverb, event sourcing).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('action_events')) {
            Schema::create('action_events', function (Blueprint $table): void {
                $table->id();
                $table->char('batch_id', 36);
                $table->unsignedBigInteger('user_id');
                $table->string('name');
                $table->string('actionable_type');
                $table->unsignedBigInteger('actionable_id');
                $table->string('target_type');
                $table->unsignedBigInteger('target_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id')->nullable();
                $table->text('fields');
                $table->string('status', 25)->default('running');
                $table->text('exception');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->mediumText('original')->nullable();
                $table->mediumText('changes')->nullable();
                $table->unsignedBigInteger('company_id');
                $table->index(['actionable_type', 'actionable_id']);
                $table->index(['batch_id', 'model_type', 'model_id']);
                $table->index(['company_id']);
                $table->index(['target_type', 'target_id']);
                $table->index(['user_id']);
            });
        }

        if (! Schema::hasTable('nova_field_attachments')) {
            Schema::create('nova_field_attachments', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('attachable_type');
                $table->unsignedBigInteger('attachable_id');
                $table->string('attachment');
                $table->string('disk');
                $table->string('url');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['attachable_type', 'attachable_id']);
                $table->index(['url']);
            });
        }

        if (! Schema::hasTable('pulse_aggregates')) {
            Schema::create('pulse_aggregates', function (Blueprint $table): void {
                $table->unsignedInteger('bucket');
                $table->unsignedMediumInteger('period');
                $table->string('type');
                $table->text('key');
                $table->binary('key_hash', 16)->nullable();
                $table->string('aggregate');
                $table->decimal('value', 20, 2);
                $table->unsignedInteger('count')->nullable();
                $table->unique(['bucket', 'period', 'type', 'aggregate', 'key_hash']);
                $table->index(['period', 'bucket']);
                $table->index(['period', 'type', 'aggregate', 'bucket']);
                $table->index(['type']);
            });
        }

        if (! Schema::hasTable('pulse_entries')) {
            Schema::create('pulse_entries', function (Blueprint $table): void {
                $table->unsignedInteger('timestamp');
                $table->string('type');
                $table->text('key');
                $table->binary('key_hash', 16)->nullable();
                $table->bigInteger('value')->nullable();
                $table->index(['key_hash']);
                $table->index(['timestamp']);
                $table->index(['timestamp', 'type', 'key_hash', 'value']);
                $table->index(['type']);
            });
        }

        if (! Schema::hasTable('pulse_values')) {
            Schema::create('pulse_values', function (Blueprint $table): void {
                $table->unsignedInteger('timestamp');
                $table->string('type');
                $table->text('key');
                $table->binary('key_hash', 16)->nullable();
                $table->text('value');
                $table->index(['timestamp']);
                $table->index(['type']);
                $table->unique(['type', 'key_hash']);
            });
        }

        if (! Schema::hasTable('stored_events')) {
            Schema::create('stored_events', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('aggregate_uuid')->nullable();
                $table->string('event_class');
                $table->json('event_properties');
                $table->json('meta_data');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->index(['aggregate_uuid']);
                $table->index(['event_class']);
            });
        }

        if (! Schema::hasTable('telescope_entries')) {
            Schema::create('telescope_entries', function (Blueprint $table): void {
                $table->bigIncrements('sequence');
                $table->char('uuid', 36);
                $table->char('batch_id', 36);
                $table->string('family_hash')->nullable();
                $table->boolean('should_display_on_index')->default(true);
                $table->string('type', 20);
                $table->longText('content');
                $table->dateTime('created_at')->nullable();
                $table->index(['batch_id']);
                $table->index(['family_hash']);
                $table->index(['type', 'should_display_on_index']);
                $table->unique(['uuid']);
            });
        }

        if (! Schema::hasTable('telescope_entries_tags')) {
            Schema::create('telescope_entries_tags', function (Blueprint $table): void {
                $table->char('entry_uuid', 36);
                $table->string('tag');
                $table->index(['entry_uuid', 'tag']);
                $table->index(['tag']);
            });
        }

        if (! Schema::hasTable('telescope_monitoring')) {
            Schema::create('telescope_monitoring', function (Blueprint $table): void {
                $table->string('tag');
            });
        }

        if (! Schema::hasTable('websockets_statistics_entries')) {
            Schema::create('websockets_statistics_entries', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('app_id');
                $table->integer('peak_connection_count');
                $table->integer('websocket_message_count');
                $table->integer('api_message_count');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['websockets_statistics_entries', 'telescope_monitoring', 'telescope_entries_tags', 'telescope_entries', 'stored_events', 'pulse_values', 'pulse_entries', 'pulse_aggregates', 'nova_field_attachments', 'action_events'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
