<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notification hub & notice board (Part 5-1).
 *  message_templates      branch overrides of the default Bangla / English templates (config/notifications.php)
 *  notification_batches   bulk / scheduled messages composed by staff
 *  notification_messages  one row per recipient × channel: outbox, delivery status, history and the in-app inbox
 *  notification_opt_outs  numbers / e-mail addresses that asked not to receive a channel
 *  notices (+ notice_reads)  notice board with audience targeting, scheduling, expiry, attachment and read receipts
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('event', 40);
            $table->string('channel', 12);                     // sms | email | whatsapp | in_app
            $table->string('locale', 5);                       // bn | en
            $table->string('subject', 191)->nullable();        // e-mail subject / in-app title
            $table->text('body');
            $table->string('whatsapp_template', 100)->nullable(); // approved template name on the WhatsApp Business account
            $table->string('whatsapp_language', 10)->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'event', 'channel', 'locale']);
        });

        Schema::create('notification_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('title', 150);
            $table->json('channels');
            $table->json('audience');                          // {type, class_ids, section_ids, student_ids}
            $table->text('body_bn')->nullable();
            $table->text('body_en')->nullable();
            $table->unsignedInteger('recipients')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->boolean('is_emergency')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('notification_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('notification_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 40);
            $table->string('channel', 12);
            $table->string('recipient_type', 20)->nullable();  // guardian | student | employee | user | applicant
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->string('recipient_name', 150)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete(); // in-app inbox owner
            $table->string('to', 191)->nullable();             // normalised phone / e-mail
            $table->string('locale', 5)->default('bn');
            $table->string('subject', 191)->nullable();
            $table->text('body');
            $table->json('payload')->nullable();               // WhatsApp template name / language / parameters
            $table->string('url', 255)->nullable();
            $table->string('status', 12)->default('queued');   // scheduled | queued | sending | sent | delivered | failed | skipped | cancelled
            $table->string('provider', 30)->nullable();
            $table->string('provider_message_id', 120)->nullable();
            $table->string('error', 255)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedSmallInteger('segments')->default(0); // SMS parts
            $table->nullableMorphs('source');
            $table->string('dedupe_key', 64)->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'dedupe_key']);
            $table->index(['branch_id', 'status', 'scheduled_at']);
            $table->index(['branch_id', 'channel', 'created_at']);
            $table->index(['user_id', 'channel', 'read_at']);
            $table->index(['provider', 'provider_message_id']);
        });

        Schema::create('notification_opt_outs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('channel', 12);
            $table->string('address', 191);                    // normalised phone or lower-case e-mail
            $table->string('reason', 191)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'channel', 'address']);
        });

        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->uuid('group_uuid')->nullable();            // institution-wide notice copied to several branches
            $table->string('title', 191);
            $table->text('body');
            $table->string('category', 20)->default('general'); // general | academic | exam | holiday | event | meeting | fee | emergency
            $table->string('audience', 20)->default('all');    // all | staff | teachers | students | guardians | classes | students_specific
            $table->json('audience_filter')->nullable();       // {class_ids, section_ids, student_ids}
            $table->boolean('is_emergency')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_public')->default(false);      // shown on the public website (Part 6)
            $table->json('channels')->nullable();              // extra distribution: sms, email, whatsapp
            $table->string('status', 12)->default('draft');    // draft | scheduled | published | archived
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('distributed_at')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name', 191)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'status', 'publish_at']);
            $table->index('group_uuid');
        });

        Schema::create('notice_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');

            $table->unique(['notice_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_reads');
        Schema::dropIfExists('notices');
        Schema::dropIfExists('notification_opt_outs');
        Schema::dropIfExists('notification_messages');
        Schema::dropIfExists('notification_batches');
        Schema::dropIfExists('message_templates');
    }
};
