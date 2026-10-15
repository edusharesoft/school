<?php

namespace App\Services\Notifications\Sms;

use App\Services\Notifications\SendResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/** Shared HTTP handling: timeouts, connection errors and 5xx / 429 are retryable; 4xx are not. */
abstract class HttpSmsGateway implements SmsGateway
{
    public function send(string $to, string $text, array $config): SendResult
    {
        foreach ($this->required() as $key) {
            if (blank($config[$key] ?? null)) {
                return SendResult::failed(__('SMS provider is not configured (:field missing).', ['field' => $key]));
            }
        }

        try {
            $response = $this->request($to, $text, $config);
        } catch (ConnectionException $e) {
            return SendResult::failed('Connection error: '.$e->getMessage(), true);
        }

        if ($response->serverError() || $response->status() === 429) {
            return SendResult::failed('HTTP '.$response->status(), true);
        }

        return $this->interpret($response);
    }

    protected function http()
    {
        return Http::timeout(20)->connectTimeout(10)->acceptJson();
    }

    /** @return string[] */
    abstract protected function required(): array;

    abstract protected function request(string $to, string $text, array $config): Response;

    abstract protected function interpret(Response $response): SendResult;
}
