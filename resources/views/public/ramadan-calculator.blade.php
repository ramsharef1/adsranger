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
// Package D2 (Packages_2026-11-08b rev 2): currency select (no conversion: code + decimals only), 29 / 30 toggle.
$currencies = [['USD', 'US dollar', 2], ['SAR', 'Saudi riyal', 2], ['AED', 'UAE dirham', 2], ['JOD', 'Jordanian dinar', 3], ['KWD', 'Kuwaiti dinar', 3], ['QAR', 'Qatari riyal', 2], ['EGP', 'Egyptian pound', 2]];
$assume = $ar ? 'افتراض للمثال، عدّله' : 'Example assumption, edit it';
// [name, label, default, hint, money field?]
$inputs = [
    ['daily_spend', $ar ? 'الإنفاق اليومي المعتاد' : 'Usual daily spend', 100, $example, true],
    ['cpr', $ar ? 'تكلفة النتيجة المعتادة' : 'Usual cost per result', 5, $example, true],
    ['spend_change', $ar ? 'تغيّر الإنفاق اليومي في رمضان (%)' : 'Change in daily spend during Ramadan (%)', 20, $assume, false],
    ['cpr_change', $ar ? 'تغيّر تكلفة النتيجة في رمضان (%)' : 'Change in cost per result during Ramadan (%)', 10, $assume, false],
];
// CL-1 rev 2 E-01 (Growth 08e §1).
$faq = $ar ? [
    ['هل رمضان 29 يومًا أم 30؟', 'يتحدد ذلك برؤية الهلال في كل دولة. اختر 29 أو 30 يومًا، وقارن النتيجتين إن أردت.'],
    ['بأي عملة أحسب؟', 'اختر عملة حساباتك الإعلانية من القائمة. لا تحوّل الحاسبة بين العملات، بل تعرض النتيجة بالعملة التي تختارها.'],
    ['هل تحفظون الأرقام التي أدخلها؟', 'لا. تعمل الحاسبة في متصفحك فقط، ولا يُرسل أي رقم إلينا.'],
] : [
    ['Is Ramadan 29 or 30 days?', 'It depends on the moon sighting in each country. Pick 29 or 30 days and compare both if you like.'],
    ['Which currency should I use?', 'Pick the currency of your ad accounts from the list. The calculator doesn\'t convert; it shows results in the currency you choose.'],
    ['Do you store the numbers I enter?', 'No. The calculator runs only in your browser, and no number is sent to us.'],
];
$schema[] = ['@type' => 'FAQPage', 'mainEntity' => array_map(fn ($q) => ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]], $faq)];
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
.rc-field{position:relative}
.rc-field input{padding-inline-end:56px}
.rc-cur{position:absolute;top:50%;transform:translateY(-50%);inset-inline-end:12px;font-size:14px;color:var(--muted);pointer-events:none}
.rc-days{border:0;padding:0;margin:8px 0 0}
.rc-days legend{min-height:44px;display:flex;align-items:flex-end;padding:0}
.rc-seg{display:flex;gap:8px}
.rc-seg label{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:44px;min-width:88px;padding:0 14px;border:1px solid var(--line);border-radius:10px;cursor:pointer}
.rc-seg input{width:auto;min-height:0;margin:0}
.rc-seg label:has(input:checked){border-color:var(--acc);box-shadow:inset 0 0 0 1px var(--acc)}
</style>
@endpush

@section('hero')
<h1>{{ $ar ? 'حاسبة ميزانية إعلانات رمضان 2027' : 'Ramadan 2027 ad budget calculator' }}</h1>
<p class="muted" style="font-size:17px">{{ $ar
? 'أدخل إنفاقك اليومي المعتاد وتكلفة النتيجة المعتادة، ثم حدّد مقدار التغيّر الذي تتوقعه لكل منهما في رمضان. تعرض الحاسبة تقديرًا لشهر رمضان كاملًا، وتعتمد فقط على الأرقام التي تدخلها.'
: 'Enter your usual daily spend and cost per result, then set how much you expect each to change in Ramadan. The calculator shows an estimate for the whole month. It uses only the numbers you enter.' }}</p>
@endsection

@section('content')
<section class="rc">

    <form class="card" id="rc-form" onsubmit="return false" novalidate>
        <div class="rc-grid">
            <div>
                <label for="rc-currency">{{ $ar ? 'العملة' : 'Currency' }}</label>
                <select id="rc-currency" name="currency">
                    @foreach ($currencies as [$code, $name, $dec])
                        <option value="{{ $code }}" data-dec="{{ $dec }}">{{ $ar ? $code : $name.' ('.$code.')' }}</option>
                    @endforeach
                </select>
                <p class="rc-hint">{{ $ar ? 'لا تحويل بين العملات.' : 'No conversion between currencies.' }}</p>
            </div>
            @foreach ($inputs as [$name, $label, $default, $hint, $isMoney])
                <div>
                    <label for="rc-{{ $name }}">{{ $label }}</label>
                    <div class="rc-field" dir="ltr">
                        <input id="rc-{{ $name }}" name="{{ $name }}" type="text" inputmode="decimal" dir="ltr" value="{{ $default }}" aria-describedby="rc-{{ $name }}-hint rc-{{ $name }}-err">
                        @if ($isMoney)<span class="rc-cur" data-cur aria-hidden="true">USD</span>@endif
                    </div>
                    <p class="rc-hint" id="rc-{{ $name }}-hint">{{ $hint }}</p>
                    <p class="rc-err" id="rc-{{ $name }}-err" hidden>{{ $ar ? 'أدخل رقمًا يساوي 0 أو أكثر.' : 'Enter a number of 0 or more.' }}</p>
                </div>
            @endforeach
        </div>
        <fieldset class="rc-days">
            <legend>{{ $ar ? 'عدد أيام رمضان' : 'Days of Ramadan' }}</legend>
            <div class="rc-seg">
                <label><input type="radio" name="days" value="29"> {{ $ar ? '29 يومًا' : '29 days' }}</label>
                <label><input type="radio" name="days" value="30" checked> {{ $ar ? '30 يومًا' : '30 days' }}</label>
            </div>
            <p class="rc-hint">{{ $ar ? 'يحدده موعد العيد في كل دولة.' : 'Set by the Eid date in each country.' }}
                {!! $ar ? 'راجع <a href="'.$u('/mena-calendar').'">مواعيد رمضان والعيد في كل دولة</a>.' : 'See <a href="'.$u('/mena-calendar').'">Ramadan and Eid dates by country</a>.' !!}</p>
        </fieldset>
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

    <h2 style="margin-top:28px">{{ $ar ? 'كيف تعمل الحاسبة' : 'How the calculator works' }}</h2>
    <p>{{ $ar
        ? 'تضرب الحاسبة إنفاقك اليومي المعتاد في نسبة التغيّر التي تتوقعها، ثم في عدد أيام رمضان، فتحصل على ميزانية الشهر. وتضيف نسبة التغيّر إلى تكلفة النتيجة المعتادة لتعرف تكلفة النتيجة في رمضان. ثم تقسم الميزانية على تكلفة النتيجة لتقدّر عدد النتائج.'
        : 'The calculator multiplies your usual daily spend by the change you expect, then by the days of Ramadan, to get the month\'s budget. It adds your expected change to your usual cost per result to get the Ramadan cost per result. Then it divides the budget by that cost to estimate results.' }}</p>
    <p>{!! $ar
        ? 'مثال بالقيم الافتراضية: إنفاق يومي 100 وتكلفة نتيجة 5، مع زيادة 20% في الإنفاق و10% في التكلفة، لمدة 30 يومًا: ميزانية <bdi dir="ltr">3,600 USD</bdi>، وتكلفة نتيجة <bdi dir="ltr">5.50 USD</bdi>، ونحو 654 نتيجة.'
        : 'With the default values — 100 a day, a cost per result of 5, spend up 20% and cost up 10%, over 30 days — the budget is 3,600 USD, the cost per result 5.50 USD, and about 654 results.' !!}</p>

    <h2>{{ $ar ? 'عن الافتراضين' : 'About the two assumptions' }}</h2>
    <p>{{ $ar
        ? 'يرتفع الإنفاق في رمضان عادةً لأن المعلنين يزيدون حملاتهم، وقد ترتفع تكلفة النتيجة لأن المنافسة على الجمهور نفسه تزيد. لا نضع نسبة واحدة تناسب الجميع: النسبتان في الحاسبة افتراض للمثال، وأفضل مرجع لهما أرقامك أنت من رمضان الماضي لكل عميل.'
        : 'Spend usually rises in Ramadan as advertisers grow their campaigns, and cost per result can rise as competition for the same audience grows. There\'s no single figure that fits everyone: the two percentages here are example assumptions, and your own numbers from last Ramadan, client by client, are the best guide.' }}</p>
    <h2>{{ $ar ? 'أسئلة شائعة' : 'FAQ' }}</h2>
    @foreach ($faq as [$q, $a])
        <h3 style="font-size:17px;margin:16px 0 4px">{{ $q }}</h3>
        <p style="margin:0">{{ $a }}</p>
    @endforeach

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
  var names=['daily_spend','cpr','spend_change','cpr_change'],cur=document.getElementById('rc-currency');
  function num(n){var el=document.getElementById('rc-'+n),v=el.value.trim().replace(/,/g,''),ok=v!==''&&!isNaN(v)&&Number(v)>=0;
    document.getElementById('rc-'+n+'-err').hidden=ok;el.setAttribute('aria-invalid',ok?'false':'true');return ok?Number(v):null;}
  function money(x,d){return x.toLocaleString('en-US',{minimumFractionDigits:d,maximumFractionDigits:d})+' '+cur.value;}
  function calc(){
    var v={},bad=false;names.forEach(function(n){v[n]=num(n);if(v[n]===null){bad=true;}});
    document.querySelectorAll('[data-cur]').forEach(function(s){s.textContent=cur.value;});
    if(bad){return;}
    var dec=Number(cur.options[cur.selectedIndex].getAttribute('data-dec')),days=Number(document.querySelector('input[name=days]:checked').value);
    var budget=v.daily_spend*(1+v.spend_change/100)*days,cpr=v.cpr*(1+v.cpr_change/100);
    document.getElementById('rc-out-budget').textContent=money(budget,budget%1?dec:0);
    document.getElementById('rc-out-cpr').textContent=money(cpr,dec);
    document.getElementById('rc-out-results').textContent=cpr>0?Math.floor(budget/cpr+1e-9).toLocaleString('en-US'):'—';
  }
  names.forEach(function(n){document.getElementById('rc-'+n).addEventListener('input',calc);});
  cur.addEventListener('change',calc);
  document.querySelectorAll('input[name=days]').forEach(function(r){r.addEventListener('change',calc);});
  calc();
})();
</script>
@endpush
