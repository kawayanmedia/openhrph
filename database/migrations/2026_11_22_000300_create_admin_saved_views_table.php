<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user saved index views (search, filters, sort and page size).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admin_saved_views')) {
            return;
        }

        Schema::create('admin_saved_views', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('user_id');
            $table->string('resource', 64);
            $table->string('name', 80);
            $table->json('query');
            $table->timestamps();

            $table->unique(['user_id', 'resource', 'name'], 'admin_saved_views_unique');
            $table->index(['company_id', 'user_id', 'resource'], 'admin_saved_views_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_saved_views');
    }
};
