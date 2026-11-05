<?php

/*
 * Plan-tier gating map (D-30, D-32; tiers from the ADSR-060 register, Tier column).
 * Only features built so far are listed; a feature not listed here is not gated.
 * Change a tier here to move a feature between plans: no code change needed.
 * Network access per plan stays in the ad_network_plan table (admin › Plans).
 */
return [
    // Order matters: a higher tier includes everything below it.
    'tiers' => ['starter' => 1, 'pro' => 2, 'agency' => 3],

    'tier_labels' => [
        'starter' => ['en' => 'Starter', 'ar' => 'الأساسية'],
        'pro' => ['en' => 'Pro', 'ar' => 'الاحترافية'],
        'agency' => ['en' => 'Agency', 'ar' => 'الوكالات'],
    ],

    // In-app prices (D-19). Shown in Settings › Plan and upgrade screens only, never on public pages (D-07).
    'prices' => ['starter' => 49, 'pro' => 149, 'agency' => 399],

    // A workspace on trial gets this tier (D-61: the 14-day trial runs at Pro, capped).
    'trial_tier' => 'pro',

    'features' => [
        // key => tier, register row(s), names and one-line descriptions (EN / AR) for the locked state (EX-50).
        'forecast' => ['tier' => 'pro', 'refs' => [37],
            'name' => ['en' => 'Month-end forecast', 'ar' => 'توقع نهاية الشهر'],
            'what' => ['en' => 'See where spend and results will land by the end of the month.', 'ar' => 'اعرف أين سيصل الإنفاق والنتائج في نهاية الشهر.']],
        'funnel' => ['tier' => 'pro', 'refs' => [16],
            'name' => ['en' => 'Full funnel', 'ar' => 'مسار التحويل الكامل'],
            'what' => ['en' => 'Every step from impressions to paid, with the drop-off between steps.', 'ar' => 'كل مرحلة من مرات الظهور حتى الدفع، مع نسبة الانتقال بين المراحل.']],
        'insights' => ['tier' => 'pro', 'refs' => [38, 39, 43, 44, 97, 98],
            'name' => ['en' => 'Insights', 'ar' => 'الرؤى'],
            'what' => ['en' => 'Budget curves, reallocation tips, best hours, placements, audiences and cities for each client.', 'ar' => 'منحنيات الميزانية واقتراحات إعادة التوزيع وأفضل الساعات والمواضع والجماهير والمدن لكل عميل.']],
        'benchmarks' => ['tier' => 'pro', 'refs' => [41, 42],
            'name' => ['en' => 'Benchmarks', 'ar' => 'المقارنات المرجعية'],
            'what' => ['en' => 'Compare each client with your own portfolio and with anonymised MENA medians.', 'ar' => 'قارن كل عميل بمحفظتك وبمتوسطات المنطقة المجهولة الهوية.']],
        'google_depth' => ['tier' => 'pro', 'refs' => [46, 47, 48],
            'name' => ['en' => 'Google Ads depth', 'ar' => 'تفاصيل Google Ads'],
            'what' => ['en' => 'Search terms, negative keywords, impression share, Quality Score and Performance Max asset groups.', 'ar' => 'عبارات البحث والكلمات السلبية وحصة الظهور ونقاط الجودة ومجموعات أصول Performance Max.']],
        'landing_pages' => ['tier' => 'pro', 'refs' => [49],
            'name' => ['en' => 'Landing page health', 'ar' => 'صحة صفحات الهبوط'],
            'what' => ['en' => 'Nightly checks for broken, slow, redirecting or insecure pages behind your ads.', 'ar' => 'فحص ليلي للصفحات المعطلة أو البطيئة أو المحوّلة أو غير الآمنة خلف إعلاناتك.']],
        'competitors' => ['tier' => 'pro', 'refs' => [50],
            'name' => ['en' => 'Competitor watch', 'ar' => 'مراقبة المنافسين'],
            'what' => ['en' => 'Follow competitors\' ads in the Meta Ad Library, with notes per client.', 'ar' => 'تابع إعلانات المنافسين في مكتبة إعلانات Meta مع ملاحظات لكل عميل.']],
        'scenario_planner' => ['tier' => 'pro', 'refs' => [96],
            'name' => ['en' => 'Ramadan / Eid scenario planner', 'ar' => 'مخطِّط سيناريوهات رمضان والعيد'],
            'what' => ['en' => 'Plan budget changes for a season and see the expected spend and results.', 'ar' => 'خطّط تغييرات الميزانية لموسم ما واطّلع على الإنفاق والنتائج المتوقعة.']],
        'alert_rules_pro' => ['tier' => 'pro', 'refs' => [23],
            'name' => ['en' => 'Pro alert rules', 'ar' => 'قواعد التنبيه الاحترافية'],
            'what' => ['en' => 'Extra rules: pacing forecast, ad-set tracking, broken landing pages and wasted search terms.', 'ar' => 'قواعد إضافية: توقع وتيرة الإنفاق، وتتبع المجموعات الإعلانية، وصفحات الهبوط المعطلة، وعبارات البحث المهدرة.']],
        // Starter = every plan; listed so the send job and the schedule screen follow this map if the tier moves.
        'scheduled_reports' => ['tier' => 'starter', 'refs' => [106],
            'name' => ['en' => 'Scheduled email reports', 'ar' => 'تقارير البريد المجدولة'],
            'what' => ['en' => 'Email each client a monthly PDF report and a weekly summary, in Arabic or English.', 'ar' => 'أرسل لكل عميل تقريرًا شهريًا بصيغة PDF وملخصًا أسبوعيًا بالبريد، بالعربية أو الإنجليزية.']],
        'annual_budget' => ['tier' => 'agency', 'refs' => [45],
            'name' => ['en' => 'Annual budget plan', 'ar' => 'خطة الميزانية السنوية'],
            'what' => ['en' => '12 months per client: plan vs actual vs forecast.', 'ar' => '12 شهرًا لكل عميل: الخطة مقابل الفعلي مقابل التوقع.']],
    ],

    // Alert rules that need `alert_rules_pro` (#23: 7 rules on Starter, the rest on Pro).
    'pro_alert_rules' => ['pacing', 'tracking_issue', 'landing_page', 'search_term_waste'],
];
