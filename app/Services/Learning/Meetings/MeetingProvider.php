<?php

namespace App\Services\Learning\Meetings;

use App\Models\Learning\OnlineClass;

interface MeetingProvider
{
    /** Create the meeting for this class and return its links. @throws MeetingException */
    public function create(OnlineClass $class): MeetingDetails;

    /** Push new title / time to an existing meeting. @throws MeetingException */
    public function update(OnlineClass $class): void;

    /** Cancel / delete the meeting. A meeting that is already gone is not an error. @throws MeetingException */
    public function cancel(OnlineClass $class): void;

    /** Link to the cloud recording, if the provider has one yet. @throws MeetingException */
    public function recording(OnlineClass $class): ?string;

    /** Verify the credentials with a harmless API call. @throws MeetingException */
    public function check(): void;
}
