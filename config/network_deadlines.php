<?php

// EG-10 / WR-01 (D-289): ad-network API deadlines from ADSR-050 › Deadlines. `done` = our code already moved off the old
// version. The daily watch pushes once at 90, 30 and 7 days before each open one. Keep this list in step with 050.
return [
    ['date' => '2026-11-16', 'network' => 'LinkedIn', 'what' => 'Version 202511 switched off', 'action' => 'Default 202610', 'done' => true],
    ['date' => '2026-12-08', 'network' => 'Reddit', 'what' => 'HOUR breakdown over 7 days returns 400', 'action' => 'Keep HOUR out (contract test, cl102)', 'done' => true],
    ['date' => '2027-01-26', 'network' => 'Apple Ads', 'what' => 'Search Ads v5 switched off', 'action' => 'Platform API 1.0', 'done' => true],
    ['date' => '2027-01-31', 'network' => 'Microsoft Advertising', 'what' => 'SOAP switched off', 'action' => 'REST v13', 'done' => true],
    ['date' => '2027-05-01', 'network' => 'Google Ads', 'what' => 'API v24 sunset (about May 2027)', 'action' => 'Default v26', 'done' => true],
    ['date' => '2027-06-30', 'network' => 'Amazon Ads', 'what' => 'Reporting v3 switched off', 'action' => 'Move to reporting v1', 'done' => false],
    ['date' => '2027-07-01', 'network' => 'Amazon Ads', 'what' => '/adsAccounts/list switched off (July 2027)', 'action' => 'Use /adsApi/v1/query/advertiserAccounts', 'done' => false],
    ['date' => '2027-10-01', 'network' => 'LinkedIn', 'what' => 'Version 202610 sunset (about Oct 2027)', 'action' => 'Bump the default version before launch', 'done' => false],
];
