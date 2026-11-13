<?php

// A-2 recorded reply shapes for Meta Marketing API (Graph v21+): campaigns, adsets, ads, insights level=ad time_increment=1.
// Built from the official reference samples, not a live reply. Swap in a scrubbed live reply once Meta is LIVE (A-11).
return [
    'source' => 'developers.facebook.com/docs/marketing-api/reference (sample shapes)',
    'confirmed' => false,
    'account' => 'act_1234567890',
    'replies' => [
        ['url' => '#/act_1234567890/campaigns#', 'json' => ['data' => [
            ['id' => '120200000000001', 'name' => 'Ramadan · Purchases', 'effective_status' => 'ACTIVE', 'objective' => 'OUTCOME_SALES', 'daily_budget' => '5000', 'start_time' => '2026-10-20T00:00:00+0300'],
        ], 'paging' => ['cursors' => ['before' => 'a', 'after' => 'b']]]],
        ['url' => '#/act_1234567890/adsets#', 'json' => ['data' => [
            ['id' => '120200000000011', 'name' => 'KSA 25-44', 'campaign_id' => '120200000000001', 'effective_status' => 'ACTIVE',
                'optimization_goal' => 'OFFSITE_CONVERSIONS', 'promoted_object' => ['pixel_id' => '999', 'custom_event_type' => 'PURCHASE'], 'daily_budget' => '5000'],
        ]]],
        ['url' => '#/act_1234567890/ads\?#', 'json' => ['data' => [
            ['id' => '120200000000021', 'name' => 'Video 1', 'adset_id' => '120200000000011', 'campaign_id' => '120200000000001', 'effective_status' => 'ACTIVE',
                'creative' => ['thumbnail_url' => 'https://example.com/t.jpg', 'object_story_spec' => ['link_data' => ['link' => 'https://shop.example.com/ramadan']]]],
        ]]],
        ['url' => '#/act_1234567890/insights#', 'json' => ['data' => [
            ['date_start' => '2026-11-01', 'date_stop' => '2026-11-01', 'ad_id' => '120200000000021', 'adset_id' => '120200000000011', 'campaign_id' => '120200000000001',
                'spend' => '12.34', 'impressions' => '1000', 'reach' => '800', 'clicks' => '60', 'inline_link_clicks' => '45',
                'actions' => [['action_type' => 'link_click', 'value' => '45'], ['action_type' => 'offsite_conversion.fb_pixel_purchase', 'value' => '2']],
                'action_values' => [['action_type' => 'offsite_conversion.fb_pixel_purchase', 'value' => '150.00']]],
            ['date_start' => '2026-11-02', 'date_stop' => '2026-11-02', 'ad_id' => '120200000000021', 'adset_id' => '120200000000011', 'campaign_id' => '120200000000001',
                'spend' => '20.00', 'impressions' => '1500', 'reach' => '1100', 'clicks' => '70', 'inline_link_clicks' => '50',
                'actions' => [['action_type' => 'offsite_conversion.fb_pixel_purchase', 'value' => '1']],
                'action_values' => [['action_type' => 'offsite_conversion.fb_pixel_purchase', 'value' => '75.00']]],
        ]]],
    ],
    'expect' => ['campaigns' => 1, 'ad_sets' => 1, 'ads' => 1, 'rows' => 2, 'spend' => 32.34, 'results' => 3.0, 'revenue' => 225.0, 'result_type' => 'purchase'],
];
