<?php

/*
 * #173 Day-1 account audit (FR-016, D-135). Thresholds are E1 defaults approved by Rami on 2026-11-07
 * ("My defaults, editable"); they are estimates, not network rules. Money amounts are in USD and converted
 * to each client's currency on the day of the run.
 */
return [
    // Days of data each check looks at (last complete days, the client's time zone).
    'window_days' => 7,

    // Most findings kept per run, ranked by money at risk. Starter sees the first `starter_visible`.
    'max_findings' => 10,
    'starter_visible' => 3,

    // Network statuses that mean an ad was rejected or has a policy issue.
    'rejected_statuses' => ['DISAPPROVED', 'REJECTED', 'WITH_ISSUES'],

    // Account spending limit: flag at this share used (the nightly health check stores it).
    'spend_cap_pct' => 90,

    // Spend with no results: an ad set with a results goal spent at least this much (USD) in the window and got 0 results.
    'no_results_min_usd' => 50,

    // Learning-limited estimate: an active ad set with a results goal and fewer results than this in the window.
    'learning_results' => 50,
    'learning_min_usd' => 20,

    // Tired ad sets come from Insights::saturation (config/insights.php: frequency + CTR drop).

    // Goals with no countable result (awareness / reach / video): skipped by the results checks.
    'awareness_goals' => ['REACH', 'IMPRESSIONS', 'BRAND_AWARENESS', 'AD_RECALL_LIFT', 'THRUPLAY', 'VIDEO_VIEWS', 'OUTCOME_AWARENESS'],

    // Default or copied names (case-insensitive regex); checked on campaigns, ad sets and ads that spent in the window.
    'default_name_patterns' => [
        '/^\s*(new|untitled)\b/i',
        '/\bcopy\b/i',
        '/\bnew (campaign|ad set|ad)\b/i',
        '/نسخة/u',
        '/^\s*(حملة|مجموعة إعلانية|إعلان) جديد(ة)?/u',
    ],
];
