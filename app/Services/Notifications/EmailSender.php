<?php

namespace App\Services\Notifications;

use App\Mail\NotificationMail;
use App\Models\Communication\NotificationMessage;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/** Sends one e-mail through the branch's SMTP server when configured, otherwise the application's mailer. */
class EmailSender
{
    public function __construct(protected NotificationSettings $settings) {}

    public function send(NotificationMessage $message): SendResult
    {
        $from = $this->settings->mailFrom();
        $mail = new NotificationMail($message->subject ?: config('app.name'), $message->body, $message->url, $from, tenant()->branch()?->displayName());

        try {
            $smtp = $this->settings->smtp();
            $mailer = $smtp ? Mail::build([
                'transport' => 'smtp',
                'host' => $smtp['host'],
                'port' => (int) ($smtp['port'] ?? 587),
                'encryption' => ($smtp['encryption'] ?? 'tls') ?: null,
                'username' => $smtp['username'] ?? null,
                'password' => $smtp['password'] ?? null,
                'timeout' => 20,
            ]) : Mail::mailer();
            $mailer->to($message->to, $message->recipient_name)->send($mail);
        } catch (TransportExceptionInterface $e) {
            return SendResult::failed('Mail: '.$e->getMessage(), true);
        } catch (\Throwable $e) {
            return SendResult::failed('Mail: '.$e->getMessage());
        }

        return SendResult::sent();
    }
}
