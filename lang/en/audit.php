<?php

/* #173 Account audit (FR-016). Placeholders that hold names or numbers are wrapped in <bdi> by the view. */
return [
    'intro' => 'Read-only checks on the last :days days of this client\'s ad accounts. Findings are ranked by money at risk: the spend in that period on each affected item, in the client\'s currency. The thresholds are estimates.',
    'choose_client' => 'Choose a client to see its audit.',
    'no_accounts' => 'This client has no ad accounts on the ad networks in your plan.',
    'not_run' => 'The audit has not run for this client yet. It runs by itself after the first sync.',
    'run' => 'Run the audit now',
    'last_run' => 'Last run: :when',
    'window' => 'Period: :from to :to',
    'total' => 'Total money at risk: :amount',
    'none' => 'No findings: nothing needs attention in the last :days days.',
    'money_at_risk' => 'Money at risk',
    'results_at_risk' => 'Results short',
    'fix' => 'Fix',
    'open' => 'Open in the ad manager',
    'opens_new_tab' => '(opens in a new tab)',
    'dismiss' => 'Dismiss',
    'restore' => 'Restore',
    'dismissed' => 'Dismissed',
    'show_dismissed' => 'Show dismissed findings',
    'more_locked' => '{1} :count more finding|[2,*] :count more findings',
    'trigger' => ['first_sync' => 'after the first sync', 'manual' => 'run by hand'],
    'checks' => [
        'rejected_ads' => [
            'title' => 'Ad rejected: :name',
            'detail' => 'Status :status in the ad set :ad_set. The amount is what that ad set spent in the last :days days while this ad could not run.',
            'fix' => 'Fix the policy issue or replace the ad, then ask for a review.',
        ],
        'spend_cap' => [
            'title' => 'Account spending limit :pct% used',
            'detail' => 'The account stops delivering when the limit is reached. The amount is what the account spent in the last :days days.',
            'fix' => 'Raise or reset the account spending limit before delivery stops.',
        ],
        'no_results' => [
            'title' => 'Spend with no results: :name',
            'detail' => 'This ad set spent with 0 results in the last :days days.',
            'fix' => 'Check the conversion event and the landing page, or pause this ad set.',
        ],
        'tired_ad_set' => [
            'title' => 'Tired ad set: :name',
            'detail' => 'Frequency :frequency over 30 days.',
            'detail_drop' => 'Frequency :frequency over 30 days, and CTR is down :ctr_drop% from 3 weeks ago.',
            'fix' => 'Refresh the creatives or widen the audience.',
        ],
        'pacing' => [
            'title_over' => 'The monthly budget will be overspent',
            'title_under' => 'The monthly budget will be underspent',
            'detail' => 'Spend is at :pace% of the expected pace. The amount is the forecast gap to the monthly budget.',
            'fix' => 'Adjust the daily budgets to land on the monthly budget.',
        ],
        'learning_limited' => [
            'title' => 'Few results to finish learning: :name',
            'detail' => ':results results in :days days; networks usually need about :target a week to finish learning (estimate).',
            'fix' => 'Merge similar ad sets or choose a conversion event that happens more often.',
        ],
        'default_name' => [
            'title' => 'Default or copied name: :name',
            'detail' => 'Names like this make reports and alerts hard to read.',
            'fix' => 'Rename it with your naming rule.',
        ],
    ],
];
