@php
    // Home: Package G rev 2 + D-241 (Ads + SEO, in development) + High-Contrast Command Canvas Architecture (E2-031)
    $lang = request()->segment(1) === 'en' ? 'en' : 'ar';
    $ar = $lang === 'ar';
    $arPath = '/';
    $u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
    $title = $ar ? 'منصة الإعلانات والسيو للوكالات بالعربية | AdsRanger' : 'Arabic-First Ads and SEO Platform for Agencies | AdsRanger'; // D-241
    $meta = $ar
        ? 'منصة عربية أولًا لوكالات الإعلان في المنطقة: إعلانات كل عملائها، وظهورهم في البحث، وتقاريرهم في مكان واحد. ما زالت AdsRanger قيد التطوير، والإطلاق في 2027.'
        : 'AdsRanger is an Arabic-first platform for MENA agencies: every client\'s ads, search visibility and reports in one place. In development; launching 2027.';
    $b = fn (string $s) => '<bdi dir="ltr">'.$s.'</bdi>';

    $faqs = $ar ? [
        ['متى تُطلق AdsRanger؟', $b('AdsRanger').' قيد التطوير، والإطلاق في 2027. انضم إلى قائمة الإطلاق الآن.'],
        ['كم السعر؟', 'تُعلن الأسعار عند الإطلاق. ويمكنك أن ترى ما تخطط كل باقة لتقديمه في <a href="'.$u('/pricing').'">صفحة الباقات</a>.'],
        ['ما المنصات الإعلانية المخطط ربطها؟', 'في المرحلة الأولى: '.$b('Meta').'، '.$b('Google Ads').'، '.$b('TikTok').'، '.$b('Snapchat').'، '.$b('Microsoft Advertising').'، '.$b('LinkedIn').'، '.$b('X').'. ويُفتح كل تكامل بعد موافقة المنصة على تطبيقنا.'],
        ['هل يمكن أن تعدّل AdsRanger حملات عملائي؟', 'تطلب بعض المنصات صلاحية إدارة عند الربط، لكن '.$b('AdsRanger').' لا تغيّر أي ميزانية أو حملة دون موافقة أحد أعضاء فريقك. والتقارير تقرأ البيانات فقط، واستوديو الإعلانات المخطط له لن ينشر أي شيء إلا بعد موافقتك.'],
        ['لمن صُممت AdsRanger؟', 'لوكالات الإعلان في المنطقة التي تدير إعلانات عملاء كثيرين، وللمستقلين عبر باقة المستقل.'],
        ['هل تشمل AdsRanger أدوات للسيو؟', 'نعم، وهي قيد التطوير: فحص المواقع، والكلمات المفتاحية، وتتبّع الترتيب، وإعلانات المنافسين في البحث، بالعربية أولًا. نعلن موعد كل أداة عند جاهزيتها.'],
    ] : [
        ['When does AdsRanger launch?', 'AdsRanger is in development and launching in 2027. Join the launch list now.'],
        ['How much will it cost?', 'Prices are shared at launch. You can see what each plan is planned to include on the <a href="'.$u('/pricing').'">plans page</a>.'],
        ['Which ad platforms are planned?', 'In the first stage: Meta, Google Ads, TikTok, Snapchat, Microsoft Advertising, LinkedIn and X. Each integration opens after the platform approves our app.'],
        ['Can AdsRanger change my clients\' campaigns?', 'Some platforms ask for management permission when you connect, but AdsRanger changes no budget or campaign without a team member\'s approval. Reports only read data, and the planned Ads Studio will publish nothing without your approval.'],
        ['Who is AdsRanger for?', 'Ad agencies in the region that run ads for many clients, and freelancers on the Solo plan.'],
        ['Does AdsRanger include SEO tools?', 'Yes, in development: site checks, keyword research, rank tracking and competitors\' search ads, Arabic first. We\'ll announce each tool when it\'s ready.'],
    ];
    $schema = [
        ['@type' => 'Organization', 'name' => 'AdsRanger', 'url' => 'https://adsranger.com/', 'logo' => 'https://adsranger.com/brand/icon-512.png'],
        ['@type' => 'WebSite', 'name' => 'AdsRanger', 'url' => \App\Support\PublicUrl::absolute('/', $lang), 'inLanguage' => $lang],
        ['@type' => 'FAQPage', 'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(strip_tags($f[1]))]], $faqs)],
    ];

    $rows = $ar ? [
        ['كل منصة تحسب النتائج بطريقتها.', 'نتيجة واحدة لكل هدف.', 'تُحسب نتيجة كل حملة حسب هدفها: الرسائل، المكالمات، العملاء المحتملون، الحجوزات، المبيعات، ثم تُجمع نتائج كل المنصات الإعلانية للعميل في عرض واحد.', ['/demo-report', 'شاهدها في التقرير التجريبي']],
        ['الهدر يُكتشف بعد أيام.', 'المكتب اليومي وتنبيهات الصباح.', 'التتبّع المعطّل، والإنفاق دون نتائج، وضعف التسليم، وارتفاع التكلفة، تظهر لك كل صباح مرتبة حسب الأولوية.', null],
        ['تقارير العملاء تُعدّ يدويًا.', 'روابط وتقارير جاهزة للعملاء.', 'رابط للقراءة فقط يفتحه العميل دون حساب، وتقرير شهري بالعربية أو الإنجليزية يحمل شعار وكالتك.', ['/demo-report', 'شاهد تقريرًا تجريبيًا']],
        ['لا تعرف أين يظهر عملاؤك في البحث.', 'فحص المواقع وتتبّع الظهور في البحث.', 'تفحص AdsRanger مواقع عملائك بالعربية والإنجليزية، وتعرض الكلمات المفتاحية التي يبحث بها جمهورهم، وترتيبهم في نتائج البحث حسب الدولة، وإعلانات المنافسين في البحث.', null],
    ] : [
        ['Every platform counts results its own way.', 'One result per goal.', 'Each campaign is counted by its goal: messages, calls, leads, bookings or sales, then all of a client\'s ad platforms come together in one view.', ['/demo-report', 'See it in the sample report']],
        ['Wasted spend is found days late.', 'A daily desk and morning alerts.', 'Broken tracking, spend with no results, under-delivery and rising costs, shown every morning in order of priority.', null],
        ['Client reports are built by hand.', 'Client-ready links and reports.', 'A read-only link your client opens without an account, and a monthly report in Arabic or English with your agency\'s logo.', ['/demo-report', 'See a sample report']],
        ['You can\'t see where your clients show up in search.', 'Site checks and search visibility.', 'AdsRanger checks your clients\' sites in Arabic and English, shows the keywords their audience searches for, their search rankings by country, and competitors\' search ads.', null],
    ];
    $more = $ar ? [
        ['مؤشر الصحة والمستهدفات', 'تقييم كل عميل من 0 إلى 100 مقابل الميزانية وتكلفة النتيجة والمستهدفات.', 'قيد التطوير', false],
        ['استوديو الإعلانات', 'أنشئ الحملات ونصوص الإعلانات لعدة منصات من موجز واحد، بموافقتك قبل نشر أي شيء.', 'مخطط له', true],
    ] : [
        ['Health score and targets', 'Each client scored from 0 to 100 against budget, cost per result and targets.', 'In development', false],
        ['Ads Studio', 'Build campaigns and ad copy for several platforms from one brief, with your approval before anything is published.', 'Planned', true],
    ];
    $steps = $ar ? [
        ['أضف حسابات عملائك الإعلانية', 'بعد أن تعتمد كل منصة تطبيقنا، ولا يتغيّر شيء فيها دون موافقتك.'],
        ['تابع النتائج حسب الهدف', 'في المكتب اليومي كل صباح، مع تنبيهات بما يحتاج إلى تدخل.'],
        ['شارك عملاءك', 'رابطًا للقراءة فقط أو تقريرًا شهريًا بالعربية أو الإنجليزية.'],
    ] : [
        ['Add your clients\' ad accounts', 'once each platform approves our app; nothing in them changes without your approval.'],
        ['Follow results by goal', 'on the daily desk every morning, with alerts for what needs attention.'],
        ['Share with your clients', 'a read-only link or a monthly report in Arabic or English.'],
    ];
    $mena = $ar ? [
        ['العربية أولًا.', 'كل شاشة وتقرير بالعربية أولًا، مع نسخة إنجليزية.'],
        ['مواسم المنطقة ومناسباتها.', 'التقويم الهجري ورمضان والعيد ومواسم البيع في تخطيط كل عميل.'],
        ['عملات المنطقة.', 'تُحوَّل نتائج كل عميل يوميًا إلى عملة واحدة يختارها، حتى لو اختلفت عملات حساباته الإعلانية.'],
    ] : [
        ['Arabic first.', 'Every screen and report in Arabic first, with an English version.'],
        ['The region\'s seasons and occasions.', 'The Hijri calendar, Ramadan, Eid and sales seasons in every client\'s planning.'],
        ['Regional currencies.', 'Each client\'s results are converted daily into one currency of your choice, even when its ad accounts use different currencies.'],
    ];
    $trust = $ar ? [
        ['لا تغيير دون موافقتك.', 'لا تغيّر AdsRanger الميزانيات أو الحملات دون موافقة أحد أعضاء فريقك، والتقارير تقرأ البيانات فقط.'],
        ['فصل بيانات كل وكالة.', 'ولا يرى أعضاء فريقك إلا العملاء المسندين إليهم.'],
        ['تحت تصرفك.', 'افصل أي حساب، أو صدّر تقاريرك، أو اطلب حذف بياناتك.'],
    ] : [
        ['Nothing changes without your approval.', 'AdsRanger changes no budget or campaign without a team member\'s approval; reports only read data.'],
        ['Each agency kept apart.', 'Team members see only the clients they\'re given.'],
        ['Your control.', 'Disconnect any account, export your reports or ask us to delete your data.'],
    ];
    $networks = ['Meta', 'Google Ads', 'TikTok', 'Snapchat', 'Microsoft Advertising', 'LinkedIn', 'X'];

    // Next 3 occasions (D-95 data); Ramadan 2027 always among them until it starts (D-234).
    $occ = \App\Support\PublicSite::nextOccasions(3);
    $ramadan = collect(config('mena_calendar_2027.rows', []))->firstWhere('id', 'A2');
    if ($ramadan && $ramadan['date'] >= now('Asia/Amman')->toDateString() && ! collect($occ)->contains('id', 'A2')) {
        $occ = array_merge(array_slice($occ, 0, 2), [$ramadan]);
    }
    $months = $ar
        ? ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر']
        : ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $day = function (string $iso) use ($months): array {
        [$y, $m, $d] = array_map('intval', explode('-', $iso));

        return [$d, $months[$m - 1], $y];
    };

    $demo = $ar ? [
        'tabs' => ['النتائج حسب الهدف', 'تنبيهات الصباح', 'تقرير العميل'],
        'sample' => 'بيانات تجريبية', 'client' => 'متجر أثاث (عميل تجريبي)', 'period' => 'آخر 7 أيام',
        'goals' => [['الرسائل', '412', '1.20 USD'], ['المكالمات', '96', '4.80 USD'], ['العملاء المحتملون', '238', '2.35 USD'], ['المبيعات', '63', '9.10 USD']],
        'cols' => ['الهدف', 'النتائج', 'تكلفة النتيجة'], 'health' => 'مؤشر الصحة',
        'alerts' => [['حرج', 'تتبّع معطّل: لا تحويلات من حملة المبيعات منذ أمس'], ['تحذير', 'ارتفاع التكلفة: تكلفة الرسالة أعلى بـ 38% من المعتاد'], ['متابعة', 'إنفاق دون نتائج: مجموعة إعلانية أنفقت 120 USD دون نتيجة']],
        'report' => [['رابط للقراءة فقط', 'يفتحه العميل دون حساب'], ['تقرير شهري', 'بالعربية أو الإنجليزية، بشعار وكالتك'], ['ملخص الشهر', 'النتائج حسب الهدف مقارنة بالشهر السابق']],
        'alt' => 'نموذج للمكتب اليومي ببيانات تجريبية: النتائج حسب الهدف وتنبيهات الصباح وتقرير العميل',
    ] : [
        'tabs' => ['Results by goal', 'Morning alerts', 'Client report'],
        'sample' => 'Sample data', 'client' => 'Furniture store (sample client)', 'period' => 'Last 7 days',
        'goals' => [['Messages', '412', '1.20 USD'], ['Calls', '96', '4.80 USD'], ['Leads', '238', '2.35 USD'], ['Sales', '63', '9.10 USD']],
        'cols' => ['Goal', 'Results', 'Cost per result'], 'health' => 'Health score',
        'alerts' => [['Critical', 'Broken tracking: no conversions from the sales campaign since yesterday'], ['Warning', 'Rising cost: cost per message 38% above usual'], ['Watch', 'Spend with no results: an ad set spent 120 USD with no result']],
        'report' => [['Read-only link', 'Your client opens it without an account'], ['Monthly report', 'In Arabic or English, with your agency\'s logo'], ['Month summary', 'Results by goal against the month before']],
        'alt' => 'Daily desk sample with demo data: results by goal, morning alerts and the client report',
    ];
@endphp
@extends('layouts.public')

@push('styles')
<style>
/* High-Contrast SaaS Command Hero + Elevated Action Bar + Overlapping Showcase Board */
.hp-hero{background:radial-gradient(980px 440px at 50% 0%,rgba(109,40,217,.34),transparent 70%),radial-gradient(760px 360px at 85% 20%,rgba(45,212,191,.18),transparent 65%),linear-gradient(180deg,#0b1120 0%,#171538 58%,#0f172a 100%);color:#f8fafc;padding:56px 0 120px;border-bottom:1px solid #1e293b;text-align:center;position:relative}
.hp-hero-in{max-width:920px;margin:0 auto}
.hp-status{display:inline-flex;align-items:center;gap:8px;padding:6px 16px;border-radius:99px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.18);color:#5eead4;font-size:13.5px;font-weight:700;margin:0 0 20px;box-shadow:0 4px 16px rgba(0,0,0,.3)}
.hp-status::before{content:"";width:8px;height:8px;border-radius:99px;background:#14b8a6;box-shadow:0 0 10px #14b8a6}
.hp-hero h1{color:#ffffff;font-size:clamp(34px,5vw,54px);line-height:1.18;letter-spacing:-.02em;margin:0 auto 18px;max-width:840px}
.hp-hero .lead{color:#cbd5e1;font-size:clamp(16.5px,2vw,19.5px);line-height:1.8;margin:0 auto 32px;max-width:780px}
/* Elevated White Command Action Bar */
.hp-cmdbar{max-width:740px;margin:0 auto 16px;background:#ffffff;border-radius:16px;padding:10px;display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;box-shadow:0 24px 54px -12px rgba(0,0,0,.6),0 0 0 1px rgba(255,255,255,.2)}
.hp-cmd-info{display:flex;align-items:center;gap:10px;padding:8px 14px;color:#0f172a;font-size:14.5px;font-weight:700;flex:1;min-width:200px;text-align:start}
.hp-cmd-dot{width:10px;height:10px;border-radius:99px;background:#0f766e;flex-shrink:0;box-shadow:0 0 0 4px rgba(15,118,110,.16)}
.hp-ctas{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.hp-ctas .btn{min-height:48px;padding-inline:24px;font-size:15.5px;border-radius:11px;font-weight:700}
.hp-ctas .btn-main{background:#5eead4;color:#0b1120;box-shadow:0 10px 24px -6px rgba(20,184,166,.5)}
.hp-ctas .btn-main:hover{background:#2dd4bf;color:#0b1120}
.hp-ctas .btn-sec{background:#f1f5f9;color:#0f172a;border:1px solid #cbd5e1}
.hp-ctas .btn-sec:hover{background:#e2e8f0;color:#0f172a}
/* Overlapping Interactive Product Showcase Board */
.hp-showcase-wrap{max-width:1160px;margin:-84px auto 0;padding-inline:16px;position:relative;z-index:10}
.dm{background:var(--card);color:var(--ink);border:1px solid var(--line);border-radius:20px;box-shadow:0 32px 72px -18px rgba(15,23,42,.42);overflow:hidden;margin:0}
.dm-bar{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:12px 18px;background:var(--surface);border-bottom:1px solid var(--line);font-size:13px}
.dm-bar .dots{display:flex;gap:6px}.dm-bar .dots i{width:11px;height:11px;border-radius:99px;background:var(--line)}
.dm-bar .dots i:nth-child(1){background:#f87171}
.dm-bar .dots i:nth-child(2){background:#fbbf24}
.dm-bar .dots i:nth-child(3){background:#34d399}
.dm-tag{font-weight:700;color:var(--info-ink);background:var(--info-bg);border-radius:99px;padding:3px 12px;font-size:12px}
.dm fieldset{border:0;margin:0;padding:0;min-width:0}
.dm legend{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}
.dm-tabs{display:flex;flex-wrap:wrap;gap:8px;padding:12px 18px;background:var(--surface);border-bottom:1px solid var(--line)}
.dm-r{position:absolute;opacity:0;width:1px;height:1px;pointer-events:none}
.dm-tabs label{display:inline-flex;align-items:center;gap:8px;min-height:44px;padding:8px 16px;margin:0;font-weight:700;font-size:14px;color:var(--ink);background:var(--card);border:1px solid var(--line);border-radius:10px;cursor:pointer;transition:all .15s ease}
.dm-tabs label b{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:6px;background:rgba(20,184,166,.14);color:#0f766e;font-size:11.5px;font-weight:700}
.dm-tabs label:hover{border-color:var(--acc)}
#dm-t1:checked~.dm-tabs label[for=dm-t1],#dm-t2:checked~.dm-tabs label[for=dm-t2],#dm-t3:checked~.dm-tabs label[for=dm-t3]{background:#0f172a;color:#ffffff;border-color:#0f172a;box-shadow:0 6px 16px -4px rgba(15,23,42,.25)}
#dm-t1:checked~.dm-tabs label[for=dm-t1] b,#dm-t2:checked~.dm-tabs label[for=dm-t2] b,#dm-t3:checked~.dm-tabs label[for=dm-t3] b{background:#14b8a6;color:#ffffff}
#dm-t1:focus-visible~.dm-tabs label[for=dm-t1],#dm-t2:focus-visible~.dm-tabs label[for=dm-t2],#dm-t3:focus-visible~.dm-tabs label[for=dm-t3]{outline:2px solid var(--acc);outline-offset:2px}
.dm-p{display:none;padding:24px}
#dm-t1:checked~.dm-ps .dm-p1,#dm-t2:checked~.dm-ps .dm-p2,#dm-t3:checked~.dm-ps .dm-p3{display:block}
.dm-board{display:grid;grid-template-columns:1fr;gap:20px;align-items:stretch}
@media (min-width:900px){.dm-board{grid-template-columns:280px 1fr}}
.dm-gauge-card{background:var(--surface);border:1px solid var(--line);border-radius:14px;padding:18px;display:flex;flex-direction:column;justify-content:space-between;gap:14px}
.dm-gauge-wrap{display:flex;align-items:center;gap:16px}
.dm-donut{width:84px;height:84px;flex-shrink:0}
.dm-main-card{background:var(--surface);border:1px solid var(--line);border-radius:14px;padding:18px}
.dm-head{display:flex;justify-content:space-between;align-items:baseline;gap:8px;margin-bottom:12px;font-size:13px;color:var(--muted)}
.dm-head strong{color:var(--ink);font-size:15px}
.dm table{width:100%;border-collapse:collapse;font-size:14px;background:var(--card);border-radius:10px;overflow:hidden;border:1px solid var(--line)}
.dm th{font-size:12px;color:var(--muted);font-weight:700;text-align:start;padding:10px 12px;background:var(--surface);border-bottom:1px solid var(--line)}
.dm td{padding:11px 12px;border-bottom:1px solid var(--line);font-variant-numeric:tabular-nums;font-weight:600}
.dm td:not(:first-child),.dm th:not(:first-child){text-align:end}
.dm-health{display:flex;align-items:center;gap:12px;margin-top:8px;font-size:13px;color:var(--muted)}
.dm-health b{font-size:28px;color:var(--ink);line-height:1}
.dm-meter{flex:1;height:8px;border-radius:99px;background:var(--neu-bg);overflow:hidden}.dm-meter i{display:block;height:100%;width:78%;background:var(--acc)}
.dm-al{list-style:none;margin:0;padding:0;display:grid;gap:10px}
.dm-al li{display:flex;gap:12px;align-items:flex-start;padding:12px 14px;background:var(--card);border:1px solid var(--line);border-inline-start:4px solid var(--acc);border-radius:10px;font-size:14px}
.dm-al li.crit{border-inline-start-color:var(--bad)}
.dm-al b{white-space:nowrap}
.dm-rep{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
.dm-rep li{padding:14px 16px;background:var(--card);border:1px solid var(--line);border-top:3px solid var(--acc);border-radius:12px;font-size:14px}
.dm-rep span{display:block;color:var(--muted);font-size:13px;margin-top:4px}
/* platforms strip */
.hp-nets{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px 18px;margin-top:28px;padding:16px 22px;background:var(--surface);border:1px solid var(--line);border-radius:14px;color:var(--muted);font-size:14px}
.hp-nets ul{list-style:none;display:flex;flex-wrap:wrap;gap:8px;margin:0;padding:0}
.hp-nets li{color:var(--ink);font-weight:700;white-space:nowrap;background:var(--card);border:1px solid var(--line);padding:5px 12px;border-radius:8px;display:inline-flex;align-items:center;gap:6px}
.hp-nets li::before{content:"";width:7px;height:7px;border-radius:99px;background:#14b8a6}
.hp-nets a{color:var(--acc);font-weight:700;display:inline-flex;align-items:center;min-height:44px}
/* sections */
.hp-sec{margin-top:64px}
.hp-sec>h2{font-size:clamp(24px,3.2vw,32px);margin:0 0 8px}
.hp-sec>.sub{color:var(--muted);margin:0 0 24px;max-width:720px}
.pa{list-style:none;margin:0;padding:0;display:grid;gap:16px;counter-reset:pa}
.pa li{counter-increment:pa;display:grid;gap:14px;border:1px solid var(--line);border-top:3px solid var(--acc);border-radius:16px;padding:20px;background:var(--card);box-shadow:0 6px 20px -8px rgba(15,23,42,.07)}
@media (min-width:768px){.pa li{grid-template-columns:1fr 1.6fr;align-items:center;padding:22px 26px}}
.pa h3{font-size:16px;font-weight:600;color:var(--muted);margin:0;display:flex;align-items:center;gap:10px}
.pa h3::before{content:"0" counter(pa);display:inline-flex;justify-content:center;align-items:center;width:30px;height:30px;flex-shrink:0;border-radius:8px;background:rgba(20,184,166,.14);color:#0f766e;font-size:12.5px;font-weight:700}
.pa .ans{border-inline-start:3px solid var(--acc);padding-inline-start:16px}
.pa .ans strong{display:block;font-size:17.5px;margin-bottom:4px}
.pa .ans p{margin:0;color:var(--muted)}
.pa .ans a{font-weight:700}
.more{list-style:none;margin:16px 0 0;padding:0;display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(min(280px,100%),1fr))}
.more li{border:1px solid var(--line);border-inline-start:4px solid #14b8a6;background:var(--surface);border-radius:14px;padding:18px 20px}
.more h3{font-size:16.5px;margin:0 0 6px;display:flex;justify-content:space-between;gap:8px;align-items:center}
.more p{margin:0;color:var(--muted);font-size:14.5px}
@media (max-width:479px){.more{display:none}}
.steps{list-style:none;margin:0;padding:0;display:grid;gap:16px;counter-reset:s;grid-template-columns:repeat(auto-fit,minmax(min(260px,100%),1fr))}
.steps li{counter-increment:s;border:1px solid var(--line);border-radius:16px;padding:22px;background:var(--card);box-shadow:0 4px 16px -6px rgba(15,23,42,.06)}
.steps li::before{content:"0" counter(s);display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:10px;background:#0f172a;color:#5eead4;font-weight:700;margin-bottom:14px}
.steps strong{display:block;font-size:17.5px;margin-bottom:6px}
.steps span{color:var(--muted)}
.two{display:grid;gap:20px}
@media (min-width:900px){.two{grid-template-columns:1fr 1fr;gap:28px}}
.ticks{list-style:none;margin:0;padding:0;display:grid;gap:12px}
.ticks li{padding-inline-start:30px;position:relative}
.ticks li::before{content:"";position:absolute;inset-inline-start:0;top:.45em;width:18px;height:18px;border-radius:99px;background:var(--info-bg);box-shadow:inset 0 0 0 5px var(--info-bg),inset 0 0 0 9px var(--acc)}
.ticks strong{display:block}
.ticks span{color:var(--muted)}
.occ{border:1px solid var(--line);border-radius:16px;background:var(--card);overflow:hidden;box-shadow:0 6px 20px -8px rgba(15,23,42,.07)}
.occ h3{margin:0;padding:14px 18px;background:#0f172a;color:#fff;border-bottom:1px solid #1e293b;font-size:16px}
.occ ol{list-style:none;margin:0;padding:0}
.occ li{display:flex;gap:14px;align-items:center;padding:12px 18px;border-bottom:1px solid var(--line)}
.occ .d{flex:none;width:58px;text-align:center;border-radius:10px;background:var(--info-bg);color:var(--info-ink);padding:6px 0;line-height:1.2}
.occ .d b{display:block;font-size:20px}.occ .d span{font-size:12px}
.occ .e strong{display:block}.occ .e span,.occ .e a{font-size:14px}
.occ .all{display:flex;align-items:center;min-height:48px;padding:0 18px;font-weight:700}
.trust{list-style:none;margin:0;padding:0;display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(min(260px,100%),1fr))}
.trust li{border:1px solid var(--line);border-top:3px solid #0f172a;border-radius:16px;padding:22px;background:var(--card);box-shadow:0 4px 16px -6px rgba(15,23,42,.06)}
.trust strong{display:block;font-size:17px;margin-bottom:6px}
.trust span{color:var(--muted)}
.faq details{border:1px solid var(--line);border-radius:14px;background:var(--card);margin-bottom:10px}
.faq summary{display:flex;justify-content:space-between;align-items:center;gap:12px;min-height:56px;padding:12px 18px;cursor:pointer;list-style:none}
.faq summary::-webkit-details-marker{display:none}
.faq summary::after{content:"+";font-size:22px;color:var(--acc);flex:none}
.faq details[open] summary::after{content:"−"}
.faq h3{font-size:16.5px;margin:0}
.faq p{margin:0;padding:0 18px 16px;color:var(--muted)}
.band{margin-top:64px;border-radius:20px;padding:28px 24px;border:1px solid var(--line);background:var(--surface);display:flex;flex-wrap:wrap;gap:16px;align-items:center;justify-content:space-between}
.band h2{margin:0 0 6px;font-size:clamp(20px,3vw,26px)}
.band p{margin:0;color:var(--muted);max-width:640px}
.hp-close{margin-top:24px;border-radius:20px;padding:42px 24px;background:radial-gradient(700px 280px at 50% 0%,rgba(109,40,217,.28),transparent 70%),linear-gradient(180deg,#0b1120 0%,#171538 100%);color:#f8fafc;text-align:center;border:1px solid #1e293b}
.hp-close h2{color:#fff;margin:0 0 8px;font-size:clamp(22px,3.4vw,30px)}
.hp-close p{color:#cbd5e1;margin:0 auto 22px;max-width:620px}
.hp-close .btn{background:#5eead4;color:#0b1120;min-height:50px;padding-inline:28px;font-weight:700;box-shadow:0 12px 28px -6px rgba(20,184,166,.5);border-radius:12px}
.hp-close .btn:hover{background:#2dd4bf}
</style>
@endpush

@section('top')
<section class="hp-hero" aria-labelledby="hp-h1">
    <div class="wrap hp-hero-in">
        <p class="hp-status">{{ $ar ? 'قيد التطوير · الإطلاق في 2027' : 'In development · launching 2027' }}</p>
        <h1 id="hp-h1">{{ $ar ? 'اعرف ما تحققه إعلانات كل عميل وظهوره في البحث، قبل أن يسألك' : 'Know what every client\'s ads and search visibility achieve, before they ask' }}</h1>
        <p class="lead">{{ $ar
            ? 'منصة عربية أولًا لإدارة إعلانات الوكالات وظهور عملائها في البحث: تحسب نتيجة كل حملة حسب هدفها، وتنبّهك كل صباح إلى الهدر، وتجهّز تقارير يفهمها عملاؤك بالعربية أو الإنجليزية. وأدوات البحث قيد التطوير أيضًا.'
            : 'An Arabic-first platform for agencies\' ads and their clients\' search visibility: it counts each campaign\'s result by its goal, flags wasted spend every morning, and prepares reports your clients understand, in Arabic or English. Search tools are in development too.' }}</p>

        <div class="hp-cmdbar">
            <div class="hp-cmd-info">
                <span class="hp-cmd-dot" aria-hidden="true"></span>
                <span>{{ $ar ? 'مساحة عمل للوكالات والمستقلين · الإعلانات والظهور في البحث' : 'Agency & freelancer workspace · Ads & search visibility' }}</span>
            </div>
            <div class="hp-ctas">
                <a class="btn btn-sec" href="{{ $u('/demo-report') }}" data-cta="sample_report" data-pos="hero">{{ $ar ? 'شاهد تقريرًا تجريبيًا' : 'See a sample report' }}</a>
                <a class="btn btn-main" href="{{ $u('/waitlist') }}?type=agency" data-cta="waitlist" data-pos="hero">{{ $ar ? 'انضم إلى قائمة الإطلاق' : 'Join the launch list' }}</a>
            </div>
        </div>
    </div>
</section>

<div class="hp-showcase-wrap">
    <figure class="dm" aria-label="{{ $demo['alt'] }}">
        <div class="dm-bar">
            <span class="dots" aria-hidden="true"><i></i><i></i><i></i></span>
            <strong style="font-size:13px">{{ $demo['client'] }} · {{ $demo['period'] }}</strong>
            <span class="dm-tag">{{ $demo['sample'] }}</span>
        </div>
        <fieldset>
            <legend>{{ $demo['alt'] }}</legend>
            @foreach ([1, 2, 3] as $n)<input class="dm-r" type="radio" name="dm" id="dm-t{{ $n }}" @checked($n === 1)>@endforeach
            <div class="dm-tabs">
                @foreach ($demo['tabs'] as $i => $tab)
                    <label for="dm-t{{ $i + 1 }}"><b>0{{ $i + 1 }}</b><span>{{ $tab }}</span></label>
                @endforeach
            </div>
            <div class="dm-ps">
                <div class="dm-p dm-p1">
                    <div class="dm-board">
                        <div class="dm-gauge-card">
                            <div class="dm-head"><strong>{{ $demo['health'] }}</strong><span>{{ $demo['sample'] }}</span></div>
                            <div class="dm-gauge-wrap">
                                <svg class="dm-donut" viewBox="0 0 36 36" aria-hidden="true">
                                    <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e2e8f0" stroke-width="3.6"/>
                                    <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#0f766e" stroke-width="3.6" stroke-dasharray="78, 100" stroke-linecap="round"/>
                                </svg>
                                <div>
                                    <div class="dm-health"><b>78</b><span>/ 100</span></div>
                                    <div style="font-size:12.5px;color:var(--muted);margin-top:4px">{{ $ar ? 'مقابل الميزانية وتكلفة النتيجة' : 'Against budget & cost per result' }}</div>
                                </div>
                            </div>
                            <div class="dm-meter" aria-hidden="true"><i></i></div>
                        </div>

                        <div class="dm-main-card">
                            <div class="dm-head"><strong>{{ $demo['client'] }}</strong><span>{{ $demo['period'] }}</span></div>
                            <table>
                                <thead><tr>@foreach ($demo['cols'] as $c)<th scope="col">{{ $c }}</th>@endforeach</tr></thead>
                                <tbody>@foreach ($demo['goals'] as [$g, $n, $c])<tr><td>{{ $g }}</td><td>{{ $n }}</td><td><bdi dir="ltr">{{ $c }}</bdi></td></tr>@endforeach</tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="dm-p dm-p2">
                    <div class="dm-head"><strong>{{ $demo['client'] }}</strong><span>{{ $ar ? 'صباح اليوم' : 'This morning' }}</span></div>
                    <ul class="dm-al">@foreach ($demo['alerts'] as $i => [$lvl, $txt])<li class="{{ $i === 0 ? 'crit' : '' }}"><b>{{ $lvl }}</b><span>{{ $txt }}</span></li>@endforeach</ul>
                </div>
                <div class="dm-p dm-p3">
                    <div class="dm-head"><strong>{{ $demo['client'] }}</strong><span>{{ $ar ? 'العربية · English' : 'English · العربية' }}</span></div>
                    <ul class="dm-rep">@foreach ($demo['report'] as [$h, $t])<li><strong>{{ $h }}</strong><span>{{ $t }}</span></li>@endforeach</ul>
                </div>
            </div>
        </fieldset>
    </figure>

    <div class="hp-nets">
        <span>{{ $ar ? 'المنصات الإعلانية التي نخطط لربطها' : 'Ad platforms we plan to connect' }}:</span>
        <ul>@foreach ($networks as $n)<li><bdi dir="ltr">{{ $n }}</bdi></li>@endforeach</ul>
        <a href="{{ $u('/integrations') }}">{{ $ar ? 'كل المنصات الإعلانية' : 'All ad platforms' }}</a>
    </div>
</div>
@endsection

@section('content')
<section class="hp-sec" aria-labelledby="h-pa">
    <h2 id="h-pa">{{ $ar ? 'ما يُبطئ الوكالات، وما نبنيه لحلّه' : 'What slows agencies down, and what we\'re building to fix it' }} <span class="badge">{{ $ar ? 'قيد التطوير' : 'In development' }}</span></h2>
    <p class="sub">{!! $ar ? 'ثلاث مشكلات نسمعها من كل وكالة، وما تبنيه '.$b('AdsRanger').' لحلّ كل منها.' : 'Three problems we hear from every agency, and what AdsRanger is building for each.' !!}</p>
    <ul class="pa">
        @foreach ($rows as [$problem, $answer, $text, $link])
            <li>
                <h3>{{ $problem }}</h3>
                <div class="ans">
                    <strong>{{ $answer }}</strong>
                    <p>{!! str_replace('AdsRanger', $b('AdsRanger'), e($text)) !!}@if ($link) <a href="{{ $u($link[0]) }}" data-cta="sample_report" data-pos="problem_row">{{ $link[1] }}</a>@endif</p>
                </div>
            </li>
        @endforeach
    </ul>
    <ul class="more">
        @foreach ($more as [$h, $p, $badge, $planned])
            <li><h3>{{ $h }} <span class="badge {{ $planned ? 'badge-neutral' : '' }}">{{ $badge }}</span></h3><p>{{ $p }}</p></li>
        @endforeach
    </ul>
</section>

<section class="hp-sec" aria-labelledby="h-how" id="how">
    <h2 id="h-how">{{ $ar ? 'كيف تعمل' : 'How it works' }} <span class="badge">{{ $ar ? 'قيد التطوير' : 'In development' }}</span></h2>
    <p class="sub">{{ $ar ? 'ثلاث خطوات من الربط إلى التقرير.' : 'Three steps from connecting to reporting.' }}</p>
    <ol class="steps">
        @foreach ($steps as [$s, $t])
            <li><strong>{{ $s }}</strong><span>{{ $t }}</span></li>
        @endforeach
    </ol>
</section>

<section class="hp-sec two" aria-labelledby="h-mena">
    <div>
        <h2 id="h-mena" style="font-size:clamp(24px,3.2vw,32px);margin:0 0 20px">{{ $ar ? 'مصمَّمة لأسواق المنطقة' : 'Built for MENA markets' }} <span class="badge">{{ $ar ? 'قيد التطوير' : 'In development' }}</span></h2>
        <ul class="ticks">
            @foreach ($mena as [$h, $p])
                <li><strong>{{ $h }}</strong><span>{{ $p }}</span></li>
            @endforeach
        </ul>
    </div>
    @if ($occ)
        <div class="occ">
            <h3>{{ $ar ? 'المناسبات القادمة في المنطقة' : 'Coming up in the region' }}</h3>
            <ol>
                @foreach ($occ as $o)
                    @php([$d, $mo, $y] = $day($o['date']))
                    <li>
                        <span class="d"><b>{{ $d }}</b><span>{{ $mo }}</span></span>
                        <span class="e">
                            <strong>{{ $ar ? $o['event_ar'] : $o['event_en'] }} {{ $y }}</strong>
                            @if ($o['id'] === 'A2')
                                <a href="{{ $u('/ramadan-2027') }}">{{ $ar ? 'دليل رمضان 2027' : 'Ramadan 2027 guide' }}</a> · <a href="{{ $u('/tools/ramadan-budget-calculator') }}">{{ $ar ? 'حاسبة الميزانية' : 'Budget calculator' }}</a>
                            @else
                                <span class="muted">{{ $o['status'] === 'fixed' ? ($ar ? 'موعد ثابت' : 'Fixed date') : ($ar ? 'موعد متوقع' : 'Expected date') }}</span>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ol>
            <a class="all" href="{{ $u('/mena-calendar') }}">{{ $ar ? 'كل مواسم 2027 في تقويم المواسم' : 'All 2027 seasons in the seasons calendar' }}</a>
        </div>
    @endif
</section>

<section class="hp-sec" aria-labelledby="h-trust">
    <h2 id="h-trust">{{ $ar ? 'بياناتك وبيانات عملائك' : 'Your data and your clients\' data' }}</h2>
    <p class="sub"><a href="{{ $u('/security') }}">{{ $ar ? 'كيف نحمي بياناتك' : 'How we protect your data' }}</a></p>
    <ul class="trust">
        @foreach ($trust as [$h, $p])
            <li><strong>{{ $h }}</strong><span>{!! $ar ? str_replace('AdsRanger', '<bdi dir="ltr">AdsRanger</bdi>', e($p)) : e($p) !!}</span></li>
        @endforeach
    </ul>
</section>

<section class="hp-sec faq" aria-labelledby="h-faq">
    <h2 id="h-faq">{{ $ar ? 'الأسئلة الشائعة' : 'Questions' }}</h2>
    @foreach ($faqs as [$q, $a])
        <details>
            <summary><h3>{!! $ar ? str_replace('AdsRanger', $b('AdsRanger'), e($q)) : e($q) !!}</h3></summary>
            <p>{!! $a !!}</p>
        </details>
    @endforeach
</section>

<section class="band" aria-labelledby="h-partners">
    <div>
        <h2 id="h-partners">{{ $ar ? 'هل تدير وكالة إعلانات في المنطقة؟' : 'Run an ad agency in the region?' }}</h2>
        <p>{{ $ar ? 'نختار عددًا قليلًا من الوكالات للعمل معنا قبل الإطلاق، بدءًا من الأردن ثم الخليج.' : 'We\'re choosing a small group of agencies to work with us before launch, starting in Jordan, then the Gulf.' }}</p>
    </div>
    <a class="btn btn-ghost" href="{{ $u('/partners') }}" data-cta="partners" data-pos="partners_band">{{ $ar ? 'قدّم طلب شراكة التصميم' : 'Apply to be a design partner' }}</a>
</section>

<section class="hp-close" aria-labelledby="h-close">
    <h2 id="h-close">{{ $ar ? 'انضم إلى قائمة الإطلاق' : 'Join the launch list' }}</h2>
    <p>{{ $ar ? 'اترك بريدك واسم وكالتك ودولتك لتنضم إلى قائمة الإطلاق. الإطلاق في 2027.' : 'Leave your email, agency name and country to join the launch list. Launching 2027.' }}</p>
    <a class="btn" href="{{ $u('/waitlist') }}?type=agency" data-cta="waitlist" data-pos="closing">{{ $ar ? 'انضم إلى قائمة الإطلاق' : 'Join the launch list' }}</a>
</section>
@endsection
