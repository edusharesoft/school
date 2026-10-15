<?php

namespace App\Services\Payments\Gateways;

use App\Models\Payments\OnlinePayment;
use App\Services\Payments\GatewayException;
use App\Services\Payments\RefundResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/** Shared HTTP plumbing for the gateways. */
abstract class HttpGateway implements PaymentGateway
{
    public function supportsRefund(): bool
    {
        return false;
    }

    public function refund(OnlinePayment $payment, float $amount, string $reason, array $config): RefundResult
    {
        return new RefundResult(false, null, __('This gateway does not offer refunds through its API. Refund the payer from the merchant panel and record it here.'));
    }

    protected function http(): PendingRequest
    {
        return Http::timeout(30)->connectTimeout(10)->acceptJson();
    }

    /** Run a request; network problems become a GatewayException the payer can read. */
    protected function call(\Closure $request): Response
    {
        try {
            return $request($this->http());
        } catch (ConnectionException $e) {
            throw new GatewayException(__('The payment gateway could not be reached. Please try again in a few minutes.'), ['error' => $e->getMessage()]);
        }
    }

    protected function require(array $config, array $keys): void
    {
        foreach ($keys as $key) {
            if (blank($config[$key] ?? null)) {
                throw new GatewayException(__('Online payment is not configured correctly (:field missing). Please contact the school.', ['field' => $key]));
            }
        }
    }

    protected function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
