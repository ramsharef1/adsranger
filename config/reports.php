<?php

/*
 * #106 scheduled email reports.
 *
 * email_enabled: master switch for the daily send job (REPORTS_EMAIL_ENABLED). Default true: reports go out through
 * the app's current mailer (MAIL_MAILER). Brevo is not live yet (D-22), so with the server's own mail these emails
 * can land in spam; the schedule screen tells agencies to ask recipients to check spam. Set it to false to pause
 * every scheduled email at once without touching the schedules (nothing is queued or logged while it is off).
 */
return [
    'email_enabled' => (bool) env('REPORTS_EMAIL_ENABLED', true),

    'max_recipients' => 10,

    // The daily job runs at this UTC time (routes/console.php); "today" for each schedule is the workspace's date.
    'send_at_utc' => '05:20',
];
