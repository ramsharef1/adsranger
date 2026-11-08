@php
// SEO slice 0 step 0.4 · /tools/serp-preview (PR-015 Part 2 §2.2). Browser only: nothing is sent or stored.
// Text block + FAQ wait for Growth's copy (UX-216…224); noindex until config('seo_tools.public') (Rami approves).
$ar = ($lang ?? 'ar') === 'ar';
$lang = $ar ? 'ar' : 'en';
$arPath = '/tools/serp-preview';
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
$title = $ar ? 'معاينة نتائج جوجل للعناوين العربية | AdsRanger' : 'Google SERP Preview for Arabic Titles | AdsRanger';
$meta = $ar
    ? 'شاهد كيف قد يظهر عنوان صفحتك ووصفها في نتائج البحث على الجوال والكمبيوتر، بالعربية أو الإنجليزية، مع تنبيه عند القطع. مجانًا ودون تسجيل، ولا نحفظ شيئًا.'
    : 'See how your title and description may look in search results on mobile and desktop, with a warning when they get cut. Free, no sign-up, nothing stored.';
$selfUrl = \App\Support\PublicUrl::absolute($arPath, $lang);
$schema = [
    ['@type' => 'WebPage', 'name' => $title, 'url' => $selfUrl, 'inLanguage' => $lang],
    ['@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => $ar ? 'الرئيسية' : 'Home', 'item' => \App\Support\PublicUrl::absolute('/', $lang)],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $ar ? 'معاينة نتائج البحث' : 'Search results preview', 'item' => $selfUrl],
    ]],
];
$t = $ar ? [
    'title' => 'العنوان', 'desc' => 'الوصف', 'url' => 'الرابط', 'url_hint' => 'للعرض فقط، لا نفتحه.',
    'device' => 'الجهاز', 'mobile' => 'الجوال', 'desktop' => 'الكمبيوتر', 'dir' => 'اتجاه الصفحة', 'dir_ar' => 'عربي', 'dir_en' => 'إنجليزي',
    'preview' => 'المعاينة', 'px' => 'بكسل تقريبًا', 'of' => 'من',
    'latin' => 'يبدأ العنوان بكلمة لاتينية، فقد يظهر بترتيب معكوس في النتائج العربية. ابدأه بكلمة عربية.',
    'cut' => 'قد يُقطع العنوان بعد: ', 'short' => 'الوصف قصير؛ قد يكتب محرك البحث وصفًا من نص الصفحة.',
    'digits' => 'يجمع النص بين أرقام ١٢٣ و123؛ استخدم نوعًا واحدًا.',
    'note' => 'المعاينة تقديرية؛ قد يغيّر محرك البحث العنوان أو الوصف.', 'privacy' => 'دون تسجيل، ولا نحفظ أي شيء تدخله.',
    'other' => 'لفحص عدة صفحات معًا، استخدم', 'other_link' => 'فاحص طول العنوان والوصف',
    'ex_title' => 'حاسبة ميزانية إعلانات رمضان 2027 | AdsRanger', 'ex_desc' => 'احسب تقديرًا لميزانية إعلانات رمضان 2027 وتكلفة النتيجة وعدد النتائج من أرقامك أنت.',
] : [
    'title' => 'Title', 'desc' => 'Description', 'url' => 'URL', 'url_hint' => 'Display only; we don\'t open it.',
    'device' => 'Device', 'mobile' => 'Mobile', 'desktop' => 'Desktop', 'dir' => 'Page language', 'dir_ar' => 'Arabic', 'dir_en' => 'English',
    'preview' => 'Preview', 'px' => 'px (approx.)', 'of' => 'of',
    'latin' => 'The title starts with a Latin word, so it may show in the wrong order in Arabic results. Start it with an Arabic word.',
    'cut' => 'The title may be cut after: ', 'short' => 'The description is short; the search engine may write its own from the page text.',
    'digits' => 'The text mixes ١٢٣ and 123 digits; use one kind.',
    'note' => 'The preview is an estimate; the search engine may rewrite the title or description.', 'privacy' => 'No sign-up. Nothing you enter is stored.',
    'other' => 'To check many pages at once, use the', 'other_link' => 'title and description length checker',
    'ex_title' => 'Ramadan 2027 Ad Budget Calculator for Agencies | AdsRanger', 'ex_desc' => 'Estimate a Ramadan 2027 ad budget, cost per result and results from your own numbers.',
];
@endphp
@extends('layouts.public')

@push('styles')
<style>
.sp{display:grid;gap:20px;padding-top:24px}
@media (min-width:1024px){.sp{grid-template-columns:1fr 1fr;align-items:start}}
.sp label{display:flex;align-items:flex-end;min-height:44px}
.sp textarea{min-height:88px}
.sp fieldset{border:0;padding:0;margin:8px 0 0}
.sp legend{min-height:44px;display:flex;align-items:flex-end;padding:0}
.sp-seg{display:flex;gap:8px;flex-wrap:wrap}
.sp-seg label{display:inline-flex;align-items:center;gap:6px;min-height:44px;padding:0 14px;border:1px solid var(--line);border-radius:10px;cursor:pointer}
.sp-seg input{width:auto;min-height:0;margin:0}
.sp-seg label:has(input:checked){border-color:var(--acc);box-shadow:inset 0 0 0 1px var(--acc)}
.sp-hint{font-size:13px;color:var(--muted);margin:4px 0 0}
.sp-card{border:1px solid var(--line);border-radius:12px;padding:16px;background:var(--card);overflow:hidden}
.sp-card .u{font-size:13px;color:var(--muted);overflow-wrap:anywhere}
.sp-card .h{font-family:arial,sans-serif;font-size:20px;line-height:1.3;color:#1a0dab;margin:4px 0}
.dark .sp-card .h,[data-theme=dark] .sp-card .h{color:#8ab4f8}
.sp-card .d{font-family:arial,sans-serif;font-size:14px;line-height:1.58;margin:0}
.sp-card.mobile{max-width:360px}
.sp-bars{list-style:none;padding:0;margin:12px 0 0;font-size:14px}
.sp-msgs{margin:12px 0 0;padding-inline-start:20px}
.sp-msgs li{margin-bottom:6px}
</style>
@endpush

@section('hero')
<h1>{{ $ar ? 'معاينة نتائج البحث' : 'Search results preview' }}</h1>
<p class="muted" style="font-size:17px">{{ $ar ? 'اكتب عنوان صفحتك ووصفها، وشاهد كيف قد يظهران في نتائج البحث على الجوال والكمبيوتر.' : 'Type your page title and description and see how they may look in search results on mobile and desktop.' }}</p>
@endsection

@section('content')
<section class="sp">
    <form class="card" id="sp-form" onsubmit="return false" novalidate>
        <label for="sp-title">{{ $t['title'] }}</label>
        <input id="sp-title" type="text" maxlength="200" value="{{ $t['ex_title'] }}">
        <label for="sp-desc">{{ $t['desc'] }}</label>
        <textarea id="sp-desc" maxlength="400">{{ $t['ex_desc'] }}</textarea>
        <label for="sp-url">{{ $t['url'] }}</label>
        <input id="sp-url" type="text" dir="ltr" inputmode="url" maxlength="200" value="https://adsranger.com/tools/ramadan-budget-calculator" aria-describedby="sp-url-hint">
        <p class="sp-hint" id="sp-url-hint">{{ $t['url_hint'] }}</p>
        <fieldset>
            <legend>{{ $t['device'] }}</legend>
            <div class="sp-seg">
                <label><input type="radio" name="device" value="mobile" checked> {{ $t['mobile'] }}</label>
                <label><input type="radio" name="device" value="desktop"> {{ $t['desktop'] }}</label>
            </div>
        </fieldset>
        <fieldset>
            <legend>{{ $t['dir'] }}</legend>
            <div class="sp-seg">
                <label><input type="radio" name="pdir" value="rtl" @checked($ar)> {{ $t['dir_ar'] }}</label>
                <label><input type="radio" name="pdir" value="ltr" @checked(! $ar)> {{ $t['dir_en'] }}</label>
            </div>
        </fieldset>
    </form>

    <div aria-live="polite">
        <h2 style="font-size:19px;margin-top:0">{{ $t['preview'] }}</h2>
        <div class="sp-card mobile" id="sp-card" dir="rtl">
            <div class="u" id="sp-out-url" dir="ltr"></div>
            <div class="h" id="sp-out-title"></div>
            <p class="d" id="sp-out-desc"></p>
        </div>
        <ul class="sp-bars">
            <li id="sp-bar-title"></li>
            <li id="sp-bar-desc"></li>
        </ul>
        <ul class="sp-msgs" id="sp-msgs"></ul>
        <p class="muted" style="margin-top:12px">{{ $t['note'] }}</p>
        <p class="muted">{{ $t['privacy'] }}</p>
    </div>
</section>
<p>{{ $t['other'] }} <a href="{{ $u('/tools/meta-length-checker') }}">{{ $t['other_link'] }}</a>.</p>
<p><a class="btn" href="{{ $u('/waitlist') }}?type=agency">{{ $ar ? 'انضم إلى قائمة الانتظار' : 'Join the waitlist' }}</a></p>
@endsection

@push('scripts')
@include('public.tools.partials.serp-engine')
<script>
(function(){
  var S = window.ArSerp, W = S.W, T = @json($t), $ = function (id) { return document.getElementById(id); };
  function val(name) { return document.querySelector('input[name=' + name + ']:checked').value; }
  function draw() {
    var title = $('sp-title').value.trim(), desc = $('sp-desc').value.trim(), dev = val('device'), dir = val('pdir');
    var tMax = W.title.max_px[dev], dMax = W.description.max_px[dev];
    var ft = S.fit(title, W.title.font_px, tMax), fd = S.fit(desc, W.description.font_px, dMax);
    var card = $('sp-card'); card.dir = dir; card.className = 'sp-card ' + dev;
    $('sp-out-url').textContent = $('sp-url').value.trim().replace(/^https?:\/\//, '').replace(/\/$/, '').replace(/\//g, ' › ');
    $('sp-out-title').textContent = ft.text; $('sp-out-desc').textContent = fd.text;
    $('sp-bar-title').textContent = T.title + ': ' + S.measure(title, W.title.font_px).toLocaleString('en-US') + ' ' + T.of + ' ' + tMax + ' ' + T.px;
    $('sp-bar-desc').textContent = T.desc + ': ' + S.measure(desc, W.description.font_px).toLocaleString('en-US') + ' ' + T.of + ' ' + dMax + ' ' + T.px;
    var msgs = [];
    if (S.latinFirst(title)) { msgs.push(T.latin); }
    if (ft.cut && ft.after) { msgs.push(T.cut + '«' + ft.after + '»'); }
    if (desc !== '' && S.chars(desc) < W.description.min_chars) { msgs.push(T.short); }
    if (S.mixedDigits(title + ' ' + desc)) { msgs.push(T.digits); }
    var ul = $('sp-msgs'); ul.innerHTML = '';
    msgs.forEach(function (m) { var li = document.createElement('li'); li.textContent = m; ul.appendChild(li); });
  }
  ['sp-title', 'sp-desc', 'sp-url'].forEach(function (id) { $(id).addEventListener('input', draw); });
  document.querySelectorAll('input[name=device],input[name=pdir]').forEach(function (r) { r.addEventListener('change', draw); });
  draw();
})();
</script>
@endpush
