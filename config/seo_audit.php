<?php

/*
 * Site audit A1 (FR-089 slice A1, #183; 910 BUILD START 2026-11-08, SEO_Slice0_Spec 0.3).
 * Quotas are placeholders until Business PR-002 (Rami 13:41, G-5). Pages per run is the A1 cap; A2 moves crawling to the worker.
 */
return [
    'max_pages_per_run' => (int) env('SEO_AUDIT_MAX_PAGES', 200),
    'request_interval_ms' => (int) env('SEO_AUDIT_INTERVAL_MS', 1000),
    'user_agent' => 'AdsRangerBot/1.0 (+https://adsranger.com/bot)',

    // Pages per month and sites per workspace, by plan (G-5 placeholders).
    'plans' => [
        'solo' => ['pages' => 500, 'sites' => 1],
        'starter' => ['pages' => 500, 'sites' => 1],
        'pro' => ['pages' => 10000, 'sites' => 5],
        'agency' => ['pages' => 50000, 'sites' => 20],
    ],

    // Health score (Rami 13:41, G-4): 100 minus weighted issues per crawled page. Shown with its formula and date; not a ranking claim.
    'score_weights' => ['critical' => 10, 'warning' => 3, 'info' => 0],
];
