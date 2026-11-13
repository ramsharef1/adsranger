<?php

// Alert rule defaults (spec module 3). Each agency can switch a rule off or change thresholds in /app → Alert rules.
return [
    'rules' => [
        // 7 Starter rules
        'tracking_break' => ['label' => 'Tracking break', 'severity' => 'critical',
            'help' => '0 results yesterday after results on each of the 7 days before.', 'thresholds' => ['min_days' => 7]],
        'spend_no_results' => ['label' => 'Spend, no results', 'severity' => 'critical',
            'help' => 'Spend of at least this amount (client currency) over 3 days with 0 results.', 'thresholds' => ['min_spend' => 60, 'days' => 3]],
        'tracking_issue' => ['label' => 'Tracking issue', 'severity' => 'critical',
            'help' => 'Ad set with a conversion goal: at least this spend in 7 days and 0 results.', 'thresholds' => ['min_spend' => 50, 'days' => 7]],
        'under_delivery' => ['label' => 'Under-delivery', 'severity' => 'warning',
            'help' => 'Yesterday\'s spend below this % of the daily budget.', 'thresholds' => ['pct' => 30]],
        'cpr_rising' => ['label' => 'Cost per result rising', 'severity' => 'warning',
            'help' => 'Cost per result up by more than 20% (Warning) or 40% (Critical) over 7 days, with at least 50 USD-equivalent spend or 3x target CPR.', 'thresholds' => ['pct' => 20, 'critical_pct' => 40, 'min_spend_usd' => 50, 'target_cpr_multiplier' => 3, 'min_results' => 5]],
        'ending_soon' => ['label' => 'Ending soon', 'severity' => 'info',
            'help' => 'An active campaign or ad set ends within this many days.', 'thresholds' => ['days' => 3]],
        'connection_problem' => ['label' => 'Connection problem', 'severity' => 'critical',
            'help' => 'Token expired or revoked, or the account failed to sync this many times in a row.', 'thresholds' => ['failures' => 2]],

        // 11 Pro rules (#23)
        'pacing' => ['label' => 'Budget pacing', 'severity' => 'warning',
            'help' => 'From this day of the month: the month-end spend forecast is more than this % above or below the monthly budget.', 'thresholds' => ['pct' => 20, 'from_day' => 7]],
        // FS-17 (R4, D-304): budget in local money, spend in another currency; the rate moved the month's budget by this % since the 1st.
        'fx_drift' => ['label' => 'Exchange rate moved', 'severity' => 'warning',
            'help' => 'The client\'s budget is in local money but its ad accounts spend in another currency, and the exchange rate has moved by at least this % since the 1st of the month.', 'thresholds' => ['pct' => 5]],
        // PS-08 (D-296): a client fed by uploads only, with no fresh upload for this many days (Desk + a phone reminder every 7 days).
        'upload_stale' => ['label' => 'No fresh upload', 'severity' => 'warning',
            'help' => 'A client with no live connection has had no new imported data for at least this many days.', 'thresholds' => ['days' => 7]],
        'landing_page' => ['label' => 'Broken landing page', 'severity' => 'critical',
            'help' => 'A landing page used by ads returns an error (checked every night) and had at least this many ad clicks in 30 days.', 'thresholds' => ['min_clicks' => 1]],
        'search_term_waste' => ['label' => 'Wasted search terms', 'severity' => 'warning',
            'help' => 'Google search terms with no conversions spent at least this amount (client currency) in the last 7 days.', 'thresholds' => ['min_spend' => 50]],
        'account_health' => ['label' => 'Account health', 'severity' => 'critical',
            'help' => 'Disabled or restricted account, failed payment, spending limit nearly used, or rejected ads (checked every night).', 'thresholds' => ['min_rejected' => 1]],
        'ctr_drop' => ['label' => 'Click-through rate drop', 'severity' => 'warning',
            'help' => 'Link click-through rate down by at least this % vs the 7 days before (needs 1,000+ impressions in each week).', 'thresholds' => ['pct' => 25, 'min_impressions' => 1000]],
        'roas_drop' => ['label' => 'Return on ad spend drop', 'severity' => 'warning',
            'help' => 'ROAS down by at least this % vs the 7 days before on revenue campaigns.', 'thresholds' => ['pct' => 25, 'min_spend' => 50]],
        'spend_spike' => ['label' => 'Daily spend spike', 'severity' => 'critical',
            'help' => 'Yesterday\'s spend jumped by at least this % above the previous 7-day daily average.', 'thresholds' => ['pct' => 80, 'min_spend' => 50]],
        'high_frequency' => ['label' => 'Creative fatigue (high frequency)', 'severity' => 'warning',
            'help' => '7-day average impressions per reached person is at least this number.', 'thresholds' => ['max_freq' => 4]],
        'zero_impressions' => ['label' => 'Active campaign with 0 impressions', 'severity' => 'critical',
            'help' => 'An active campaign had 0 impressions yesterday after delivering impressions in the 7 days before.', 'thresholds' => ['min_prior_impressions' => 500]],
        'lead_cost_spike' => ['label' => 'Cost per lead above target', 'severity' => 'warning',
            'help' => '7-day cost per result exceeds the client\'s target CPR by at least this %.', 'thresholds' => ['pct' => 25]],
        'high_cpc' => ['label' => 'Cost per click surge', 'severity' => 'info',
            'help' => '7-day cost per link click up by at least this % vs the 7 days before (needs 20+ clicks in each week).', 'thresholds' => ['pct' => 40, 'min_clicks' => 20]],
    ],
    'severities' => ['critical' => 'Critical', 'warning' => 'Warning', 'info' => 'Info'], // D-88
    'conversion_goals' => ['OFFSITE_CONVERSIONS', 'VALUE', 'LEAD_GENERATION', 'QUALITY_LEAD', 'CONVERSATIONS', 'QUALITY_CALL'],
];
