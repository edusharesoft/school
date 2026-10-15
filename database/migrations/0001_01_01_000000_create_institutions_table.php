<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('slug', 100)->unique();
            $table->string('code', 30)->unique();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('website')->nullable();
            $table->text('address')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('primary_color', 9)->default('#1f6f5c');
            $table->string('timezone', 64)->default('Asia/Dhaka');
            $table->string('currency', 3)->default('BDT');
            $table->string('locale', 5)->default('en');
            $table->string('status', 20)->default('active')->index(); // active | suspended | inactive
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('code', 30);
            $table->string('subdomain', 63)->unique();
            $table->string('type', 20)->default('school_college'); // school | college | school_college
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('principal_name')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('primary_color', 9)->nullable();
            $table->string('timezone', 64)->default('Asia/Dhaka');
            $table->string('currency', 3)->default('BDT');
            $table->string('locale', 5)->default('en');
            $table->string('status', 20)->default('active'); // active | inactive
            $table->date('established_on')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['institution_id', 'code']);
            $table->index(['institution_id', 'status']);
        });

        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('domain')->unique();         // full host name, lower-case, no port
            $table->string('type', 20)->default('branch'); // branch | custom
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('institutions');
    }
};
