@php
// Free ad tool · /tools/budget-pacing-calculator (D-90 set). Browser only.
extract(\App\Support\ToolPage::head($lang ?? 'ar', '/tools/budget-pacing-calculator',
    ['حاسبة وتيرة إنفاق الميزانية الإعلانية | AdsRanger', 'Ad Budget Pacing Calculator | AdsRanger'],
    ['اعرف إن كان حسابك الإعلاني ينفق حسب خطة الشهر، وكم يجب أن تنفق يوميًا حتى نهايته. مجانًا ودون تسجيل، ولا نحفظ شيئًا.',
        'See whether your ad account is spending to the month\'s plan and how much to spend a day until month-end. Free, no sign-up, nothing stored.']));
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
$t = $ar ? ['cur' => 'العملة', 'budget' => 'ميزانية الشهر', 'spent' => 'المنفَق حتى الآن', 'day' => 'اليوم من الشهر', 'days' => 'عدد أيام الشهر',
    'exp' => 'المتوقع إنفاقه حتى اليوم', 'pace' => 'الوتيرة', 'proj' => 'الإنفاق المتوقع في نهاية الشهر', 'daily' => 'الإنفاق اليومي اللازم لبقية الشهر',
    'over' => 'ينفق الحساب أسرع من الخطة.', 'under' => 'ينفق الحساب أبطأ من الخطة.', 'ok' => 'الإنفاق قريب من الخطة.', 'err' => 'تحقّق من الأرقام: اليوم بين 1 وعدد أيام الشهر.', 'note' => 'الحساب خطي: يفترض إنفاقًا متساويًا كل يوم.']
    : ['cur' => 'Currency', 'budget' => 'Monthly budget', 'spent' => 'Spent so far', 'day' => 'Day of the month', 'days' => 'Days in the month',
    'exp' => 'Expected spend to date', 'pace' => 'Pace', 'proj' => 'Projected month-end spend', 'daily' => 'Daily spend needed for the rest of the month',
    'over' => 'The account is spending faster than planned.', 'under' => 'The account is spending slower than planned.', 'ok' => 'Spend is close to plan.', 'err' => 'Check the numbers: the day must be between 1 and the days in the month.', 'note' => 'Linear: it assumes the same spend every day.'];
$curs = ['USD', 'SAR', 'AED', 'JOD', 'KWD', 'QAR', 'EGP'];
$today = now('Asia/Amman');
@endphp
@extends('layouts.public')
@push('styles')
@include('public.tools.partials.styles')
@endpush
@section('hero')
<h1>{{ $ar ? 'حاسبة وتيرة الإنفاق' : 'Budget pacing calculator' }}</h1>
<p class="muted" style="font-size:17px">{{ $ar ? 'أدخل ميزانية الشهر وما أنفقته حتى الآن، واعرف إن كنت تسبق الخطة أو تتأخر عنها.' : 'Enter the month\'s budget and what you\'ve spent so far, and see whether you\'re ahead of plan or behind.' }}</p>
@endsection
@section('content')
<section class="tl">
    <form class="card" id="pc-form" onsubmit="return false" novalidate>
        <label for="pc-cur">{{ $t['cur'] }}</label>
        <select id="pc-cur">@foreach ($curs as $c)<option>{{ $c }}</option>@endforeach</select>
        <label for="pc-budget">{{ $t['budget'] }}</label>
        <input id="pc-budget" type="text" inputmode="decimal" dir="ltr" value="3000">
        <label for="pc-spent">{{ $t['spent'] }}</label>
        <input id="pc-spent" type="text" inputmode="decimal" dir="ltr" value="1200">
        <label for="pc-day">{{ $t['day'] }}</label>
        <input id="pc-day" type="text" inputmode="numeric" dir="ltr" value="{{ $today->day }}">
        <label for="pc-days">{{ $t['days'] }}</label>
        <input id="pc-days" type="text" inputmode="numeric" dir="ltr" value="{{ $today->daysInMonth }}">
        <p class="tl-err" id="pc-err" hidden>{{ $t['err'] }}</p>
    </form>
    <div class="tl-out" aria-live="polite">
        <p class="tl-big" id="pc-msg"></p>
        <ul class="tl-kv" style="margin-top:12px">
            <li>{{ $t['exp'] }}<strong id="pc-exp" dir="ltr"></strong></li>
            <li>{{ $t['pace'] }}<strong id="pc-pace" dir="ltr"></strong></li>
            <li>{{ $t['proj'] }}<strong id="pc-proj" dir="ltr"></strong></li>
            <li>{{ $t['daily'] }}<strong id="pc-daily" dir="ltr"></strong></li>
        </ul>
        <p class="muted" style="margin-top:12px">{{ $t['note'] }}</p>
        <p class="muted">{{ $privacy }}</p>
    </div>
</section>
<p><a class="btn" href="{{ $u('/waitlist') }}?type=agency">{{ $ar ? 'انضم إلى قائمة الانتظار' : 'Join the waitlist' }}</a></p>
@include('public.tools.partials.tool-guide-faq', ['toolSlug' => 'budget-pacing-calculator', 'ar' => $ar])
@endsection
@push('scripts')
<script>
(function(){
  var T = @json($t), $ = function (id) { return document.getElementById(id); };
  function n(id) { var v = $(id).value.trim().replace(/,/g, ''); return v === '' || isNaN(v) ? null : Number(v); }
  function fmt(x) { return x.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + $('pc-cur').value; }
  function calc() {
    var b = n('pc-budget'), s = n('pc-spent'), d = n('pc-day'), D = n('pc-days');
    var bad = !(b > 0) || s === null || s < 0 || !(D >= 28 && D <= 31) || !(d >= 1 && d <= D);
    $('pc-err').hidden = !bad;
    if (bad) { ['pc-exp', 'pc-pace', 'pc-proj', 'pc-daily'].forEach(function (i) { $(i).textContent = '—'; }); $('pc-msg').textContent = ''; return; }
    var exp = b * d / D, pace = s / exp, proj = s / d * D, left = D - d;
    $('pc-exp').textContent = fmt(exp);
    $('pc-pace').textContent = Math.round(pace * 100) + '%';
    $('pc-proj').textContent = fmt(proj);
    $('pc-daily').textContent = left > 0 ? fmt(Math.max(0, (b - s) / left)) : '—';
    $('pc-msg').textContent = pace > 1.1 ? T.over : pace < 0.9 ? T.under : T.ok;
  }
  ['pc-budget', 'pc-spent', 'pc-day', 'pc-days'].forEach(function (i) { $(i).addEventListener('input', calc); });
  $('pc-cur').addEventListener('change', calc);
  calc();
})();
</script>
@endpush
