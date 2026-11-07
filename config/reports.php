<?php

/*
 * #106 scheduled email & WhatsApp reports.
 *
 * email_enabled: master switch for the daily send job (REPORTS_EMAIL_ENABLED). Default true.
 * whatsapp_enabled: master switch for WhatsApp scheduled sends (REPORTS_WHATSAPP_ENABLED). Default false (ADSR-110 0.2).
 */
return [
    'email_enabled' => (bool) env('REPORTS_EMAIL_ENABLED', true),
    'whatsapp_enabled' => (bool) env('REPORTS_WHATSAPP_ENABLED', false), // ADSR-110 0.2: off until #162 / #171 (D-94); module 'whatsapp_reports'
    'whatsapp_phone_id' => env('WHATSAPP_PHONE_ID'),
    'whatsapp_token' => env('WHATSAPP_TOKEN'),

    'max_recipients' => 10,

    // The daily job runs at this UTC time (routes/console.php); "today" for each schedule is the workspace's date.
    'send_at_utc' => '05:20',
];
