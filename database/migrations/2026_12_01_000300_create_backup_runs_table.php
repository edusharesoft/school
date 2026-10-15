<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->string('trigger', 20)->default('manual');           // manual | schedule
            $table->string('status', 20)->default('running');           // running | done | failed
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->unsignedInteger('tables_count')->nullable();
            $table->unsignedBigInteger('rows_count')->nullable();
            $table->unsignedInteger('files_count')->nullable();
            $table->boolean('encrypted')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('verify_status', 10)->nullable();             // ok | failed
            $table->boolean('drilled')->default(false);                  // verified by a full test restore
            $table->text('verify_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'finished_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
