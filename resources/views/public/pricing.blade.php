@php
// Website P-6 "Plans" (U-16, UX-155, UX-116; D-07 no prices before launch, D-180 Solo card, D-199 founding line).
$ar = ($lang ?? 'ar') === 'ar';
$arPath = '/pricing';
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $ar ? 'ar' : 'en');
$title = $ar ? 'باقات AdsRanger للوكالات والمستقلين' : 'AdsRanger Plans for Agencies and Freelancers';
$meta = $ar
    ? 'ما تخطط باقات AdsRanger لتقديمه، من الأساسية إلى باقة الوكالات. قيد التطوير، والأسعار تُعلن عند الإطلاق في 2027.'
    : 'What each AdsRanger plan is planned to include, from Starter to Agency. In development; prices are shared at launch in 2027.';
$schema = [['@type' => 'WebPage', 'name' => $title, 'url' => \App\Support\PublicUrl::absolute($arPath, $ar ? 'ar' : 'en'), 'inLanguage' => $ar ? 'ar' : 'en']];
$dev = $ar ? 'قيد التطوير' : 'In development';
$planned = $ar ? 'مخطط له' : 'Planned';
// [title, one line, [[line, planned?], …]]
$plans = $ar ? [
    ['باقة المستقل', 'للمستقلين الذين يديرون عددًا قليلًا من العملاء: روابط العملاء والتقارير الأساسية. تناسب أيضًا <a href="'.$u('/solutions/business-owners').'">أصحاب المشاريع</a> الذين يديرون إعلاناتهم بأنفسهم.', null],
    ['الباقة الأساسية', 'للوكالات الناشئة وفرق الأداء الصغيرة.', [
        ['المكتب اليومي ومؤشر الصحة والمستهدفات', false],
        ['تقارير <bdi dir="ltr">Meta</bdi>، <bdi dir="ltr">Google Ads</bdi> (تكاملات مخطط لها)، وسجل 13 شهرًا، وعملات متعددة', true],
        ['قواعد التنبيه الأساسية وملخص الصباح بالبريد', false],
        ['تقرير شهري بالعربية أو الإنجليزية وروابط للعملاء: <a href="'.$u('/demo-report').'">شكل تقرير العميل</a>', false],
        ['منشئ روابط التتبّع وفحص الروابط وحاسبة الأهداف', false],
    ]],
    ['الباقة الاحترافية', 'للوكالات المتنامية التي تدير عدة منصات وميزانيات.', [
        ['كل ما في الباقة الأساسية، مع قواعد التنبيه المتقدمة', false],
        ['تنبيهات حرجة على هاتفك، مع ساعات الهدوء', false],
        ['توقع نهاية الشهر ومخطِّط سيناريوهات رمضان والعيد', false],
        ['تفاصيل إعلانات البحث وفحص صفحات الهبوط ومتابعة المنافسين', false],
        ['جدولة المنشورات العضوية وصندوق وارد موحد', true],
    ]],
    ['باقة الوكالات', 'للوكالات الكبيرة متعددة الفرق.', [
        ['كل ما في الباقة الاحترافية، مع منصات إعلانية إضافية في مرحلة لاحقة', true],
        ['خطة الميزانية السنوية مقابل الفعلي', false],
        ['نطاق مخصص وألوان وكالتك على تقارير العملاء', true],
        ['عمليات الوكالة والمالية وحملات المؤثرين', true],
    ]],
] : [
    ['Solo', 'For freelancers who manage a few clients: client links and core reporting. Also fits <a href="'.$u('/solutions/business-owners').'">business owners</a> who run their own ads.', null],
    ['Starter', 'For new agencies and small performance teams.', [
        ['Daily desk, health score and targets', false],
        ['Meta and Google Ads reporting (planned integrations), 13 months of history and multi-currency', true],
        ['Core alert rules and a morning email digest', false],
        ['Monthly report in Arabic or English, and client links: <a href="'.$u('/demo-report').'">what a client report looks like</a>', false],
        ['UTM builder, link checker and goal calculator', false],
    ]],
    ['Pro', 'For growing agencies running several platforms and budgets.', [
        ['Everything in Starter, with advanced alert rules', false],
        ['Critical alerts on your phone, with quiet hours', false],
        ['Month-end forecast and a Ramadan and Eid scenario planner', false],
        ['Search ads detail, landing-page checks and competitor watch', false],
        ['Organic post scheduling and a unified inbox', true],
    ]],
    ['Agency', 'For large agencies with several teams.', [
        ['Everything in Pro, with more ad platforms at a later stage', true],
        ['Annual budget plan vs actual', false],
        ['Custom domain and your colours on client reports', true],
        ['Agency operations, finance and influencer campaigns', true],
    ]],
];
// CL-1 E-06 comparison table, built from config/plan_features.php (gated features already built). Benchmarks left out:
// MENA medians need 5+ opted-in agencies first (D-111). Every row is still «قيد التطوير» on the public site.
$pf = config('plan_features');
$tierOrder = $pf['tiers'];
$compareTiers = ['solo', 'starter', 'pro', 'agency'];
$compare = collect($pf['features'])->except(['benchmarks'])
    ->sortBy(fn ($f) => $tierOrder[$f['tier']])
    ->map(fn ($f) => [
        'name' => str_replace('Google Ads', '<bdi dir="ltr">Google Ads</bdi>', e($f['name'][$ar ? 'ar' : 'en'])),
        'in' => array_map(fn ($t) => $tierOrder[$t] >= $tierOrder[$f['tier']], $compareTiers),
    ])->values()->all();
// CL-1 rev 2 E-06 FAQ (Growth 08e §6; Business 913 · 18:59: "Confirm with 2 small fixes"; trial = D-61, limits = D-208, Solo = D-184).
$faq = $ar ? [
    ['هل توجد فترة تجربة؟', 'نعم. عند الإطلاق، تبدأ كل مساحة عمل جديدة بتجربة لمدة 14 يومًا على الباقة الاحترافية، دون الحاجة إلى بطاقة.'],
    ['هل يمكنني تغيير الباقة لاحقًا؟', 'نعم، يمكنك الترقية أو الانتقال إلى باقة أصغر عند حاجتك، ما دام عدد عملائك وحساباتك الإعلانية ضمن حدود الباقة الأصغر.'],
    ['ما باقة المستقل؟', 'باقة للمستقلين والشركات التي تدير إعلاناتها بنفسها، تشمل روابط العملاء والتقارير الأساسية لعدد قليل من العملاء.'],
] : [
    ['Is there a trial?', 'Yes. At launch, each new workspace starts with a 14-day trial of Pro, no card needed.'],
    ['Can I change plans later?', 'Yes, you can move up or down a plan when you need to, as long as your clients and ad accounts fit the smaller plan\'s limits.'],
    ['What is Solo?', 'A plan for freelancers and businesses that run their own ads, with client links and basic reports for a few clients.'],
];
$schema[] = ['@type' => 'FAQPage', 'mainEntity' => array_map(fn ($q) => ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]], $faq)];
@endphp
@extends('layouts.public')

@push('styles')
<style>
.plans{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(260px,100%),1fr));gap:16px;list-style:none;padding:0;margin:24px 0}
.plans>li{display:flex;flex-direction:column;justify-content:space-between;gap:16px}
.plans>li.plan-featured{border-color:var(--acc)}
.plans ul{list-style:none;padding:0;margin:14px 0 0}
.plans ul li{padding:10px 0;border-top:1px solid var(--line);display:flex;flex-direction:column;gap:6px;font-size:14.5px}
.plans ul li .badge{align-self:flex-start}
.cmp{position:relative;overflow-x:auto;max-width:100%;border:1px solid var(--line);border-radius:12px}
.cmp table{width:100%;border-collapse:collapse;min-width:520px;font-size:14.5px}
.cmp th,.cmp td{padding:10px 12px;border-bottom:1px solid var(--line);text-align:center}
.cmp tbody th,.cmp thead th:first-child{text-align:start;font-weight:600}
.cmp td .yes{color:var(--acc);font-weight:700}
.cmp td .no{color:var(--muted)}
.sr{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap}
</style>
@endpush

@section('hero')
<h1>{{ $ar ? 'باقات تناسب كل حجم من الوكالات' : 'Plans for every size of agency' }}</h1>
<p class="muted" style="font-size:17px">{!! $ar
? 'ما زالت <bdi dir="ltr">AdsRanger</bdi> قيد التطوير، والإطلاق في 2027. هذا ما تخطط كل باقة لتقديمه، والأسعار تُعلن عند الإطلاق، وتشمل كل باقة <a href="'.$u('/integrations').'">المنصات الإعلانية المخطط لربطها</a> حسب مستواها.'
: 'AdsRanger is in development and launching in 2027. Here is what each plan is planned to include. Prices are shared at launch, and each plan covers <a href="'.$u('/integrations').'">the ad platforms we plan to connect</a> by level.' !!}</p>
@endsection

@section('content')
<section style="padding-top:24px;max-width:820px">
    <p>{{ $ar ? 'عند الإطلاق، تبدأ كل مساحة عمل جديدة بتجربة لمدة 14 يومًا على الباقة الاحترافية.' : 'At launch, each new workspace starts with a 14-day trial of Pro.' }}</p>
</section>
<ul class="plans">
    @foreach ($plans as $idx => [$name, $line, $features])
        <li class="card {{ $idx === 2 ? 'plan-featured' : '' }}">
            <div>
                <h2 style="margin:0 0 4px;font-size:21px">{{ $name }}</h2>
                @if ($features === null)<span class="badge badge-neutral">{{ $planned }}</span>@endif
                <p class="muted" style="margin:8px 0 0">{!! $line !!}</p>
                @if ($features)
                    <ul>
                        @foreach ($features as [$feature, $isPlanned])
                            <li><span>{!! $feature !!}</span><span class="badge {{ $isPlanned ? 'badge-neutral' : '' }}">{{ $isPlanned ? $planned : $dev }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <a class="btn" href="{{ $u('/waitlist') }}">{{ $ar ? 'انضم إلى قائمة الإطلاق' : 'Join the launch list' }}</a>
        </li>
    @endforeach
</ul>
<p class="muted">{{ $ar ? 'تحتفظ الوكالات المؤسِّسة بسعر الإطلاق.' : 'Founding agencies keep their launch price.' }}</p>
<section style="max-width:960px;margin-top:28px;min-width:0;overflow:hidden" aria-labelledby="h-compare">
    <h2 id="h-compare">{{ $ar ? 'مقارنة الباقات' : 'Compare plans' }}</h2>
    <p class="muted">{{ $ar ? 'كل الميزات أدناه قيد التطوير. والقائمة تعرض فقط ما يختلف بين الباقات.' : 'Every feature below is in development. The list shows only what differs between plans.' }}</p>
    <div class="cmp" tabindex="0" role="region" aria-label="{{ $ar ? 'مقارنة الباقات' : 'Compare plans' }}">
        <table>
            <caption class="sr">{{ $ar ? 'مقارنة الباقات' : 'Compare plans' }}</caption>
            <thead><tr><th scope="col">{{ $ar ? 'الميزة' : 'Feature' }}</th>@foreach ($compareTiers as $tier)<th scope="col">{{ $pf['tier_short'][$tier][$ar ? 'ar' : 'en'] }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach ($compare as $row)
                    <tr>
                        <th scope="row">{!! $row['name'] !!}</th>
                        @foreach ($row['in'] as $has)
                            <td>@if ($has)<span class="yes" aria-hidden="true">✓</span><span class="sr">{{ $ar ? 'مشمولة' : 'Included' }}</span>@else<span class="no" aria-hidden="true">—</span><span class="sr">{{ $ar ? 'غير مشمولة' : 'Not included' }}</span>@endif</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
<section style="max-width:820px">
    <h2>{{ $ar ? 'أسئلة شائعة' : 'FAQ' }}</h2>
    @foreach ($faq as [$q, $a])
        <h3 style="font-size:17px;margin:16px 0 4px">{{ $q }}</h3>
        <p style="margin:0">{{ $a }}</p>
    @endforeach
    <p style="margin-top:20px">{!! $ar ? 'اقرأ <a href="'.$u('/security').'">كيف نحمي بياناتك</a>.' : 'Read <a href="'.$u('/security').'">how we protect your data</a>.' !!}</p>
</section>
@endsection
