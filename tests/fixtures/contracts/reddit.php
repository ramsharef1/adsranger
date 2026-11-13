<?php

// A-2 recorded reply shapes for Reddit Ads API v3: campaigns, ad_groups, ads, POST reports (breakdowns DATE + AD_ID, money in micros).
// Built from the official reference samples, not a live reply. WR-06: the report request must never carry HOUR over more than 7 days.
return [
    'source' => 'ads-api.reddit.com/docs/v3 (sample shapes)',
    'confirmed' => false,
    'account' => 't2_abc123',
    'replies' => [
        ['url' => '#/ad_accounts/t2_abc123/campaigns#', 'json' => ['data' => [
            ['id' => 'c1', 'name' => 'Gaming KSA', 'effective_status' => 'ACTIVE', 'objective' => 'CONVERSIONS', 'goal_type' => 'DAILY_SPEND', 'goal_value' => 30000000],
        ], 'pagination' => ['next_url' => null]]],
        ['url' => '#/ad_accounts/t2_abc123/ad_groups#', 'json' => ['data' => [
            ['id' => 'g1', 'name' => 'r/gaming', 'campaign_id' => 'c1', 'effective_status' => 'ACTIVE', 'optimization_goal' => 'PURCHASE'],
        ], 'pagination' => ['next_url' => null]]],
        ['url' => '#/ad_accounts/t2_abc123/ads\?#', 'json' => ['data' => [
            ['id' => 'a1', 'name' => 'Promo', 'ad_group_id' => 'g1', 'campaign_id' => 'c1', 'effective_status' => 'ACTIVE'],
        ], 'pagination' => ['next_url' => null]]],
        ['url' => '#/ad_accounts/t2_abc123/reports#', 'json' => ['data' => ['metrics' => [
            ['date' => '2026-11-01', 'ad_id' => 'a1', 'spend' => 15000000, 'impressions' => 3000, 'clicks' => 90, 'reach' => 2500,
                'conversion_purchase_clicks' => 2, 'conversion_purchase_views' => 1, 'conversion_purchase_total_value' => 12000],
        ]], 'pagination' => ['next_url' => null]]],
    ],
    'expect' => ['campaigns' => 1, 'ad_sets' => 1, 'ads' => 1, 'rows' => 1, 'spend' => 15.0, 'results' => 3.0, 'revenue' => 120.0, 'result_type' => 'purchase'],
];
