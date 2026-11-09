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
