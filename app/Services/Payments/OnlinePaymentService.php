<?php

namespace App\Services\Payments;

use App\Models\Finance\FeeInvoice;
use App\Models\Payments\OnlinePayment;
use App\Models\Payments\OnlinePaymentEvent;
use App\Models\Students\Student;
use App\Models\User;
use App\Services\Finance\FeePaymentService;
use App\Services\Notifications\PhoneNumber;
use App\Services\Payments\BanglaQr\EmvQr;
use App\Services\Tenancy\SequenceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Online fee payments.
 *
 *   start()          records the attempt, asks the gateway for a checkout and returns where to send the payer
 *   handleCallback() browser return / IPN: finds the attempt, asks the gateway server-to-server, then finalize()
 *   finalize()       idempotent (row lock + status check): a verified payment of the right amount becomes a fee receipt
 *                    (FeePaymentService::collect — books, invoices, notifications); anything odd goes to "review"
 *   reconcile()      scheduler: re-asks the gateway about open attempts and expires abandoned ones
 *   refund()         full refund: the receipt is reversed and the gateway refund (or a recorded manual refund) follows
 *
 * A browser redirect is never proof of payment; only verify() results are.
 */
class OnlinePaymentService
{
    public function __construct(
        protected PaymentSettings $settings,
        protected FeePaymentService $payments,
        protected SequenceService $sequences,
    ) {}

    /** @param int[] $invoiceIds @return array{0:OnlinePayment,1:string} the attempt and the checkout URL */
    /** @param string $channel 'web' (portal) or 'api' (mobile app: the payer lands on a public result page afterwards) */
    public function start(Student $student, array $invoiceIds, string $gateway, User $payer, string $ip, string $channel = 'web'): array
    {
        if (! $this->settings->isEnabled($gateway)) {
            throw ValidationException::withMessages(['gateway' => __('This payment method is not available.')]);
        }
        $invoices = FeeInvoice::query()->open()->where('student_id', $student->id)->whereIn('id', $invoiceIds ?: [0])->orderBy('due_date')->get();
        if ($invoices->isEmpty() || $invoices->count() !== count(array_unique($invoiceIds))) {
            throw ValidationException::withMessages(['invoice_ids' => __('Choose unpaid invoices of this student.')]);
        }
        $amount = round((float) $invoices->sum('balance'), 2);
        if ($amount < 10) {
            throw ValidationException::withMessages(['invoice_ids' => __('The amount is too small for online payment.')]);
        }

        if ($this->settings->isManual($gateway)) {
            return $this->startManual($student, $invoices, $amount, $gateway, $payer, $ip, $channel);
        }

        $payment = new OnlinePayment([
            'gateway' => $gateway, 'tran_id' => $this->tranId(), 'student_id' => $student->id, 'invoice_ids' => $invoices->pluck('id')->all(),
            'amount' => $amount, 'currency' => 'BDT', 'payer_user_id' => $payer->id, 'payer_ip' => $ip,
            'expires_at' => now()->addMinutes((int) config('payments.expire_after_minutes', 120)),
        ]);
        $payment->forceFill(['status' => 'initiated', 'meta' => ['sandbox' => $this->settings->sandbox($gateway), 'channel' => $channel]])->save();

        $tran = $payment->tran_id;
        $urls = [
            'return' => route('payments.return', ['gateway' => $gateway, 'tran' => $tran, 'result' => 'success']),
            'fail' => route('payments.return', ['gateway' => $gateway, 'tran' => $tran, 'result' => 'fail']),
            'cancel' => route('payments.return', ['gateway' => $gateway, 'tran' => $tran, 'result' => 'cancel']),
            'ipn' => route('payments.ipn', ['gateway' => $gateway]),
        ];

        try {
            $result = $this->settings->gateway($gateway)->initiate($payment, $this->customer($student, $payer, $invoices, $ip), $urls, $this->settings->config($gateway));
        } catch (GatewayException $e) {
            $payment->forceFill(['status' => 'failed', 'failure_reason' => mb_substr($e->getMessage(), 0, 255)])->save();
            $this->log($payment, 'initiate', 'error', $e->raw + ['message' => $e->getMessage()], $ip);
            throw ValidationException::withMessages(['gateway' => $e->getMessage()]);
        }

        $payment->forceFill(['gateway_ref' => $result->gatewayRef ? mb_substr($result->gatewayRef, 0, 120) : null])->save();
        $this->log($payment, 'initiate', 'redirect', $result->raw, $ip);

        return [$payment, $result->redirectUrl];
    }

    /** Browser return or IPN. Returns the attempt (after verification) or null when it is not ours. */
    public function handleCallback(string $gateway, array $input, string $type, ?string $ip = null): ?OnlinePayment
    {
        $tran = $input['tran'] ?? $input['tran_id'] ?? $input['value_a'] ?? $input['mer_txnid'] ?? $input['merchantInvoiceNumber'] ?? null;
        $payment = $tran ? OnlinePayment::query()->where('gateway', $gateway)->where('tran_id', (string) $tran)->first() : null;
        if (! $payment) {
            $this->log(null, $type, 'unknown transaction', ['gateway' => $gateway] + $input, $ip, $gateway);

            return null;
        }
        $this->log($payment, $type, (string) ($input['result'] ?? $input['status'] ?? ''), $input, $ip);

        return $this->verifyAndFinalize($payment, $input, $type, $ip);
    }

    public function requery(OnlinePayment $payment): OnlinePayment
    {
        return $this->verifyAndFinalize($payment, [], 'verify', null);
    }

    public function verifyAndFinalize(OnlinePayment $payment, array $input, string $source, ?string $ip): OnlinePayment
    {
        if (in_array($payment->status, ['paid', 'review', 'refunded'], true) || $this->settings->isManual($payment->gateway)) {
            return $payment;   // already settled — a repeated return / IPN does nothing; manual (Bangla QR) payments have no gateway to ask
        }
        try {
            $result = $this->settings->gateway($payment->gateway)->verify($payment, $input, $this->settings->config($payment->gateway));
        } catch (GatewayException $e) {
            $this->log($payment, 'verify', 'error', $e->raw + ['message' => $e->getMessage()], $ip);

            return $payment;   // try again later (reconciliation)
        }
        $this->log($payment, 'verify', $result->status, $result->raw + array_filter(['message' => $result->message]), $ip);

        return $this->finalize($payment, $result, $source, $input);
    }

    public function finalize(OnlinePayment $payment, VerifyResult $result, string $source = 'verify', array $input = []): OnlinePayment
    {
        return DB::transaction(function () use ($payment, $result, $source, $input) {
            $payment = OnlinePayment::query()->lockForUpdate()->findOrFail($payment->id);
            if (in_array($payment->status, ['paid', 'review', 'refunded'], true)) {
                return $payment;
            }
            $payment->forceFill(['verified_at' => now()]);

            if ($result->status === 'paid') {
                return $this->settle($payment, $result);
            }
            if (! $payment->isOpen()) {
                return $payment;   // failed / cancelled / expired stay so unless the gateway later says "paid"
            }
            // The browser came back through the fail / cancel URL and the gateway has nothing paid yet: believe the "not paid"
            // (never a "paid") — reconciliation still re-checks such attempts for an hour in case the gateway was slow.
            $browser = $source === 'return' ? ($input['result'] ?? null) : null;
            match (true) {
                in_array($result->status, ['failed', 'cancelled'], true) => $payment->forceFill(['status' => $result->status, 'failure_reason' => mb_substr((string) $result->message, 0, 255)]),
                $browser === 'cancel' => $payment->forceFill(['status' => 'cancelled', 'failure_reason' => __('Cancelled by the payer.')]),
                $browser === 'fail' => $payment->forceFill(['status' => 'failed', 'failure_reason' => __('The gateway reported a failed payment.')]),
                $source !== 'verify' => $payment->forceFill(['status' => 'pending']),
                default => null,
            };
            $payment->save();

            return $payment;
        });
    }

    /** Scheduler: re-ask about open attempts (and recently failed ones), expire abandoned ones. */
    public function reconcile(): array
    {
        $stats = ['checked' => 0, 'paid' => 0, 'expired' => 0];
        OnlinePayment::query()->where('created_at', '>=', now()->subDays(3))
            ->where(fn ($q) => $q->where(fn ($o) => $o->open()->where('created_at', '<=', now()->subMinutes(3)))
                ->orWhere(fn ($r) => $r->whereIn('status', ['failed', 'cancelled'])->where('updated_at', '>=', now()->subHour())))
            ->orderBy('id')->limit(200)->get()
            ->each(function (OnlinePayment $payment) use (&$stats) {
                $stats['checked']++;
                $payment = $this->requery($payment);
                if ($payment->status === 'paid') {
                    $stats['paid']++;
                } elseif ($payment->isOpen() && $payment->expires_at && $payment->expires_at->isPast()) {
                    $payment->forceFill(['status' => 'expired', 'failure_reason' => __('The payer did not complete the payment.')])->save();
                    $stats['expired']++;
                }
            });

        // Bangla QR attempts live for days and have nothing to re-verify: just close the ones nobody paid.
        OnlinePayment::query()->open()->whereIn('gateway', array_keys(array_filter(config('payments.gateways'), fn ($g) => $g['manual'] ?? false)))
            ->where('expires_at', '<', now())->get()->each(function (OnlinePayment $payment) use (&$stats) {
                $payment->forceFill(['status' => 'expired', 'failure_reason' => __('The payer did not complete the payment.')])->save();
                $stats['expired']++;
            });

        return $stats;
    }

    /**
     * Bangla QR: the payer scans a QR carrying the exact amount and our payment reference as the bill number. The attempt stays
     * "pending" until the payer sends the TrxID (submitClaim → "review") and the accounts office accepts it, or a bank statement
     * row matches it (acceptFromStatement). The same unpaid invoices reuse one open attempt, so a refresh never piles up codes.
     */
    protected function startManual(Student $student, $invoices, float $amount, string $gateway, User $payer, string $ip, string $channel): array
    {
        $ids = $invoices->pluck('id')->map(fn ($i) => (int) $i)->sort()->values()->all();
        $existing = OnlinePayment::query()->where('gateway', $gateway)->where('student_id', $student->id)->open()
            ->where('expires_at', '>', now())->get()
            ->first(fn (OnlinePayment $p) => collect((array) $p->invoice_ids)->map(fn ($i) => (int) $i)->sort()->values()->all() === $ids && abs($p->amount - $amount) < 0.01);
        if ($existing) {
            return [$existing, route('payments.qr', $existing->tran_id)];
        }
        $payment = new OnlinePayment([
            'gateway' => $gateway, 'tran_id' => $this->tranId(), 'student_id' => $student->id, 'invoice_ids' => $ids,
            'amount' => $amount, 'currency' => 'BDT', 'payer_user_id' => $payer->id, 'payer_ip' => $ip,
            'expires_at' => now()->addMinutes((int) config('payments.qr_expire_after_minutes', 4320)),
        ]);
        $payment->forceFill(['status' => 'pending', 'meta' => ['sandbox' => false, 'channel' => $channel, 'manual' => true]])->save();
        $this->log($payment, 'initiate', 'qr', ['amount' => $amount, 'invoices' => $ids], $ip);

        return [$payment, route('payments.qr', $payment->tran_id)];
    }

    /** The Bangla QR text of one attempt (EMV, dynamic: amount + bill number = our reference + student ID). */
    public function qrPayload(OnlinePayment $payment): string
    {
        $static = $this->settings->qrText();
        if ($static === null) {
            throw ValidationException::withMessages(['gateway' => __('The school has not set up Bangla QR yet.')]);
        }

        return EmvQr::dynamic($static, (float) $payment->amount, $payment->tran_id, $payment->student?->student_code);
    }

    /**
     * The payer says: "I paid, here is the TrxID". The attempt goes to "review" for the accounts office, who match it with the
     * bank / wallet statement. A TrxID that already belongs to another attempt is refused.
     *
     * @param  array{trx_id:string, amount?:float|string|null, payer_account?:?string, paid_at?:?string}  $data
     */
    public function submitClaim(OnlinePayment $payment, array $data, ?string $ip = null): OnlinePayment
    {
        if (! $this->settings->isManual($payment->gateway)) {
            throw ValidationException::withMessages(['trx_id' => __('This payment method does not take a transaction ID.')]);
        }
        $trx = strtoupper(trim((string) $data['trx_id']));

        return DB::transaction(function () use ($payment, $data, $trx, $ip) {
            $payment = OnlinePayment::query()->lockForUpdate()->findOrFail($payment->id);
            if (! in_array($payment->status, ['initiated', 'pending', 'expired'], true)) {
                throw ValidationException::withMessages(['trx_id' => __('This payment has already been sent to the accounts office.')]);
            }
            if (OnlinePayment::query()->where('gateway', $payment->gateway)->where('gateway_txn_id', $trx)->whereKeyNot($payment->id)->exists()) {
                throw ValidationException::withMessages(['trx_id' => __('This transaction ID was already used for another payment.')]);
            }
            $paid = isset($data['amount']) && $data['amount'] !== '' ? round((float) $data['amount'], 2) : (float) $payment->amount;
            $note = abs($paid - $payment->amount) > 0.009
                ? __('The payer reports TrxID :trx with ৳:paid, but ৳:asked was asked. Check the statement before accepting.', ['trx' => $trx, 'paid' => number_format($paid, 2), 'asked' => number_format($payment->amount, 2)])
                : __('The payer reports TrxID :trx (৳:paid). Check it in the bank / wallet statement, then accept or reject.', ['trx' => $trx, 'paid' => number_format($paid, 2)]);
            $meta = (array) $payment->meta;
            $meta['claim'] = array_filter(['trx_id' => $trx, 'amount' => $paid, 'payer_account' => $data['payer_account'] ?? null, 'paid_at' => $data['paid_at'] ?? null, 'at' => now()->toIso8601String()]);
            $payment->forceFill([
                'gateway_txn_id' => $trx, 'paid_amount' => $paid, 'payment_method' => mb_substr((string) ($data['payer_account'] ?? ''), 0, 60) ?: null,
                'paid_at' => ! empty($data['paid_at']) ? $data['paid_at'] : now(), 'status' => 'review', 'failure_reason' => mb_substr($note, 0, 255), 'meta' => $meta,
            ])->save();
            $this->log($payment, 'claim', 'review', ['trx_id' => $trx, 'amount' => $paid, 'payer_account' => $data['payer_account'] ?? null], $ip);

            return $payment;
        });
    }

    /**
     * Bank / wallet statement rows ([['trx' => …, 'amount' => …, 'ref' => text that may hold our reference]]): a row that matches
     * a payer's claim (same TrxID and amount) is accepted; a row whose text holds the reference of an unclaimed attempt with the
     * same amount is accepted too (the payer paid but never sent the TrxID). Everything else is reported, nothing guessed.
     *
     * @return array{accepted:list<array>, already:list<array>, mismatch:list<array>, unmatched:list<array>}
     */
    public function reconcileStatement(array $rows, User $user): array
    {
        $out = ['accepted' => [], 'already' => [], 'mismatch' => [], 'unmatched' => []];
        foreach ($rows as $row) {
            $trx = strtoupper(trim((string) ($row['trx'] ?? '')));
            $amount = round((float) ($row['amount'] ?? 0), 2);
            if ($trx === '' || $amount <= 0) {
                continue;
            }
            $byTrx = OnlinePayment::query()->where('gateway', 'banglaqr')->where('gateway_txn_id', $trx)->first();
            if ($byTrx) {
                if ($byTrx->status === 'paid') {
                    $out['already'][] = $row + ['tran_id' => $byTrx->tran_id];
                } elseif ($byTrx->status === 'review' && abs((float) $byTrx->paid_amount - $amount) < 0.01) {
                    $this->resolveReview($byTrx, 'accept', __('Matched with the bank statement (TrxID :trx).', ['trx' => $trx]), $user);
                    $out['accepted'][] = $row + ['tran_id' => $byTrx->tran_id, 'by' => 'trx'];
                } else {
                    $out['mismatch'][] = $row + ['tran_id' => $byTrx->tran_id, 'expected' => $byTrx->paid_amount, 'status' => $byTrx->status];
                }

                continue;
            }
            $open = OnlinePayment::query()->where('gateway', 'banglaqr')->whereIn('status', ['initiated', 'pending', 'expired'])
                ->where('created_at', '>=', now()->subDays(30))->get()->first(fn ($p) => stripos((string) ($row['ref'] ?? ''), $p->tran_id) !== false);
            if ($open && abs($open->amount - $amount) < 0.01) {
                $payment = $this->submitClaim($open, ['trx_id' => $trx, 'amount' => $amount, 'paid_at' => $row['date'] ?? null], null);
                $this->resolveReview($payment, 'accept', __('Matched with the bank statement by payment reference :ref.', ['ref' => $open->tran_id]), $user);
                $out['accepted'][] = $row + ['tran_id' => $open->tran_id, 'by' => 'reference'];
            } elseif ($open) {
                $out['mismatch'][] = $row + ['tran_id' => $open->tran_id, 'expected' => $open->amount, 'status' => 'pending'];
            } else {
                $out['unmatched'][] = $row;
            }
        }

        return $out;
    }

    /**
     * Full refund of a paid attempt. The fee receipt is reversed (the invoices open again) in the same transaction
     * as the gateway refund call, so a refused refund leaves the books unchanged. Gateways without a refund API need
     * $manualReference: the refund made from the merchant panel.
     */
    public function refund(OnlinePayment $payment, string $reason, User $user, ?string $manualReference = null): OnlinePayment
    {
        if ($payment->status !== 'paid' || ! $payment->feePayment) {
            throw ValidationException::withMessages(['reason' => __('Only a paid online payment can be refunded.')]);
        }
        $gateway = $this->settings->gateway($payment->gateway);
        if (! $gateway->supportsRefund() && blank($manualReference)) {
            throw ValidationException::withMessages(['reference' => __('Refund the payer from the gateway merchant panel, then enter its refund reference.')]);
        }

        return DB::transaction(function () use ($payment, $reason, $user, $manualReference, $gateway) {
            $payment = OnlinePayment::query()->lockForUpdate()->findOrFail($payment->id);
            $this->payments->reverse($payment->feePayment, __('Online payment :tran refunded — :reason', ['tran' => $payment->tran_id, 'reason' => $reason]), $user);

            if (filled($manualReference)) {
                $reference = $manualReference;
                $this->log($payment, 'refund', 'manual', ['reference' => $manualReference, 'reason' => $reason, 'by' => $user->id]);
            } else {
                $result = $gateway->refund($payment, (float) $payment->paid_amount, $reason, $this->settings->config($payment->gateway));
                $this->log($payment, 'refund', $result->ok ? 'ok' : 'refused', $result->raw + ['message' => $result->message]);
                if (! $result->ok) {
                    throw ValidationException::withMessages(['reason' => __('The gateway refused the refund: :message', ['message' => $result->message])]);
                }
                $reference = $result->reference;
            }
            $payment->forceFill(['status' => 'refunded', 'refunded_amount' => $payment->paid_amount, 'refund_ref' => $reference ? mb_substr($reference, 0, 120) : null])->save();

            return $payment;
        });
    }

    /**
     * The accounts office decides about a payment held for review: "accept" posts the money receipt for the amount
     * the gateway reported (any surplus stays as advance credit), "reject" closes the attempt (the payer is refunded
     * from the merchant panel). Either way the decision and its note are logged.
     */
    public function resolveReview(OnlinePayment $payment, string $decision, string $note, User $user): OnlinePayment
    {
        if ($payment->status !== 'review') {
            throw ValidationException::withMessages(['decision' => __('Only a payment held for review can be accepted or rejected.')]);
        }

        return DB::transaction(function () use ($payment, $decision, $note, $user) {
            $payment = OnlinePayment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($decision === 'accept') {
                $amount = (float) ($payment->paid_amount ?? $payment->amount);
                $receipt = $this->payments->collect($payment->student, $amount, (string) config("payments.gateways.{$payment->gateway}.method", 'online'),
                    $this->settings->account($payment->gateway), now(), mb_substr(config("payments.gateways.{$payment->gateway}.short").' '.$payment->gateway_txn_id, 0, 100),
                    __('Online payment :tran (accepted after review: :note)', ['tran' => $payment->tran_id, 'note' => $note]), (array) $payment->invoice_ids, $user);
                $payment->forceFill(['status' => 'paid', 'fee_payment_id' => $receipt->id])->save();
            } else {
                $payment->forceFill(['status' => 'failed', 'failure_reason' => mb_substr(__('Rejected after review: :note', ['note' => $note]), 0, 255)])->save();
            }
            $this->log($payment, 'review', $decision, ['note' => $note, 'by' => $user->id]);

            return $payment;
        });
    }

    protected function settle(OnlinePayment $payment, VerifyResult $result): OnlinePayment
    {
        $payment->forceFill([
            'gateway_txn_id' => $result->txnId ? mb_substr($result->txnId, 0, 120) : null,
            'paid_amount' => $result->amount, 'payment_method' => $result->method ? mb_substr($result->method, 0, 60) : null, 'paid_at' => now(),
        ]);
        $review = match (true) {
            $result->txnId && OnlinePayment::query()->where('gateway', $payment->gateway)->where('gateway_txn_id', $result->txnId)->whereKeyNot($payment->id)->exists()
                => __('This gateway transaction already paid another attempt.'),
            strtoupper((string) $result->currency) !== 'BDT' => __('Paid in :currency, not BDT.', ['currency' => $result->currency]),
            abs((float) $result->amount - $payment->amount) > 0.009 => __('Paid :paid instead of :amount.', ['paid' => number_format((float) $result->amount, 2), 'amount' => number_format($payment->amount, 2)]),
            $result->reviewReason !== null => $result->reviewReason,
            $result->risky => __('The gateway marked this payment as risky. Check it in the merchant panel before accepting it.'),
            default => null,
        };
        if ($review) {
            $payment->forceFill(['status' => 'review', 'failure_reason' => mb_substr($review, 0, 255), 'gateway_txn_id' => $this->uniqueTxn($payment, $result->txnId)])->save();
            $this->log($payment, 'verify', 'review', ['reason' => $review]);

            return $payment;
        }

        try {
            $receipt = $this->payments->collect($payment->student, (float) $result->amount, (string) config("payments.gateways.{$payment->gateway}.method", 'online'),
                $this->settings->account($payment->gateway), now(), mb_substr(config("payments.gateways.{$payment->gateway}.short").' '.$result->txnId, 0, 100),
                __('Online payment :tran', ['tran' => $payment->tran_id]), (array) $payment->invoice_ids, null);
        } catch (\Throwable $e) {
            // e.g. the financial year is closed: the money arrived, so keep it visible for the accounts office.
            Log::warning('Online payment could not be posted', ['tran' => $payment->tran_id, 'error' => $e->getMessage()]);
            $payment->forceFill(['status' => 'review', 'failure_reason' => mb_substr(__('Paid, but the receipt could not be posted: :error', ['error' => $e->getMessage()]), 0, 255)])->save();

            return $payment;
        }
        $payment->forceFill(['status' => 'paid', 'fee_payment_id' => $receipt->id, 'failure_reason' => null])->save();

        return $payment;
    }

    /** A duplicate gateway transaction id cannot be stored twice (unique index); keep it readable but distinct. */
    protected function uniqueTxn(OnlinePayment $payment, ?string $txn): ?string
    {
        if (! $txn || ! OnlinePayment::query()->where('gateway', $payment->gateway)->where('gateway_txn_id', $txn)->whereKeyNot($payment->id)->exists()) {
            return $txn;
        }

        return mb_substr($txn.'#dup'.$payment->id, 0, 120);
    }

    protected function customer(Student $student, User $payer, $invoices, string $ip): array
    {
        $guardian = $student->guardians()->first();
        $phone = PhoneNumber::normalize($payer->phone) ?? PhoneNumber::normalize($guardian?->phone) ?? PhoneNumber::normalize($student->phone) ?? '8801700000000';
        $email = collect([$payer->email, $guardian?->email, $student->email])->first(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ?? 'payments@'.(parse_url(tenant()->branch()->url('/'), PHP_URL_HOST) ?: 'school.local');

        return [
            'name' => mb_substr($payer->name ?: ($guardian?->name ?? $student->name), 0, 50),
            'email' => $email,
            'phone' => '0'.substr($phone, 3),                            // 01XXXXXXXXX
            'address' => mb_substr($student->present_address ?: tenant()->branch()->displayName(), 0, 100),
            'city' => 'Dhaka',
            'postcode' => '1000',
            'reference' => $student->student_code,
            'ip' => $ip,
            'description' => mb_substr(__('School fees — :student (:invoices)', ['student' => $student->name, 'invoices' => $invoices->pluck('invoice_no')->implode(', ')]), 0, 250),
        ];
    }

    /** Short, letters-and-digits only (Nagad and aamarPay are strict): BR01P2603150000123. */
    protected function tranId(): string
    {
        $code = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper(tenant()->branch()->code)), 0, 5);

        return $code.'P'.now()->format('ymd').sprintf('%06d', $this->sequences->next('online_payment', now()->format('Y')));
    }

    public function log(?OnlinePayment $payment, string $type, string $result, array $payload, ?string $ip = null, ?string $gateway = null): void
    {
        OnlinePaymentEvent::query()->create([
            'online_payment_id' => $payment?->id, 'gateway' => $payment?->gateway ?? $gateway ?? 'unknown', 'type' => $type,
            'result' => mb_substr($result, 0, 60), 'payload' => $this->redact($payload), 'ip' => $ip,
        ]);
    }

    /** Remove secrets and card data from what is stored. */
    protected function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->redact($value);
            } elseif (is_string($key) && preg_match('/pass|secret|token|signature|sensitive|app_key|signature_key|card_no|cardnumber|cvv/i', $key)) {
                $data[$key] = '***';
            }
        }

        return $data;
    }
}
