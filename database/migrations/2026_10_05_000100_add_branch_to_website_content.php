<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every branch gets its own public website. Pages and posts with branch_id = NULL belong to the
     * institution-wide (central) site; rows with a branch_id belong to that branch's site only.
     */
    public function up(): void
    {
        foreach (['website_pages', 'website_posts'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->foreignId('branch_id')->nullable()->after('institution_id')->constrained()->cascadeOnDelete();
                $t->index(['branch_id', $table === 'website_pages' ? 'placement' : 'type', 'status'], $table.'_branch_idx');
            });
        }
    }

    public function down(): void
    {
        foreach (['website_pages', 'website_posts'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropIndex($table.'_branch_idx');
                $t->dropConstrainedForeignId('branch_id');
            });
        }
    }
};
