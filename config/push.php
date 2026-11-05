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
];
