<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Report exports prepared in the background (Part 6e). One row per request; the finished file sits on the private
 * disk until `expires_at`. Rows belong to the user who asked and are never shown to anybody else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();   // null = institution-wide (central) report
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('report_key', 60);
            $table->string('title', 150);
            $table->string('format', 5);                                  // csv | xlsx | pdf
            $table->json('filters')->nullable();
            $table->string('status', 10)->default('queued');              // queued | running | done | failed | expired
            $table->string('file_path', 255)->nullable();
            $table->string('file_name', 150)->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->string('error', 500)->nullable();
            $table->unsignedSmallInteger('downloads')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_runs');
    }
};
