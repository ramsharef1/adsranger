<?php

namespace App\Support\Seo\Audit;

/**
 * Rule catalog for site audit A1 (FR-089, 910 BUILD START step 3; PR-014 §2.3). Codes, D-88 levels, PR-014 categories and the
 * Arabic + English title / why / fix lines. Every Latin term in Arabic text is wrapped in <bdi> by the view (Bidi helper).
 */
class Catalog
{
    public const CRITICAL = 'critical';

    public const WARNING = 'warning';

    public const INFO = 'info';

    /** PR-014 §2.2 category bars, Arabic + RTL first. */
    public const CATEGORIES = [
        'arabic' => ['العربية والاتجاه', 'Arabic & direction'],
        'hreflang' => ['روابط اللغات', 'Language links'],
        'titles' => ['العناوين والأوصاف', 'Titles & descriptions'],
        'indexing' => ['الأرشفة', 'Indexing'],
        'links' => ['الروابط الداخلية والمعطلة', 'Internal & broken links'],
        'speed' => ['سرعة الصفحة', 'Page speed'],
        'schema' => ['البيانات المنظّمة', 'Structured data'],
    ];

    /**
     * code => [level, category, title AR, title EN, why AR, why EN, fix AR, fix EN]
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string, 7: string}>
     */
    public static function rules(): array
    {
        return [
            'AR-RTL-01' => [self::CRITICAL, 'arabic', 'صفحة عربية بلا اتجاه من اليمين إلى اليسار', 'Arabic page without right-to-left direction',
                'النص العربي في هذه الصفحة يُعرض باتجاه من اليسار إلى اليمين (dir="ltr" أو لا اتجاه)، فتختل علامات الترقيم والأرقام والقراءة على الهاتف.', 'The Arabic text on this page renders left-to-right (dir="ltr" or no dir), so punctuation, numbers and phone reading break.',
                'أضف dir="rtl" إلى وسم <html> في النسخة العربية.', 'Add dir="rtl" to the <html> tag of the Arabic version.'],
            'AR-LANG-01' => [self::CRITICAL, 'arabic', 'صفحة عربية بلا سمة اللغة', 'Arabic page without a language attribute',
                'لا تحمل الصفحة lang="ar"، فلا تعرف محركات البحث وقارئات الشاشة أنها عربية.', 'The page has no lang="ar", so search engines and screen readers don\'t know it is Arabic.',
                'أضف lang="ar" (أو lang="ar-SA" مثلًا) إلى وسم <html>.', 'Add lang="ar" (or a regional code such as lang="ar-SA") to the <html> tag.'],
            'AR-FONT-01' => [self::WARNING, 'arabic', 'لا يوجد خط عربي من الويب', 'No Arabic web font',
                'تحمّل الصفحة خطوطًا من الويب لكن لا يدعم أيٌّ منها العربية، فيُعرض النص العربي بخط النظام الذي يختلف بين الأجهزة.', 'The page loads web fonts but none covers Arabic, so Arabic text falls back to a system font that differs between devices.',
                'حمّل خطًا عربيًا (مثل IBM Plex Sans Arabic أو Tajawal) مع font-display: swap.', 'Load an Arabic web font (for example IBM Plex Sans Arabic or Tajawal) with font-display: swap.'],
            'AR-FONT-02' => [self::INFO, 'arabic', 'الخط بلا font-display', 'Font without font-display',
                'الخط المحمّل بلا font-display، فقد يظهر نص فارغ حتى يكتمل تحميل الخط.', 'The loaded font has no font-display, so text may stay invisible until the font loads.',
                'أضف font-display: swap أو optional إلى @font-face، أو &display=swap إلى رابط الخط.', 'Add font-display: swap or optional to @font-face, or &display=swap to the font link.'],
            'AR-TITLE-01' => [self::WARNING, 'titles', 'العنوان أطول من عرض نتائج البحث', 'Title wider than the search result',
                'يُقطع العنوان في نتائج البحث عندما يتجاوز عرضه نحو 580 بكسل.', 'The title gets cut in search results when it is wider than about 580 px.',
                'اختصر العنوان إلى نحو 55 حرفًا عربيًا، والأهم في بدايته.', 'Shorten the title to about 55 Arabic characters, with the key words first.'],
            'AR-TITLE-02' => [self::INFO, 'titles', 'العنوان العربي يبدأ بكلمة لاتينية', 'Arabic title opens with a Latin word',
                'عندما يبدأ عنوان عربي بكلمة لاتينية قد تُعرض نهاية العنوان في بدايته داخل نتائج البحث.', 'When an Arabic title starts with a Latin word, search results may show its end at the start.',
                'ابدأ العنوان بكلمة عربية وضع الاسم اللاتيني بعدها.', 'Start the title with an Arabic word and put the Latin name after it.'],
            'AR-TITLE-03' => [self::INFO, 'titles', 'تشكيل في العنوان', 'Tashkeel in the title',
                'الحركات في العنوان تجعل مطابقته لما يكتبه الناس في البحث أصعب.', 'Diacritics in the title make it harder to match what people type in search.',
                'احذف التشكيل من العنوان والوصف إلا عند الحاجة لتمييز معنى.', 'Remove tashkeel from the title and description unless it is needed to tell meanings apart.'],
            'AR-ONPAGE-01' => [self::WARNING, 'titles', 'الكلمة المستهدفة ليست في العنوان', 'Target keyword not in the title',
                'لم نجد الكلمة المفتاحية المستهدفة (أو إحدى صيغها) في عنوان الصفحة.', 'The target keyword (or one of its spellings) is not in the page title.',
                'ضع الكلمة أو إحدى صيغها في العنوان، قرب بدايته.', 'Put the keyword or one of its spellings in the title, near the start.'],
            'AR-ONPAGE-02' => [self::INFO, 'titles', 'الكلمة المستهدفة ليست في العنوان الرئيسي', 'Target keyword not in the H1',
                'العنوان الرئيسي <h1> لا يحمل الكلمة المستهدفة أو إحدى صيغها.', 'The <h1> does not carry the target keyword or one of its spellings.',
                'اذكر الكلمة أو صيغة منها في <h1>.', 'Mention the keyword or a spelling of it in the <h1>.'],
            'HREF-01' => [self::WARNING, 'hreflang', 'لا روابط لغات على صفحة بنسختين', 'No hreflang on a bilingual site',
                'الموقع فيه صفحات عربية وإنجليزية لكن هذه الصفحة لا تعلن نسختها الأخرى، فقد يُعرض للزائر العربي النسخة الإنجليزية.', 'The site has Arabic and English pages but this page doesn\'t declare its other version, so an Arabic visitor may be shown the English one.',
                'أضف <link rel="alternate" hreflang="ar"> و hreflang="en" إلى كل نسخة.', 'Add <link rel="alternate" hreflang="ar"> and hreflang="en" to each version.'],
            'HREF-02' => [self::CRITICAL, 'hreflang', 'رابط لغة بلا رابط عودة', 'Language link without a return link',
                'تشير هذه الصفحة إلى نسخة أخرى لا تشير إليها بالمقابل، فتتجاهل محركات البحث الزوج كله.', 'This page points to another version that doesn\'t point back, so search engines ignore the whole pair.',
                'أضف على الصفحة الأخرى رابط hreflang يعود إلى هذه الصفحة.', 'Add an hreflang link on the other page that points back to this one.'],
            'HREF-03' => [self::WARNING, 'hreflang', 'لا x-default في روابط اللغات', 'No x-default in the language links',
                'بلا x-default لا تعرف محركات البحث أي نسخة تعرضها لزائر من لغة أخرى.', 'Without x-default, search engines don\'t know which version to show a visitor from another language.',
                'أضف hreflang="x-default" يشير إلى النسخة الافتراضية (العربية عادةً).', 'Add hreflang="x-default" pointing to the default version (usually Arabic).'],
            'HREF-04' => [self::CRITICAL, 'hreflang', 'الرابط الأساسي يشير إلى اللغة الأخرى', 'Canonical points to the other language',
                'الرابط الأساسي (canonical) يشير إلى نسخة بلغة أخرى، فقد تختفي هذه الصفحة من نتائج بحث لغتها.', 'The canonical points to a version in another language, so this page may vanish from its language\'s results.',
                'اجعل كل نسخة تشير إلى نفسها في canonical، واربط اللغات عبر روابط hreflang فقط.', 'Make each version canonical to itself and link languages with hreflang only.'],
            'HREF-05' => [self::WARNING, 'hreflang', 'رمز لغة غير صحيح', 'Invalid language code',
                'رمز اللغة أو الدولة في hreflang ليس بالصيغة المعتمدة (مثل ar-UAE أو en-UK أو ar_SA).', 'The hreflang language or country code isn\'t in the accepted form (for example ar-UAE, en-UK or ar_SA).',
                'استخدم رمز لغة ISO 639-1 ورمز دولة ISO 3166-1 بشرطة: ar-AE، en-GB، ar-SA.', 'Use an ISO 639-1 language code and an ISO 3166-1 country code with a hyphen: ar-AE, en-GB, ar-SA.'],
            'HREF-06' => [self::INFO, 'hreflang', 'روابط اللغات تشير إلى صفحة لا تفتح', 'Language link points to a page that doesn\'t open',
                'إحدى النسخ المعلنة في hreflang لا تفتح برمز 200.', 'One of the versions declared in hreflang doesn\'t answer 200.',
                'صحّح الرابط أو احذف النسخة التي لم تعد موجودة.', 'Fix the link or remove the version that no longer exists.'],
            'URL-01' => [self::INFO, 'links', 'رابط عربي مرمّز', 'Percent-encoded Arabic URL',
                'الرابط يحمل حروفًا عربية مرمّزة (%D8…)، فيبدو طويلًا وغير مقروء عند مشاركته.', 'The URL carries percent-encoded Arabic (%D8…), so it looks long and unreadable when shared.',
                'يعمل الرابط كما هو؛ عند بناء صفحات جديدة فضّل مسارًا لاتينيًا قصيرًا أو عربيًا قصيرًا.', 'The URL works as it is; for new pages prefer a short Latin or short Arabic path.'],
            'URL-02' => [self::INFO, 'links', 'رابط يخلط حروفًا عربية ولاتينية', 'URL mixes Arabic and Latin letters',
                'خلط الحروف في المسار نفسه يصعّب قراءة الرابط ونسخه.', 'Mixing scripts in the same path segment makes the URL hard to read and copy.',
                'استخدم حروفًا من نظام كتابة واحد في كل مقطع من الرابط.', 'Use one script per path segment.'],
            'URL-03' => [self::INFO, 'links', 'رابط طويل جدًا', 'Very long URL',
                'الرابط أطول من 150 حرفًا، فيُقطع في نتائج البحث وعند المشاركة.', 'The URL is longer than 150 characters, so it gets cut in results and when shared.',
                'اختصر المسار إلى الكلمات المهمة.', 'Shorten the path to the important words.'],
            'META-01' => [self::CRITICAL, 'titles', 'الصفحة بلا عنوان', 'Page without a title',
                'بلا <title> تعرض محركات البحث عنوانًا من اختيارها.', 'Without a <title>, search engines show a title of their own choosing.',
                'أضف <title> واضحًا لكل صفحة.', 'Add a clear <title> to every page.'],
            'META-02' => [self::WARNING, 'titles', 'الصفحة بلا وصف', 'Page without a description',
                'بلا وصف تعرض محركات البحث مقتطفًا من الصفحة قد لا يشجّع على النقر.', 'Without a description, search engines show a snippet from the page that may not invite the click.',
                'أضف <meta name="description"> من 70 إلى 155 حرفًا.', 'Add a <meta name="description"> of 70 to 155 characters.'],
            'META-03' => [self::WARNING, 'titles', 'عنوان مكرر في صفحات أخرى', 'Title duplicated on other pages',
                'عدة صفحات تحمل العنوان نفسه، فتتنافس على الظهور نفسه.', 'Several pages share the same title, so they compete for the same result.',
                'اجعل عنوان كل صفحة مختلفًا ويصف محتواها.', 'Give each page a different title that describes its content.'],
            'META-04' => [self::INFO, 'titles', 'وصف مكرر في صفحات أخرى', 'Description duplicated on other pages',
                'عدة صفحات تحمل الوصف نفسه.', 'Several pages share the same description.',
                'اكتب وصفًا خاصًا بكل صفحة.', 'Write a description specific to each page.'],
            'H1-01' => [self::WARNING, 'titles', 'الصفحة بلا عنوان رئيسي', 'Page without an H1',
                'لا يوجد <h1>، فلا تعرف محركات البحث موضوع الصفحة الأول.', 'There is no <h1>, so search engines don\'t know the page\'s main topic.',
                'أضف <h1> واحدًا يحمل موضوع الصفحة.', 'Add one <h1> that carries the page topic.'],
            'H1-02' => [self::INFO, 'titles', 'أكثر من عنوان رئيسي', 'More than one H1',
                'الصفحة فيها أكثر من <h1>.', 'The page has more than one <h1>.',
                'اترك <h1> واحدًا واجعل الباقي <h2>.', 'Keep one <h1> and make the rest <h2>.'],
            'IDX-01' => [self::CRITICAL, 'indexing', 'الصفحة ممنوعة من الأرشفة', 'Page blocked from indexing',
                'الصفحة تحمل noindex، فلن تظهر في نتائج البحث.', 'The page carries noindex, so it won\'t appear in search results.',
                'احذف noindex إن كانت الصفحة مقصودة للظهور في البحث.', 'Remove noindex if the page is meant to appear in search.'],
            'IDX-02' => [self::WARNING, 'indexing', 'الصفحة بلا رابط أساسي', 'Page without a canonical',
                'بلا canonical قد تُعدّ نسخ الرابط (مع معاملات أو شرطة مائلة) صفحات مختلفة.', 'Without a canonical, URL variants (with parameters or a trailing slash) may count as different pages.',
                'أضف <link rel="canonical"> يشير إلى الرابط المفضّل.', 'Add a <link rel="canonical"> pointing to the preferred URL.'],
            'IDX-03' => [self::WARNING, 'indexing', 'الرابط الأساسي يشير إلى صفحة أخرى', 'Canonical points elsewhere',
                'الصفحة تعلن أن نسختها الأساسية صفحة أخرى، فلن تُؤرشف هي نفسها.', 'The page declares another page as its canonical, so it won\'t be indexed itself.',
                'تأكد أن هذا مقصود؛ وإلا اجعل canonical يشير إلى الصفحة نفسها.', 'Make sure this is intended; otherwise point the canonical to the page itself.'],
            'LINK-01' => [self::WARNING, 'links', 'رابط داخلي معطّل', 'Broken internal link',
                'تشير الصفحة إلى رابط داخلي يجيب بخطأ (404 أو أعلى).', 'The page links to an internal URL that answers with an error (404 or higher).',
                'صحّح الرابط أو وجّهه إلى الصفحة الجديدة.', 'Fix the link or redirect it to the new page.'],
            'LINK-02' => [self::INFO, 'links', 'رابط داخلي يمر بتحويل', 'Internal link goes through a redirect',
                'الرابط يُحوَّل قبل الوصول إلى الصفحة، فيبطئ الزيارة قليلًا.', 'The link redirects before reaching the page, which slows the visit a little.',
                'اربط مباشرة بالرابط النهائي.', 'Link straight to the final URL.'],
            'IMG-01' => [self::INFO, 'links', 'صور بلا نص بديل', 'Images without alt text',
                'صور في الصفحة بلا سمة alt (الصور الزخرفية تكفيها alt="").', 'Images on the page have no alt attribute (decorative images only need alt="").',
                'أضف alt يصف الصورة، أو alt="" للصور الزخرفية.', 'Add an alt that describes the image, or alt="" for decorative images.'],
            'STATUS-01' => [self::CRITICAL, 'links', 'الصفحة لا تفتح', 'Page doesn\'t open',
                'الصفحة أجابت بخطأ (4xx أو 5xx).', 'The page answered with an error (4xx or 5xx).',
                'أصلح الصفحة أو احذف الروابط إليها.', 'Fix the page or remove the links to it.'],
            'SCHEMA-01' => [self::INFO, 'schema', 'لا بيانات منظّمة', 'No structured data',
                'الصفحة بلا JSON-LD، فتفوتها عناصر نتائج البحث (أسئلة، منتجات، مقالات).', 'The page has no JSON-LD, so it misses search result features (FAQs, products, articles).',
                'أضف JSON-LD مناسبًا لنوع الصفحة.', 'Add JSON-LD that fits the page type.'],
            'SCHEMA-02' => [self::WARNING, 'schema', 'بيانات منظّمة غير صالحة', 'Invalid structured data',
                'كتلة JSON-LD في الصفحة لا تُقرأ (خطأ في الصيغة).', 'A JSON-LD block on the page can\'t be parsed (syntax error).',
                'صحّح صيغة JSON (فاصلة زائدة، علامة اقتباس ناقصة).', 'Fix the JSON syntax (a stray comma, a missing quote).'],
            'SCHEMA-03' => [self::INFO, 'schema', 'تاريخ غير معياري في البيانات المنظّمة', 'Non-standard date in structured data',
                'حقل تاريخ في JSON-LD ليس بصيغة ISO 8601 (مثل تاريخ هجري أو نص عربي).', 'A date field in the JSON-LD is not ISO 8601 (for example a Hijri or Arabic-text date).',
                'اكتب التواريخ بصيغة YYYY-MM-DD في البيانات المنظّمة واعرض الهجري في النص.', 'Write dates as YYYY-MM-DD in structured data and show the Hijri date in the text.'],
            // A2 speed lab (E2-062): read from the HTML, so they name the cause, not a measured time.
            'SPEED-01' => [self::WARNING, 'speed', 'سكربت يوقف عرض الصفحة', 'Render-blocking script',
                'سكربت خارجي في <head> بلا defer أو async، فيتوقف المتصفح عن رسم الصفحة حتى يحمّله وينفّذه.', 'An external script in <head> has no defer or async, so the browser stops drawing the page until it loads and runs it.',
                'أضف defer (أو async للسكربتات المستقلة) أو انقل السكربت إلى نهاية <body>.', 'Add defer (or async for independent scripts) or move the script to the end of <body>.'],
            'SPEED-02' => [self::INFO, 'speed', 'ملفات تنسيق كثيرة توقف العرض', 'Many render-blocking stylesheets',
                'أكثر من ملفَّي تنسيق في <head> تُحمَّل قبل أول رسم للصفحة.', 'More than two stylesheets in <head> load before the first paint.',
                'ادمج ملفات التنسيق، وضع التنسيق الحرج داخل الصفحة، وحمّل الباقي لاحقًا.', 'Merge stylesheets, inline the critical CSS and load the rest later.'],
            'SPEED-03' => [self::INFO, 'speed', 'الصورة الأولى بلا أبعاد', 'First image without dimensions',
                'أول صورة في الصفحة بلا width و height، فتقفز المحتويات عند تحميلها (CLS).', 'The first image on the page has no width and height, so content jumps when it loads (CLS).',
                'أضف width و height (أو aspect-ratio في التنسيق) إلى الصورة.', 'Add width and height (or a CSS aspect-ratio) to the image.'],
            'SPEED-04' => [self::INFO, 'speed', 'محتوى مضمَّن ثقيل', 'Heavy inline payload',
                'سكربتات وتنسيقات وصور مضمّنة (data:) داخل الصفحة تتجاوز 100 كيلوبايت، فتبطئ أول تحميل ولا تُخزَّن.', 'Inline scripts, styles and data: images inside the page exceed 100 KB, which slows the first load and can\'t be cached.',
                'انقل السكربتات والتنسيقات الكبيرة إلى ملفات خارجية، والصور إلى ملفات صور.', 'Move large scripts and styles to external files, and images to image files.'],
        ];
    }

    /** @return array{level: string, category: string, title: string, why: string, fix: string} */
    public static function describe(string $code, bool $ar): array
    {
        $r = self::rules()[$code] ?? [self::INFO, 'links', $code, $code, '', '', '', ''];

        return ['level' => $r[0], 'category' => $r[1], 'title' => $r[$ar ? 2 : 3], 'why' => $r[$ar ? 4 : 5], 'fix' => $r[$ar ? 6 : 7]];
    }

    public static function level(string $code): string
    {
        return self::rules()[$code][0] ?? self::INFO;
    }

    public static function category(string $code): string
    {
        return self::rules()[$code][1] ?? 'links';
    }

    public static function levelLabel(string $level, bool $ar): string
    {
        return match ($level) {
            // D-274 (UX-267): the D-88 words on every screen, never «خطأ / ملاحظة».
            self::CRITICAL => $ar ? 'حرج' : 'Critical',
            self::WARNING => $ar ? 'تحذير' : 'Warning',
            default => $ar ? 'للعلم' : 'Info',
        };
    }
}
