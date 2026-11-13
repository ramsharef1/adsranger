<?php

/*
 * E2-066 creative fatigue & peak-season decay guard (module studio, off). Ad-level rows of daily_metrics only.
 * Frequency = impressions ÷ reach over the recent window (daily reach summed, so it reads low, never high).
 * Click-through rate = link clicks (clicks when the network has no link clicks) ÷ impressions, recent vs baseline.
 * The ThruPlay rate is used for video when the network reports ThruPlays; we have no 3-second views, so no "thumbstop".
 */
return [
    'recent_days' => 7,
    'baseline_days' => 21,         // the 21 days before the recent window
    'min_impressions' => 1000,     // in each window
    'normal' => ['frequency' => 3.5, 'ctr_drop_pct' => 25, 'thruplay_drop_pct' => 30],
    // Peak windows wear creatives out faster: tighter limits while one is running (MenaCalendar dates).
    'peak' => ['frequency' => 2.5, 'ctr_drop_pct' => 15, 'thruplay_drop_pct' => 20],
    'peak_events' => ['ramadan_last_10', 'eid_fitr', 'eid_adha', 'white_friday'],
    'ramadan_last_10_from_day' => 20, // Ramadan day 20 onwards = the last ten nights
];
