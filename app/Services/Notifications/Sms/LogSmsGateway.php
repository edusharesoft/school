<?php

namespace App\Services\Notifications\Sms;

use App\Services\Notifications\SendResult;
use Illuminate\Support\Facades\Log;

/** Test driver: writes the message to the application log. Nothing reaches a phone; the history shows provider "log". */
class LogSmsGateway implements SmsGateway
{
    public function send(string $to, string $text, array $config): SendResult
    {
        Log::info('[sms:log] '.$to.' — '.$text);

        return SendResult::sent('log-'.bin2hex(random_bytes(6)));
    }
}
