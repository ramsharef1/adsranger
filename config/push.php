<?php

/* #175 Critical alerts as phone push (D-104 Pro, D-141 quiet hours, experience spec A). */
return [
    'enabled' => env('PUSH_ENABLED', true),
    'subject' => env('PUSH_SUBJECT', 'mailto:support@adsranger.com'),
    'vapid_path' => env('PUSH_VAPID_PATH'), // default storage/app/private/vapid.json
    // More than this many Critical alerts for one user within the window → one grouped push.
    'group_over' => 3,
    'group_minutes' => 10,
    // Workspace weekend by country (D-122: Jordan rules until the country is set). ISO weekday 5 = Fri, 6 = Sat, 7 = Sun.
    'weekend' => ['default' => [5, 6], 'AE' => [6, 7], 'MA' => [6, 7], 'TN' => [6, 7], 'DZ' => [5, 6], 'LB' => [6, 7]],
    // DS-26 (R5): weekends a country had before a change, newest first. The UAE moved from Fri–Sat to Sat–Sun on 1 Jan 2022.
    'weekend_history' => ['AE' => [['until' => '2021-12-31', 'days' => [5, 6]]]],
    // WK-12 (R5, D-308): fixed, approximate prayer windows (workspace local time) when non-critical pushes wait.
    'prayer_windows' => ['jumuah' => ['day' => 5, 'from' => '11:30', 'to' => '13:30'], 'taraweeh' => ['from' => '20:00', 'to' => '22:30']],
];
