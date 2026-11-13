<?php

// A-3 (R2-69): platform health checks every 10 minutes (PlatformHealth). Turn the URL check off on machines with no web server.
return [
    'check_urls' => (bool) env('HEALTH_CHECK_URLS', true),
];
