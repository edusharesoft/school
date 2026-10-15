<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Online fee payments (Part 5-2).
 *  online_payments        one checkout attempt through a gateway (SSLCOMMERZ, aamarPay, shurjoPay, bKash, Nagad).
 *                         A fee receipt is created only after server-side verification with the gateway.
 *                         unique (gateway, gateway_txn_id) stops one gateway transaction from paying twice.
 *  online_payment_events  every initiation, browser return, IPN, verification and refund call (secrets removed) — the audit
 *                         trail for reconciliation and disputes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('gateway', 20);
            $table->string('tran_id', 40);                           // ours, sent to the gateway as the order / transaction id
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->json('invoice_ids');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('BDT');
            $table->string('status', 20)->default('initiated');     // initiated | pending | paid | failed | cancelled | expired | review | refunded
            $table->string('gateway_ref', 120)->nullable();          // session key / paymentID / sp_order_id / paymentReferenceId
            $table->string('gateway_txn_id', 120)->nullable();       // bank / wallet transaction id after payment
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->string('payment_method', 60)->nullable();        // card brand, wallet …
            $table->foreignId('fee_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payer_ip', 45)->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->string('refund_ref', 120)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'tran_id']);
            $table->unique(['gateway', 'gateway_txn_id']);
            $table->index(['branch_id', 'status', 'created_at']);
            $table->index(['gateway', 'gateway_ref']);
        });

        Schema::create('online_payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('online_payment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('gateway', 20);
            $table->string('type', 20);                              // initiate | return | ipn | verify | refund | error
            $table->string('result', 60)->nullable();
            $table->json('payload')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['online_payment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_payment_events');
        Schema::dropIfExists('online_payments');
    }
};
