<?php

namespace App\Services\Learning\Meetings;

/** What a provider returns when it creates a meeting. */
final class MeetingDetails
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $joinUrl,
        public readonly ?string $hostUrl = null,
        public readonly ?string $meetingId = null,
        public readonly ?string $passcode = null,
    ) {}
}
