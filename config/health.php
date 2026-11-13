<?php

// A-3 (R2-69): platform health checks every 10 minutes (PlatformHealth). Turn the URL check off on machines with no web server.
return [
    'check_urls' => (bool) env('HEALTH_CHECK_URLS', true),
    // A-5: alert when the monthly restore test failed, is missing or is over 35 days old.
    'restore_test' => (bool) env('HEALTH_RESTORE_TEST', true),
];
