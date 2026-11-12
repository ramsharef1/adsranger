@php
extract(\App\Support\ToolPage::head($lang ?? 'ar', '/tools/ab-significance-calculator',
    ['حاسبة دلالة اختبارات A/B | AdsRanger', 'A/B Test Significance & Sample Size Calculator | AdsRanger'],
    ['احسب الدلالة الإحصائية (Z-test وp-value) واحتمال الفوز البايزي والحد الأدنى لحجم العينة لاختبارات الإعلانات وصفحات الهبوط.',
        'Calculate Frequentist Z-score, p-value, Bayesian win probability, and minimum sample size for ad and landing-page split tests.']));
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
@endphp
@extends('layouts.public')
@push('styles')
@include('public.tools.partials.styles')
@endpush
@section('hero')
<h1>{!! $ar ? 'حاسبة الدلالة الإحصائية لاختبارات <bdi dir="ltr">A/B</bdi>' : 'A/B Test Significance & Sample Size Calculator' !!}</h1>
<p class="muted" style="font-size:17px">{{ $ar ? 'تحقق من ثقة نتائج تجارب الإعلانات وصفحات الهبوط واحسب حجم العينة المطلوب قبل إعلان الفائز.' : 'Verify Frequentist Z-test confidence, Bayesian win probability, and minimum required sample size.' }}</p>
@endsection
@section('content')
<section class="tl">
    <form class="card" onsubmit="return false" novalidate>
        <h2 style="margin-top:0">{{ $ar ? 'النسخة الأصلية (A) والنسخة المختبرة (B)' : 'Control (A) vs Variant (B)' }}</h2>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
                <label for="ab-va">{{ $ar ? 'زوار / نقرات (A)' : 'Visitors / clicks (A)' }}</label>
                <input id="ab-va" type="text" inputmode="numeric" dir="ltr" value="4000">
            </div>
            <div>
                <label for="ab-ca">{{ $ar ? 'تحويلات (A)' : 'Conversions (A)' }}</label>
                <input id="ab-ca" type="text" inputmode="numeric" dir="ltr" value="160">
            </div>
            <div>
                <label for="ab-vb">{{ $ar ? 'زوار / نقرات (B)' : 'Visitors / clicks (B)' }}</label>
                <input id="ab-vb" type="text" inputmode="numeric" dir="ltr" value="4000">
            </div>
            <div>
                <label for="ab-cb">{{ $ar ? 'تحويلات (B)' : 'Conversions (B)' }}</label>
                <input id="ab-cb" type="text" inputmode="numeric" dir="ltr" value="204">
            </div>
        </div>
        <label for="ab-conf">{{ $ar ? 'مستوى الثقة المطلوب' : 'Confidence threshold' }}</label>
        <select id="ab-conf" dir="ltr">
            <option value="0.90">90%</option>
            <option value="0.95" selected>95%</option>
            <option value="0.99">99%</option>
        </select>

        <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">
        <h2 style="margin:0">{{ $ar ? 'تقدير الحد الأدنى لحجم العينة' : 'Minimum sample-size planner' }}</h2>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
                <label for="ab-base">{{ $ar ? 'معدل التحويل الأساسي (%)' : 'Baseline CVR (%)' }}</label>
                <input id="ab-base" type="text" inputmode="decimal" dir="ltr" value="4.0">
            </div>
            <div>
                <label for="ab-mde">{{ $ar ? 'أقل تحسن نسبي مطلوب MDE (%)' : 'Minimum relative lift MDE (%)' }}</label>
                <input id="ab-mde" type="text" inputmode="decimal" dir="ltr" value="20">
            </div>
        </div>
    </form>

    <div class="tl-out" aria-live="polite">
        <p class="tl-big" id="ab-verdict"></p>
        <ul class="tl-kv" style="margin-top:14px">
            <li><span>{{ $ar ? 'معدل تحويل (A)' : 'Control CVR (A)' }}</span><strong id="ab-ra" dir="ltr"></strong></li>
            <li><span>{{ $ar ? 'معدل تحويل (B)' : 'Variant CVR (B)' }}</span><strong id="ab-rb" dir="ltr"></strong></li>
            <li><span>{{ $ar ? 'التحسن النسبي (Lift)' : 'Relative lift' }}</span><strong id="ab-lift" dir="ltr"></strong></li>
            <li><span>{!! $ar ? 'قيمة <bdi dir="ltr">Z</bdi> وقيمة <bdi dir="ltr">p</bdi>' : 'Z-score / p-value' !!}</span><strong id="ab-zp" dir="ltr"></strong></li>
            <li><span>{{ $ar ? 'احتمال فوز (B) البايزي' : 'Bayesian P(B beats A)' }}</span><strong id="ab-bayes" dir="ltr"></strong></li>
            <li><span>{{ $ar ? 'حجم العينة المطلوب لكل نسخة (قوة 80%)' : 'Min sample per variant (80% power)' }}</span><strong id="ab-n" dir="ltr"></strong></li>
        </ul>
        <p class="muted" style="margin-top:14px">{{ $privacy }}</p>
    </div>
</section>
<p style="margin-top:20px"><a class="btn" href="{{ $u('/waitlist') }}?type=agency">{{ $ar ? 'انضم إلى قائمة الانتظار' : 'Join the waitlist' }}</a></p>
@include('public.tools.partials.tool-guide-faq', ['toolSlug' => 'ab-significance-calculator', 'ar' => $ar])
@endsection
@push('scripts')
<script>
(function(){
  var $ = function(id){ return document.getElementById(id); }, isAr = @json($ar);
  function num(id){ var v = parseFloat($(id).value.replace(/,/g,'')); return isNaN(v) ? 0 : v; }
  function normCdf(x){
    var t = 1 / (1 + 0.2316419 * Math.abs(x));
    var d = 0.3989423 * Math.exp(-x * x / 2);
    var p = d * t * (0.3193815 + t * (-0.3565638 + t * (1.781478 + t * (-1.821256 + t * 1.330274))));
    return x > 0 ? 1 - p : p;
  }
  function calc(){
    var nA = Math.max(1, num('ab-va')), cA = Math.min(nA, Math.max(0, num('ab-ca')));
    var nB = Math.max(1, num('ab-vb')), cB = Math.min(nB, Math.max(0, num('ab-cb')));
    var conf = parseFloat($('ab-conf').value) || 0.95;
    var pA = cA / nA, pB = cB / nB;
    var pool = (cA + cB) / (nA + nB);
    var se = Math.sqrt(Math.max(1e-12, pool * (1 - pool) * (1/nA + 1/nB)));
    var z = (pB - pA) / se;
    var pVal = Math.max(0, Math.min(1, 2 * (1 - normCdf(Math.abs(z)))));
    var sig = (1 - pVal) >= conf;

    // Beta(c+1, n-c+1) posterior normal approximation for P(B > A)
    var muA = (cA + 1) / (nA + 2), varA = (muA * (1 - muA)) / (nA + 3);
    var muB = (cB + 1) / (nB + 2), varB = (muB * (1 - muB)) / (nB + 3);
    var bayes = normCdf((muB - muA) / Math.sqrt(varA + varB));

    var base = Math.min(99, Math.max(0.01, num('ab-base'))) / 100;
    var mde = Math.max(1, num('ab-mde')) / 100;
    var p2 = Math.min(0.999, base * (1 + mde));
    var zAlpha = conf >= 0.99 ? 2.576 : (conf >= 0.95 ? 1.96 : 1.645);
    var zBeta = 0.84;
    var pBar = (base + p2) / 2;
    var nReq = Math.ceil(Math.pow(zAlpha * Math.sqrt(2 * pBar * (1 - pBar)) + zBeta * Math.sqrt(base * (1 - base) + p2 * (1 - p2)), 2) / Math.pow(p2 - base, 2));

    $('ab-verdict').textContent = sig
      ? (isAr ? 'النتيجة دالة إحصائيًا عند مستوى الثقة المحدد' : 'Statistically significant at selected confidence')
      : (isAr ? 'النتيجة غير حاسمة إحصائيًا بعد' : 'Not statistically significant yet');
    $('ab-verdict').style.color = sig ? 'var(--acc)' : 'var(--muted)';
    $('ab-ra').textContent = (pA * 100).toFixed(2) + '%';
    $('ab-rb').textContent = (pB * 100).toFixed(2) + '%';
    $('ab-lift').textContent = (pA > 0 ? (((pB - pA) / pA) * 100).toFixed(1) + '%' : '—');
    $('ab-zp').textContent = 'Z = ' + z.toFixed(2) + ' · p = ' + pVal.toFixed(4);
    $('ab-bayes').textContent = (bayes * 100).toFixed(1) + '%';
    $('ab-n').textContent = nReq.toLocaleString('en-US');
  }
  ['ab-va','ab-ca','ab-vb','ab-cb','ab-base','ab-mde'].forEach(function(id){ $(id).addEventListener('input', calc); });
  $('ab-conf').addEventListener('change', calc);
  calc();
})();
</script>
@endpush
