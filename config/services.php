<?php

return [

    // GA4 on the public site (D-255 follow-up, Rami 2026-11-12). Loaded only after the visitor accepts analytics cookies.
    'ga4' => [
        'measurement_id' => preg_match('/^G-[A-Z0-9]{4,20}$/', (string) env('GA4_MEASUREMENT_ID', '')) ? env('GA4_MEASUREMENT_ID') : null,
    ],

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

    // #74 "Continue with Google" on /app/login and /app/register. Empty keys = the button is hidden and the
    // routes answer 404. Create the OAuth client under Rami's personal Google account (D-67/D-75).
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/app/oauth/google/callback'),
    ],

    // E2-062 site audit A2: optional Google PageSpeed Insights v5 key (same key as the landing-page check). Empty = static asset checks only.
    'pagespeed' => [
        'key' => env('PAGESPEED_API_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // DB-05 (D-292): the client intake form's extra fields show only once Business's LEGAL line is set here.
    'client_intake' => [
        'legal_line' => env('CLIENT_INTAKE_LEGAL_LINE'),
    ],

];
