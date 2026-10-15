<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Plain notification e-mail (the message text of the hub, with the branch name and an optional link). */
class NotificationMail extends Mailable
{
    use Queueable;

    public function __construct(
        public string $subjectLine,
        public string $messageText,
        public ?string $link,
        public array $sender,
        public ?string $branchName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(from: new Address($this->sender["address"], $this->sender["name"]), subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.notification', text: 'emails.notification-text');
    }
}
