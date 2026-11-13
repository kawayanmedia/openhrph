<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Users, authentication, authorisation and framework support tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_log')) {
            Schema::create('activity_log', function (Blueprint $table): void {
                $table->id();
                $table->string('log_name')->nullable();
                $table->text('description');
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->string('subject_type')->nullable();
                $table->unsignedBigInteger('causer_id')->nullable();
                $table->string('causer_type')->nullable();
                $table->json('properties')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['log_name']);
            });
        }

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid');
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->unique(['uuid']);
            });
        }

        if (! Schema::hasTable('features')) {
            Schema::create('features', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('scope');
                $table->text('value');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique(['name', 'scope']);
            });
        }

        if (! Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table): void {
                $table->string('id');
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
                $table->primary('id');
            });
        }

        if (! Schema::hasTable('media')) {
            Schema::create('media', function (Blueprint $table): void {
                $table->id();
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->char('uuid', 36)->nullable();
                $table->string('collection_name');
                $table->string('name');
                $table->string('file_name');
                $table->string('mime_type')->nullable();
                $table->string('disk');
                $table->string('conversions_disk')->nullable();
                $table->unsignedBigInteger('size');
                $table->json('manipulations');
                $table->json('custom_properties');
                $table->json('generated_conversions');
                $table->json('responsive_images');
                $table->unsignedInteger('order_column')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['model_type', 'model_id']);
                $table->index(['order_column']);
                $table->unique(['uuid']);
            });
        }

        if (! Schema::hasTable('model_has_permissions')) {
            Schema::create('model_has_permissions', function (Blueprint $table): void {
                $table->unsignedInteger('permission_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->index(['model_id', 'model_type']);
                $table->primary(['permission_id', 'model_id', 'model_type']);
            });
        }

        if (! Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', function (Blueprint $table): void {
                $table->unsignedInteger('role_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->index(['model_id', 'model_type']);
                $table->primary(['role_id', 'model_id', 'model_type']);
            });
        }

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table): void {
                $table->string('email');
                $table->string('token');
                $table->timestamp('created_at')->nullable();
                $table->primary('email');
            });
        }

        if (! Schema::hasTable('password_resets')) {
            Schema::create('password_resets', function (Blueprint $table): void {
                $table->string('email');
                $table->string('token');
                $table->timestamp('created_at')->nullable();
                $table->index(['email']);
            });
        }

        if (! Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('name');
                $table->string('guard_name');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table): void {
                $table->id();
                $table->string('tokenable_type');
                $table->unsignedBigInteger('tokenable_id');
                $table->string('name');
                $table->string('token', 64);
                $table->text('abilities')->nullable();
                $table->dateTime('expires_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique(['token']);
                $table->index(['tokenable_type', 'tokenable_id']);
            });
        }

        if (! Schema::hasTable('role_has_permissions')) {
            Schema::create('role_has_permissions', function (Blueprint $table): void {
                $table->unsignedInteger('permission_id');
                $table->unsignedInteger('role_id');
                $table->primary(['permission_id', 'role_id']);
                $table->index(['role_id']);
            });
        }

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('name');
                $table->string('guard_name');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id')->nullable();
                $table->unsignedBigInteger('group_id')->nullable();
                $table->integer('account_id')->nullable();
                $table->integer('employee_id')->nullable();
                $table->string('first_name', 25);
                $table->string('last_name', 25);
                $table->string('email', 50);
                $table->string('password')->nullable();
                $table->text('two_factor_secret')->nullable();
                $table->text('two_factor_recovery_codes')->nullable();
                $table->integer('office_id')->nullable();
                $table->boolean('owner')->default(false);
                $table->text('referer')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->boolean('is_temporary_password')->default(false);
                $table->string('token_password', 36)->nullable();
                $table->string('name', 32)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->index(['account_id']);
                $table->unique(['email']);
                $table->index(['group_id']);
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'roles', 'role_has_permissions', 'personal_access_tokens', 'permissions', 'password_resets', 'password_reset_tokens', 'model_has_roles', 'model_has_permissions', 'media', 'job_batches', 'features', 'failed_jobs', 'activity_log'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
