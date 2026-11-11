<?php

namespace App\Support\Seo;

/**
 * Package C (E1-034 / D-241): "How it works" text blocks and FAQPage Q&A for the 9 free SEO & ad tools.
 * Follows R-21 (AdsRanger is feminine in Arabic), UX-226 (<bdi dir="ltr">AdsRanger</bdi>), and D-229 honesty rules.
 */
class ToolCopy
{
    /**
     * @return array{how_title: string, how_steps: list<string>, faq_title: string, faqs: list<array{q: string, a: string}>}
     */
    public static function for(string $tool, bool $ar): array
    {
        $map = [
            'serp-preview' => [
                'ar' => [
                    'how_title' => 'كيف تعمل أداة معاينة نتائج البحث',
                    'how_steps' => [
                        'أدخل عنوان الصفحة والوصف التعريفي والرابط الذي تود معاينته.',
                        'بدّل بين عرض الجوال والكمبيوتر واتجاه الصفحة (عربي أو إنجليزي) لمحاكاة العرض الفعلي.',
                        'راجع عداد البكسلات والتنبيهات الفورية لتفادي قطع العنوان أو انعكاس ترتيب الكلمات اللاتينية.',
                    ],
                    'faq_title' => 'أسئلة شائعة حول معاينة نتائج البحث',
                    'faqs' => [
                        ['q' => 'لماذا تحسب الأداة العرض بالبكسل بدل عدد الأحرف فقط؟', 'a' => 'لأن محركات البحث تقطع العناوين والأوصاف بناءً على عرضها بالبكسل (نحو 580 بكسل للعنوان)، والحروف العربية المتصلة تختلف في عرضها عن الحروف اللاتينية.'],
                        ['q' => 'هل تحفظ AdsRanger العناوين أو الروابط التي أدخلها؟', 'a' => 'لا، تعمل الأداة داخل متصفحك مباشرة ولا ترسل أو تخزن أي نصوص تدخلها.'],
                    ],
                ],
                'en' => [
                    'how_title' => 'How the search results preview works',
                    'how_steps' => [
                        'Enter your page title, meta description, and display URL.',
                        'Switch between mobile and desktop views and RTL/LTR page direction.',
                        'Check the pixel counters and instant warnings to avoid truncation or mixed-direction title inversions.',
                    ],
                    'faq_title' => 'Frequently asked questions',
                    'faqs' => [
                        ['q' => 'Why does this tool measure pixels instead of character count alone?', 'a' => 'Search engines truncate titles and descriptions by pixel width (around 580px for titles), and connected Arabic script has different widths than Latin letters.'],
                        ['q' => 'Does AdsRanger store the titles or URLs I type here?', 'a' => 'No. Everything runs in your browser and nothing you enter is sent or stored.'],
                    ],
                ],
            ],
            'meta-length-checker' => [
                'ar' => [
                    'how_title' => 'كيف يعمل فاحص طول العنوان والوصف',
                    'how_steps' => [
                        'ألصق حتى 50 سطرًا من العناوين والأوصاف (مفصولة بعلامة الجدولة أو الفاصلة).',
                        'تحسب الأداة عرض كل سطر بالبكسل وعدد الأحرف فورًا داخل المتصفح.',
                        'عدّل العناوين الطويلة قبل نشرها على موقعك لتفادي قطعها في نتائج البحث.',
                    ],
                    'faq_title' => 'أسئلة شائعة حول فاحص الطول',
                    'faqs' => [
                        ['q' => 'كم صفحة يمكنني فحصها في المرة الواحدة؟', 'a' => 'يمكنك فحص حتى 50 عنوانًا ووصفًا دفعة واحدة داخل المتصفح دون تسجيل.'],
                        ['q' => 'ما الحد الموصى به لطول العنوان العربي؟', 'a' => 'يُفضّل ألا يتجاوز العنوان 580 بكسل (نحو 55 إلى 65 حرفًا عربيًا) حتى لا يُقطع في نتائج الجوال والكمبيوتر.'],
                    ],
                ],
                'en' => [
                    'how_title' => 'How the title and description length checker works',
                    'how_steps' => [
                        'Paste up to 50 rows of titles and descriptions (tab- or pipe-separated).',
                        'The tool calculates pixel width and character length for every row right in your browser.',
                        'Spot truncated lines immediately and adjust them before publishing.',
                    ],
                    'faq_title' => 'Frequently asked questions',
                    'faqs' => [
                        ['q' => 'How many rows can I check at once?', 'a' => 'You can check up to 50 titles and descriptions at a time in your browser with no sign-up.'],
                        ['q' => 'What is the recommended pixel width for Arabic titles?', 'a' => 'Keep titles under 580px (roughly 55–65 Arabic characters) so they do not get cut off on mobile or desktop results.'],
                    ],
                ],
            ],
            'arabic-keyword-generator' => [
                'ar' => [
                    'how_title' => 'كيف يعمل مولد الكلمات المفتاحية العربية',
                    'how_steps' => [
                        'أدخل الكلمة المفتاحية الأساسية بالعربية في حقل البحث.',
                        'تولّد الأداة صيغ الهمزات والهاء/التاء المربوطة، ومرادفات اللهجات، وصيغ الأسئلة الشائعة.',
                        'انسخ القائمة أو نزّلها بصيغة CSV لاستخدامها في خطة المحتوى أو حملات إعلانات البحث.',
                    ],
                    'faq_title' => 'أسئلة شائعة حول مولد الكلمات العربية',
                    'faqs' => [
                        ['q' => 'هل تعرض الأداة أحجام البحث الشهرية؟', 'a' => 'تعرض الأداة صيغ الكتابة العربية والأسئلة واللهجات لتوسيع قائمتك مجانًا، ولا تعرض أحجام بحث تقديرية غير موثقة.'],
                        ['q' => 'لماذا تهم صيغ الهمزة والتاء المربوطة في السيو العربي؟', 'a' => 'يكتب المستخدمون العرب الكلمة الواحدة بعدة طرق (مثل اعلانات وإعلانات، أو شركة وشركه)، وتغطيتها تزيد فرص ظهور صفحتك وإعلاناتك.'],
                    ],
                ],
                'en' => [
                    'how_title' => 'How the Arabic keyword generator works',
                    'how_steps' => [
                        'Enter your seed Arabic keyword.',
                        'The tool generates hamza and ta-marbuta spelling variants, regional dialect markers, Arabizi forms, and common question prefixes.',
                        'Copy the list or download a CSV for your content brief or search campaigns.',
                    ],
                    'faq_title' => 'Frequently asked questions',
                    'faqs' => [
                        ['q' => 'Does this tool show monthly search volumes?', 'a' => 'This free tool generates real Arabic orthographic variants, questions, and dialect forms—it never invents unverified search volume numbers.'],
                        ['q' => 'Why do hamza and ta-marbuta variants matter in Arabic search?', 'a' => 'Arabic searchers frequently omit hamzas or type ha instead of ta-marbuta; covering those variants improves organic and paid search coverage.'],
                    ],
                ],
            ],
            'arabizi-converter' => [
                'ar' => [
                    'how_title' => 'كيف يعمل محوّل العربيزي',
                    'how_steps' => [
                        'أدخل الكلمة المكتوبة بالعربيزي (مثل 3arabi أو 7amla) أو بالعربية.',
                        'تطبّق الأداة قواعد التحويل الصوتية للأرقام والحروف اللاتينية إلى مقابلها العربي الأكثر احتمالًا.',
                        'استخدم النتائج لفهم استعلامات البحث المختلطة أو توحيد الكلمات المفتاحية.',
                    ],
                    'faq_title' => 'أسئلة شائعة حول محوّل العربيزي',
                    'faqs' => [
                        ['q' => 'ما المقصود بالعربيزي في محركات البحث؟', 'a' => 'العربيزي هو كتابة الكلمات العربية بحروف لاتينية وأرقام (مثل 3 لحرف العين و7 لحرف الحاء)، ويستخدمه جزء من الباحثين في المنطقة.'],
                        ['q' => 'هل يتم إرسال النصوص إلى خوادم خارجية؟', 'a' => 'لا، يعمل المحوّل محليًا باستخدام طبقة تطبيع النصوص العربية الخاصة بـ AdsRanger دون حفظ أي مدخلات.'],
                    ],
                ],
                'en' => [
                    'how_title' => 'How the Arabizi converter works',
                    'how_steps' => [
                        'Type an Arabizi phrase (such as 3arabi or 7amla) or an Arabic term.',
                        'The converter maps digit substitutions (2, 3, 5, 7, 8, 9) and Latin digrams to ranked Arabic candidates.',
                        'Use the output to normalize mixed search queries and negative keyword lists.',
                    ],
                    'faq_title' => 'Frequently asked questions',
                    'faqs' => [
                        ['q' => 'What is Arabizi in MENA search marketing?', 'a' => 'Arabizi writes Arabic words with Latin letters and numbers representing Arabic consonants (e.g., 3 for Ain, 7 for Haa).'],
                        ['q' => 'Is my text stored when I use the converter?', 'a' => 'No. Conversion runs on AdsRanger\'s shared Arabic normalization rules without storing your input.'],
                    ],
                ],
            ],
            'roas-calculator' => [
                'ar' => [
                    'how_title' => 'كيف تعمل حاسبة العائد على الإنفاق الإعلاني (ROAS)',
                    'how_steps' => [
                        'أدخل إجمالي الإنفاق الإعلاني والإيرادات الناتجة عنه، مع هامش الربح الإجمالي لمنتجك.',
                        'تحسب الأداة العائد الحالي (ROAS) ونقطة التعادل (Break-even ROAS) التي تبدأ بعدها بتحقيق ربح صافي.',
                        'قارن عائد حملتك الفعلي بنقطة التعادل لمعرفة ما إذا كانت الحملة رابحة قبل زيادة الميزانية.',
                    ],
                    'faq_title' => 'أسئلة شائعة حول حاسبة ROAS',
                    'faqs' => [
                        ['q' => 'ما الفرق بين ROAS ونقطة التعادل (Break-even ROAS)؟', 'a' => 'يقيس ROAS إجمالي الإيرادات مقابل كل وحدة إنفاق إعلاني، بينما تحسب نقطة التعادل الحد الأدنى من العائد اللازم لتغطية تكلفة البضاعة والإنفاق الإعلاني معًا.'],
                        ['q' => 'هل تدعم الحاسبة عملات دول المنطقة؟', 'a' => 'العمليات الحسابية تعتمد على النسب والأرقام التي تدخلها، لذا تعمل مع أي عملة (مثل USD أو SAR أو AED أو JOD).'],
                    ],
                ],
                'en' => [
                    'how_title' => 'How the ROAS & break-even calculator works',
                    'how_steps' => [
                        'Enter your ad spend, revenue from ads, and gross profit margin.',
                        'The calculator computes your actual ROAS alongside your break-even ROAS target.',
                        'Compare actual ROAS against break-even before scaling campaign budgets.',
                    ],
                    'faq_title' => 'Frequently asked questions',
                    'faqs' => [
                        ['q' => 'What is the difference between ROAS and break-even ROAS?', 'a' => 'ROAS divides ad revenue by ad spend, while break-even ROAS uses your gross margin to show the minimum ROAS required before you lose money on each sale.'],
                        ['q' => 'Does the calculator store my client numbers?', 'a' => 'No. All calculations happen in your browser and nothing is saved.'],
                    ],
                ],
            ],
            'budget-pacing-calculator' => [
                'ar' => [
                    'how_title' => 'كيف تعمل حاسبة وتيرة الإنفاق الإعلاني',
                    'how_steps' => [
                        'أدخل الميزانية الشهرية المعتمدة، وما أُنفق حتى اليوم، وعدد الأيام المنقضية من الشهر.',
                        'تحسب الأداة نسبة وتيرة الإنفاق، والتوقع المتوقع لنهاية الشهر، والإنفاق اليومي المقترح للأيام المتبقية.',
                        'استخدم الإنفاق اليومي المقترح لضبط ميزانيات الحملات وتفادي نفاد الميزانية مبكرًا أو نقص الصرف.',
                    ],
                    'faq_title' => 'أسئلة شائعة حول وتيرة الإنفاق',
                    'faqs' => [
                        ['q' => 'متى تعتبر وتيرة الإنفاق الإعلاني صحية؟', 'a' => 'تكون الوتيرة متوازنة عندما تقترب نسبة الصرف الفعلي من نسبة الأيام المنقضية من الشهر (بين 90% و110% في الحملات المستمرة).'],
                        ['q' => 'كيف تضبط AdsRanger وتيرة الإنفاق للوكالات؟', 'a' => 'تبني AdsRanger لوحة وتيرة إنفاق وتنبيهات تلقائية لكل عميل ضمن المنصة (قيد التطوير · الإطلاق في 2027).'],
                    ],
                ],
                'en' => [
                    'how_title' => 'How the budget pacing calculator works',
                    'how_steps' => [
                        'Enter the monthly budget, spend to date, and days elapsed in the period.',
                        'The tool calculates your pacing ratio, projected month-end spend, and recommended daily spend for the remaining days.',
                        'Adjust daily campaign caps using the recommended daily figure to finish on budget.',
                    ],
                    'faq_title' => 'Frequently asked questions',
                    'faqs' => [
                        ['q' => 'What is a healthy budget pacing percentage?', 'a' => 'For steady monthly campaigns, pacing between 90% and 110% of the time-elapsed target keeps spend on track without sudden end-of-month spikes.'],
                        ['q' => 'Does AdsRanger automate budget pacing for agencies?', 'a' => 'AdsRanger is building automated multi-client pacing monitors and morning alerts for agencies (in development, launching in 2027).'],
                    ],
                ],
            ],
            'utm-builder' => [
                'ar' => [
                    'how_title' => 'كيف يعمل منشئ روابط التتبّع (UTM)',
                    'how_steps' => [
                        'أدخل رابط صفحة الهبوط الأساسي واختر المنصة الإعلانية أو اكتب معاملات التتبع يدويًا.',
                        'تولّد الأداة الرابط الكامل مع معاملات utm_source وutm_medium وutm_campaign بشكل قياسي.',
                        'انسخ الرابط النهائي واستخدمه في إعلاناتك لتظهر المصادر بدقة في تقارير التحليلات.',
                    ],
                    'faq_title' => 'أسئلة شائعة حول روابط UTM',
                    'faqs' => [
                        ['q' => 'لماذا يفضّل استخدام حروف لاتينية صغيرة في معاملات UTM؟', 'a' => 'لأن أدوات التحليلات تعامل الحروف الكبيرة والصغيرة كقيم منفصلة، كما أن الروابط اللاتينية القصيرة أسهل قراءة وأقل عرضة للترميز الطويل.'],
                        ['q' => 'هل تغيّر معاملات UTM محتوى صفحة الهبوط؟', 'a' => 'لا، تُستخدم معاملات UTM لتسجيل مصدر الزيارة فقط ولا تغيّر محتوى الصفحة في أغلب المواقع.'],
                    ],
                ],
                'en' => [
                    'how_title' => 'How the UTM link builder works',
                    'how_steps' => [
                        'Enter your destination landing page URL and pick a platform preset or fill in UTM parameters.',
                        'The tool builds a clean, properly encoded URL with utm_source, utm_medium, and utm_campaign.',
                        'Copy the tracked link into your ad sets so analytics reports attribute visits accurately.',
                    ],
                    'faq_title' => 'Frequently asked questions',
                    'faqs' => [
                        ['q' => 'Why should UTM tags use consistent lowercase naming?', 'a' => 'Analytics tools treat "Meta" and "meta" as two separate sources; lowercase presets keep campaign attribution clean across your team.'],
                        ['q' => 'Is anything I enter saved on your servers?', 'a' => 'No. Links are assembled in your browser and never stored.'],
                    ],
                ],
            ],
            'hreflang-checker' => [
                'ar' => [
                    'how_title' => 'كيف يعمل فاحص روابط اللغات (Hreflang)',
                    'how_steps' => [
                        'أدخل رابط الصفحة التي تحتوي على نسخ بلغات متعددة (مثل العربية والإنجليزية).',
                        'تفحص الأداة ترويسة الصفحة وروابط hreflang المتبادلة والرابط الأساسي (canonical) واتجاه اللغة العربية (dir="rtl").',
                        'راجع تقرير الفحص للتأكد من أن كل نسخة تشير إلى نفسها وإلى النسخ الأخرى بشكل متبادل.',
                    ],
                    'faq_title' => 'أسئلة شائعة حول فحص Hreflang',
                    'faqs' => [
                        ['q' => 'ما أهمية الرابط المتبادل (Return link) في Hreflang؟', 'a' => 'إذا أشارت الصفحة العربية إلى النسخة الإنجليزية دون أن تشير النسخة الإنجليزية إليها بالمقابل، قد تتجاهل محركات البحث وسم اللغة بالكامل.'],
                        ['q' => 'كم عملية فحص متاحة يوميًا؟', 'a' => 'تتيح الأداة المجانية حتى 5 عمليات فحص يوميًا لكل زائر دون تسجيل، ولا تخزّن عناوين المواقع المفحوصة.'],
                    ],
                ],
                'en' => [
                    'how_title' => 'How the hreflang checker works',
                    'how_steps' => [
                        'Enter the full URL of a bilingual or multi-regional page.',
                        'Our SSRF-safe fetcher inspects status codes, language-region tags, self-references, x-default, canonical alignment, and Arabic dir="rtl" attributes.',
                        'Verify that every alternate page links back reciprocally so search engines honour your language versions.',
                    ],
                    'faq_title' => 'Frequently asked questions',
                    'faqs' => [
                        ['q' => 'Why do reciprocal return links matter for hreflang?', 'a' => 'If Page A declares Page B as its alternate but Page B does not link back to Page A, search engines may ignore the hreflang cluster.'],
                        ['q' => 'What is the daily limit on the free checker?', 'a' => 'You can run up to 5 live checks per day (10 per hour) per visitor without signing in, and nothing you check is stored.'],
                    ],
                ],
            ],
            'page-audit' => [
                'ar' => [
                    'how_title' => 'كيف يعمل فاحص السيو العربي للصفحة',
                    'how_steps' => [
                        'أدخل رابط الصفحة والكلمة المفتاحية العربية المستهدفة (اختياريًا).',
                        'يفحص المحرك 14 قاعدة مخصصة للسيو العربي تشمل عرض العنوان والوصف بالبكسل، وسم lang="ar" وdir="rtl"، وتطابق الكلمة المفتاحية بعد تطبيع الهمزات، والبيانات المنظمة (JSON-LD).',
                        'احصل على درجة جاهزية الصفحة من 100 مع قائمة واضحة بالمشكلات التي تحتاج إلى معالجة.',
                    ],
                    'faq_title' => 'أسئلة شائعة حول فحص السيو العربي للصفحة',
                    'faqs' => [
                        ['q' => 'كيف يطابق الفاحص الكلمات المفتاحية العربية؟', 'a' => 'يستخدم الفاحص طبقة التطبيع العربية الخاصة بـ AdsRanger لمطابقة الكلمة حتى لو اختلفت أشكال الهمزات (أ/إ/آ/ا) أو التاء المربوطة والهاء في عنوان الصفحة ونصها.'],
                        ['q' => 'كم صفحة يمكنني فحصها مجانًا؟', 'a' => 'يمكنك إجراء حتى 5 فحوصات حية يوميًا مجانًا ودون تسجيل، أو طلب إرسال ملخص الفحص إلى بريدك عند تفعيل خدمة الرسائل.'],
                    ],
                ],
                'en' => [
                    'how_title' => 'How the Arabic page SEO audit works',
                    'how_steps' => [
                        'Enter the page URL and an optional target Arabic keyword.',
                        'The engine runs 14 Arabic-first on-page checks covering pixel widths, lang="ar" and dir="rtl", normalized Arabic keyword matching, headings, canonicals, and JSON-LD schema.',
                        'Review your 0–100 score and fix flagged issues before launching campaigns or publishing.',
                    ],
                    'faq_title' => 'Frequently asked questions',
                    'faqs' => [
                        ['q' => 'How does the audit match Arabic keywords across spelling variants?', 'a' => 'It uses AdsRanger\'s Arabic normalization layer so hamza, alef, and ta-marbuta variations match accurately without false negatives.'],
                        ['q' => 'How many live page audits can I run per day?', 'a' => 'Visitors can run up to 5 live page audits per day for free without signing up.'],
                    ],
                ],
            ],
            'capi-pixel-checker' => [
                'ar' => [
                    'how_title' => 'كيف يعمل فاحص البكسل ووسوم التتبع',
                    'how_steps' => [
                        'أدخل الرابط العام لصفحة الهبوط أو المتجر واضغط فحص.',
                        'تفحص الأداة كود الصفحة والسكربتات الظاهرة لرصد وسوم المنصات ومنصات التجارة مثل سلة وزد.',
                        'راجع التنبيهات حول غياب dataLayer أو وسم noscript الاحتياطي.',
                    ],
                    'faq_title' => 'أسئلة شائعة حول فحص البكسل',
                    'faqs' => [
                        ['q' => 'هل يستطيع الفحص تأكيد عمل واجهة التحويلات الخادمية (CAPI)؟', 'a' => 'لا؛ يفحص هذا الاختبار كود HTML والسكربتات العامة في الصفحة فقط، بينما تُرسل أحداث الخادم مباشرة من خادمك إلى المنصة الإعلانية ولا تظهر في الكود العام.'],
                        ['q' => 'هل تحفظ AdsRanger روابط المتاجر التي أفحصها؟', 'a' => 'لا، يُنفَّذ الفحص لحظيًا ولا نخزّن الرابط أو محتوى الصفحة.'],
                    ],
                ],
                'en' => [
                    'how_title' => 'How the ad pixel & tag checker works',
                    'how_steps' => [
                        'Enter any public landing page or storefront URL and run the check.',
                        'The inspector scans public HTML and script tags for Meta, GTM, GA4, Google Ads, TikTok, Snap, LinkedIn, and Salla/Zid hooks.',
                        'Review warnings for missing dataLayer initialization or noscript fallbacks.',
                    ],
                    'faq_title' => 'Frequently asked questions',
                    'faqs' => [
                        ['q' => 'Can this check verify server-side CAPI delivery?', 'a' => 'No. Public HTML inspection only detects browser-side pixels and storefront hooks; server-to-server CAPI events fire from your backend and must be verified inside Events Manager.'],
                        ['q' => 'Does AdsRanger store inspected URLs?', 'a' => 'No. Checks run on demand and neither the URL nor the HTML is stored.'],
                    ],
                ],
            ],
            'cod-poas-calculator' => [
                'ar' => [
                    'how_title' => 'كيف تعمل حاسبة أرباح الدفع عند الاستلام والتقسيط',
                    'how_steps' => [
                        'اختر العملة وأدخل متوسط قيمة الطلب ونسبة تكلفة البضاعة وتكلفة الشحن ذهابًا وإيابًا.',
                        'حدد نسبة تسليم طلبات الدفع عند الاستلام وعمولة بوابات التقسيط أو الدفع.',
                        'أدخل الإنفاق الإعلاني وعدد الطلبات لمعرفة نقطة تعادل العائد وصافي الربح الحقيقي وعائد الربح (POAS).',
                    ],
                    'faq_title' => 'أسئلة شائعة حول ربحية الدفع عند الاستلام',
                    'faqs' => [
                        ['q' => 'لماذا يبدو العائد الإعلاني مرتفعًا بينما يخسر المتجر؟', 'a' => 'لأن العائد الإعلاني التقليدي يحسب الطلبات المرسلة قبل خصم الطلبات المرتجعة وتكلفة شحنها ذهابًا وإيابًا.'],
                        ['q' => 'ما الفرق بين ROAS وPOAS؟', 'a' => 'يقيس ROAS إجمالي الإيرادات مقابل الإنفاق الإعلاني، بينما يقيس POAS هامش الربح الفعلي بعد خصم البضاعة والشحن والعمولات مقسومًا على الإنفاق الإعلاني.'],
                    ],
                ],
                'en' => [
                    'how_title' => 'How the COD & BNPL profit calculator works',
                    'how_steps' => [
                        'Select your currency and enter AOV, COGS %, outbound shipping, and return shipping cost.',
                        'Set your Cash-on-Delivery fulfillment success rate and BNPL/payment gateway fee percentage.',
                        'Enter ad spend and total orders to compute Break-Even ROAS, POAS, and net profit.',
                    ],
                    'faq_title' => 'Frequently asked questions',
                    'faqs' => [
                        ['q' => 'Why can high platform ROAS still lose money on COD?', 'a' => 'Ad platforms count placed orders before undelivered COD returns, which incur both lost revenue and round-trip courier fees.'],
                        ['q' => 'What is POAS?', 'a' => 'Profit on Ad Spend divides gross profit (after COGS, shipping, returns, and gateway fees) by ad spend—a value above 1.0x means net profitable ads.'],
                    ],
                ],
            ],
            'whatsapp-link-generator' => [
                'ar' => [
                    'how_title' => 'كيف يعمل مولد روابط واتساب للحملات',
                    'how_steps' => [
                        'أدخل رقم واتساب بصيغة دولية دون أصفار بادئة أو علامة زائد.',
                        'اكتب الرسالة الترحيبية الجاهزة وأضف وسوم الحملة الاختيارية لتتبع المصدر.',
                        'انسخ رابط wa.me الجاهز أو نزّل رمز الاستجابة السريعة بصيغة SVG.',
                    ],
                    'faq_title' => 'أسئلة شائعة حول روابط واتساب الإعلانية',
                    'faqs' => [
                        ['q' => 'هل يُرسل الرقم أو نص الرسالة إلى خادم خارجي؟', 'a' => 'لا، يُبنى الرابط ورمز QR بالكامل داخل متصفحك دون إرسال أي بيانات.'],
                        ['q' => 'كيف أميّز رسائل الإعلانات في واتساب؟', 'a' => 'ضمّن عبارة مميزة أو رمز مرجعي قصير في بداية الرسالة التلقائية لكل حملة لتعرف الإعلان الذي جلب المحادثة.'],
                    ],
                ],
                'en' => [
                    'how_title' => 'How the WhatsApp link & QR generator works',
                    'how_steps' => [
                        'Enter the international phone number without leading zeros or plus signs.',
                        'Write your pre-filled Arabic or English greeting and optional UTM campaign tags.',
                        'Copy the generated wa.me link or download the standalone SVG QR code.',
                    ],
                    'faq_title' => 'Frequently asked questions',
                    'faqs' => [
                        ['q' => 'Are phone numbers or messages uploaded anywhere?', 'a' => 'No. Both the wa.me link and the SVG QR code are generated entirely inside your browser.'],
                        ['q' => 'How can I attribute WhatsApp leads to specific campaigns?', 'a' => 'Include a short campaign reference phrase inside the pre-filled greeting and use UTM parameters on landing pages that house the WhatsApp button.'],
                    ],
                ],
            ],
            'ad-mockup-preview' => [
                'ar' => [
                    'how_title' => 'كيف تعمل أداة معاينة الإعلانات الحية',
                    'how_steps' => [
                        'اختر اتجاه الإعلان (عربي من اليمين لليصار أو إنجليزي) وأدخل اسم العلامة والنص والعنوان.',
                        'بدّل بين مواضع العرض: منشور ميتا، ريلز وتيك توك العمودي، قصص سناب شات، وإعلان بحث جوجل.',
                        'انتبه لتنبيهات تجاوز الطول الموصى به قبل إرسال المسودة للعميل أو النشر.',
                    ],
                    'faq_title' => 'أسئلة شائعة حول معاينة الإعلانات',
                    'faqs' => [
                        ['q' => 'متى يُقطع النص الأساسي في إعلانات الخلاصة؟', 'a' => 'غالبًا يظهر أول 125 حرفًا تقريبًا قبل زر عرض المزيد على شاشات الجوال.'],
                        ['q' => 'ما الحد الآمن لعناوين إعلانات بحث جوجل المتجاوبة؟', 'a' => 'الحد الأقصى لكل عنوان هو 30 حرفًا، وللوصف 90 حرفًا.'],
                    ],
                ],
                'en' => [
                    'how_title' => 'How the live ad mockup previewer works',
                    'how_steps' => [
                        'Choose RTL Arabic or LTR English direction and type your brand name, primary text, headline, and CTA.',
                        'Preview simultaneously across Meta Feed, 9:16 Reels/TikTok, Snapchat Story, and Google Search RSA.',
                        'Watch live character-truncation warnings before sharing copy for sign-off.',
                    ],
                    'faq_title' => 'Frequently asked questions',
                    'faqs' => [
                        ['q' => 'Where do social feeds truncate primary ad copy?', 'a' => 'Mobile feeds typically fold primary text around 125 characters behind a "See more" prompt.'],
                        ['q' => 'What are Google Search RSA character limits?', 'a' => 'Each headline allows up to 30 characters and each description up to 90 characters.'],
                    ],
                ],
            ],
            'llms-txt-generator' => [
                'ar' => [
                    'how_title' => 'كيف يعمل منشئ ملف llms.txt وفاحص زواحف الذكاء',
                    'how_steps' => [
                        'أدخل اسم موقعك ووصفه الموجز وأهم الروابط المرجعية لبناء ملف llms.txt القياسي.',
                        'انسخ الملف أو نزّله لرفعه في جذر موقعك على المسار /llms.txt.',
                        'استخدم تبويب فحص الموقع للتحقق من قواعد GPTBot وClaudeBot وPerplexityBot في ملف robots.txt.',
                    ],
                    'faq_title' => 'أسئلة شائعة حول ملف llms.txt',
                    'faqs' => [
                        ['q' => 'ما فائدة ملف llms.txt؟', 'a' => 'يقدّم ملخصًا منظمًا بصيغة ماركداون لمساعدة أنظمة الإجابة الذكية في فهم صفحاتك الأساسية ووثائقك الرسمية بدقة.'],
                        ['q' => 'هل يغني ملف llms.txt عن ملف robots.txt؟', 'a' => 'لا؛ يتحكم robots.txt في السماح أو المنع للزواحف، بينما يوجّه llms.txt الأنظمة المسموح لها إلى أهم محتواك.'],
                    ],
                ],
                'en' => [
                    'how_title' => 'How the llms.txt generator & AI crawler checker works',
                    'how_steps' => [
                        'Enter your site title, summary, and key documentation or product URLs to generate a clean llms.txt markdown file.',
                        'Copy or download the file to host at your domain root (/llms.txt).',
                        'Optionally check any public domain\'s robots.txt to see whether GPTBot, OAI-SearchBot, ClaudeBot, PerplexityBot, and Google-Extended are allowed or blocked.',
                    ],
                    'faq_title' => 'Frequently asked questions',
                    'faqs' => [
                        ['q' => 'What does llms.txt do?', 'a' => 'It provides a concise markdown directory of your canonical pages so AI search and answer engines read accurate source material.'],
                        ['q' => 'Does llms.txt replace robots.txt?', 'a' => 'No. Crawler access permissions are controlled in robots.txt, while llms.txt curates key resources for permitted agents.'],
                    ],
                ],
            ],
            'schema-generator' => [
                'ar' => [
                    'how_title' => 'كيف يعمل مولد البيانات المنظمة JSON-LD',
                    'how_steps' => [
                        'اختر نوع الكيان المطلوب: منظمة، نشاط تجاري محلي، أسئلة شائعة، منتج، أو تطبيق برمجي.',
                        'أدخل الحقول بالعربية أو الإنجليزية لمشاهدة كود JSON-LD يتحدث فورًا.',
                        'تحقق من سلامة الصياغة البرمجية وانسخ الوسم الجاهز لوضعه داخل رأس الصفحة.',
                    ],
                    'faq_title' => 'أسئلة شائعة حول البيانات المنظمة',
                    'faqs' => [
                        ['q' => 'هل يجب أن يطابق محتوى JSON-LD النص الظاهر في الصفحة؟', 'a' => 'نعم، تشترط إرشادات محركات البحث أن تعكس البيانات المنظمة المحتوى المرئي الفعلي للزائر دون مبالغة.'],
                        ['q' => 'هل يدعم الكود النصوص العربية بالكامل؟', 'a' => 'نعم، يُولَّد الكود بترميز UTF-8 مباشر دون تهريب الحروف العربية ليبقى سهل القراءة والمراجعة.'],
                    ],
                ],
                'en' => [
                    'how_title' => 'How the bilingual JSON-LD schema generator works',
                    'how_steps' => [
                        'Pick a schema type: Organization, LocalBusiness, FAQPage, Product, or SoftwareApplication.',
                        'Fill in the Arabic or English fields to build clean JSON-LD in real time.',
                        'Validate JSON syntax in one click and copy the ready-to-paste script block.',
                    ],
                    'faq_title' => 'Frequently asked questions',
                    'faqs' => [
                        ['q' => 'Must JSON-LD match visible page text?', 'a' => 'Yes. Search engine guidelines require structured data to accurately represent content visible to users on the page.'],
                        ['q' => 'Can I validate existing JSON-LD snippets here?', 'a' => 'Yes. Paste any JSON-LD object into the validator box to verify valid JSON syntax and @context/@type presence.'],
                    ],
                ],
            ],
            'ab-significance-calculator' => [
                'ar' => [
                    'how_title' => 'كيف تعمل حاسبة الدلالة الإحصائية لاختبارات A/B',
                    'how_steps' => [
                        'أدخل عدد الزوار أو النقرات وعدد التحويلات لكل من النسخة الأصلية (أ) والنسخة المختبرة (ب).',
                        'اختر مستوى الثقة المطلوب (90% أو 95% أو 99%) لعرض نتيجة اختبار Z وقيمة p واحتمال الفوز البايزي.',
                        'استخدم حاسبة حجم العينة المدمجة لمعرفة عدد الزوار المطلوب قبل إيقاف الاختبار.',
                    ],
                    'faq_title' => 'أسئلة شائعة حول اختبارات A/B',
                    'faqs' => [
                        ['q' => 'متى أعتبر نتيجة الاختبار دالة إحصائيًا؟', 'a' => 'عند مستوى ثقة 95% يجب أن تقل قيمة p عن 0.05 مع الوصول إلى حجم العينة المقدر مسبقًا.'],
                        ['q' => 'ماذا يعني احتمال الفوز البايزي؟', 'a' => 'يقدّر احتمال أن يكون معدل التحويل الحقيقي للنسخة (ب) أعلى من النسخة (أ) بناءً على توزيع بيتا للبيانات المرصودة.'],
                    ],
                ],
                'en' => [
                    'how_title' => 'How the A/B significance & sample-size calculator works',
                    'how_steps' => [
                        'Enter visitors/clicks and conversions for Control (A) and Variant (B).',
                        'Review relative lift, two-sided Z-score, p-value, confidence verdict, and Bayesian probability B beats A.',
                        'Use the minimum sample-size estimator to plan how much traffic each variant needs before calling a winner.',
                    ],
                    'faq_title' => 'Frequently asked questions',
                    'faqs' => [
                        ['q' => 'When should I call an A/B test statistically significant?', 'a' => 'Typically at 95% confidence (two-tailed p-value < 0.05) once your planned minimum sample size per variant has been reached.'],
                        ['q' => 'How is Bayesian win probability estimated?', 'a' => 'It compares Beta posterior distributions for Variant A and Variant B to estimate the probability that B\'s true conversion rate exceeds A\'s.'],
                    ],
                ],
            ],
            'safe-zone-checker' => [
                'ar' => [
                    'how_title' => 'كيف يعمل فاحص المناطق الآمنة للفيديو العمودي',
                    'how_steps' => [
                        'اسحب صورة تصميم إعلانك العمودي (9:16) أو لقطة من الفيديو إلى مساحة المعاينة.',
                        'بدّل بين طبقات واجهة إنستغرام ريلز وتيك توك وسناب شات لمعرفة أماكن الأزرار والوصف.',
                        'تأكّد أن الشعار والترجمة العربية وزر الدعوة لاتخاذ إجراء تقع داخل المنطقة الخضراء الآمنة.',
                    ],
                    'faq_title' => 'أسئلة شائعة حول المناطق الآمنة للإعلانات',
                    'faqs' => [
                        ['q' => 'هل تُرفع صوري أو تصاميمي إلى خوادم AdsRanger؟', 'a' => 'لا إطلاقًا؛ تُقرأ الصورة محليًا داخل متصفحك فقط ولا يغادر ملفك جهازك.'],
                        ['q' => 'كم تبلغ الهوامش الآمنة التقريبية لفيديو 1080×1920؟', 'a' => 'يُنصح بترك نحو 250 بكسل من الأعلى، و380 إلى 420 بكسل من الأسفل، و120 بكسل من الجانب الذي تظهر فيه أيقونات التفاعل.'],
                    ],
                ],
                'en' => [
                    'how_title' => 'How the 9:16 safe-zone checker works',
                    'how_steps' => [
                        'Drag and drop a 9:16 story/reel image or video frame directly into the preview canvas.',
                        'Switch between Instagram Reels, TikTok, and Snapchat Story UI overlays.',
                        'Verify that Arabic/English subtitles, logos, and offers stay clear of top headers, side action rails, and bottom CTA drawers.',
                    ],
                    'faq_title' => 'Frequently asked questions',
                    'faqs' => [
                        ['q' => 'Are my creative files uploaded to any server?', 'a' => 'Never. File rendering runs 100% locally in your browser via FileReader/URL.createObjectURL with zero server uploads.'],
                        ['q' => 'What safe margins work across Reels, TikTok, and Snap?', 'a' => 'On a 1080x1920 frame, keeping critical text at least 250px from the top, 400px from the bottom, and 120px from side icon rails avoids UI overlap.'],
                    ],
                ],
            ],
            'ksa-ad-compliance-checker' => [
                'ar' => [
                    'how_title' => 'كيف يعمل قائمة إفصاحات الإعلانات في السعودية والإمارات',
                    'how_steps' => [
                        'اختر القطاع الإعلاني (عقارات، صحة وتجميل، غذاء ومكملات، تقسيط وتمويل، أو مؤثرين).',
                        'ألصق نص الإعلان لفحص وجود عبارات الإفصاح وأرقام التراخيص المطلوبة ومراجعة قائمة التحقق.',
                        'صحّح النواقص قبل إطلاق الحملة لتقليل احتمالية رفض الإعلان أو المخالفات التنظيمية.',
                    ],
                    'faq_title' => 'أسئلة شائعة حول ضوابط الإعلانات الخليجية',
                    'faqs' => [
                        ['q' => 'هل يُعدّ هذا الفحص استشارة قانونية رسمية؟', 'a' => 'لا؛ هذه الأداة قائمة إرشادية تشغيلية لفرق التسويق وليست استشارة قانونية، ويجب دائمًا الرجوع للأنظمة الرسمية المحدثة لكل جهة مختصة.'],
                        ['q' => 'ما الإفصاحات التي تذكّر بها القائمة؟', 'a' => 'رقم ترخيص الإعلان العقاري ورخصة فال في السعودية، وتنبيهات الصحة والغذاء والدواء، ووسم #معلن في الإمارات، وشروط التقسيط ورسومه. القواعد تتغيّر، فتحقّق منها لدى الجهة المختصة.'],
                    ],
                ],
                'en' => [
                    'how_title' => 'How the KSA & UAE ad disclosure checklist works',
                    'how_steps' => [
                        'Select your campaign vertical (Real Estate, Healthcare, Food/SFDA, BNPL/Finance, or Creator/Influencer).',
                        'Paste your Arabic or English ad copy to scan for required permit markers and work through the regulatory checklist.',
                        'Address missing disclosures before publishing campaigns.',
                    ],
                    'faq_title' => 'Frequently asked questions',
                    'faqs' => [
                        ['q' => 'Is this tool legal advice?', 'a' => 'No. This is an operational pre-flight checklist for marketing teams, not legal counsel. Always verify current regulations with REGA, SFDA, MOH, the UAE Media Council, or qualified legal advisors.'],
                        ['q' => 'Which disclosures does the checklist remind you of?', 'a' => 'Saudi REGA / Fal real estate licence numbers, SFDA / MOH health notes, the UAE Media Council advertiser tag (#معلن), and instalment fees and terms. Rules change, so confirm them with the regulator.'],
                    ],
                ],
            ],
            'cpm-cpc-cpa-calculator' => [
                'ar' => [
                    'how_title' => 'كيف تعمل حاسبة مؤشرات الإعلانات الشاملة',
                    'how_steps' => [
                        'أدخل الإنفاق الإعلاني وعدد مرات الظهور والنقرات والتحويلات.',
                        'تحسب الأداة فورًا تكلفة الألف ظهور (CPM) ونسبة النقر (CTR) وتكلفة النقرة (CPC) ومعدل التحويل (CVR) وتكلفة الاكتساب (CPA).',
                        'عدّل أي رقم لمحاكاة أثر رفع نسبة النقر أو تحسين معدل تحويل صفحة الهبوط على تكلفة العميل.',
                    ],
                    'faq_title' => 'أسئلة شائعة حول معادلات الأداء الإعلاني',
                    'faqs' => [
                        ['q' => 'كيف أخفض تكلفة الاكتساب (CPA) دون زيادة الميزانية؟', 'a' => 'رفع نسبة النقر (CTR) عبر إعلان أوضح أو رفع معدل التحويل (CVR) في صفحة الهبوط يخفض تكلفة الاكتساب مباشرة بنفس الميزانية.'],
                        ['q' => 'ما معادلة CPM القياسية؟', 'a' => 'تكلفة الألف ظهور تساوي (الإنفاق الإعلاني ÷ عدد مرات الظهور) × 1000.'],
                    ],
                ],
                'en' => [
                    'how_title' => 'How the full-funnel CPM, CPC & CPA calculator works',
                    'how_steps' => [
                        'Enter your budget/spend, impressions, clicks, and conversions.',
                        'Instantly see calculated CPM, CTR %, CPC, CVR %, and CPA side by side.',
                        'Adjust CTR or landing-page CVR to model how funnel improvements lower your CPA.',
                    ],
                    'faq_title' => 'Frequently asked questions',
                    'faqs' => [
                        ['q' => 'How are CPM, CPC, and CPA linked mathematically?', 'a' => 'CPC equals CPM divided by (10 × CTR%), and CPA equals CPC divided by CVR—so improving either CTR or CVR lowers CPA proportionally.'],
                        ['q' => 'Does this calculator convert currencies?', 'a' => 'No. All metrics are calculated in the currency you select with no hidden exchange assumptions.'],
                    ],
                ],
            ],
            'agency-fee-vat-calculator' => [
                'ar' => [
                    'how_title' => 'كيف تعمل حاسبة أتعاب الوكالة وضريبة القيمة المضافة',
                    'how_steps' => [
                        'اختر الدولة ونسبة ضريبة القيمة المضافة (السعودية 15%، الأردن 16%، الإمارات 5%، مصر 14%، أو مخصص).',
                        'أدخل ميزانية الإعلانات الشهرية وقارن بين الأتعاب الشهرية الثابتة ونسبة الإدارة من الإنفاق.',
                        'راجع إجمالي فاتورة العميل شاملة الضريبة وصافي هامش ربح الوكالة بعد تكلفة الفريق.',
                    ],
                    'faq_title' => 'أسئلة شائعة حول تسعير خدمات الوكالات',
                    'faqs' => [
                        ['q' => 'ما الفرق بين فوترة الإعلانات عبر بطاقة العميل أو بطاقة الوكالة؟', 'a' => 'عند الدفع عبر بطاقة العميل تفوتر الوكالة أتعابها فقط، أما عند تمرير ميزانية الإعلانات عبر حساب الوكالة فتحتاج الوكالة لحساب التدفق النقدي والضريبة على كامل المبلغ حسب النظام الضريبي المحلي.'],
                        ['q' => 'هل تغني الحاسبة عن مراجعة المحاسب القانوني؟', 'a' => 'لا، الأرقام تقديرية لتخطيط عروض الأسعار ويجب مراجعة المعالجة الضريبية مع محاسب معتمد في بلدك.'],
                    ],
                ],
                'en' => [
                    'how_title' => 'How the agency fee & MENA VAT calculator works',
                    'how_steps' => [
                        'Pick a MENA VAT preset (KSA 15%, Jordan 16%, UAE 5%, Egypt 14%, or custom %) and currency.',
                        'Enter client ad spend, fixed monthly retainer, percentage-of-spend fee, and internal delivery cost.',
                        'Compare effective agency revenue, net agency margin, media float exposure, and VAT-inclusive invoice totals.',
                    ],
                    'faq_title' => 'Frequently asked questions',
                    'faqs' => [
                        ['q' => 'Why separate agency fee from ad media float?', 'a' => 'Passing client media spend through agency cards inflates top-line invoice totals and cash-flow risk without increasing real agency gross margin.'],
                        ['q' => 'Is VAT applied to media spend or agency fees?', 'a' => 'Tax treatment depends on jurisdiction and whether the agency acts as principal or agent; toggle pass-through billing in the calculator and confirm with your tax advisor.'],
                    ],
                ],
            ],
            'robots-sitemap-checker' => [
                'ar' => [
                    'how_title' => 'كيف يعمل فاحص robots.txt وخريطة الموقع',
                    'how_steps' => [
                        'أدخل رابط الصفحة التي تريد التحقق من قابليتها للأرشفة.',
                        'تجلب الأداة ملف robots.txt وتفحص روابط Sitemap المعلنة ووسم الرابط المعتمد (canonical) في الصفحة.',
                        'راجع أي تعارض بين حظر الزحف ووسم الصفحة أو أخطاء فتح خريطة الموقع.',
                    ],
                    'faq_title' => 'أسئلة شائعة حول فحص robots.txt وخريطة الموقع',
                    'faqs' => [
                        ['q' => 'لماذا يُعدّ إعلان Sitemap داخل robots.txt مهمًا؟', 'a' => 'لأنه يرشد محركات البحث تلقائيًا إلى موقع خريطة XML المحدثة حتى قبل إرسالها يدويًا في أدوات مشرفي المواقع.'],
                        ['q' => 'ماذا يحدث إذا كانت الصفحة محظورة في robots.txt؟', 'a' => 'تمتنع محركات البحث عن زحف محتوى الصفحة وقد لا تقرأ وسم canonical أو التحديثات الموجودة داخلها.'],
                    ],
                ],
                'en' => [
                    'how_title' => 'How the robots.txt & XML sitemap checker works',
                    'how_steps' => [
                        'Enter a public page URL to inspect.',
                        'The checker fetches /robots.txt, verifies declared XML Sitemap URLs, and inspects the target page\'s canonical link tag.',
                        'Spot Disallow conflicts, missing Sitemap declarations, non-200 sitemap responses, or canonical mismatches.',
                    ],
                    'faq_title' => 'Frequently asked questions',
                    'faqs' => [
                        ['q' => 'Why declare Sitemap URLs inside robots.txt?', 'a' => 'Listing Sitemap directives in robots.txt helps search engines discover your XML sitemaps automatically across crawls.'],
                        ['q' => 'What happens when a canonical URL is blocked by Disallow?', 'a' => 'Crawlers blocked by robots.txt cannot read the page\'s canonical tag or content, causing indexing conflicts.'],
                    ],
                ],
            ],
        ];

        return $map[$tool][$ar ? 'ar' : 'en'] ?? $map['serp-preview'][$ar ? 'ar' : 'en'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function faqSchema(string $tool, bool $ar): array
    {
        $copy = self::for($tool, $ar);
        $entities = [];
        foreach ($copy['faqs'] as $f) {
            $entities[] = [
                '@type' => 'Question',
                'name' => $f['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $f['a'],
                ],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }
}
