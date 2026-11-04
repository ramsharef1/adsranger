<?php

// Wave 3b insights: #38 budget planner, #39 reallocation, #41/#42 benchmarks, #43 heatmap, #44 placements + audience, #97 geo, #98 overlap + saturation.
return [
    'pull_breakdowns' => true,      // Meta + Google: hour, placement, device, region / city, age × gender
    'pull_google_extras' => true,   // Google: impression share, search terms, keyword QS, PMax asset groups, landing URLs (Wave 3c)
    'search_terms' => ['waste_min_spend' => 20, 'waste_min_clicks' => 5], // client currency, period shown
    'landing' => ['slow_ms' => 3000, 'pagespeed_key' => env('PAGESPEED_API_KEY')],
    'curve' => [
        'days' => 90,               // history used to fit the spend → results curve
        'min_points' => 14,         // days with spend and results needed before a curve is shown
        'elasticity_min' => 0.15,
        'elasticity_max' => 1.0,
        'steps' => [0.5, 0.75, 1.0, 1.25, 1.5, 2.0],
    ],
    'reallocation' => [
        'shift' => 0.2,             // move up to 20% of a campaign's daily spend
        'min_gain_pct' => 5,        // only suggest when results go up at least this much
    ],
    'saturation' => ['frequency' => 3.0, 'ctr_drop_pct' => 20],
    'overlap_warn_pct' => 25,
    'benchmarks' => [
        'days' => 30,
        'min_agencies' => 5,        // MENA network: a cell shows only with 5+ agencies (decided by Rami 2026-11-04)
        'min_clients' => 3,         // internal: a cell shows with 3+ clients
        'cache_hours' => 12,
    ],
];
