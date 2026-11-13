<?php

// R1 ops + safety (ADSR-923, D-289). Everything here is about the server, never about customer data.
return [
    // EG-04 queue lanes. Off until the server's worker reads all three queues (`--queue=high,default,slow`); the deploy
    // script switches QUEUE_LANES on only after it changed and restarted the worker.
    'lanes_enabled' => (bool) env('QUEUE_LANES', false),
    'lanes' => [
        'high' => ['DailySync', 'SyncAdAccount', 'RunAlerts', 'CheckAccountHealth'],
        'slow' => ['RunNightShift', 'RunSeoAudit', 'VerifyOwnLinks', 'CheckLandingPages', 'SnapshotHealth', 'RunAccountAudit', 'RunTrialLifecycle'],
        // everything else (reports, webhooks) stays on `default`
    ],

    // EG-07: scheduled task name → what JobRuns records (job class or the closure's own name).
    'schedule' => [
        'daily-sync' => 'DailySync',
        'alerts' => 'RunAlerts',
        'platform-health' => 'platform-health',
        'health-snapshot' => 'SnapshotHealth',
        'landing-pages' => 'CheckLandingPages',
        'fx-pull' => 'fx-pull',
        'scheduled-reports' => 'QueueScheduledReports',
        'trial-lifecycle' => 'RunTrialLifecycle',
        'ai-night-shift' => 'RunNightShift',
        'links-verify' => 'VerifyOwnLinks',
        'ops-heartbeat' => 'ops-heartbeat',
        'ops-prune' => 'ops-prune',
        'ops-log-roll' => 'ops-log-roll',
        'ops-deadlines' => 'ops-deadlines',
        'ops-live-site' => 'ops-live-site',
        'usage-ledger' => 'usage-ledger',
    ],

    // EG-01: how long ops rows are kept (days). Customer data is never pruned here (D-164 / D-166: purge stays manual).
    'retention_days' => [
        'failed_jobs' => 30,
        'notifications_read' => 90,
        'ai_night_runs' => 90,
        'missing_pages' => 180,
        'job_runs_errors' => 90,
    ],

    // EG-02: roll laravel.log when it passes this size, or every Sunday; keep this many old files.
    'log_roll_mb' => 20,
    'log_keep' => 8,

    // EG-03: the heartbeat is stale after this many minutes.
    'heartbeat_stale_minutes' => 15,

    // EG-13 / EG-14: certificates to watch and the warning window.
    'cert_hosts' => array_values(array_filter([parse_url((string) env('APP_URL', 'https://adsranger.com'), PHP_URL_HOST), env('AI_MCP_HOST', 'mcp.adsranger.com')])),
    'cert_warn_days' => 14,

    // EG-06: what production must look like. Mail stays `log` while D-266 holds.
    'expect_mailer' => env('OPS_EXPECT_MAILER', 'log'),

    // FS-54: health-alert kind (prefix) → the runbook step that fixes it (ADSR-003).
    'runbook' => [
        'log' => 'ADSR-003 › Logs: open /admin/operations, paste the error reference',
        'failed_jobs' => 'ADSR-003 › Queue: php artisan queue:failed, then queue:retry <id> after the fix',
        'queue' => 'ADSR-003 › Queue: check the worker process, then php artisan queue:restart',
        'sync' => 'ADSR-003 › Sync: /admin › Live verification shows the failing account',
        'uptime' => 'ADSR-003 › Web: systemctl status nginx php84-php-fpm',
        'restore' => 'ADSR-003 › Backups: run /usr/local/bin/adsranger-restore-test.sh',
        'config' => 'ADSR-003 › .env: fix the key named in the alert, then php artisan optimize:clear',
        'cert' => 'ADSR-003 › TLS: certbot renew --dry-run, then certbot renew',
        'jobs' => 'ADSR-003 › Scheduler: crontab -l for schedule:run; /admin/operations shows which task',
        'live_site' => 'ADSR-003 › Site: /admin/operations lists the pages and rules',
    ],

    // Run the drift / cert checks outside production too (tests).
    'force_checks' => (bool) env('OPS_FORCE_CHECKS', false),
];
