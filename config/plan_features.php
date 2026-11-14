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

        // E2-074 unified plan tier matrix across SEO, SEM, MENA & AI Workspace M2 capabilities:
        'gsc_top10_snapshot' => ['tier' => 'free', 'refs' => ['E2-069'],
            'name' => ['en' => '1-site Search Console top-10 snapshot', 'ar' => 'لقطة أهم 10 عبارات في Search Console لموقع واحد'],
            'what' => ['en' => 'Inspect top 10 organic search queries for one verified site.', 'ar' => 'استعرض أهم 10 استعلامات بحث عضوي لموقع واحد.']],
        'watched_urls_basic' => ['tier' => 'free', 'refs' => ['E2-072'],
            'name' => ['en' => 'Single watched ad landing URL', 'ar' => 'مراقبة رابط هبوط إعلاني واحد'],
            'what' => ['en' => 'Monitor HTTP status, redirects, noindex and pixels on 1 ad URL.', 'ar' => 'راقب حالة الاستجابة والتحويلات ووسوم التتبع على رابط إعلاني واحد.']],

        'seo_gsc_ga4_sync' => ['tier' => 'starter', 'refs' => ['E2-069'],
            'name' => ['en' => 'Search Console & GA4 reconciliation', 'ar' => 'مزامنة Search Console ومطابقة مسار GA4'],
            'what' => ['en' => 'Sync Search Console queries and reconcile ad claims against GA4 events and store orders.', 'ar' => 'زامن استعلامات Search Console وطابق أرقام الإعلانات مع أحداث GA4 وطلبات المتجر.']],
        'sem_root_cause_investigator' => ['tier' => 'starter', 'refs' => ['E2-071'],
            'name' => ['en' => 'Campaign root-cause investigator', 'ar' => 'محقِّق الأسباب الجذرية لأداء الحملات'],
            'what' => ['en' => 'Decompose CPA, ROAS and POAS shifts across CPC, CTR, CVR, AOV and COD returns.', 'ar' => 'فكّك تغيّرات CPA ثم ROAS ثم POAS عبر CPC ونسبة النقر والتحويل ومتوسط السلة والمرتجعات.']],

        'seo_log_analyzer' => ['tier' => 'pro', 'refs' => ['E2-069'],
            'name' => ['en' => 'Server access log & AI bot analyzer', 'ar' => 'محلل سجلات الخادم وزواحف الذكاء الاصطناعي'],
            'what' => ['en' => 'Stream .log and .gz server logs to uncover crawl waste and orphan paths.', 'ar' => 'افحص ملفات السجلات لاكتشاف هدر ميزانية الزحف والمسارات اليتيمة.']],
        'seo_rank_tracker' => ['tier' => 'pro', 'refs' => ['E2-070'],
            'name' => ['en' => '100-keyword rank & cannibalization tracker', 'ar' => 'متتبع ترتيب 100 كلمة ورصد التنافس الداخلي'],
            'what' => ['en' => 'Track up to 100 bilingual keywords across MENA cities and catch URL cannibalization.', 'ar' => 'تتبّع حتى 100 كلمة مفتاحية ثنائية اللغة في مدن المنطقة واكتشف تنافس الروابط.']],
        'seo_topical_planner' => ['tier' => 'pro', 'refs' => ['E2-070'],
            'name' => ['en' => 'Topical cluster & silo planner', 'ar' => 'مخطِّط العناقيد الموضوعية والصفحات المحورية'],
            'what' => ['en' => 'Map bilingual pillar pages, spoke articles, hreflang pairs and internal anchors.', 'ar' => 'نظّم الصفحات المحورية والمقالات الفرعية وأزواج اللغة ونصوص الربط الداخلي.']],
        'seo_local_gbp' => ['tier' => 'pro', 'refs' => ['E2-070'],
            'name' => ['en' => '3-branch Local SEO & Business Profile', 'ar' => 'السيو المحلي وإدارة 3 فروع للملف التجاري'],
            'what' => ['en' => 'Audit RTL phone/hours consistency and draft bilingual review replies for up to 3 branches.', 'ar' => 'افحص اتساق الهاتف وساعات العمل وجهّز مسودات ردود ثنائية اللغة لثلاثة فروع.']],
        'sem_ngram_miner' => ['tier' => 'pro', 'refs' => ['E2-071'],
            'name' => ['en' => 'Search-term n-gram bleed miner', 'ar' => 'منقِّب هدر عبارات البحث بالجذور المشتركة'],
            'what' => ['en' => 'Surface zero-conversion 1/2/3-word Arabic and English stems and export negatives.', 'ar' => 'اكشف الجذور العربية والإنجليزية المهدرة بلا تحويلات وصدّر الكلمات السلبية.']],
        'sem_shopping_feeds' => ['tier' => 'pro', 'refs' => ['E2-071'],
            'name' => ['en' => '5,000-SKU profit-labeled shopping feeds', 'ar' => 'خلاصات تسوق مصنّفة بالربحية حتى 5,000 منتج'],
            'what' => ['en' => 'Publish OOS-filtered XML catalog feeds enriched with SKU profit quadrants.', 'ar' => 'انشر خلاصات منتجات XML تستبعد النافد وتصنّف المنتجات حسب الربحية.']],
        'sem_rule_engine' => ['tier' => 'pro', 'refs' => ['E2-072'],
            'name' => ['en' => 'Cross-channel dry-run rule engine', 'ar' => 'محرك القواعد عبر القنوات في وضع المحاكاة'],
            'what' => ['en' => 'Combine ad metrics with live store stock and COD return rates under a 20% delta cap.', 'ar' => 'اربط مؤشرات الإعلانات بمخزون المتجر ومعدل المرتجعات تحت سقف تغيّر 20%.']],
        'sem_cohort_ltv' => ['tier' => 'pro', 'refs' => ['E2-072'],
            'name' => ['en' => 'Customer cohort LTV & payback curves', 'ar' => 'منحنيات القيمة الدائمة للأفواج واسترداد التكلفة'],
            'what' => ['en' => 'Track D30, D60, D90 and D180 cohort LTV against acquisition cost.', 'ar' => 'تتبّع القيمة الدائمة للأفواج عبر 30 ثم 60 ثم 90 ثم 180 يومًا مقابل تكلفة الاستحواذ.']],
        'mena_creator_poas' => ['tier' => 'pro', 'refs' => ['E2-073'],
            'name' => ['en' => 'Creator & influencer Net POAS', 'ar' => 'صافي ربحية شراكات صناع المحتوى'],
            'what' => ['en' => 'Measure net creator return after flat fees, gifted COGS, boosted spend and COD returns.', 'ar' => 'قِس صافي عائد صناع المحتوى بعد الأجر الثابت وتكلفة الهدايا والتمويل والمرتجعات.']],
        'mena_offline_conversions' => ['tier' => 'pro', 'refs' => ['E2-073'],
            'name' => ['en' => 'Offline CAPI & GCLID conversion loop', 'ar' => 'حلقة تحويلات المبيعات الميدانية عبر CAPI ثم GCLID'],
            'what' => ['en' => 'Hash phone and email with SHA-256 and sync qualified or closed-won deals.', 'ar' => 'شفّر الهاتف والبريد بمعيار SHA-256 وزامن الصفقات المؤهلة والمغلقة.']],
        'ai_night_shift_queue' => ['tier' => 'pro', 'refs' => ['E2-073'],
            'name' => ['en' => 'Night Shift v1 supervised queue', 'ar' => 'طابور المناوب الليلي الخاضع للإشراف'],
            'what' => ['en' => 'Review, confirm or undo morning change proposals from Night Shift.', 'ar' => 'راجع المقترحات الصباحية من المناوب الليلي وأكّدها أو تراجع عنها.']],
        'prospect_pitch_decks' => ['tier' => 'pro', 'refs' => ['E2-073'],
            'name' => ['en' => '60-second prospect pitch decks (2 / month)', 'ar' => 'عروض تقديمية للعملاء المحتملين (عرضان شهريًا)'],
            'what' => ['en' => 'Build up to 2 agency-branded prospect audit and 3-tier retainer decks a month.', 'ar' => 'أنشئ حتى عرضين تقديميين شهريًا بهوية وكالتك مع فحص فني وثلاث باقات.']],

        'seo_rank_tracker_500' => ['tier' => 'agency', 'refs' => ['E2-070'],
            'name' => ['en' => '500-keyword rank tracker', 'ar' => 'متتبع ترتيب 500 كلمة مفتاحية'],
            'what' => ['en' => 'Expand tracked bilingual search rankings to 500 keywords across your portfolio.', 'ar' => 'وسّع تتبع الترتيب ثنائي اللغة إلى 500 كلمة مفتاحية عبر محفظة عملائك.']],
        'seo_backlink_disavow' => ['tier' => 'agency', 'refs' => ['E2-070'],
            'name' => ['en' => 'Backlink domain gap & Disavow export', 'ar' => 'فجوة النطاقات المرجعية وتصدير ملف التنصل'],
            'what' => ['en' => 'Find multi-domain link intersections and export UTF-8 Google Disavow files.', 'ar' => 'اكتشف النطاقات المشتركة وصدّر ملفات التنصل بترميز UTF-8 القياسي.']],
        'seo_local_multi_branch' => ['tier' => 'agency', 'refs' => ['E2-070'],
            'name' => ['en' => 'Multi-branch Local SEO suite', 'ar' => 'إدارة الفروع المتعددة للسيو المحلي'],
            'what' => ['en' => 'Manage Local SEO NAP consistency and review drafts across all client branches.', 'ar' => 'أدر اتساق بيانات الفروع ومسودات الردود عبر جميع فروع عملائك.']],
        'sem_shopping_feeds_50k' => ['tier' => 'agency', 'refs' => ['E2-071'],
            'name' => ['en' => '50,000-SKU shopping feeds', 'ar' => 'خلاصات تسوق موسّعة حتى 50,000 منتج'],
            'what' => ['en' => 'Scale profit-labeled XML shopping feeds up to 50,000 SKUs.', 'ar' => 'وسّع خلاصات التسوق المصنّفة بالربحية لتشمل حتى 50,000 منتج.']],
        'sem_rule_templates' => ['tier' => 'agency', 'refs' => ['E2-072'],
            'name' => ['en' => 'Cross-account automation rule templates', 'ar' => 'قوالب قواعد الأتمتة عبر الحسابات'],
            'what' => ['en' => 'Clone proven cross-channel automation rules across every client in your workspace.', 'ar' => 'انسخ قوالب قواعد الأتمتة الناجحة عبر مختلف عملاء وكالتك.']],
        'prospect_pitch_decks_unlimited' => ['tier' => 'agency', 'refs' => ['E2-073'],
            'name' => ['en' => 'Unlimited pitch decks (fair use)', 'ar' => 'عروض تقديمية غير محدودة (ضمن الاستخدام العادل)'],
            'what' => ['en' => 'Generate unlimited 60-second prospect pitch decks and bilingual PDFs (fair use).', 'ar' => 'أنشئ عروضًا تقديمية غير محدودة للعملاء المحتملين وملفات PDF ثنائية اللغة (ضمن الاستخدام العادل).']],
        'retainer_split' => ['tier' => 'agency', 'refs' => ['E2-067'],
            'name' => ['en' => 'Retainer vs pass-through ad-spend split', 'ar' => 'فصل أتعاب الوكالة عن الإنفاق الإعلاني العابر'],
            'what' => ['en' => 'Separate agency management fees from pass-through media spend with CSV, ZATCA TLV and UBL drafts.', 'ar' => 'افصل أتعاب الإدارة عن الإنفاق الإعلاني العابر مع مسودات CSV ثم ZATCA TLV ثم UBL.']],
    ],

    // E2-074 unified numeric quotas per tier (null = unlimited under fair use).
    'quotas' => [
        'watched_urls' => ['free' => 1, 'solo' => 5, 'starter' => 10, 'pro' => 50, 'agency' => 250],
        'gsc_sites' => ['free' => 1, 'solo' => 2, 'starter' => 10, 'pro' => 50, 'agency' => 300],
        'gsc_snapshot_rows' => ['free' => 10, 'solo' => 100, 'starter' => 5000, 'pro' => 25000, 'agency' => 100000],
        'tracked_keywords' => ['free' => 0, 'solo' => 0, 'starter' => 0, 'pro' => 100, 'agency' => 500],
        'local_seo_branches' => ['free' => 0, 'solo' => 0, 'starter' => 0, 'pro' => 3, 'agency' => null],
        'shopping_feed_skus' => ['free' => 0, 'solo' => 0, 'starter' => 0, 'pro' => 5000, 'agency' => 50000],
        'pitch_decks_per_month' => ['free' => 0, 'solo' => 0, 'starter' => 0, 'pro' => 2, 'agency' => null],
    ],

    // Alert rules that need `alert_rules_pro` (#23: 7 rules on Starter, the rest on Pro; the PS-08 upload reminder is on every plan, D-296).
    'pro_alert_rules' => ['pacing', 'landing_page', 'search_term_waste', 'account_health', 'ctr_drop', 'roas_drop', 'spend_spike', 'high_frequency', 'zero_impressions', 'lead_cost_spike', 'high_cpc'],
];
