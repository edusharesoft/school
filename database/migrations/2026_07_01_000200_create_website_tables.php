<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Free-form pages (About Us, Academic, Regulations, FAQ, Privacy Policy …) managed from the Super Admin area.
        Schema::create('website_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 120);
            $table->string('title');
            $table->string('title_bn')->nullable();
            $table->longText('body')->nullable();              // Markdown (raw HTML is stripped on output)
            $table->longText('body_bn')->nullable();
            $table->string('meta_description', 300)->nullable();
            $table->string('placement', 20)->default('hidden'); // menu | footer_quick | footer_campus | hidden
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 20)->default('published'); // published | draft
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['institution_id', 'slug']);
            $table->index(['institution_id', 'placement', 'status']);
        });

        // News & blog articles and public notices.
        Schema::create('website_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('news');       // news | notice
            $table->string('slug', 140);
            $table->string('title');
            $table->string('title_bn')->nullable();
            $table->string('excerpt', 400)->nullable();
            $table->longText('body')->nullable();              // Markdown
            $table->longText('body_bn')->nullable();
            $table->string('image_path')->nullable();
            $table->string('attachment_path')->nullable();     // public notices may carry a PDF
            $table->string('author_name', 120)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('status', 20)->default('published'); // published | draft
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['institution_id', 'slug']);
            $table->index(['institution_id', 'type', 'status', 'published_at']);
        });

        // Footer newsletter sign-ups (double opt-out: every address can be unsubscribed).
        Schema::create('website_subscribers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('locale', 5)->default('en');
            $table->string('unsubscribe_token', 64)->unique();
            $table->string('ip', 45)->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            $table->unique(['institution_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_subscribers');
        Schema::dropIfExists('website_posts');
        Schema::dropIfExists('website_pages');
    }
};
