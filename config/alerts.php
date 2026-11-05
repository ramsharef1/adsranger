<?php

// Alert rule defaults (spec module 3). Each agency can switch a rule off or change thresholds in /app → Alert rules.
return [
    'rules' => [
        'tracking_break' => ['label' => 'Tracking break', 'severity' => 'critical',
            'help' => '0 results yesterday after results on each of the 7 days before.', 'thresholds' => ['min_days' => 7]],
        'spend_no_results' => ['label' => 'Spend, no results', 'severity' => 'critical',
            'help' => 'Spend of at least this amount (client currency) over 3 days with 0 results.', 'thresholds' => ['min_spend' => 60, 'days' => 3]],
        'tracking_issue' => ['label' => 'Tracking issue', 'severity' => 'critical',
            'help' => 'Ad set with a conversion goal: at least this spend in 7 days and 0 results.', 'thresholds' => ['min_spend' => 50, 'days' => 7]],
        'under_delivery' => ['label' => 'Under-delivery', 'severity' => 'warning',
            'help' => 'Yesterday\'s spend below this % of the daily budget.', 'thresholds' => ['pct' => 30]],
        'cpr_rising' => ['label' => 'Cost per result rising', 'severity' => 'warning',
            'help' => 'Cost per result up by at least this % vs the 7 days before (needs 5+ results in each week).', 'thresholds' => ['pct' => 30, 'min_results' => 5]],
        'ending_soon' => ['label' => 'Ending soon', 'severity' => 'info',
            'help' => 'An active campaign or ad set ends within this many days.', 'thresholds' => ['days' => 3]],
        'connection_problem' => ['label' => 'Connection problem', 'severity' => 'critical',
            'help' => 'Token expired or revoked, or the account failed to sync this many times in a row.', 'thresholds' => ['failures' => 2]],
        'pacing' => ['label' => 'Budget pacing', 'severity' => 'warning',
            'help' => 'From this day of the month: the month-end spend forecast is more than this % above or below the monthly budget.', 'thresholds' => ['pct' => 20, 'from_day' => 7]],
        'landing_page' => ['label' => 'Broken landing page', 'severity' => 'critical',
            'help' => 'A landing page used by ads returns an error (checked every night) and had at least this many ad clicks in 30 days.', 'thresholds' => ['min_clicks' => 1]],
        'search_term_waste' => ['label' => 'Wasted search terms', 'severity' => 'warning',
            'help' => 'Google search terms with no conversions spent at least this amount (client currency) in the last 7 days.', 'thresholds' => ['min_spend' => 50]],
        'account_health' => ['label' => 'Account health', 'severity' => 'critical',
            'help' => 'Disabled or restricted account, failed payment, spending limit nearly used, or rejected ads (checked every night).', 'thresholds' => ['min_rejected' => 1]],
    ],
    'severities' => ['critical' => 'Critical', 'warning' => 'Warning', 'info' => 'Info'], // D-88
    'conversion_goals' => ['OFFSITE_CONVERSIONS', 'VALUE', 'LEAD_GENERATION', 'QUALITY_LEAD', 'CONVERSATIONS', 'QUALITY_CALL'],
];
