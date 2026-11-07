@php
// Website P-4 Ramadan budget calculator (R-02, D-181 money order, A-3 44 px labels). Nothing is stored.
$ar = ($lang ?? 'ar') === 'ar';
$lang = $ar ? 'ar' : 'en';
$arPath = '/tools/ramadan-budget-calculator';
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
$title = $ar ? 'حاسبة ميزانية إعلانات رمضان 2027 | AdsRanger' : 'Ramadan 2027 Ad Budget Calculator for Agencies | AdsRanger';
$meta = $ar
    ? 'احسب تقديرًا لميزانية إعلانات رمضان 2027 وتكلفة النتيجة وعدد النتائج من أرقامك أنت. مجانًا ودون تسجيل، وكل افتراض قابل للتعديل.'
    : 'Estimate a Ramadan 2027 ad budget, cost per result and results from your own numbers. Free, no sign-up. Every assumption is yours to change.';
$selfUrl = \App\Support\PublicUrl::absolute($arPath, $lang);
$schema = [
    ['@type' => 'WebPage', 'name' => $title, 'url' => $selfUrl, 'inLanguage' => $lang],
    ['@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => $ar ? 'الرئيسية' : 'Home', 'item' => \App\Support\PublicUrl::absolute('/', $lang)],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $ar ? 'رمضان 2027' : 'Ramadan 2027', 'item' => \App\Support\PublicUrl::absolute('/ramadan-2027', $lang)],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $ar ? 'حاسبة ميزانية رمضان' : 'Ramadan budget calculator', 'item' => $selfUrl],
    ]],
];
$example = $ar ? 'قيمة مثال' : 'Example value';
$assume = $ar ? 'افتراض للمثال، غيّره حسب خطتك' : 'Example assumption, change it to your plan';
$usd = $ar ? '<bdi dir="ltr">USD</bdi>' : 'USD';
$inputs = [
    ['daily_spend', $ar ? 'الإنفاق اليومي المعتاد ('.$usd.')' : 'Usual daily spend (USD)', 100, $example],
    ['cpr', $ar ? 'تكلفة النتيجة المعتادة ('.$usd.')' : 'Usual cost per result (USD)', 5, $example],
    ['days', $ar ? 'عدد الأيام' : 'Days', 30, $ar ? 'رمضان 29 أو 30 يومًا' : 'Ramadan has 29 or 30 days'],
    ['spend_change', $ar ? 'تغيّر الإنفاق اليومي في رمضان (%)' : 'Change in daily spend during Ramadan (%)', 20, $assume],
    ['cpr_change', $ar ? 'تغيّر تكلفة النتيجة في رمضان (%)' : 'Change in cost per result during Ramadan (%)', 10, $assume],
];
@endphp
@extends('layouts.public')

@push('styles')
<style>
.rc{max-width:820px;padding-top:24px}
.rc-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(240px,100%),1fr));gap:4px 16px}
.rc-grid label{display:flex;align-items:flex-end;min-height:44px}
.rc-hint{font-size:13px;color:var(--muted);margin:4px 0 0}
.rc-err{font-size:13px;color:var(--bad);margin:4px 0 0}
.rc-out{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(200px,100%),1fr));gap:12px;list-style:none;padding:0;margin:20px 0 0}
.rc-out li{border:1px solid var(--line);border-radius:12px;padding:14px}
.rc-out strong{display:block;font-size:22px;color:var(--acc)}
</style>
@endpush

@section('content')
<section class="rc">
    <h1>{{ $ar ? 'حاسبة ميزانية إعلانات رمضان 2027' : 'Ramadan 2027 ad budget calculator' }}</h1>
    <p class="muted" style="font-size:17px">{{ $ar
        ? 'أدخل إنفاقك اليومي المعتاد وتكلفة النتيجة المعتادة، ثم حدّد مقدار التغيّر الذي تتوقعه لكل منهما في رمضان. تعرض الحاسبة تقديرًا لثلاثين يومًا، وتعتمد فقط على الأرقام التي تدخلها.'
        : 'Enter your usual daily spend and cost per result, then set how much you expect each to change in Ramadan. The calculator shows a 30-day estimate. It uses only the numbers you enter.' }}</p>

    <form class="card" id="rc-form" onsubmit="return false" novalidate>
        <div class="rc-grid">
            @foreach ($inputs as [$name, $label, $default, $hint])
                <div>
                    <label for="rc-{{ $name }}">{!! $label !!}</label>
                    <input id="rc-{{ $name }}" name="{{ $name }}" type="text" inputmode="decimal" dir="ltr" value="{{ $default }}" aria-describedby="rc-{{ $name }}-hint rc-{{ $name }}-err">
                    <p class="rc-hint" id="rc-{{ $name }}-hint">{{ $hint }}</p>
                    <p class="rc-err" id="rc-{{ $name }}-err" hidden>{{ $ar ? 'أدخل رقمًا يساوي 0 أو أكثر.' : 'Enter a number of 0 or more.' }}</p>
                </div>
            @endforeach
        </div>
        <h2 style="font-size:19px;margin-top:24px">{{ $ar ? 'التقدير' : 'Estimate' }}</h2>
        <ul class="rc-out" aria-live="polite">
            <li>{{ $ar ? 'ميزانية رمضان' : 'Ramadan budget' }}<strong><bdi dir="ltr" id="rc-out-budget">3,600 USD</bdi></strong></li>
            <li>{{ $ar ? 'تكلفة النتيجة في رمضان' : 'Cost per result in Ramadan' }}<strong><bdi dir="ltr" id="rc-out-cpr">5.50 USD</bdi></strong></li>
            <li>{{ $ar ? 'النتائج المقدّرة' : 'Estimated results' }}<strong><bdi dir="ltr" id="rc-out-results">654</bdi></strong></li>
        </ul>
        <noscript><p>{{ $ar ? 'الميزانية = الإنفاق اليومي × (1 + نسبة التغيّر) × عدد الأيام' : 'Budget = daily spend × (1 + spend change) × days' }}</p></noscript>
        <p class="muted" style="margin-top:16px">{{ $ar
            ? 'هذا تقدير مبني على الأرقام التي تدخلها، وليس توقعًا. وأفضل مرجع للنسبتين هو بياناتك أنت من رمضان الماضي.'
            : 'This is an estimate from the numbers you enter, not a forecast. Your own data from last Ramadan is the best guide for the two percentages.' }}</p>
    </form>

    <p style="margin-top:20px">{!! $ar
        ? 'خطّط للشهر كاملًا مرحلةً بمرحلة في <a href="'.$u('/ramadan-2027').'">دليل رمضان 2027</a>.'
        : 'Plan the whole month by phase in the <a href="'.$u('/ramadan-2027').'">Ramadan 2027 guide</a>.' !!}</p>
    <p>{!! $ar
        ? 'ومخطِّط السيناريوهات داخل <bdi dir="ltr">AdsRanger</bdi>، المبني على سجل كل عميل، قيد التطوير.'
        : 'A scenario planner inside AdsRanger, using each client\'s own history, is in development.' !!}</p>
    <p><a class="btn" href="{{ $u('/waitlist') }}">{{ $ar ? 'انضم إلى قائمة الانتظار' : 'Join the waitlist' }}</a></p>
</section>
@endsection

@push('scripts')
<script>
(function(){
  var names=['daily_spend','cpr','days','spend_change','cpr_change'];
  function num(n){var el=document.getElementById('rc-'+n),v=el.value.trim().replace(/,/g,''),ok=v!==''&&!isNaN(v)&&Number(v)>=0;
    document.getElementById('rc-'+n+'-err').hidden=ok;el.setAttribute('aria-invalid',ok?'false':'true');return ok?Number(v):null;}
  function money(x,d){return x.toLocaleString('en-US',{minimumFractionDigits:d,maximumFractionDigits:d})+' USD';}
  function calc(){
    var v={},bad=false;names.forEach(function(n){v[n]=num(n);if(v[n]===null){bad=true;}});
    if(bad){return;}
    var budget=v.daily_spend*(1+v.spend_change/100)*v.days,cpr=v.cpr*(1+v.cpr_change/100);
    document.getElementById('rc-out-budget').textContent=money(budget,budget%1?2:0);
    document.getElementById('rc-out-cpr').textContent=money(cpr,2);
    document.getElementById('rc-out-results').textContent=cpr>0?Math.floor(budget/cpr+1e-9).toLocaleString('en-US'):'—';
  }
  names.forEach(function(n){document.getElementById('rc-'+n).addEventListener('input',calc);});
  calc();
})();
</script>
@endpush
