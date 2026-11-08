@php
// Free ad tool · /tools/utm-builder (D-90 set). Builds a tracking link in the browser; nothing is opened or stored.
extract(\App\Support\ToolPage::head($lang ?? 'ar', '/tools/utm-builder',
    ['منشئ روابط التتبّع UTM للحملات | AdsRanger', 'UTM Link Builder for Campaigns | AdsRanger'],
    ['أنشئ رابط تتبّع UTM لحملتك في ثوانٍ، مع تنبيه عند المسافات والحروف العربية في القيم. مجانًا ودون تسجيل، ولا نحفظ شيئًا.',
        'Build a UTM tracking link for your campaign in seconds, with a warning for spaces and Arabic letters in the values. Free, no sign-up, nothing stored.']));
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
$fields = [['url', $ar ? 'رابط الصفحة' : 'Page URL', 'https://example.com/offer', true], ['source', $ar ? 'المصدر (utm_source)' : 'Source (utm_source)', 'facebook', true],
    ['medium', $ar ? 'الوسيط (utm_medium)' : 'Medium (utm_medium)', 'paid_social', true], ['campaign', $ar ? 'الحملة (utm_campaign)' : 'Campaign (utm_campaign)', 'ramadan_2027', true],
    ['term', $ar ? 'الكلمة (utm_term، اختياري)' : 'Term (utm_term, optional)', '', false], ['content', $ar ? 'المحتوى (utm_content، اختياري)' : 'Content (utm_content, optional)', '', false]];
$t = $ar ? ['link' => 'الرابط', 'copy' => 'انسخ الرابط', 'copied' => 'نُسخ الرابط.', 'need' => 'أدخل رابطًا يبدأ بـ https:// والحقول المطلوبة.',
    'space' => 'في القيم مسافات؛ استبدلها بـ _ لتسهل قراءة التقارير.', 'arabic' => 'في القيم حروف عربية؛ ستظهر مُرمَّزة في بعض التقارير. يُفضَّل استخدام حروف لاتينية.', 'upper' => 'في القيم حروف كبيرة؛ تفرّق بعض الأدوات بين facebook وFacebook.']
    : ['link' => 'Link', 'copy' => 'Copy link', 'copied' => 'Link copied.', 'need' => 'Enter a URL starting with https:// and the required fields.',
    'space' => 'The values contain spaces; use _ instead to keep reports readable.', 'arabic' => 'The values contain Arabic letters; some reports will show them encoded. Latin letters are safer.', 'upper' => 'The values contain capitals; some tools treat facebook and Facebook as different.'];
@endphp
@extends('layouts.public')
@push('styles')
@include('public.tools.partials.styles')
@endpush
@section('hero')
<h1>{{ $ar ? 'منشئ روابط التتبّع' : 'UTM link builder' }}</h1>
<p class="muted" style="font-size:17px">{{ $ar ? 'أضف إلى رابط صفحتك معلومات الحملة، لتعرف في تقاريرك من أين جاء كل زائر.' : 'Add campaign details to your page link so your reports show where each visitor came from.' }}</p>
@endsection
@section('content')
<section class="tl">
    <form class="card" id="ut-form" onsubmit="return false" novalidate>
        @foreach ($fields as [$id, $label, $val, $req])
            <label for="ut-{{ $id }}">{{ $label }}</label>
            <input id="ut-{{ $id }}" type="text" dir="ltr" maxlength="300" value="{{ $val }}" @if ($req) required @endif>
        @endforeach
    </form>
    <div class="tl-out" aria-live="polite">
        <h3>{{ $t['link'] }}</h3>
        <code class="tl-code" id="ut-out" dir="ltr"></code>
        <div class="tl-actions"><button type="button" class="btn" id="ut-copy">{{ $t['copy'] }}</button></div>
        <ul id="ut-msgs" style="margin:12px 0 0;padding-inline-start:20px"></ul>
        <p class="muted">{{ $privacy }}</p>
    </div>
</section>
<p><a class="btn" href="{{ $u('/waitlist') }}?type=agency">{{ $ar ? 'انضم إلى قائمة الانتظار' : 'Join the waitlist' }}</a></p>
@endsection
@push('scripts')
<script>
(function(){
  var T = @json($t), $ = function (id) { return document.getElementById(id); }, keys = ['source', 'medium', 'campaign', 'term', 'content'], link = '';
  function build() {
    var url = $('ut-url').value.trim(), msgs = [], vals = {};
    keys.forEach(function (k) { vals[k] = $('ut-' + k).value.trim(); });
    var ok = /^https:\/\/[^\s]+\.[^\s]+/i.test(url) && vals.source && vals.medium && vals.campaign;
    link = '';
    if (ok) {
      try {
        var u = new URL(url);
        keys.forEach(function (k) { if (vals[k]) { u.searchParams.set('utm_' + k, vals[k]); } });
        link = u.toString();
      } catch (e) { ok = false; }
    }
    $('ut-out').textContent = ok ? link : T.need;
    var all = keys.map(function (k) { return vals[k]; }).join(' ');
    if (/\s/.test(keys.map(function (k) { return vals[k]; }).filter(Boolean).join(''))) { msgs.push(T.space); }
    if (/[؀-ۿ]/.test(all)) { msgs.push(T.arabic); }
    if (/[A-Z]/.test(all)) { msgs.push(T.upper); }
    var ul = $('ut-msgs'); ul.innerHTML = '';
    msgs.forEach(function (m) { var li = document.createElement('li'); li.textContent = m; ul.appendChild(li); });
    $('ut-copy').disabled = !ok;
  }
  ['url'].concat(keys).forEach(function (k) { $('ut-' + k).addEventListener('input', build); });
  $('ut-copy').addEventListener('click', function () { if (navigator.clipboard) { navigator.clipboard.writeText(link).catch(function () {}); } });
  build();
})();
</script>
@endpush
