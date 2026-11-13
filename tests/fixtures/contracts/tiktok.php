<?php

// A-2 recorded reply shapes for TikTok Business API v1.3: campaign/get, adgroup/get, ad/get, report/integrated/get (AUCTION_AD, ad_id × stat_time_day).
// Built from the official reference samples, not a live reply.
return [
    'source' => 'business-api.tiktok.com/portal/docs (v1.3 sample shapes)',
    'confirmed' => false,
    'account' => '7000000000000000001',
    'replies' => [
        ['url' => '#/campaign/get/#', 'json' => ['code' => 0, 'message' => 'OK', 'data' => ['list' => [
            ['campaign_id' => '1800000000000001', 'campaign_name' => 'UAE launch', 'operation_status' => 'ENABLE', 'objective_type' => 'WEB_CONVERSIONS', 'budget_mode' => 'BUDGET_MODE_DAY', 'budget' => 100.0],
        ], 'page_info' => ['page' => 1, 'page_size' => 1000, 'total_number' => 1, 'total_page' => 1]]]],
        ['url' => '#/adgroup/get/#', 'json' => ['code' => 0, 'message' => 'OK', 'data' => ['list' => [
            ['adgroup_id' => '1800000000000011', 'adgroup_name' => 'Broad', 'campaign_id' => '1800000000000001', 'operation_status' => 'ENABLE', 'optimization_goal' => 'CONVERT', 'budget_mode' => 'BUDGET_MODE_DAY', 'budget' => 100.0],
        ], 'page_info' => ['total_page' => 1]]]],
        ['url' => '#/ad/get/#', 'json' => ['code' => 0, 'message' => 'OK', 'data' => ['list' => [
            ['ad_id' => '1800000000000021', 'ad_name' => 'Spark 1', 'campaign_id' => '1800000000000001', 'adgroup_id' => '1800000000000011', 'operation_status' => 'ENABLE'],
        ], 'page_info' => ['total_page' => 1]]]],
        ['url' => '#/report/integrated/get/#', 'json' => ['code' => 0, 'message' => 'OK', 'data' => ['list' => [
            ['dimensions' => ['ad_id' => '1800000000000021', 'stat_time_day' => '2026-11-01 00:00:00'],
                'metrics' => ['spend' => '40.00', 'impressions' => '5000', 'reach' => '4200', 'clicks' => '120', 'result' => '4', 'conversion' => '4', 'complete_payment' => '3', 'complete_payment_roas' => '2.5', 'video_watched_6s' => '900']],
        ], 'page_info' => ['total_page' => 1]]]],
    ],
    'expect' => ['campaigns' => 1, 'ad_sets' => 1, 'ads' => 1, 'rows' => 1, 'spend' => 40.0, 'results' => 4.0, 'revenue' => 100.0, 'result_type' => 'conversion'],
];
