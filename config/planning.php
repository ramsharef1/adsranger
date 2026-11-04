<?php

// Wave 3a: health score (#35), pacing (#36), forecast (#37), MENA calendar + seasonality (#40), Ramadan / Eid planner (#96).
return [
    // Decided by Rami 2026-11-04: 40 / 20 / 20 / 20.
    'health' => [
        'weights' => ['results' => 40, 'pacing' => 20, 'trend' => 20, 'tracking' => 20],
        'bands' => [80 => ['Healthy', 'success'], 60 => ['Watch', 'warning'], 0 => ['At risk', 'danger']],
        'window_days' => 14,
    ],

    'pacing' => [
        'tolerance' => 10, // ± % of the expected spend to date that still counts as "on pace"
        'min_day' => 3,    // pacing status shows from this day of the month
        'run_rate_days' => 7,
    ],

    // Diminishing returns for the scenario planner: CPR × (1 + budget change)^elasticity.
    'elasticity' => 0.3,

    // Hijri events: [hijri month, day, length in days]. Dates come from the Umm al-Qura calendar (± 1 day for moon sighting).
    'hijri_events' => [
        'ramadan' => ['m' => 9, 'd' => 1, 'days' => 30, 'label' => 'Ramadan', 'ar' => 'رمضان',
            'tip' => 'Evening and late-night traffic rises; prepare creatives and budgets 2–3 weeks before.'],
        'eid_fitr' => ['m' => 10, 'd' => 1, 'days' => 3, 'label' => 'Eid al-Fitr', 'ar' => 'عيد الفطر',
            'tip' => 'Gifting and fashion peak in the last 10 days of Ramadan; many offices close.'],
        'dhul_hijjah' => ['m' => 12, 'd' => 1, 'days' => 9, 'label' => 'First 10 days of Dhul Hijjah', 'ar' => 'العشر الأوائل من ذي الحجة',
            'tip' => 'Charity and Udhiyah campaigns peak.'],
        'eid_adha' => ['m' => 12, 'd' => 10, 'days' => 4, 'label' => 'Eid al-Adha', 'ar' => 'عيد الأضحى',
            'tip' => 'Travel and gifting rise before; B2B is quiet during the holiday.'],
        'hijri_new_year' => ['m' => 1, 'd' => 1, 'days' => 1, 'label' => 'Islamic New Year', 'ar' => 'رأس السنة الهجرية', 'tip' => 'Public holiday in most markets.'],
        'mawlid' => ['m' => 3, 'd' => 12, 'days' => 1, 'label' => 'Prophet\'s Birthday', 'ar' => 'المولد النبوي', 'tip' => 'Public holiday in most markets (not KSA).'],
    ],

    // Fallback Gregorian start dates (Umm al-Qura) used only if PHP intl is missing.
    'hijri_fallback' => [
        'ramadan' => ['2026-02-18', '2027-02-08', '2028-01-28'],
        'eid_fitr' => ['2026-03-20', '2027-03-09', '2028-02-26'],
        'dhul_hijjah' => ['2026-05-18', '2027-05-07', '2028-04-26'],
        'eid_adha' => ['2026-05-27', '2027-05-16', '2028-05-05'],
        'hijri_new_year' => ['2026-06-16', '2027-06-06', '2028-05-25'],
        'mawlid' => ['2026-08-25', '2027-08-14', '2028-08-03'],
    ],

    // Fixed Gregorian events: 'md' => month-day, or 'rule' => 'last_friday_nov'. markets = [] means all.
    'fixed_events' => [
        'new_year' => ['md' => '01-01', 'days' => 1, 'markets' => [], 'label' => 'New Year', 'ar' => 'رأس السنة الميلادية', 'tip' => ''],
        'saudi_founding' => ['md' => '02-22', 'days' => 1, 'markets' => ['SA'], 'label' => 'Saudi Founding Day', 'ar' => 'يوم التأسيس', 'tip' => 'National offers; patriotic creatives.'],
        'kuwait_national' => ['md' => '02-25', 'days' => 2, 'markets' => ['KW'], 'label' => 'Kuwait National & Liberation Days', 'ar' => 'العيد الوطني الكويتي', 'tip' => ''],
        'mothers_day' => ['md' => '03-21', 'days' => 1, 'markets' => ['JO', 'EG', 'LB', 'PS', 'SY', 'IQ', 'AE', 'QA', 'BH', 'KW', 'OM'], 'label' => 'Mother\'s Day', 'ar' => 'عيد الأم', 'tip' => 'Gifting, beauty and restaurants peak the week before.'],
        'jordan_independence' => ['md' => '05-25', 'days' => 1, 'markets' => ['JO'], 'label' => 'Jordan Independence Day', 'ar' => 'عيد الاستقلال', 'tip' => ''],
        'back_to_school' => ['md' => '08-15', 'days' => 21, 'markets' => [], 'label' => 'Back to school', 'ar' => 'العودة إلى المدارس', 'tip' => 'Education, stationery, electronics and fashion.'],
        'saudi_national' => ['md' => '09-23', 'days' => 1, 'markets' => ['SA'], 'label' => 'Saudi National Day', 'ar' => 'اليوم الوطني السعودي', 'tip' => 'One of the biggest sale days in KSA.'],
        'singles_day' => ['md' => '11-11', 'days' => 1, 'markets' => [], 'label' => '11.11 sales', 'ar' => 'تخفيضات 11.11', 'tip' => 'E-commerce marketplaces push heavy discounts.'],
        'oman_national' => ['md' => '11-20', 'days' => 2, 'markets' => ['OM'], 'label' => 'Oman National Day', 'ar' => 'العيد الوطني العماني', 'tip' => ''],
        'white_friday' => ['rule' => 'last_friday_nov', 'days' => 4, 'markets' => [], 'label' => 'White / Black Friday', 'ar' => 'الجمعة البيضاء', 'tip' => 'CPMs rise sharply; book budgets and creatives early.'],
        'uae_national' => ['md' => '12-02', 'days' => 2, 'markets' => ['AE'], 'label' => 'UAE National Day', 'ar' => 'اليوم الوطني الإماراتي', 'tip' => ''],
        'bahrain_national' => ['md' => '12-16', 'days' => 2, 'markets' => ['BH'], 'label' => 'Bahrain National Day', 'ar' => 'العيد الوطني البحريني', 'tip' => ''],
        'qatar_national' => ['md' => '12-18', 'days' => 1, 'markets' => ['QA'], 'label' => 'Qatar National Day', 'ar' => 'اليوم الوطني القطري', 'tip' => ''],
    ],

    // Used by the scenario planner when a client has no history for the event. Ratios vs a normal day.
    // Starting assumptions only, replaced by the client's own history once it exists.
    'seasonality_defaults' => [
        'ramadan' => ['spend' => 1.2, 'cpr' => 1.1],
        'eid_fitr' => ['spend' => 0.8, 'cpr' => 1.15],
        'dhul_hijjah' => ['spend' => 1.1, 'cpr' => 1.05],
        'eid_adha' => ['spend' => 0.8, 'cpr' => 1.15],
        'white_friday' => ['spend' => 1.5, 'cpr' => 1.25],
        '_default' => ['spend' => 1.0, 'cpr' => 1.05],
    ],
];
