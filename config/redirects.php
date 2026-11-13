<?php

// BX-09: slug renames keep their old URLs as 301s (Growth O-339, 12 Nov). Arabic paths; the /en twin is added in routes.
// Add every future rename here, never as a one-off route: the reclamation board and RedirectsBx09Test check each one.
return [
    'renames' => [
        '/tools/capi-pixel-checker' => '/tools/ad-pixel-checker',
        '/tools/ksa-ad-compliance-checker' => '/tools/gulf-ad-disclosure-checklist',
    ],
];
