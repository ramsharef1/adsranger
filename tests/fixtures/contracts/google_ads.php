<?php

// A-2 recorded reply shapes for Google Ads API googleAds:search (REST, camelCase fields). Matched on the GAQL text in the request body.
// Built from the official reference samples, not a live reply.
return [
    'source' => 'developers.google.com/google-ads/api/rest (search response sample shapes)',
    'confirmed' => false,
    'account' => '123-456-7890',
    'replies' => [
        ['url' => '#/customers/1234567890/googleAds:search#', 'body' => 'campaign_budget.amount_micros', 'json' => ['results' => [
            ['campaign' => ['resourceName' => 'customers/1234567890/campaigns/111', 'id' => '111', 'name' => 'Search · Brand AR', 'status' => 'ENABLED', 'advertisingChannelType' => 'SEARCH'],
                'campaignBudget' => ['resourceName' => 'customers/1234567890/campaignBudgets/9', 'amountMicros' => '50000000']],
        ], 'fieldMask' => 'campaign.id,campaign.name']],
        ['url' => '#googleAds:search#', 'body' => 'segments.conversion_action_name', 'json' => ['results' => [
            ['campaign' => ['id' => '111'], 'segments' => ['date' => '2026-11-01', 'conversionActionName' => 'Lead form', 'conversionActionCategory' => 'SUBMIT_LEAD_FORM'],
                'metrics' => ['conversions' => 5.0, 'conversionsValue' => 0.0]],
        ]]],
        ['url' => '#googleAds:search#', 'body' => 'metrics.cost_micros', 'json' => ['results' => [
            ['campaign' => ['id' => '111'], 'segments' => ['date' => '2026-11-01'], 'metrics' => ['costMicros' => '25500000', 'impressions' => '900', 'clicks' => '75', 'conversions' => 5.0, 'conversionsValue' => 0.0]],
            ['campaign' => ['id' => '111'], 'segments' => ['date' => '2026-11-02'], 'metrics' => ['costMicros' => '10000000', 'impressions' => '400', 'clicks' => '30']],
        ]]],
    ],
    'expect' => ['campaigns' => 1, 'ad_sets' => 0, 'ads' => 0, 'rows' => 2, 'spend' => 35.5, 'results' => 5.0, 'revenue' => 0.0],
];
