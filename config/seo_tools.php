<?php

/*
 * Free SEO tools (SEO slice 0 step 0.4, PR-015 Part 2). While `public` is false the tool pages answer at their URLs
 * for checks (Product EX check, Growth live check) but send noindex, stay out of the sitemap and the menu, and no
 * page links to them. Rami approves each publish (SEO_Slice0_Spec 0.4 gate) before this is switched on.
 */
return [
    'public' => (bool) env('SEO_TOOLS_PUBLIC', false),

    // Each tool's view is resources/views/public/tools/<slug>.blade.php. URLs per Content's map (ADSR-042 §13) where set;
    // the three ad tools (D-90) wait for Content's OK on their slugs.
    'tools' => [
        'serp-preview', 'meta-length-checker', 'arabic-keyword-generator', 'arabizi-converter',
        'roas-calculator', 'budget-pacing-calculator', 'utm-builder', 'hreflang-checker',
    ],

    // Our own hosts, pinned to the server's public IP for SafeFetcher: the VPS resolves adsranger.com to ::1 in its hosts
    // file, which the SSRF guard (rightly) refuses. Only these exact hosts are mapped; every other host goes through DNS.
    'own_hosts' => [
        'adsranger.com' => env('SEO_OWN_HOST_IP', '72.62.132.138'),
        'www.adsranger.com' => env('SEO_OWN_HOST_IP', '72.62.132.138'),
    ],
];
