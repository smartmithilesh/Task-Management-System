<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'integrations' => [
        'oauth' => [
            'slack' => [
                'enabled' => env('SLACK_OAUTH_CLIENT_ID') !== null && env('SLACK_OAUTH_CLIENT_ID') !== '' && env('SLACK_OAUTH_CLIENT_SECRET') !== null && env('SLACK_OAUTH_CLIENT_SECRET') !== '',
                'client_id' => env('SLACK_OAUTH_CLIENT_ID'),
                'client_secret' => env('SLACK_OAUTH_CLIENT_SECRET'),
                'authorization_url' => 'https://slack.com/oauth/v2/authorize',
                'token_url' => 'https://slack.com/api/oauth.v2.access',
                'test_url' => 'https://slack.com/api/auth.test',
                'scopes' => array_filter(explode(',', (string) env('SLACK_OAUTH_SCOPES', 'chat:write'))),
                'capabilities' => ['oauth2_authorization_code', 'connection_test', 'event_delivery'],
            ],
            'github' => [
                'enabled' => env('GITHUB_OAUTH_CLIENT_ID') !== null && env('GITHUB_OAUTH_CLIENT_ID') !== '' && env('GITHUB_OAUTH_CLIENT_SECRET') !== null && env('GITHUB_OAUTH_CLIENT_SECRET') !== '',
                'client_id' => env('GITHUB_OAUTH_CLIENT_ID'),
                'client_secret' => env('GITHUB_OAUTH_CLIENT_SECRET'),
                'authorization_url' => 'https://github.com/login/oauth/authorize',
                'token_url' => 'https://github.com/login/oauth/access_token',
                'test_url' => 'https://api.github.com/user',
                'scopes' => array_filter(explode(',', (string) env('GITHUB_OAUTH_SCOPES', 'read:user'))),
                'capabilities' => ['oauth2_authorization_code', 'connection_test'],
            ],
            'google_calendar' => [
                'enabled' => env('GOOGLE_OAUTH_CLIENT_ID') !== null && env('GOOGLE_OAUTH_CLIENT_ID') !== '' && env('GOOGLE_OAUTH_CLIENT_SECRET') !== null && env('GOOGLE_OAUTH_CLIENT_SECRET') !== '',
                'client_id' => env('GOOGLE_OAUTH_CLIENT_ID'),
                'client_secret' => env('GOOGLE_OAUTH_CLIENT_SECRET'),
                'authorization_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token_url' => 'https://oauth2.googleapis.com/token',
                'test_url' => 'https://www.googleapis.com/calendar/v3/users/me/calendarList?maxResults=1',
                'scopes' => array_filter(explode(',', (string) env('GOOGLE_CALENDAR_OAUTH_SCOPES', 'https://www.googleapis.com/auth/calendar.events'))),
                'authorization_parameters' => ['access_type' => 'offline', 'prompt' => 'consent'],
                'capabilities' => ['oauth2_authorization_code', 'connection_test', 'token_refresh', 'event_delivery'],
            ],
            'microsoft_calendar' => [
                'enabled' => env('MICROSOFT_OAUTH_CLIENT_ID') !== null && env('MICROSOFT_OAUTH_CLIENT_ID') !== '' && env('MICROSOFT_OAUTH_CLIENT_SECRET') !== null && env('MICROSOFT_OAUTH_CLIENT_SECRET') !== '',
                'client_id' => env('MICROSOFT_OAUTH_CLIENT_ID'),
                'client_secret' => env('MICROSOFT_OAUTH_CLIENT_SECRET'),
                'authorization_url' => 'https://login.microsoftonline.com/'.rawurlencode((string) env('MICROSOFT_OAUTH_TENANT', 'organizations')).'/oauth2/v2.0/authorize',
                'token_url' => 'https://login.microsoftonline.com/'.rawurlencode((string) env('MICROSOFT_OAUTH_TENANT', 'organizations')).'/oauth2/v2.0/token',
                'test_url' => 'https://graph.microsoft.com/v1.0/me',
                'scopes' => array_filter(explode(',', (string) env('MICROSOFT_CALENDAR_OAUTH_SCOPES', 'offline_access,User.Read,Calendars.ReadWrite'))),
                'capabilities' => ['oauth2_authorization_code', 'connection_test', 'token_refresh', 'event_delivery'],
            ],
            'dropbox' => [
                'enabled' => env('DROPBOX_OAUTH_CLIENT_ID') !== null && env('DROPBOX_OAUTH_CLIENT_ID') !== '' && env('DROPBOX_OAUTH_CLIENT_SECRET') !== null && env('DROPBOX_OAUTH_CLIENT_SECRET') !== '',
                'client_id' => env('DROPBOX_OAUTH_CLIENT_ID'),
                'client_secret' => env('DROPBOX_OAUTH_CLIENT_SECRET'),
                'authorization_url' => 'https://www.dropbox.com/oauth2/authorize',
                'token_url' => 'https://api.dropboxapi.com/oauth2/token',
                'test_url' => 'https://api.dropboxapi.com/2/users/get_current_account',
                'test_method' => 'POST',
                'scopes' => array_filter(explode(',', (string) env('DROPBOX_OAUTH_SCOPES', 'account_info.read,files.metadata.read'))),
                'authorization_parameters' => ['token_access_type' => 'offline'],
                'capabilities' => ['oauth2_authorization_code', 'connection_test', 'token_refresh'],
            ],
        ],
    ],

    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'client_email' => env('FIREBASE_CLIENT_EMAIL'),
        'private_key' => env('FIREBASE_PRIVATE_KEY'),
    ],

];
