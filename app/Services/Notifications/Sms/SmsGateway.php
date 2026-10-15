<?php

namespace App\Services\Notifications\Sms;

use App\Services\Notifications\SendResult;

/** An SMS provider. $to is a normalised Bangladeshi number (8801XXXXXXXXX); $config holds the branch's credentials. */
interface SmsGateway
{
    public function send(string $to, string $text, array $config): SendResult;
}
