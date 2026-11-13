<?php

/*
 * Plan-tier gating map (D-30, D-32; tiers from the ADSR-060 register, Tier column).
 * Only features built so far are listed; a feature not listed here is not gated.
 * Change a tier here to move a feature between plans: no code change needed.
 * Network access per plan stays in the ad_network_plan table (admin › Plans).
 */
return [
    // Order matters: a higher tier includes everything below it.
    // D-280 / D-286: Free «الباقة المجانية» is the first step of the one ladder (AI Workspace basics; lite view in the app).
    'tiers' => ['free' => 0, 'solo' => 1, 'starter' => 2, 'pro' => 3, 'agency' => 4],

    'tier_labels' => [
        'free' => ['en' => 'Free', 'ar' => 'الباقة المجانية'],
        'solo' => ['en' => 'Solo', 'ar' => 'باقة المستقل'],
        // Full glossary names (EX-61, UX-65): used inside sentences, so templates never add «الباقة».
        'starter' => ['en' => 'Starter', 'ar' => 'الباقة الأساسية'],
        'pro' => ['en' => 'Pro', 'ar' => 'الباقة الاحترافية'],
        'agency' => ['en' => 'Agency', 'ar' => 'باقة الوكالات'],
    ],
    // Short forms for badges (PlanBadge, nav badge, locked buttons).
    'tier_short' => [
        'free' => ['en' => 'Free', 'ar' => 'المجانية'],
        'solo' => ['en' => 'Solo', 'ar' => 'المستقل'],
        'starter' => ['en' => 'Starter', 'ar' => 'الأساسية'],
        'pro' => ['en' => 'Pro', 'ar' => 'الاحترافية'],
        'agency' => ['en' => 'Agency', 'ar' => 'الوكالات'],
    ],

    // In-app prices (D-19). Shown in Settings › Plan and upgrade screens only, never on public pages (D-07).
    'prices' => ['free' => 0, 'solo' => 19, 'starter' => 49, 'pro' => 149, 'agency' => 399],

    // A workspace on trial gets this tier (D-61: the 14-day trial runs at Pro, capped).
    'trial_tier' => 'pro',

    'features' => [
        // D-286: AI Workspace in full (AI box on own key, night shift, editing the client brain) from Pro; lower plans keep the
        // connector + a read-only client brain.
        'ai_workspace_full' => ['tier' => 'pro', 'refs' => [169],
            'name' => ['en' => 'AI Workspace in full', 'ar' => 'مساحة الذكاء الاصطناعي كاملة'],
            'what' => ['en' => 'Run AI on your own key inside AdsRanger, get the night shift\'s morning list, and organise each client brain.', 'ar' => 'شغّل الذكاء بمفتاحك داخل AdsRanger، واحصل على قائمة المناوب الليلي الصباحية، ونظّم ذاكرة كل عميل.']],
        // key => tier, register row(s), names and one-line descriptions (EN / AR) for the locked state (EX-50).
        // #173 (FR-016): every plan gets the audit; Starter sees the top 3 findings, Pro and up see all of them.
        'account_audit_full' => ['tier' => 'pro', 'refs' => [173, 31, 32, 33, 34],
            'name' => ['en' => 'Full account audit', 'ar' => 'تدقيق الحساب الكامل'],
            'what' => ['en' => 'See every finding of the account audit, up to 10, ranked by money at risk.', 'ar' => 'اطّلع على كل نتائج تدقيق الحساب، حتى 10 نتائج، مرتبة حسب المبلغ المعرّض للخطر.']],
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
            'what' => ['en' => 'Compare each client with your own portfolio and with anonymised MENA medians.', 'ar' => 'قارن كل عميل بمحفظتك وبالقيم الوسيطة المجهولة الهوية في المنطقة.']],
        'google_depth' => ['tier' => 'pro', 'refs' => [46, 47, 48],
            'name' => ['en' => 'Google Ads depth', 'ar' => 'تفاصيل Google Ads'],
            'what' => ['en' => 'Search terms, negative keywords, impression share, Quality Score and Performance Max asset groups.', 'ar' => 'عبارات البحث والكلمات الرئيسية السلبية وحصة الظهور ونقاط الجودة ومجموعات أصول Performance Max.']],
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
        'phone_push' => ['tier' => 'pro', 'refs' => [175, 78],
            'name' => ['en' => 'Phone alerts', 'ar' => 'تنبيهات الهاتف'],
            'what' => ['en' => 'Critical alerts arrive on your phone within a minute, with quiet hours you choose.', 'ar' => 'تصل التنبيهات الحرجة إلى هاتفك خلال دقيقة، مع ساعات هدوء تختارها.']],
        // Starter = every plan; listed so the send job and the schedule screen follow this map if the tier moves.
        // #177 Solo = Starter minus the monthly PDF (D-115, D-140).
        'monthly_pdf' => ['tier' => 'starter', 'refs' => [57],
            'name' => ['en' => 'Monthly PDF report', 'ar' => 'تقرير PDF الشهري'],
            'what' => ['en' => 'A branded monthly PDF for each client, in Arabic or English.', 'ar' => 'تقرير PDF شهري يحمل هويتك لكل عميل، بالعربية أو الإنجليزية.']],
        'scheduled_reports' => ['tier' => 'starter', 'refs' => [106],
            'name' => ['en' => 'Scheduled email reports', 'ar' => 'تقارير البريد المجدولة'],
            'what' => ['en' => 'Email each client a monthly PDF report and a weekly summary, in Arabic or English.', 'ar' => 'أرسل لكل عميل تقريرًا شهريًا بصيغة PDF وملخصًا أسبوعيًا بالبريد، بالعربية أو الإنجليزية.']],
        'annual_budget' => ['tier' => 'agency', 'refs' => [45],
            'name' => ['en' => 'Annual budget plan', 'ar' => 'خطة الميزانية السنوية'],
            'what' => ['en' => '12 months per client: plan vs actual vs forecast.', 'ar' => '12 شهرًا لكل عميل: الخطة مقابل الفعلي مقابل التوقع.']],
    ],

    // Alert rules that need `alert_rules_pro` (#23: 7 rules on Starter, the rest on Pro; the PS-08 upload reminder is on every plan, D-296).
    'pro_alert_rules' => ['pacing', 'landing_page', 'search_term_waste', 'account_health', 'ctr_drop', 'roas_drop', 'spend_spike', 'high_frequency', 'zero_impressions', 'lead_cost_spike', 'high_cpc'],
];
