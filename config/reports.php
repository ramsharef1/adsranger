<?php

/*
 * #106 scheduled email & WhatsApp reports.
 *
 * email_enabled: master switch for the daily send job (REPORTS_EMAIL_ENABLED). Default true.
 * whatsapp_enabled: master switch for WhatsApp scheduled sends (REPORTS_WHATSAPP_ENABLED). Default true.
 */
return [
    'email_enabled' => (bool) env('REPORTS_EMAIL_ENABLED', true),
    'whatsapp_enabled' => (bool) env('REPORTS_WHATSAPP_ENABLED', true),
    'whatsapp_phone_id' => env('WHATSAPP_PHONE_ID'),
    'whatsapp_token' => env('WHATSAPP_TOKEN'),

    'max_recipients' => 10,

    // The daily job runs at this UTC time (routes/console.php); "today" for each schedule is the workspace's date.
    'send_at_utc' => '05:20',
];
