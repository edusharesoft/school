<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('cover_path')->nullable()->after('logo_path');   // campus photo for the public website
            $table->decimal('latitude', 10, 7)->nullable()->after('cover_path');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['cover_path', 'latitude', 'longitude']);
        });
    }
};
