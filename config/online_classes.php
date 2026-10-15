<?php

/*
| Live-class providers. Credentials are entered per branch (Learning → Online class integrations) and stored
| encrypted — nothing secret belongs in this file or in .env.
|
| fields[]: key => [label, type (text|password|url), required, help]
*/
return [
    'providers' => [
        'zoom' => [
            'label' => 'Zoom',
            'auth' => 'server_to_server',
            'fields' => [
                'account_id' => ['label' => 'Account ID', 'type' => 'text', 'required' => true],
                'client_id' => ['label' => 'Client ID', 'type' => 'text', 'required' => true],
                'client_secret' => ['label' => 'Client secret', 'type' => 'password', 'required' => true, 'secret' => true],
                'host_user' => ['label' => 'Host user (e-mail or "me")', 'type' => 'text', 'required' => false, 'default' => 'me'],
            ],
            'help' => 'Create a Server-to-Server OAuth app in the Zoom App Marketplace with the scopes meeting:write:admin, meeting:read:admin and cloud_recording:read:admin.',
            'api' => 'https://api.zoom.us/v2',
            'oauth' => 'https://zoom.us/oauth/token',
        ],
        'google_meet' => [
            'label' => 'Google Meet',
            'auth' => 'oauth_code',
            'fields' => [
                'client_id' => ['label' => 'OAuth client ID', 'type' => 'text', 'required' => true],
                'client_secret' => ['label' => 'OAuth client secret', 'type' => 'password', 'required' => true, 'secret' => true],
                'redirect_uri' => ['label' => 'Redirect URI (register this exact address in Google Cloud)', 'type' => 'url', 'required' => false],
                'calendar_id' => ['label' => 'Calendar ID', 'type' => 'text', 'required' => false, 'default' => 'primary'],
            ],
            'help' => 'Create an OAuth client (Web application) in Google Cloud with the Google Calendar API enabled, then press “Connect Google account”.',
            'api' => 'https://www.googleapis.com/calendar/v3',
            'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'scope' => 'https://www.googleapis.com/auth/calendar.events',
        ],
    ],

    'http_timeout' => 20,
    'default_duration_minutes' => 45,
];
