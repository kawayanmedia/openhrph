<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Document metadata, immutable versions and access history.
 *
 * File bytes live in the media library; these tables carry the ownership,
 * classification, retention and audit metadata the media table does not.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('documents')) {
            Schema::create('documents', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->string('owner_type')->nullable();
                $table->unsignedBigInteger('owner_id')->nullable();
                $table->string('title', 255);
                $table->string('category', 32)->default('other');
                $table->string('classification', 32)->default('internal');
                $table->string('document_number', 64)->nullable();
                $table->text('description')->nullable();
                $table->date('issued_at')->nullable();
                $table->date('expires_at')->nullable();
                $table->date('retention_until')->nullable();
                $table->string('status', 32)->default('active');
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'owner_type', 'owner_id']);
                $table->index(['company_id', 'classification']);
                $table->index(['company_id', 'category']);
                $table->index(['company_id', 'expires_at']);
            });
        }

        if (! Schema::hasTable('document_versions')) {
            Schema::create('document_versions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('document_id');
                $table->unsignedInteger('version')->default(1);
                $table->unsignedBigInteger('media_id')->nullable();
                $table->string('file_name', 255)->nullable();
                $table->string('mime_type', 128)->nullable();
                $table->unsignedBigInteger('size')->nullable();
                $table->string('checksum', 64)->nullable();
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'document_id', 'version']);
                $table->unique(['document_id', 'version']);
            });
        }

        if (! Schema::hasTable('document_access_logs')) {
            Schema::create('document_access_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('document_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action', 32);
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['company_id', 'document_id', 'created_at']);
                $table->index(['company_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('document_acknowledgments')) {
            Schema::create('document_acknowledgments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('document_id');
                $table->unsignedBigInteger('employee_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamp('acknowledged_at');
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->index(['company_id', 'document_id']);
                $table->unique(['document_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_acknowledgments');
        Schema::dropIfExists('document_access_logs');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
    }
};
