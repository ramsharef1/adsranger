<?php

namespace App\Support\Seo;

/**
 * Structured content catalog for ADSR-042 B-06 (Product pages), B-07/B-08 (Blog spokes),
 * B-09 (Glossary terms), and B-11 (Stage-1 platform integration pages).
 * Strictly enforces D-170 stage labels, D-229 honesty rules, R-20/D-181 ("USD" format), R-21 (AdsRanger feminine in AR), and R-22 (Organization author).
 */
class PublicCatalog
{
    /**
     * B-06 Product pages: /features, /reports, /studio, /search, /ai
     *
     * @return array<string, array<string, mixed>>
     */
    public static function products(): array
    {
        return [
            'features' => [
                'ar' => [
                    'nav' => 'الميزات',
                    'title' => 'ميزات منصة إدارة الإعلانات والسيو للوكالات | AdsRanger',
                    'meta' => 'تعرّف على الميزات التي تبنيها AdsRanger لوكالات التسويق في المنطقة: متابعة الميزانيات، التقارير الموحدة، استوديو الإعلانات، وأدوات السيو العربي.',
                    'h1' => 'ميزات مصمَّمة لعمل وكالات الإعلانات والسيو يوميًا',
                    'lead' => 'تجمع <bdi dir="ltr">AdsRanger</bdi> متابعة الحسابات الإعلانية، وتيرة الإنفاق، تقارير العملاء، وفحص السيو العربي في مساحة عمل واحدة. قيد التطوير · الإطلاق في 2027.',
                    'cards' => [
                        ['h' => 'تقارير إعلانات موحدة', 'p' => 'تجميع الأداء عبر المنصات الإعلانية المخطط لربطها مع ملخص واضح لكل عميل.', 'link' => '/reports', 'link_label' => 'استعرض صفحة التقارير'],
                        ['h' => 'استوديو الإعلانات', 'p' => 'تجهيز مسودات الإعلانات ومراجعتها مع فريقك وعملائك لعدة منصات إعلانية.', 'link' => '/studio', 'link_label' => 'استعرض استوديو الإعلانات'],
                        ['h' => 'أدوات إعلانات البحث والسيو العربي', 'p' => 'تحليل الكلمات المفتاحية العربية وفحص صفحات الهبوط ومعاينة نتائج البحث.', 'link' => '/search', 'link_label' => 'استعرض أدوات البحث'],
                        ['h' => 'مساعد الذكاء الاصطناعي للوكالات', 'p' => 'ملخصات وتنبيهات باللغة العربية والإنجليزية مبنية على أرقام حساباتك فقط.', 'link' => '/ai', 'link_label' => 'استعرض ميزات الذكاء الاصطناعي'],
                    ],
                ],
                'en' => [
                    'nav' => 'Features',
                    'title' => 'Agency Ad Management & Arabic SEO Platform Features | AdsRanger',
                    'meta' => 'Explore what AdsRanger is building for MENA agencies: budget pacing, unified client reports, ad studio workflows, and Arabic search tools.',
                    'h1' => 'Features built for daily agency ad and search operations',
                    'lead' => 'AdsRanger brings budget pacing, cross-platform reporting, client approval links, and Arabic SEO checks into one workspace. In development · launching in 2027.',
                    'cards' => [
                        ['h' => 'Unified client reporting', 'p' => 'Combine performance across planned ad platforms with a clean shareable client view.', 'link' => '/en/reports', 'link_label' => 'Explore reporting'],
                        ['h' => 'Multi-platform ad studio', 'p' => 'Draft, preview, and collect client sign-off on creatives across several ad platforms.', 'link' => '/en/studio', 'link_label' => 'Explore ad studio'],
                        ['h' => 'Search ads & Arabic SEO tools', 'p' => 'Expand Arabic keyword variants, audit landing pages, and check SERP pixel widths.', 'link' => '/en/search', 'link_label' => 'Explore search tools'],
                        ['h' => 'AI assistant for ad agencies', 'p' => 'Bilingual summaries and pacing explanations grounded strictly in your workspace numbers.', 'link' => '/en/ai', 'link_label' => 'Explore AI features'],
                    ],
                ],
            ],
            'reports' => [
                'ar' => [
                    'nav' => 'التقارير',
                    'title' => 'تقارير إعلانات موحدة للوكالات والعملاء | AdsRanger',
                    'meta' => 'صمّمنا تقارير AdsRanger لتوضّح للعميل ما أُنفق وما تحقق بلغة عربية وإنجليزية سليمة، مع رابط قراءة فقط وتصدير PDF.',
                    'h1' => 'تقارير إعلانات موحدة يفهمها العميل من أول نظرة',
                    'lead' => 'بدل جمع الجداول يدويًا في نهاية كل شهر، تبني <bdi dir="ltr">AdsRanger</bdi> تقارير موحدة تعرض الإنفاق والنتائج ومؤشر الصحة لكل عميل. قيد التطوير · الإطلاق في 2027.',
                    'cards' => [
                        ['h' => 'رابط تقرير العميل (قراءة فقط)', 'p' => 'شارك رابطًا محميًا مع العميل يعرض الأداء باللغة العربية أو الإنجليزية دون الحاجة لحساب داخل المنصة.', 'link' => '/demo-report', 'link_label' => 'شاهد تقريرًا تجريبيًا'],
                        ['h' => 'تتبع وتيرة الإنفاق والأهداف', 'p' => 'مقارنة الصرف الفعلي بالميزانية الشهرية المعتمدة مع تنبيهات مبكرة قبل نفاد الرصيد.', 'link' => '/tools/budget-pacing-calculator', 'link_label' => 'جرّب حاسبة وتيرة الإنفاق'],
                        ['h' => 'المنصات المخطط لربطها', 'p' => 'تغطية منصات المرحلة الأولى عبر تكاملات مخطط لها، مع إمكانية استيراد ملفات CSV.', 'link' => '/integrations', 'link_label' => 'راجع المنصات المخطط لها'],
                    ],
                ],
                'en' => [
                    'nav' => 'Reports',
                    'title' => 'Unified Client Ad Reporting for Agencies | AdsRanger',
                    'meta' => 'AdsRanger client reports show spend, outcomes, and pacing in clear Arabic and English, with read-only client links and PDF exports.',
                    'h1' => 'Cross-platform ad reporting clients understand at a glance',
                    'lead' => 'Instead of stitching spreadsheets together at month-end, AdsRanger is building unified client reporting with health scores and read-only links. In development · launching in 2027.',
                    'cards' => [
                        ['h' => 'Read-only client report link', 'p' => 'Share a clean bilingual link with your client—no login required and optional spend hiding.', 'link' => '/en/demo-report', 'link_label' => 'See a sample report'],
                        ['h' => 'Budget pacing & goal tracking', 'p' => 'Monitor daily spend against monthly targets with morning alerts when pacing drifts.', 'link' => '/en/tools/budget-pacing-calculator', 'link_label' => 'Try the pacing calculator'],
                        ['h' => 'Planned platform integrations', 'p' => 'Stage-1 ad platforms are planned for direct connection alongside CSV imports.', 'link' => '/en/integrations', 'link_label' => 'See planned platforms'],
                    ],
                ],
            ],
            'studio' => [
                'ar' => [
                    'nav' => 'استوديو الإعلانات',
                    'title' => 'إنشاء إعلانات لعدة منصات ومراجعتها مع العميل | AdsRanger',
                    'meta' => 'جهّز نصوص الإعلانات وتصاميمها بالعربية والإنجليزية وشارك رابط اعتماد مباشر مع العميل قبل النشر. ميزة مخطط لها ضمن AdsRanger.',
                    'h1' => 'تجهيز الإعلانات واعتمادها لعدة منصات من مكان واحد',
                    'lead' => 'يساعد استوديو الإعلانات في <bdi dir="ltr">AdsRanger</bdi> فرق الوكالات على كتابة النسخ الإعلانية، ومراجعة المقاسات، وأخذ موافقة العميل قبل إطلاق الحملة. مخطط له · الإطلاق في 2027.',
                    'cards' => [
                        ['h' => 'روابط اعتماد الإعلانات للعميل', 'p' => 'أرسل مسودة الإعلان برابط مباشر ليعتمدها العميل أو يطلب تعديلها مع حفظ سجل القرارات.', 'link' => '/features', 'link_label' => 'كل ميزات المنصة'],
                        ['h' => 'كتابة إعلانية تراعي العربية', 'p' => 'فحص اتجاه النص العربي والأرقام وعلامات الترقيم قبل إرسال المسودة للعميل.', 'link' => '/ai', 'link_label' => 'استعرض مساعد الذكاء الاصطناعي'],
                        ['h' => 'مواصفات المنصات الإعلانية', 'p' => 'إرشادات واضحة لمقاسات الفيديو والصور ونصوص الإعلانات لكل منصة مخطط لربطها.', 'link' => '/integrations', 'link_label' => 'المنصات الإعلانية'],
                    ],
                ],
                'en' => [
                    'nav' => 'Ad Studio',
                    'title' => 'Create & Approve Ads for Several Platforms | AdsRanger',
                    'meta' => 'Prepare bilingual ad copy and creative briefs, preview placements, and collect client approvals with a signed link. Planned in AdsRanger.',
                    'h1' => 'Prepare and approve ads for several platforms in one workflow',
                    'lead' => 'AdsRanger Studio helps agency teams draft Arabic and English ad copy, check creative specs, and collect client approvals before launch. Planned · launching in 2027.',
                    'cards' => [
                        ['h' => 'Signed client approval links', 'p' => 'Send a creative brief link to your client so they can approve or request edits without logging in.', 'link' => '/en/features', 'link_label' => 'All platform features'],
                        ['h' => 'Arabic-first copy checks', 'p' => 'Catch mixed digits, RTL punctuation issues, and character limits before campaigns go live.', 'link' => '/en/ai', 'link_label' => 'Explore AI assistant'],
                        ['h' => 'Platform creative specs', 'p' => 'Built-in aspect ratio and text length checks for major social and search placements.', 'link' => '/en/integrations', 'link_label' => 'Ad platforms'],
                    ],
                ],
            ],
            'search' => [
                'ar' => [
                    'nav' => 'البحث والسيو',
                    'title' => 'أدوات إعلانات البحث والسيو العربي للوكالات | AdsRanger',
                    'meta' => 'أدوات متخصصة لتوسيع الكلمات المفتاحية العربية، وفحص صفحات الهبوط، ومعاينة نتائج البحث بالبكسل لوكالات التسويق.',
                    'h1' => 'أدوات إعلانات البحث والسيو المصمَّمة للغة العربية',
                    'lead' => 'تعالج <bdi dir="ltr">AdsRanger</bdi> اختلافات كتابة الهمزات والتاء المربوطة واللهجات العربية في كل من إعلانات البحث وفحص السيو الداخلي للصفحات. قيد التطوير · الأدوات المجانية متاحة الآن.',
                    'cards' => [
                        ['h' => 'توليد الكلمات المفتاحية العربية (S1)', 'p' => 'توسيع البذور المفتاحية إلى صيغ إملائية، وعربيزي، وأسئلة بحث شائعة، وتصنيف اللهجات.', 'link' => '/tools/arabic-keyword-generator', 'link_label' => 'جرّب مولد الكلمات مجانًا'],
                        ['h' => 'فحص السيو العربي لصفحات الهبوط (A1/S3)', 'p' => 'فحص 14 قاعدة تشمل عرض البكسل، وسم الاتجاه RTL، وتطابق الكلمات العربية، والبيانات المنظمة.', 'link' => '/tools/page-audit', 'link_label' => 'افحص صفحتك الآن'],
                        ['h' => 'معاينة نتائج البحث وفحص Hreflang', 'p' => 'تحقق من ظهور عناوينك في نتائج الجوال والكمبيوتر ومن سلامة روابط اللغات المتبادلة.', 'link' => '/tools/serp-preview', 'link_label' => 'معاينة نتائج البحث'],
                    ],
                ],
                'en' => [
                    'nav' => 'Search & SEO',
                    'title' => 'Search Ads & Arabic SEO Tools for Agencies | AdsRanger',
                    'meta' => 'Purpose-built Arabic keyword expansion, landing page SEO audits, and SERP pixel readiness checks for MENA marketing agencies.',
                    'h1' => 'Search ad and SEO tools built around Arabic script and dialects',
                    'lead' => 'AdsRanger handles hamza variants, ta-marbuta endings, Arabizi queries, and RTL layout checks across search campaigns and landing page audits. In development · free tools live now.',
                    'cards' => [
                        ['h' => 'Arabic keyword generator (S1)', 'p' => 'Expand seed terms into orthographic variants, Arabizi forms, dialect tags, and common Arabic questions.', 'link' => '/en/tools/arabic-keyword-generator', 'link_label' => 'Try the keyword generator'],
                        ['h' => 'On-page Arabic SEO audit (A1/S3)', 'p' => 'Run 14 checks covering pixel truncation, dir="rtl", normalized keyword presence, and JSON-LD schema.', 'link' => '/en/tools/page-audit', 'link_label' => 'Audit a page for free'],
                        ['h' => 'SERP pixel preview & hreflang checker', 'p' => 'Preview mobile and desktop snippets in pixels and verify reciprocal bilingual hreflang tags.', 'link' => '/en/tools/serp-preview', 'link_label' => 'Open SERP preview'],
                    ],
                ],
            ],
            'ai' => [
                'ar' => [
                    'nav' => 'الذكاء الاصطناعي',
                    'title' => 'الذكاء الاصطناعي لوكالات الإعلان بالعربية | AdsRanger',
                    'meta' => 'مساعد ذكاء اصطناعي يشرح تغيرات الأداء ووتيرة الإنفاق بالعربية والإنجليزية اعتمادًا على بيانات مساحة عملك فقط، دون تدريب على بيانات العملاء.',
                    'h1' => 'ذكاء اصطناعي يشرح أرقام حملاتك بالعربية دون اختلاق',
                    'lead' => 'تستخدم <bdi dir="ltr">AdsRanger</bdi> الذكاء الاصطناعي لتلخيص التنبيهات وتفسير تغيرات التكلفة وصياغة ملخصات التقارير من أرقام حسابك وحدها—ولا يتخذ أي قرار أو يعدّل ميزانية دون موافقة فريقك. قيد التطوير · الإطلاق في 2027.',
                    'cards' => [
                        ['h' => 'ملخصات مبنية على أرقامك فقط', 'p' => 'ينطلق الشرح من الجداول والتنبيهات المسجلة في مساحة عملك، ولا ندّعي نسب دقة مطلقة أو توقعات غير معللة.', 'link' => '/features', 'link_label' => 'تعرّف على ميزات المنصة'],
                        ['h' => 'خصوصية بيانات الوكالة والعملاء', 'p' => 'تبقى بيانات كل وكالة معزولة تمامًا، ولا تُستخدم بيانات عملائك لتدريب نماذج عامة.', 'link' => '/security', 'link_label' => 'راجع صفحة الأمان'],
                        ['h' => 'مسودات نصوص إعلانية ثنائية اللغة', 'p' => 'اقتراح مسودات أولية للعناوين والأوصاف بالعربية الفصحى أو الإنجليزية ليراجعها فريقك في الاستوديو.', 'link' => '/studio', 'link_label' => 'استوديو الإعلانات'],
                    ],
                ],
                'en' => [
                    'nav' => 'AI',
                    'title' => 'AI for Ad Agencies in Arabic & English | AdsRanger',
                    'meta' => 'Bilingual AI summaries that explain campaign shifts and draft report notes strictly from your workspace data—never trained on other clients.',
                    'h1' => 'Bilingual AI grounded in your agency numbers—never on autopilot',
                    'lead' => 'AdsRanger uses AI to summarize alert triggers, explain pacing shifts, and draft report notes in Arabic and English. Nothing changes in any ad account without your team\'s approval. In development · launching in 2027.',
                    'cards' => [
                        ['h' => 'Grounded in your workspace metrics', 'p' => 'Summaries reference the exact metrics and alert thresholds in your workspace with transparent assumptions.', 'link' => '/en/features', 'link_label' => 'See all features'],
                        ['h' => 'Strict tenant data isolation', 'p' => 'Your agency and client data stay isolated and are never used to train shared external models.', 'link' => '/en/security', 'link_label' => 'Read security practices'],
                        ['h' => 'Bilingual creative & report drafts', 'p' => 'Draft initial Arabic and English report commentary and ad variations for your team to review.', 'link' => '/en/studio', 'link_label' => 'Explore Ad Studio'],
                    ],
                ],
            ],
        ];
    }

    /**
     * B-11 Stage-1 Platform pages: /integrations/{platform} (7 platforms; no GA4 per D-204)
     *
     * @return array<string, array{name_ar: string, name_en: string, summary_ar: string, summary_en: string, metrics_ar: list<string>, metrics_en: list<string>, guide_link: string}>
     */
    public static function platforms(): array
    {
        return [
            'meta' => [
                'name_ar' => 'Meta (فيسبوك وإنستغرام)',
                'name_en' => 'Meta (Facebook & Instagram)',
                'summary_ar' => 'تخطط <bdi dir="ltr">AdsRanger</bdi> لربط حسابات <bdi dir="ltr">Meta</bdi> الإعلانية لعرض الإنفاق والنتائج وتكلفة النتيجة وتنبيهات التشبع الإعلاني في تقارير موحدة للوكالات.',
                'summary_en' => 'AdsRanger plans to connect Meta ad accounts so agencies can track spend, results, cost per result, and creative fatigue alerts in one unified report.',
                'metrics_ar' => ['الإنفاق اليومي والشهري ومقارنته بالميزانية المعتمدة', 'النتائج حسب هدف الحملة (مبيعات، رسائل، زيارات، عملاء محتملون)', 'معدل التكرار (Frequency) وتنبيهات تشبع الجمهور'],
                'metrics_en' => ['Daily and monthly spend paced against approved client budgets', 'Results grouped by campaign objective (leads, purchases, messages, traffic)', 'Frequency and audience saturation alerts'],
                'guide_link' => '/ramadan-2027',
            ],
            'google-ads' => [
                'name_ar' => 'Google Ads (إعلانات جوجل)',
                'name_en' => 'Google Ads',
                'summary_ar' => 'تخطط <bdi dir="ltr">AdsRanger</bdi> لربط <bdi dir="ltr">Google Ads</bdi> لمتابعة حملات البحث وPerformance Max ويوتيوب ضمن لوحة الوكالة الموحدة.',
                'summary_en' => 'AdsRanger plans to integrate Google Ads to monitor Search, Performance Max, and YouTube campaigns alongside your other ad platforms.',
                'metrics_ar' => ['الإنفاق والتحويلات وتكلفة التحويل عبر حملات البحث وPerformance Max', 'نسبة الظهور في الشبكة الإعلانية (Impression Share)', 'تكامل مع أدوات الكلمات المفتاحية العربية وفحص صفحات الهبوط'],
                'metrics_en' => ['Spend, conversions, and cost per conversion across Search and Performance Max', 'Search impression share and budget-lost indicators', 'Paired with Arabic keyword expansion and landing page SEO checks'],
                'guide_link' => '/search',
            ],
            'tiktok' => [
                'name_ar' => 'TikTok Ads (إعلانات تيك توك)',
                'name_en' => 'TikTok Ads',
                'summary_ar' => 'تكامل مخطط له لربط حسابات <bdi dir="ltr">TikTok Ads</bdi> ومتابعة أداء الفيديو القصير والمبيعات وتيرة الصرف اليومية لعملاء الوكالة.',
                'summary_en' => 'Planned integration for TikTok Ads to track short-form video performance, conversions, and daily budget pacing across agency clients.',
                'metrics_ar' => ['الإنفاق والمشاهدات والتحويلات وتكلفة النتيجة', 'معدلات استكمال الفيديو والنقر إلى صفحة الهبوط', 'دمج النتائج في التقرير الشهري الموحد للعميل'],
                'metrics_en' => ['Spend, video views, conversions, and cost per result', 'Video view-through and click-through performance', 'Included in unified monthly client PDF and link reports'],
                'guide_link' => '/reports',
            ],
            'snapchat' => [
                'name_ar' => 'Snapchat Ads (إعلانات سناب شات)',
                'name_en' => 'Snapchat Ads',
                'summary_ar' => 'تكامل مخطط له لمنصة <bdi dir="ltr">Snapchat Ads</bdi> ذات الحضور القوي في أسواق الخليج والمنطقة لمتابعة الحملات والميزانيات.',
                'summary_en' => 'Planned integration for Snapchat Ads—essential for Gulf and wider MENA campaigns—bringing spend and conversion tracking into your agency desk.',
                'metrics_ar' => ['الإنفاق اليومي والنتائج وتكلفة السحب للأعلى أو التحويل', 'متابعة وتيرة الصرف خلال المواسم الذروة مثل رمضان والعيد', 'مقارنة الأداء مع بقية المنصات في تقرير واحد'],
                'metrics_en' => ['Daily spend, swipe-ups, conversions, and cost per result', 'Peak-season pacing monitoring during Ramadan and White Friday', 'Side-by-side comparison with other client channels'],
                'guide_link' => '/mena-calendar',
            ],
            'microsoft-advertising' => [
                'name_ar' => 'Microsoft Advertising',
                'name_en' => 'Microsoft Advertising',
                'summary_ar' => 'تكامل مخطط له لربط حسابات <bdi dir="ltr">Microsoft Advertising</bdi> لتغطية إعلانات البحث على Bing وشبكة مايكروسوفت ضمن تقارير الوكالة.',
                'summary_en' => 'Planned integration for Microsoft Advertising to include Bing and Microsoft network search campaigns in your cross-platform agency reports.',
                'metrics_ar' => ['الإنفاق والنقرات والتحويلات وتكلفة النقرة', 'توحيد تقارير إعلانات البحث في شاشة واحدة', 'تنبيهات انقطاع التتبع أو توقف الصرف'],
                'metrics_en' => ['Spend, clicks, conversions, and average CPC', 'Unified search reporting alongside Google Ads', 'Morning alerts for spend drops or tracking breaks'],
                'guide_link' => '/search',
            ],
            'linkedin' => [
                'name_ar' => 'LinkedIn Ads (إعلانات لينكدإن)',
                'name_en' => 'LinkedIn Ads',
                'summary_ar' => 'تكامل مخطط له لربط حسابات <bdi dir="ltr">LinkedIn Ads</bdi> لعملاء قطاع الأعمال (B2B) ومتابعة تكلفة العميل المحتمل والميزانيات.',
                'summary_en' => 'Planned integration for LinkedIn Ads to track B2B lead generation, account-based campaigns, and monthly budget pacing.',
                'metrics_ar' => ['الإنفاق وعدد العملاء المحتملين (Leads) وتكلفة النتيجة', 'متابعة الميزانيات الشهرية لحملات الشركات', 'تضمين النتائج في تقرير العميل الموحد'],
                'metrics_en' => ['Spend, lead form submissions, and cost per lead', 'Monthly pacing for B2B campaign budgets', 'Client-ready bilingual summary reporting'],
                'guide_link' => '/reports',
            ],
            'x' => [
                'name_ar' => 'X Ads (إعلانات منصة X)',
                'name_en' => 'X Ads',
                'summary_ar' => 'تكامل مخطط له لربط حسابات <bdi dir="ltr">X Ads</bdi> ومتابعة حملات التفاعل والزيارات والمواسم الإعلانية في المنطقة.',
                'summary_en' => 'Planned integration for X Ads to monitor reach, engagement, traffic, and seasonal campaign spend across MENA markets.',
                'metrics_ar' => ['الإنفاق والتفاعل والزيارات وتكلفة النتيجة', 'مراقبة الصرف اليومي خلال المناسبات والمواسم', 'عرض موحد مع باقي المنصات الإعلانية'],
                'metrics_en' => ['Spend, engagements, site visits, and cost per result', 'Daily pacing tracking during live events and seasonal peaks', 'Unified view alongside all client ad accounts'],
                'guide_link' => '/mena-calendar',
            ],
        ];
    }

    /**
     * B-09 Glossary terms (10 foundational terms with formulas & MENA USD examples per D-181).
     *
     * @return array<string, array{term_ar: string, term_en: string, def_ar: string, def_en: string, formula_ar: string, formula_en: string, example_ar: string, example_en: string, related_path: string}>
     */
    public static function glossary(): array
    {
        return [
            'roas' => [
                'term_ar' => 'العائد على الإنفاق الإعلاني (ROAS)',
                'term_en' => 'Return on Ad Spend (ROAS)',
                'def_ar' => 'يقيس العائد على الإنفاق الإعلاني إجمالي الإيرادات المباشرة الناتجة عن الإعلانات مقسومًا على تكلفة تلك الإعلانات.',
                'def_en' => 'Return on Ad Spend (ROAS) measures the gross revenue generated directly from advertising divided by the ad spend.',
                'formula_ar' => 'ROAS = إجمالي إيرادات الإعلانات ÷ إجمالي الإنفاق الإعلاني',
                'formula_en' => 'ROAS = Revenue from Ads / Ad Spend',
                'example_ar' => 'إذا أنفق متجر إلكتروني 2,000 USD على حملة إعلانية وحقق مبيعات بقيمة 8,000 USD، فإن العائد على الإنفاق الإعلاني يساوي 4.0x.',
                'example_en' => 'If an online store spends 2,000 USD on a campaign and generates 8,000 USD in tracked sales, its ROAS is 4.0x.',
                'related_path' => '/tools/roas-calculator',
            ],
            'cpr' => [
                'term_ar' => 'تكلفة النتيجة (CPR)',
                'term_en' => 'Cost Per Result (CPR)',
                'def_ar' => 'تمثل تكلفة النتيجة متوسط ما تدفعه الحملة الإعلانية مقابل تحقيق هدف واحد محدد (مثل عملية شراء، أو رسالة، أو تسجيل عميل محتمل).',
                'def_en' => 'Cost Per Result (CPR) is the average amount spent to achieve one defined campaign outcome (such as a lead, purchase, or message).',
                'formula_ar' => 'تكلفة النتيجة = إجمالي الإنفاق الإعلاني ÷ عدد النتائج المحققة',
                'formula_en' => 'CPR = Total Ad Spend / Number of Results',
                'example_ar' => 'عند إنفاق 1,500 USD والحصول على 300 طلب تواصل، تكون تكلفة النتيجة الواحدة 5.00 USD.',
                'example_en' => 'Spending 1,500 USD to generate 300 qualified inquiries produces a CPR of 5.00 USD.',
                'related_path' => '/tools/ramadan-budget-calculator',
            ],
            'ctr' => [
                'term_ar' => 'نسبة النقر إلى الظهور (CTR)',
                'term_en' => 'Click-Through Rate (CTR)',
                'def_ar' => 'تقيس نسبة النقر إلى الظهور النسبة المئوية للأشخاص الذين نقروا على الإعلان أو نتيجة البحث بعد مشاهدتها.',
                'def_en' => 'Click-Through Rate (CTR) is the percentage of impressions that resulted in a click on your ad or search snippet.',
                'formula_ar' => 'CTR (%) = (عدد النقرات ÷ عدد مرات الظهور) × 100',
                'formula_en' => 'CTR (%) = (Clicks / Impressions) × 100',
                'example_ar' => 'إعلان ظهر 50,000 مرة وحصل على 1,000 نقرة يحقق نسبة نقر إلى ظهور قدرها 2.0%.',
                'example_en' => 'An ad with 50,000 impressions and 1,000 clicks has a 2.0% CTR.',
                'related_path' => '/tools/serp-preview',
            ],
            'cpm' => [
                'term_ar' => 'تكلفة الألف ظهور (CPM)',
                'term_en' => 'Cost Per Mille / 1,000 Impressions (CPM)',
                'def_ar' => 'تعبر تكلفة الألف ظهور عن السعر الذي يدفعه المعلن مقابل كل 1,000 مرة يظهر فيها الإعلان للجمهور المستهدف.',
                'def_en' => 'Cost Per Mille (CPM) is the price paid for every 1,000 impressions served in an ad auction.',
                'formula_ar' => 'CPM = (الإنفاق الإعلاني ÷ مرات الظهور) × 1,000',
                'formula_en' => 'CPM = (Ad Spend / Impressions) × 1,000',
                'example_ar' => 'إذا أنفقت الحملة 600 USD وحققت 100,000 ظهور، فإن تكلفة الألف ظهور تساوي 6.00 USD.',
                'example_en' => 'Spending 600 USD for 100,000 impressions equals a 6.00 USD CPM.',
                'related_path' => '/ramadan-2027',
            ],
            'cpc' => [
                'term_ar' => 'تكلفة النقرة (CPC)',
                'term_en' => 'Cost Per Click (CPC)',
                'def_ar' => 'تقيس تكلفة النقرة المبلغ الفعلي المدفوع مقابل كل نقرة ينتقل بها المستخدم من الإعلان إلى صفحة الهبوط.',
                'def_en' => 'Cost Per Click (CPC) is the average amount paid for each click on an ad.',
                'formula_ar' => 'CPC = إجمالي الإنفاق الإعلاني ÷ عدد النقرات',
                'formula_en' => 'CPC = Total Ad Spend / Total Clicks',
                'example_ar' => 'إنفاق 450 USD مقابل 900 نقرة يعني أن متوسط تكلفة النقرة هو 0.50 USD.',
                'example_en' => '450 USD spent across 900 clicks results in a 0.50 USD average CPC.',
                'related_path' => '/search',
            ],
            'pacing' => [
                'term_ar' => 'وتيرة الإنفاق (Budget Pacing)',
                'term_en' => 'Budget Pacing',
                'def_ar' => 'توضح وتيرة الإنفاق مدى التزام الحملة بصرف الميزانية المقررة بالتوازي مع الأيام المنقضية من الشهر أو الموسم.',
                'def_en' => 'Budget pacing tracks how closely actual ad spend matches the planned target spend for the time elapsed in a period.',
                'formula_ar' => 'نسبة الوتيرة (%) = (الإنفاق الفعلي حتى اليوم ÷ الإنفاق المخطط حتى اليوم) × 100',
                'formula_en' => 'Pacing (%) = (Spend to Date / Planned Spend to Date) × 100',
                'example_ar' => 'عميل ميزانيته الشهرية 6,000 USD يجب أن يكون قد أنفق نحو 3,000 USD في منتصف الشهر (100% وتيرة متوازنة).',
                'example_en' => 'A client with a 6,000 USD monthly budget should be near 3,000 USD halfway through the month (100% on-pace).',
                'related_path' => '/tools/budget-pacing-calculator',
            ],
            'conversion' => [
                'term_ar' => 'التحويل (Conversion)',
                'term_en' => 'Conversion',
                'def_ar' => 'التحويل هو إتمام الزائر للإجراء المطلوب على الموقع أو التطبيق، مثل إتمام طلب شراء أو تعبئة نموذج تواصل.',
                'def_en' => 'A conversion occurs when a visitor completes a valuable action, such as purchasing a product or submitting a lead form.',
                'formula_ar' => 'معدل التحويل (%) = (عدد التحويلات ÷ عدد الزيارات أو النقرات) × 100',
                'formula_en' => 'Conversion Rate (%) = (Conversions / Clicks or Sessions) × 100',
                'example_ar' => 'صفحة هبوط استقبلت 1,000 زائر من الإعلانات وسجلت 35 طلب شراء تحقق معدل تحويل قدره 3.5%.',
                'example_en' => 'A landing page receiving 1,000 ad clicks and generating 35 orders has a 3.5% conversion rate.',
                'related_path' => '/reports',
            ],
            'impression-share' => [
                'term_ar' => 'نسبة الظهور (Impression Share)',
                'term_en' => 'Impression Share',
                'def_ar' => 'تمثل نسبة الظهور في إعلانات البحث النسبة المئوية لمرات ظهور إعلانك مقارنة بإجمالي المرات التي كان مؤهلًا للظهور فيها.',
                'def_en' => 'Impression Share is the percentage of eligible search impressions your ads actually received compared to total available impressions.',
                'formula_ar' => 'نسبة الظهور (%) = (مرات الظهور الفعلية ÷ إجمالي مرات الظهور المؤهلة) × 100',
                'formula_en' => 'Impression Share (%) = (Impressions / Total Eligible Impressions) × 100',
                'example_ar' => 'إذا ظهر إعلان البحث 700 مرة من أصل 1,000 عملية بحث مطابقة لاستهدافك، فإن نسبة الظهور هي 70%.',
                'example_en' => 'Receiving 700 impressions out of 1,000 eligible searches equals a 70% impression share.',
                'related_path' => '/search',
            ],
            'quality-score' => [
                'term_ar' => 'نقاط الجودة (Quality Score)',
                'term_en' => 'Quality Score',
                'def_ar' => 'مقياس من 1 إلى 10 في إعلانات البحث يقيّم مدى صلة الكلمة المفتاحية بنص الإعلان وتجربة صفحة الهبوط.',
                'def_en' => 'A 1–10 diagnostic rating in search platforms measuring keyword relevance, expected CTR, and landing page experience.',
                'formula_ar' => 'يعتمد على 3 عوامل: نسبة النقر المتوقعة + صلة الإعلان + تجربة صفحة الهبوط',
                'formula_en' => 'Driven by Expected CTR + Ad Relevance + Landing Page Experience',
                'example_ar' => 'تحسين سرعة صفحة الهبوط العربية وتطابق عنوانها مع الكلمة المفتاحية يرفع نقاط الجودة ويخفض تكلفة النقرة.',
                'example_en' => 'Aligning your Arabic landing page H1 and title with the search query improves Quality Score and lowers CPC.',
                'related_path' => '/tools/page-audit',
            ],
            'utm' => [
                'term_ar' => 'معاملات تتبّع الروابط (UTM Parameters)',
                'term_en' => 'UTM Parameters',
                'def_ar' => 'وسوم نصية تُضاف إلى نهاية رابط صفحة الهبوط لتحديد المنصة والحملة والإعلان الذي جلب الزيارة في تقارير التحليلات.',
                'def_en' => 'Query tags appended to a destination URL so analytics tools can identify which platform, campaign, and ad drove each session.',
                'formula_ar' => '?utm_source=meta&utm_medium=paid_social&utm_campaign=ramadan_2027',
                'formula_en' => '?utm_source=meta&utm_medium=paid_social&utm_campaign=ramadan_2027',
                'example_ar' => 'إضافة معاملات UTM القياسية لكل إعلان تمنع ظهور زيارات الحملات الممولة تحت تصنيف الزيارات المباشرة غير المعرّفة.',
                'example_en' => 'Consistent UTM tags prevent paid social traffic from being misclassified as direct sessions.',
                'related_path' => '/tools/utm-builder',
            ],
        ];
    }

    /**
     * B-07 & B-08 Blog spokes (/blog/{slug})
     *
     * @return array<string, array{title_ar: string, title_en: string, meta_ar: string, meta_en: string, summary_ar: string, summary_en: string, sections_ar: list<array{h: string, p: string}>, sections_en: list<array{h: string, p: string}>, related_links: list<string>}>
     */
    public static function posts(): array
    {
        return [
            'eid-al-fitr-2027-campaign-checklist' => [
                'title_ar' => 'قائمة التحقق لحملات عيد الفطر 2027 الإعلانية | AdsRanger',
                'title_en' => 'Eid al-Fitr 2027 Ad Campaign Checklist for Agencies | AdsRanger',
                'meta_ar' => 'خطوات عملية لوكالات الإعلان للتحضير لحملات عيد الفطر 2027 (المتوقع 9 أو 10 مارس 2027): توقيت العروض، مناوبات الفريق، وميزانية ما بعد العيد.',
                'meta_en' => 'Practical agency checklist for Eid al-Fitr 2027 campaigns (expected 9 or 10 March 2027): offer cutoff times, holiday cover, and post-Eid pacing.',
                'summary_ar' => 'دليل تشغيلي لتجهيز حملات عيد الفطر 2027 (المتوقع 9 أو 10 مارس 2027 حسب إعلان رؤية الهلال) دون هدر في ميزانيات الشحن أو التوصيل.',
                'summary_en' => 'Operational checklist for transitioning Ramadan campaigns into Eid al-Fitr 2027 (expected 9 or 10 March 2027) without wasting post-cutoff shipping spend.',
                'sections_ar' => [
                    ['h' => '1. فصل ميزانية العشر الأواخر عن أيام العيد', 'p' => 'تختلف نية الشراء في الأسبوع الأخير من رمضان (هدايا وتجهيزات العيد) عن أيام العيد نفسها (مطاعم، ترفيه، وعروض فورية). خصّص مجموعة إعلانية مستقلة لكل مرحلة.'],
                    ['h' => '2. إيقاف إعلانات الشحن عند انتهاء مهلة التوصيل', 'p' => 'تأكد من جدولة إيقاف إعلانات المتاجر التي تتطلب شحنًا قبل آخر موعد تسليم يحدده المتجر، وتحويل الميزانية إلى بطاقات الهدايا الرقمية أو الاستلام من الفرع.'],
                    ['h' => '3. ضبط وتيرة الإنفاق لما بعد عطلة العيد', 'p' => 'راجع الميزانية المتبقية لشهر مارس 2027 باستخدام حاسبة وتيرة الإنفاق لضمان عودة الحملات اليومية المعتادة بسلاسة بعد انتهاء العطلة.'],
                ],
                'sections_en' => [
                    ['h' => '1. Separate last-ten-nights spend from Eid days', 'p' => 'Search and social intent shifts from gift shopping during the last ten nights to dining, entertainment, and instant offers during Eid days.'],
                    ['h' => '2. Schedule shipping cutoff rules in advance', 'p' => 'Pause delivery-dependent product sets once the courier cutoff passes, and pivot copy toward in-store pickup or instant digital vouchers.'],
                    ['h' => '3. Re-pace remaining March 2027 budgets after Eid', 'p' => 'Check remaining monthly budgets right after the holiday so evergreen campaigns finish March on target.'],
                ],
                'related_links' => ['/ramadan-2027', '/mena-calendar', '/tools/budget-pacing-calculator'],
            ],
            'ramadan-budget-by-phase' => [
                'title_ar' => 'توزيع ميزانية إعلانات رمضان 2027 على مراحل الشهر | AdsRanger',
                'title_en' => 'How to Split a Ramadan 2027 Ad Budget Across 5 Phases | AdsRanger',
                'meta_ar' => 'مثال تطبيقي لتوزيع ميزانية عميل بقيمة 3,600 USD على مراحل رمضان الخمس من التحضير في يناير حتى عيد الفطر في مارس 2027.',
                'meta_en' => 'Worked example showing how to allocate a 3,600 USD client budget across the 5 stages of Ramadan 2027, from January prep to Eid.',
                'summary_ar' => 'كيف تقسّم ميزانية إعلانية شهرية (مثال تطبيقي: 3,600 USD) بين مرحلة التحضير وأسابيع الذروة والعشر الأواخر دون نفاد الرصيد مبكرًا.',
                'summary_en' => 'How to structure a 3,600 USD campaign budget across pre-Ramadan testing, peak weeks, and the final ten nights without exhausting budget early.',
                'sections_ar' => [
                    ['h' => '1. مرحلة التحضير والاختبار (من 11 يناير 2027)', 'p' => 'خصّص حصة لاختبار الرسائل الإعلانية وصفحات الهبوط وبناء قوائم إعادة الاستهداف قبل ارتفاع الطلب في فبراير.'],
                    ['h' => '2. الأسابيع الأولى ومنتصف رمضان', 'p' => 'حافظ على وتيرة إنفاق يومية متوازنة مع مراقبة تكلفة النتيجة اليومية وتعديل الميزانية حسب الفئات الأعلى تحويلًا.'],
                    ['h' => '3. العشر الأواخر والتحول إلى عروض العيد', 'p' => 'استخدم حاسبة ميزانية إعلانات رمضان لتقدير أثر تغير تكلفة النتيجة على إجمالي النتائج المتوقعة خلال 29 أو 30 يومًا.'],
                ],
                'sections_en' => [
                    ['h' => '1. Preparation & testing phase (from 11 January 2027)', 'p' => 'Dedicate early testing budget to validate landing pages, creative hooks, and retargeting pools before February auction pressure.'],
                    ['h' => '2. Early and mid-Ramadan steady pacing', 'p' => 'Keep daily spend aligned with conversion volume and monitor CPR shifts across platforms.'],
                    ['h' => '3. Modeling 29-day vs 30-day scenarios', 'p' => 'Use the Ramadan budget calculator to model your own daily spend and CPR assumptions across 29 or 30 days.'],
                ],
                'related_links' => ['/ramadan-2027', '/tools/ramadan-budget-calculator', '/tools/budget-pacing-calculator'],
            ],
            'ramadan-ad-scheduling' => [
                'title_ar' => 'توقيت وجدولة إعلانات رمضان حسب ساعات الذروة | AdsRanger',
                'title_en' => 'Ramadan Ad Scheduling Across Time Zones & Peak Hours | AdsRanger',
                'meta_ar' => 'دليل جدولة الحملات الإعلانية في رمضان بين ساعات النهار وما بعد الإفطار والسحور، مع الانتباه للمناطق الزمنية للحسابات الإعلانية.',
                'meta_en' => 'How to manage Ramadan ad scheduling across daytime, post-Iftar, and Suhoor windows while verifying ad account time zones.',
                'summary_ar' => 'إرشادات عملية لمطابقة جدولة الإعلانات مع المنطقة الزمنية لكل حساب إعلاني وسلوك الجمهور قبل الإفطار وبعده.',
                'summary_en' => 'Practical rules for aligning ad schedules with account time zones and audience behavior before Iftar and late at night.',
                'sections_ar' => [
                    ['h' => '1. التحقق من المنطقة الزمنية للحساب الإعلاني أولًا', 'p' => 'قبل تطبيق أي جدولة بالساعات، تأكد مما إذا كان الحساب الإعلاني مضبوطًا على توقيت الرياض أو عمّان أو دبي أو التوقيت العالمي (UTC).'],
                    ['h' => '2. تجنب إيقاف الحملات يوميًا بشكل يعيد مرحلة التعلم', 'p' => 'الإيقاف اليدوي المتكرر قد يربك خوارزميات التوصيل؛ اعتمد على قواعد الجدولة المدمجة في المنصة الإعلانية أو الميزانيات المستمرة.'],
                ],
                'sections_en' => [
                    ['h' => '1. Verify ad account time zones before setting dayparting', 'p' => 'Always check whether each client ad account runs on local market time (e.g., Asia/Riyadh, Asia/Amman, Asia/Dubai) or UTC before applying hourly rules.'],
                    ['h' => '2. Avoid manual daily pauses that reset learning', 'p' => 'Use lifetime campaign schedules or steady daily budgets rather than manual daily pauses that disrupt platform delivery.'],
                ],
                'related_links' => ['/ramadan-2027', '/reports', '/mena-calendar'],
            ],
            'ramadan-creative-checklist' => [
                'title_ar' => 'قائمة فحص تصاميم ونصوص إعلانات رمضان للوكالات | AdsRanger',
                'title_en' => 'Ramadan Ad Creative & Arabic Copy Checklist | AdsRanger',
                'meta_ar' => 'قائمة مراجعة لتصاميم ونصوص إعلانات رمضان بالعربية والإنجليزية: المقاسات، اتجاه النص RTL، ومهل مراجعة الإعلانات في المنصات.',
                'meta_en' => 'Pre-launch checklist for bilingual Ramadan creatives: aspect ratios, Arabic RTL typography, and platform ad review lead times.',
                'summary_ar' => 'خطوات فحص التصاميم والنصوص الإعلانية العربية والإنجليزية قبل إرسالها لاعتماد العميل ورفعها على المنصات الإعلانية.',
                'summary_en' => 'Checklist for reviewing Arabic and English creative assets, landing page alignment, and approval lead times before Ramadan.',
                'sections_ar' => [
                    ['h' => '1. سلامة الخط العربي واتجاه الأرقام والعملات', 'p' => 'تأكد من عدم انعكاس الكلمات اللاتينية أو رموز العملات داخل النص العربي، والتزم بنوع أرقام موحد وواضح.'],
                    ['h' => '2. إرسال التصاميم للمراجعة قبل بداية الموسم بوقت كافٍ', 'p' => 'احصل على اعتماد العميل النهائي وارفع الإعلانات قبل موعد الانطلاق لتفادي أي تأخير في المراجعة الآلية للمنصات.'],
                ],
                'sections_en' => [
                    ['h' => '1. Check Arabic RTL rendering and mixed-script labels', 'p' => 'Ensure brand names, ISO currency codes, and numbers render in the right visual order across mobile placements.'],
                    ['h' => '2. Submit creatives early to clear platform review queues', 'p' => 'Collect client sign-off and upload assets ahead of seasonal launch dates so campaigns start on schedule.'],
                ],
                'related_links' => ['/ramadan-2027', '/studio', '/tools/serp-preview'],
            ],
            'ramadan-client-report' => [
                'title_ar' => 'ماذا يتضمن تقرير حملات رمضان للعميل؟ | AdsRanger',
                'title_en' => 'What to Include in a Client Ramadan Campaign Report | AdsRanger',
                'meta_ar' => 'هيكل عملي لتقرير حملات رمضان للعملاء: ملخص الإنفاق والنتائج، مقارنة مراحل الشهر، والتوصيات لما بعد العيد.',
                'meta_en' => 'How to structure a clear client Ramadan report covering spend pacing, results by phase, and post-Eid recommendations.',
                'summary_ar' => 'كيف تقدم لعميلك تقريرًا واضحًا ومختصرًا يربط الإنفاق الإعلاني بالنتائج الفعلية خلال مراحل شهر رمضان.',
                'summary_en' => 'How to present a transparent bilingual Ramadan campaign report that connects spend pacing to business outcomes.',
                'sections_ar' => [
                    ['h' => '1. الملخص التنفيذي في أعلى التقرير', 'p' => 'ابدأ بإجمالي الإنفاق، وإجمالي النتائج المحققة، ومتوسط تكلفة النتيجة مقارنة بالمستهدف المتفق عليه مع العميل.'],
                    ['h' => '2. قراءة الأداء حسب مراحل الشهر', 'p' => 'اعرض كيف تغير الطلب وتكلفة النتيجة بين الأسبوع الأول والعشر الأواخر لتكوين مرجع حقيقي لخطط العام القادم.'],
                ],
                'sections_en' => [
                    ['h' => '1. Lead with the executive outcome summary', 'p' => 'Open with total spend, primary outcomes achieved, and average cost per result against the agreed monthly target.'],
                    ['h' => '2. Break down performance by Ramadan phase', 'p' => 'Document how conversion volume and CPR behaved across early Ramadan, mid-month, and the final ten nights.'],
                ],
                'related_links' => ['/ramadan-2027', '/demo-report', '/reports'],
            ],
            'ramadan-ads-small-shop' => [
                'title_ar' => 'كيف تخطط لإعلانات متجرك في رمضان خطوة بخطوة | AdsRanger',
                'title_en' => 'How to Plan Ramadan Ads for Your Own Shop | AdsRanger',
                'meta_ar' => 'دليل مبسط لأصحاب المتاجر والمشاريع الذين يديرون إعلاناتهم بأنفسهم لتحديد ميزانية رمضان وحساب نقطة التعادل.',
                'meta_en' => 'Step-by-step guide for shop and business owners running their own Ramadan ads: setting a realistic budget and break-even target.',
                'summary_ar' => 'خطوات عملية لأصحاب المشاريع الذين يديرون إعلاناتهم بأنفسهم لتحديد ميزانية رمضان ومتابعتها يوميًا دون تعقيد.',
                'summary_en' => 'Clear steps for business owners who run their own ads to estimate a Ramadan budget and protect profit margins.',
                'sections_ar' => [
                    ['h' => '1. احسب نقطة التعادل لمنتجك قبل تحديد الميزانية', 'p' => 'استخدم حاسبة العائد على الإنفاق الإعلاني (ROAS) لمعرفة الحد الأدنى من المبيعات اللازم لتغطية تكلفة البضاعة والإعلان (مثلًا عند ميزانية 1,000 USD).'],
                    ['h' => '2. ركّز على المنتجات الأكثر طلبًا وجاهزية للتوصيل', 'p' => 'بدل توزيع ميزانية محدودة على عشرات المنتجات، ركّز الإعلان على العرض الرئيسي وتأكد من وضوح السعر وطريقة الطلب في صفحة الهبوط.'],
                ],
                'sections_en' => [
                    ['h' => '1. Calculate your break-even ROAS before setting spend', 'p' => 'Use the ROAS calculator with your product margin so you know the exact return needed on a 1,000 USD budget.'],
                    ['h' => '2. Focus spend on your best-selling offer and clear landing page', 'p' => 'Concentrate your daily budget on in-stock items with clear pricing and fast checkout or inquiry options.'],
                ],
                'related_links' => ['/solutions/business-owners', '/tools/ramadan-budget-calculator', '/tools/roas-calculator'],
            ],
        ];
    }

    /**
     * B-10 Evergreen Pillar Guides (/guides and /guides/{slug})
     *
     * @return array<string, array{title_ar: string, title_en: string, meta_ar: string, meta_en: string, summary_ar: string, summary_en: string, sections_ar: list<array{h: string, p: string}>, sections_en: list<array{h: string, p: string}>, product_link: string, related_links: list<string>}>
     */
    public static function guides(): array
    {
        return [
            'meta-ads-mena' => [
                'title_ar' => 'دليل إعلانات Meta (فيسبوك وإنستغرام) في الخليج والمنطقة | AdsRanger',
                'title_en' => 'Meta Ads (Facebook & Instagram) Guide for MENA Agencies | AdsRanger',
                'meta_ar' => 'دليل شامل لوكالات الإعلان لإدارة حملات Meta في أسواق الخليج والمنطقة: هيكلة الحسابات، صياغة الإعلانات العربية، وحملات الرسائل والعملاء المحتملين.',
                'meta_en' => 'Comprehensive agency guide to managing Meta Ads across Gulf and MENA markets: account structure, Arabic copy, messaging ads, and lead forms.',
                'summary_ar' => 'إرشادات عملية لوكالات التسويق لإدارة حملات فيسبوك وإنستغرام في أسواق الخليج والمنطقة، من كتابة النصوص العربية إلى مراقبة التشبع الإعلاني.',
                'summary_en' => 'Actionable framework for agencies running Facebook and Instagram campaigns in MENA—covering Arabic copy, lead quality, and creative fatigue.',
                'sections_ar' => [
                    ['h' => '1. هيكلة الحملات حسب هدف العمل الفعلي', 'p' => 'افصل بوضوح بين حملات المبيعات المباشرة، وحملات رسائل واتساب، وحملات نماذج العملاء المحتملين حتى تظل تكلفة النتيجة قابلة للمقارنة في تقارير العميل.'],
                    ['h' => '2. صياغة النصوص الإعلانية العربية ومراعاة اللهجات', 'p' => 'اختر بين العربية الفصحى الواضحة أو اللهجة المحلية المناسبة للسوق المستهدف، وتأكد من عدم انعكاس الأرقام أو الأسماء اللاتينية في العناوين.'],
                    ['h' => '3. مراقبة التكرار (Frequency) وتشبع الجمهور', 'p' => 'في الأسواق المحدودة جغرافيًا يرتفع معدل تكرار ظهور الإعلان بسرعة؛ راقب ارتفاع تكلفة النتيجة بالتوازي مع التكرار لتجديد التصاميم في الوقت المناسب.'],
                ],
                'sections_en' => [
                    ['h' => '1. Structure campaigns around primary client outcomes', 'p' => 'Keep purchase conversions, click-to-message campaigns, and instant lead forms clearly separated so cost per result stays accurate in client reporting.'],
                    ['h' => '2. Write natural Arabic ad copy and verify RTL rendering', 'p' => 'Match Modern Standard Arabic or regional dialect tone to the client market, and test mixed Arabic-Latin headlines on mobile placements.'],
                    ['h' => '3. Track frequency and audience saturation early', 'p' => 'In focused city or country targets, frequency climbs quickly—pair frequency monitoring with CPR trends to rotate creatives before performance drops.'],
                ],
                'product_link' => '/integrations/meta',
                'related_links' => ['/integrations', '/studio', '/tools/roas-calculator'],
            ],
            'google-ads-for-agencies' => [
                'title_ar' => 'دليل إدارة إعلانات جوجل للوكالات في المنطقة | AdsRanger',
                'title_en' => 'Google Ads Guide for MENA Marketing Agencies | AdsRanger',
                'meta_ar' => 'كيف تدير وكالات التسويق حملات بحث جوجل وPerformance Max بالعربية والإنجليزية: أنواع المطابقة، الكلمات السلبية، وجاهزية صفحات الهبوط.',
                'meta_en' => 'How MENA agencies structure Arabic and English Google Search and Performance Max campaigns: match types, negatives, and landing page readiness.',
                'summary_ar' => 'منهجية عملية لإدارة حملات بحث جوجل وPerformance Max للعملاء باللغتين العربية والإنجليزية، مع تغطية اختلافات الهمزات والكلمات السلبية.',
                'summary_en' => 'Practical blueprint for running bilingual Google Search and Performance Max accounts—covering Arabic spelling variants, negative lists, and landing page checks.',
                'sections_ar' => [
                    ['h' => '1. تغطية صيغ الكتابة العربية في مجموعات الإعلانات', 'p' => 'استخدم مولد الكلمات المفتاحية العربية لرصد صيغ الهمزات والتاء المربوطة ومرادفات اللهجات التي يكتبها الباحثون فعليًا في محرك البحث.'],
                    ['h' => '2. بناء قوائم الكلمات السلبية المشتركة للوكالة', 'p' => 'حدّث قوائم الكلمات السلبية بانتظام لاستبعاد استعلامات الوظائف أو البحث المجاني غير التجاري وحماية ميزانية العميل.'],
                    ['h' => '3. رفع نقاط الجودة عبر فحص صفحات الهبوط العربية', 'p' => 'تأكد من تطابق الكلمة المفتاحية مع العنوان الرئيسي (H1) وعنوان الصفحة (Title) وسلامة اتجاه الصفحة RTL قبل تفعيل الحملة.'],
                ],
                'sections_en' => [
                    ['h' => '1. Account for Arabic spelling and dialect variations', 'p' => 'Use Arabic keyword expansion to cover hamza omissions, ta-marbuta variations, and regional terms alongside English queries.'],
                    ['h' => '2. Maintain disciplined negative keyword lists', 'p' => 'Filter out non-commercial queries, job seekers, and unrelated Arabizi terms before they drain daily search budgets.'],
                    ['h' => '3. Audit Arabic landing pages to lift Quality Score', 'p' => 'Verify title pixel widths, H1 alignment, and clean dir="rtl" markup using the Arabic page SEO audit tool.'],
                ],
                'product_link' => '/search',
                'related_links' => ['/integrations/google-ads', '/tools/arabic-keyword-generator', '/tools/page-audit'],
            ],
            'snapchat-tiktok-ads-gulf' => [
                'title_ar' => 'دليل إعلانات سناب شات وتيك توك في السعودية والخليج | AdsRanger',
                'title_en' => 'Snapchat & TikTok Ads Guide for Gulf Campaigns | AdsRanger',
                'meta_ar' => 'إرشادات لوكالات الإعلان لإدارة حملات الفيديو العمودي على سناب شات وتيك توك في أسواق الخليج: المقاسات، الرسائل التسويقية، ومتابعة الميزانيات.',
                'meta_en' => 'Agency guide to running vertical video campaigns on Snapchat and TikTok in Gulf markets: creative specs, hooks, and budget pacing.',
                'summary_ar' => 'كيف تخطط وتدير حملات الفيديو العمودي على منصتي سناب شات وتيك توك لعملاء التجارة الإلكترونية والخدمات في أسواق الخليج.',
                'summary_en' => 'How agencies plan, test, and pace vertical video campaigns across Snapchat and TikTok for Gulf e-commerce and service brands.',
                'sections_ar' => [
                    ['h' => '1. تصميم الفيديو العمودي الكامل منذ الثواني الأولى', 'p' => 'اعتمد مقاس 9:16 الكامل مع نص عربي واضح في المنطقة الآمنة بعيدًا عن أزرار التفاعل، واذكر العرض الأساسي خلال أول ثانيتين.'],
                    ['h' => '2. موازنة الميزانيات خلال مواسم الذروة الخليجية', 'p' => 'خلال مواسم مثل رمضان والجمعة البيضاء واليوم الوطني، راقب وتيرة الصرف اليومية لتفادي استهلاك الميزانية في ساعات الذروة الأولى فقط.'],
                    ['h' => '3. توحيد قراءة التحويلات مع باقي المنصات', 'p' => 'استخدم معاملات UTM قياسية لكل حملة ومجموعة إعلانية لمقارنة أداء الفيديو القصير مع حملات البحث والمنصات الأخرى.'],
                ],
                'sections_en' => [
                    ['h' => '1. Build native 9:16 creatives with safe-zone Arabic captions', 'p' => 'Keep Arabic text overlays inside platform safe zones and lead with the core offer in the opening seconds.'],
                    ['h' => '2. Pace budgets carefully during Gulf retail peaks', 'p' => 'Monitor daily spend velocity during Ramadan, Eid, and White Friday so budgets pace evenly through the month.'],
                    ['h' => '3. Standardize UTM attribution across video channels', 'p' => 'Tag every Snapchat and TikTok ad with consistent UTM parameters so results reconcile cleanly in client reports.'],
                ],
                'product_link' => '/integrations',
                'related_links' => ['/integrations/snapchat', '/integrations/tiktok', '/tools/utm-builder'],
            ],
            'client-ad-reporting' => [
                'title_ar' => 'دليل إعداد تقارير الحملات الإعلانية للعملاء | AdsRanger',
                'title_en' => 'Client Ad Reporting Guide for Agencies | AdsRanger',
                'meta_ar' => 'كيف تبني تقرير حملات إعلانية شهري واضح لعملاء الوكالة يجمع نتائج المنصات الإعلانية وتحليلات الموقع (GA4) بلغة يفهمها صاحب القرار.',
                'meta_en' => 'How to structure clear monthly client ad reports combining cross-platform ad results and web analytics (GA4) for decision-makers.',
                'summary_ar' => 'منهجية عملية لتحويل أرقام المنصات الإعلانية وتحليلات الموقع إلى تقرير شهري واضح يربط الإنفاق بالنتائج الفعلية للعميل.',
                'summary_en' => 'A practical standard for turning multi-platform ad metrics and web analytics into a concise, decision-ready client report.',
                'sections_ar' => [
                    ['h' => '1. ابدأ بالنتائج التجارية قبل التفاصيل التقنية', 'p' => 'يحتاج العميل في الصفحة الأولى إلى رؤية إجمالي الإنفاق، والنتائج المحققة حسب الهدف، ومتوسط تكلفة النتيجة مقارنة بالمستهدف الشهري.'],
                    ['h' => '2. توضيح الفروق بين أرقام المنصات الإعلانية وبيانات تحليلات الموقع (GA4)', 'p' => 'اشرح للعميل سبب اختلاف أرقام التحويل بين المنصات الإعلانية وتقارير تحليلات الموقع نتيجة اختلاف نوافذ الإحالة ومعاملات التتبع (UTM).'],
                    ['h' => '3. تقديم خطة واضحة للشهر القادم', 'p' => 'اختم التقرير دائمًا بثلاث توصيات محددة للشهر التالي تشمل توزيع الميزانية والتصاميم المطلوب اختبارها.'],
                ],
                'sections_en' => [
                    ['h' => '1. Lead with business outcomes before channel breakdowns', 'p' => 'Open every report with total spend, primary conversions achieved, and cost per result against the agreed monthly goal.'],
                    ['h' => '2. Reconcile ad platform conversions with web analytics (GA4)', 'p' => 'Explain how attribution windows work alongside UTM-tagged sessions in GA4 so clients understand why platform and analytics totals differ.'],
                    ['h' => '3. Close with concrete next-month actions', 'p' => 'End with three clear priorities covering budget shifts, creative refreshes, and landing page improvements.'],
                ],
                'product_link' => '/reports',
                'related_links' => ['/demo-report', '/tools/utm-builder', '/glossary/roas'],
            ],
            'ad-budget-pacing' => [
                'title_ar' => 'دليل إدارة وتيرة إنفاق الإعلانات الشهرية للوكالات | AdsRanger',
                'title_en' => 'Ad Budget Pacing Guide for Marketing Agencies | AdsRanger',
                'meta_ar' => 'كيف تتابع وتيرة إنفاق حسابات العملاء يوميًا وتوزع الميزانيات بين المنصات وتتجنب نفاد الميزانية المبكر أو نقص الصرف في نهاية الشهر.',
                'meta_en' => 'How agencies monitor daily ad budget pacing across clients, split spend across platforms, and prevent overspend or under-delivery.',
                'summary_ar' => 'قواعد عملية لضبط وتيرة الصرف اليومي عبر حسابات العملاء وتوقع نهاية الشهر قبل فوات الأوان.',
                'summary_en' => 'Operational rules for tracking daily spend velocity, forecasting month-end totals, and keeping every client account on budget.',
                'sections_ar' => [
                    ['h' => '1. حساب وتيرة الصرف اليومية مقارنة بالأيام المنقضية', 'p' => 'راقب نسبة الصرف الفعلي إلى الصرف المخطط له حتى اليوم؛ أي انحراف يتجاوز 10% يستدعي مراجعة حدود الميزانية اليومية للحملات.'],
                    ['h' => '2. التعامل مع المواسم ذات الذروة غير المتساوية', 'p' => 'في مواسم مثل رمضان أو العروض الكبرى، خطط لتوزيع متدرج للميزانية بدل القسمة المتساوية على أيام الشهر.'],
                    ['h' => '3. استخدام حاسبة وتيرة الإنفاق لتحديد الصرف اليومي المتبقي', 'p' => 'احسب المبلغ اليومي المطلوب للأيام المتبقية من الشهر بدقة لتجنب التعديلات العشوائية في الأيام الأخيرة.'],
                ],
                'sections_en' => [
                    ['h' => '1. Track spend-to-date against elapsed calendar time', 'p' => 'Check pacing percentages twice a week; when an account drifts beyond 10% above or below plan, adjust daily caps early.'],
                    ['h' => '2. Plan weighted pacing curves for seasonal peaks', 'p' => 'During Ramadan or major retail events, weight daily targets around peak conversion days rather than flat daily averages.'],
                    ['h' => '3. Calculate exact remaining daily spend targets', 'p' => 'Use the budget pacing calculator to compute the exact daily spend needed for the remaining days of the month.'],
                ],
                'product_link' => '/reports',
                'related_links' => ['/tools/budget-pacing-calculator', '/tools/ramadan-budget-calculator', '/glossary/pacing'],
            ],
        ];
    }

    /**
     * B-12 Solutions, Role & Roadmap pages (/solutions/agencies, /solutions/freelancers, /for-clients, /for-teams, /roadmap)
     *
     * @return array<string, array{ar: array{nav: string, title: string, meta: string, h1: string, lead: string, cards: list<array{h: string, p: string, link: string, link_label: string}>}, en: array{nav: string, title: string, meta: string, h1: string, lead: string, cards: list<array{h: string, p: string, link: string, link_label: string}>}}>
     */
    public static function solutionsAndRoles(): array
    {
        return [
            'solutions/agencies' => [
                'ar' => [
                    'nav' => 'للوكالات',
                    'title' => 'حلول إدارة الإعلانات والتقارير لوكالات التسويق | AdsRanger',
                    'meta' => 'صمّمت AdsRanger لوكالات التسويق في المنطقة لإدارة حسابات العملاء، مراقبة وتيرة الميزانيات، وإصدار تقارير موحدة بالعربية والإنجليزية.',
                    'h1' => 'مساحة عمل واحدة لإدارة حسابات عملاء وكالتك وتقاريرهم',
                    'lead' => 'بدل التنقل بين عشرات الحسابات الإعلانية والجداول اليدوية، تبني <bdi dir="ltr">AdsRanger</bdi> مكتب عمليات يومي للوكالات في المنطقة. قيد التطوير · الإطلاق في 2027.',
                    'cards' => [
                        ['h' => 'متابعة يومية لميزانيات جميع العملاء', 'p' => 'شاهد وتيرة الإنفاق والتنبيهات الصباحية لكل عميل في شاشة واحدة قبل أن تتأثر الحملة.', 'link' => '/features', 'link_label' => 'استعرض ميزات المنصة'],
                        ['h' => 'تقارير عملاء جاهزة للمشاركة', 'p' => 'روابط تقارير قراءة فقط وملفات PDF باللغتين العربية والإنجليزية تعكس هوية وكالتك.', 'link' => '/demo-report', 'link_label' => 'شاهد التقرير التجريبي'],
                        ['h' => 'صلاحيات دقيقة لأعضاء الفريق', 'p' => 'حدّد ما يراه كل عضو في الوكالة حسب دوره والعملاء المسندين إليه.', 'link' => '/for-teams', 'link_label' => 'تعرّف على أدوار الفريق'],
                    ],
                ],
                'en' => [
                    'nav' => 'For agencies',
                    'title' => 'Ad Operations & Client Reporting for Marketing Agencies | AdsRanger',
                    'meta' => 'AdsRanger is built for MENA marketing agencies to monitor client budgets, catch pacing issues early, and share bilingual client reports.',
                    'h1' => 'One daily operations desk for every client your agency manages',
                    'lead' => 'Replace scattered tabs and manual end-of-month spreadsheets with a unified bilingual workspace built for agencies. In development · launching in 2027.',
                    'cards' => [
                        ['h' => 'Morning budget pacing & alerts', 'p' => 'Spot overspend, stalled delivery, and CPR spikes across all client accounts in one view.', 'link' => '/en/features', 'link_label' => 'Explore platform features'],
                        ['h' => 'Client-ready bilingual reports', 'p' => 'Share read-only report links and monthly PDF summaries in Arabic and English.', 'link' => '/en/demo-report', 'link_label' => 'See sample report'],
                        ['h' => 'Role-based team access', 'p' => 'Assign specific clients and permissions across account managers and analysts.', 'link' => '/en/for-teams', 'link_label' => 'See team workflows'],
                    ],
                ],
            ],
            'solutions/freelancers' => [
                'ar' => [
                    'nav' => 'للمستقلين',
                    'title' => 'إدارة حملات العملاء وتقاريرهم للمستقلين | AdsRanger',
                    'meta' => 'أدوات عملية للمسوقين المستقلين ومديري الحملات لإدارة ميزانيات العملاء وإصدار تقارير احترافية بالعربية والإنجليزية.',
                    'h1' => 'أدِر حسابات عملائك كمحترف وقدّم تقارير واضحة في دقائق',
                    'lead' => 'تخطط <bdi dir="ltr">AdsRanger</bdi> لتقديم باقة المستقل (مخطط لها) لتمنحك متابعة دقيقة للميزانيات وتقارير عملاء احترافية دون تعقيد. قيد التطوير · الإطلاق في 2027.',
                    'cards' => [
                        ['h' => 'باقة مصمَّمة للمسوق المستقل', 'p' => 'مقعد واحد يغطي حتى 5 عملاء و3 حسابات إعلانية مع تنبيهات الميزانية الأساسية (مخطط لها).', 'link' => '/pricing', 'link_label' => 'راجع مقارنة الباقات'],
                        ['h' => 'أدوات مجانية جاهزة للاستخدام اليوم', 'p' => 'استخدم حاسبات العائد ووتيرة الإنفاق وفاحص السيو العربي مجانًا أثناء التخطيط لحملات عملائك.', 'link' => '/tools', 'link_label' => 'افتح الأدوات المجانية'],
                        ['h' => 'رابط تقرير مباشر لكل عميل', 'p' => 'وفّر ساعات إعداد التقارير الأسبوعية عبر رابط قراءة فقط يعرض الأرقام بوضوح.', 'link' => '/demo-report', 'link_label' => 'شاهد نموذج التقرير'],
                    ],
                ],
                'en' => [
                    'nav' => 'For freelancers',
                    'title' => 'Client Ad Tracking & Reporting for Freelancers | AdsRanger',
                    'meta' => 'Built for independent media buyers and freelance marketers managing multiple client accounts in Arabic and English.',
                    'h1' => 'Keep every freelance client on budget without spreadsheet busywork',
                    'lead' => 'AdsRanger plans a dedicated Solo tier so independent marketers can track client pacing, catch alerts, and share polished bilingual links. In development · launching in 2027.',
                    'cards' => [
                        ['h' => 'Planned Solo tier for independents', 'p' => '1 seat covering up to 5 clients and 3 ad accounts with core pacing and alert rules.', 'link' => '/en/pricing', 'link_label' => 'Compare planned tiers'],
                        ['h' => '10 free tools available right now', 'p' => 'Use our Arabic SEO audit, keyword generator, and pacing calculators today with no sign-up.', 'link' => '/en/tools', 'link_label' => 'Browse free tools'],
                        ['h' => 'Shareable read-only client links', 'p' => 'Send clients a clean bilingual summary link instead of manually formatting weekly decks.', 'link' => '/en/demo-report', 'link_label' => 'View sample report'],
                    ],
                ],
            ],
            'for-clients' => [
                'ar' => [
                    'nav' => 'لعملاء الوكالات',
                    'title' => 'كيف تعمل روابط مشاركة الأداء لعملاء الوكالات | AdsRanger',
                    'meta' => 'تعرّف على طريقة عمل روابط المشاهدة المخصصة لعملاء الوكالات في AdsRanger: عرض قراءة فقط، دون تسجيل دخول، وباللغتين العربية والإنجليزية.',
                    'h1' => 'متابعة واضحة لما تحققه ميزانيتك الإعلانية مع وكالتك',
                    'lead' => 'عندما تشاركك وكالتك رابطًا من <bdi dir="ltr">AdsRanger</bdi>، يمكنك الاطلاع على ملخص الأداء بلغة عربية أو إنجليزية واضحة دون الحاجة لإنشاء حساب أو كلمة مرور. قيد التطوير · الإطلاق في 2027.',
                    'cards' => [
                        ['h' => 'عرض قراءة فقط ودون تسجيل دخول', 'p' => 'يعرض الرابط الملخص المعتمد لحسابك فقط، ولا يطلب منك ربط أي حساب أو إدخال بيانات دخول.', 'link' => '/demo-report', 'link_label' => 'شاهد مثالًا حيًا'],
                        ['h' => 'حماية وخصوصية بياناتك', 'p' => 'يمكن للوكالة إيقاف الرابط أو تقييده بكلمة مرور أو تاريخ انتهاء في أي وقت لضمان سرية أرقامك.', 'link' => '/security', 'link_label' => 'كيف نحمي البيانات'],
                        ['h' => 'أرقام واضحة حسب هدف نشاطك', 'p' => 'يركّز العرض على النتائج التي تهم نشاطك التجاري ومؤشر صحة الحملة بدل الجداول المعقدة.', 'link' => '/features', 'link_label' => 'تعرّف على المنصة'],
                    ],
                ],
                'en' => [
                    'nav' => 'For agency clients',
                    'title' => 'How Shared Agency Performance Links Work for Clients | AdsRanger',
                    'meta' => 'Learn how AdsRanger read-only links let agency clients view campaign progress in Arabic or English without creating an account.',
                    'h1' => 'A clear view of what your marketing budget achieves with your agency',
                    'lead' => 'When your agency shares an AdsRanger link with you, you can view approved campaign outcomes in Arabic or English—read-only and with no login required. In development · launching in 2027.',
                    'cards' => [
                        ['h' => 'Read-only access with zero sign-up', 'p' => 'Open your agency\'s signed link on mobile or desktop without managing passwords or platform logins.', 'link' => '/en/demo-report', 'link_label' => 'See a sample view'],
                        ['h' => 'Controlled and revocable by your agency', 'p' => 'Links can be password-protected, given an expiry date, or revoked at any time.', 'link' => '/en/security', 'link_label' => 'Security & data protection'],
                        ['h' => 'Focused on real business outcomes', 'p' => 'See spend pacing, primary outcomes, and plain-language summaries tailored to your goals.', 'link' => '/en/features', 'link_label' => 'Platform overview'],
                    ],
                ],
            ],
            'for-teams' => [
                'ar' => [
                    'nav' => 'لفرق العمل',
                    'title' => 'تنظيم صلاحيات وأدوار فريق الوكالة الإعلانية | AdsRanger',
                    'meta' => 'صمّمت AdsRanger صلاحيات واضحة لأعضاء فريق الوكالة: المالك، المدير، مدير علاقات العملاء، والمحلل، مع سجل تدقيق للتغييرات.',
                    'h1' => 'أدوار وصلاحيات واضحة لكل عضو في فريق وكالتك',
                    'lead' => 'تمنح <bdi dir="ltr">AdsRanger</bdi> كل عضو في الفريق الصلاحيات التي يحتاجها فقط، مع إمكانية تخصيص العملاء المرئيين لكل عضو ومعاينة الواجهة بصفته. قيد التطوير · الإطلاق في 2027.',
                    'cards' => [
                        ['h' => 'أربعة أدوار قياسية للوكالات', 'p' => 'تشمل الأدوار: مالك مساحة العمل، المدير، مدير علاقات العملاء (إدارة العملاء والحملات والتقارير)، والمحلل (عرض فقط).', 'link' => '/security', 'link_label' => 'الأمان وسجل التدقيق'],
                        ['h' => 'تخصيص العملاء لكل عضو', 'p' => 'قصر رؤية الحسابات والتقارير على العملاء المسندين لمدير علاقات العملاء أو المحلل المعني.', 'link' => '/solutions/agencies', 'link_label' => 'حلول الوكالات'],
                        ['h' => 'المعاينة بصفة عضو آخر (View As)', 'p' => 'يمكن لمالك الوكالة أو المدير معاينة مساحة العمل بوضع القراءة فقط للتأكد مما يراه كل عضو.', 'link' => '/features', 'link_label' => 'كل ميزات المنصة'],
                    ],
                ],
                'en' => [
                    'nav' => 'For agency teams',
                    'title' => 'Agency Team Roles, Permissions & Client Scopes | AdsRanger',
                    'meta' => 'Manage workspace permissions across Owners, Admins, Account Managers, and Analysts with per-client visibility and audit logs.',
                    'h1' => 'Clear roles and per-client permissions for your agency team',
                    'lead' => 'AdsRanger scopes workspace access by role and assigned clients, complete with an audit log and read-only role previewing. In development · launching in 2027.',
                    'cards' => [
                        ['h' => 'Four purpose-built agency roles', 'p' => 'Owner, Admin (everything except billing), Account Manager (clients, ads, and reports), and Analyst (view only).', 'link' => '/en/security', 'link_label' => 'Security & audit log'],
                        ['h' => 'Per-client visibility assignment', 'p' => 'Restrict team members to specific client portfolios so sensitive accounts stay compartmentalized.', 'link' => '/en/solutions/agencies', 'link_label' => 'Solutions for agencies'],
                        ['h' => 'Read-only "View As" preview mode', 'p' => 'Workspace owners and admins can preview the exact navigation and client list any teammate sees.', 'link' => '/en/features', 'link_label' => 'All platform features'],
                    ],
                ],
            ],
            'roadmap' => [
                'ar' => [
                    'nav' => 'خارطة الطريق',
                    'title' => 'خارطة طريق بناء منصة AdsRanger | AdsRanger',
                    'meta' => 'اطّلع على ما بنيناه في AdsRanger وما هو قيد التطوير أو مخطط له للإطلاق في 2027 لوكالات التسويق في المنطقة.',
                    'h1' => 'خارطة الطريق: ما هو متاح الآن وما نبنيه للإطلاق في 2027',
                    'lead' => 'نشارك هنا حالة وحدات <bdi dir="ltr">AdsRanger</bdi> بشفافية: الأدوات المجانية المتاحة اليوم، والميزات الأساسية قيد التطوير، والتكاملات المخطط لها للإطلاق في 2027.',
                    'cards' => [
                        ['h' => 'متاح الآن مجانًا على الموقع', 'p' => '10 أدوات مجانية للسيو العربي وحاسبات الإعلانات، وتقويم مواسم 2027، ودليل حملات رمضان، والتقرير التجريبي.', 'link' => '/tools', 'link_label' => 'جرّب الأدوات المجانية'],
                        ['h' => 'قيد التطوير (مساحة عمل الوكالة)', 'p' => 'متابعة وتيرة الميزانيات، قواعد التنبيه الصباحية، روابط تقارير العملاء (قراءة فقط + PDF)، وفحص السيو العربي داخل المنصة.', 'link' => '/features', 'link_label' => 'استعرض الميزات'],
                        ['h' => 'مخطط له للإطلاق في 2027', 'p' => 'ربط مباشر لمنصات المرحلة الأولى الإعلانية، استوديو اعتماد الإعلانات، وتنبيهات وتقارير إضافية.', 'link' => '/integrations', 'link_label' => 'المنصات المخطط لربطها'],
                    ],
                ],
                'en' => [
                    'nav' => 'Roadmap',
                    'title' => 'AdsRanger Product Roadmap (2027 Launch) | AdsRanger',
                    'meta' => 'See what is live today on adsranger.com, what is in active development inside the workspace, and what is planned for launch in 2027.',
                    'h1' => 'Product roadmap: live free tools, in-development core, and 2027 plans',
                    'lead' => 'We label every capability transparently: free Arabic SEO and ad tools live today, core workspace modules in development, and platform integrations planned for 2027.',
                    'cards' => [
                        ['h' => 'Live today (free public tools & guides)', 'p' => '10 free Arabic SEO and ad calculators, the 2027 MENA seasons calendar, Ramadan guide, and interactive sample report.', 'link' => '/en/tools', 'link_label' => 'Open free tools'],
                        ['h' => 'In development (core agency workspace)', 'p' => 'Multi-client budget pacing, morning alert rules, signed client report links & PDFs, and the in-app Arabic SEO workspace.', 'link' => '/en/features', 'link_label' => 'Explore features'],
                        ['h' => 'Planned for 2027 launch', 'p' => 'Direct Stage-1 ad platform OAuth connections, multi-platform Ad Studio sign-off flows, and automated digests.', 'link' => '/en/integrations', 'link_label' => 'See planned integrations'],
                    ],
                ],
            ],
        ];
    }
}
