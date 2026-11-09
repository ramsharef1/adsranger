@php
extract(\App\Support\ToolPage::head($lang ?? 'ar', '/tools/page-audit',
    ['فاحص السيو العربي للصفحة | AdsRanger', 'Arabic Page SEO Audit | AdsRanger'],
    ['افحص عنوان الصفحة ووصفها واتجاه النص العربي والبيانات المنظمة والكلمات المفتاحية مجانًا ودون تسجيل.',
        'Check your page title, description, Arabic RTL direction, structured data and keyword match. Free, no sign-up.']));
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
$t = $ar ? [
    'url' => 'رابط الصفحة', 'kw' => 'الكلمة المفتاحية المستهدفة (اختياري)', 'run' => 'افحص الصفحة', 'running' => 'جارٍ الفحص…',
    'score' => 'نتيجة فحص الصفحة', 'ok' => 'لم نجد مشكلات أساسية في تهيئة الصفحة.',
    'bad_url' => 'أدخل رابطًا يبدأ بـ https://', 'limit' => 'بلغت الحد اليومي لهذه الأداة؛ جرّبها غدًا.', 'failed' => 'تعذّر الفحص الآن؛ جرّب بعد قليل.',
    'privacy' => 'نفتح الصفحة التي تطلبها فقط لهذا الفحص، ولا نحفظ الروابط أو النتائج.',
    'm' => [
        'blocked' => 'لا يمكن فحص هذا الرابط.', 'fetch_failed' => 'تعذّر فتح الصفحة.', 'not_200' => 'الصفحة لا تفتح بنجاح.',
        'missing_ar_lang' => 'الصفحة بلا سمة lang="ar".', 'missing_rtl_dir' => 'الصفحة بلا اتجاه dir="rtl".',
        'missing_title' => 'عنوان الصفحة <title> مفقود.', 'missing_meta_description' => 'وصف الصفحة meta description مفقود.',
        'missing_h1' => 'لا يوجد عنوان رئيسي <h1> في الصفحة.', 'multiple_h1' => 'يوجد أكثر من عنوان <h1> واحد في الصفحة.',
        'missing_canonical' => 'الرابط المعتمد (canonical) مفقود.', 'image_missing_alt' => 'توجد صور بلا نص بديل (alt).',
        'page_is_noindex' => 'الصفحة تحتوي على وسم noindex يمنع أرشفتها.',
    ],
] : [
    'url' => 'Page URL', 'kw' => 'Target keyword (optional)', 'run' => 'Audit page', 'running' => 'Auditing…',
    'score' => 'Page audit score', 'ok' => 'No core on-page issues found.',
    'bad_url' => 'Enter a URL starting with https://', 'limit' => 'You have reached today’s limit for this tool; try again tomorrow.', 'failed' => 'The check could not run now; try again shortly.',
    'privacy' => 'We open the page you ask for only for this check, and we do not store URLs or results.',
    'm' => [
        'blocked' => 'This URL cannot be checked.', 'fetch_failed' => 'The page could not be opened.', 'not_200' => 'The page did not return HTTP 200.',
        'missing_ar_lang' => 'Missing lang="ar" on <html>.', 'missing_rtl_dir' => 'Missing dir="rtl" on <html>.',
        'missing_title' => 'Missing <title> tag.', 'missing_meta_description' => 'Missing meta description.',
        'missing_h1' => 'Missing <h1> heading.', 'multiple_h1' => 'Multiple <h1> headings found.',
        'missing_canonical' => 'Missing rel="canonical" link.', 'image_missing_alt' => 'One or more images are missing alt text.',
        'page_is_noindex' => 'Page has a noindex meta tag.',
    ],
];
@endphp
@extends('layouts.public')
@push('styles')
@include('public.tools.partials.styles')
<style>.pa-issue{border-inline-start:4px solid var(--bad);padding:8px 12px;margin:8px 0;background:var(--bg)}.pa-ok{border-inline-start:4px solid #0f766e;padding:8px 12px;margin:8px 0;background:var(--bg)}</style>
@endpush
@section('hero')
<h1>{{ $ar ? 'فاحص السيو العربي للصفحة' : 'Arabic page SEO audit' }}</h1>
<p class="muted" style="font-size:17px">{{ $ar ? 'افحص جاهزية صفحتك لمحركات البحث العربية: ضبط اللغة والاتجاه، طول العنوان والوصف بالبكسل، الترويسات، والبيانات المنظمة.' : 'Audit your page for Arabic search readiness: language and RTL attributes, title and description pixel width, headings, and structured data.' }}</p>
@endsection
@section('content')
<section class="tl">
    <form class="card" id="pa-form" novalidate>
        <label for="pa-url">{{ $t['url'] }}</label>
        <input id="pa-url" type="text" dir="ltr" inputmode="url" maxlength="500" value="https://adsranger.com/" aria-describedby="pa-err">
        <label for="pa-kw" style="margin-top:12px;display:block">{{ $t['kw'] }}</label>
        <input id="pa-kw" type="text" maxlength="120" placeholder="{{ $ar ? 'مثال: إدارة الإعلانات والسيو' : 'e.g. Arabic SEO' }}">
        <p class="tl-err" id="pa-err" hidden></p>
        <div class="tl-actions"><button type="submit" class="btn" id="pa-run">{{ $t['run'] }}</button></div>
        <p class="muted" style="margin-top:12px">{{ $t['privacy'] }}</p>
    </form>
    <div class="tl-out" aria-live="polite" id="pa-out" hidden></div>
</section>
<p><a class="btn" href="{{ $u('/waitlist') }}?type=agency">{{ $ar ? 'انضم إلى قائمة الانتظار' : 'Join the waitlist' }}</a></p>
@include('public.tools.partials.tool-guide-faq', ['toolSlug' => 'page-audit', 'ar' => $ar])
@endsection
@push('scripts')
<script>
(function(){
  var T = @json($t), $ = function (id) { return document.getElementById(id); };
  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) { e.className = cls; } if (text !== undefined) { e.textContent = text; } return e; }
  $('pa-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var url = $('pa-url').value.trim(), kw = $('pa-kw').value.trim(), err = $('pa-err'), out = $('pa-out');
    if (!/^https?:\/\/\S+\.\S+/i.test(url)) { err.textContent = T.bad_url; err.hidden = false; return; }
    err.hidden = true; $('pa-run').disabled = true; $('pa-run').textContent = T.running;
    fetch(@json(url('/tools/page-audit/run')), { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) }, body: JSON.stringify({ url: url, keyword: kw }) })
      .then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
      .then(function (res) {
        out.innerHTML = '';
        if (res.status === 429) { err.textContent = T.limit; err.hidden = false; return; }
        if (res.status !== 200 || !res.body) { err.textContent = T.failed; err.hidden = false; return; }
        var d = res.body;
        if (!d.ok) { err.textContent = T.m[d.error] || T.failed; err.hidden = false; return; }
        out.appendChild(el('h3', '', T.score + ': ' + d.score + ' / 100'));
        if (d.serp) {
          out.appendChild(el('p', 'muted', 'Title: ' + d.serp.title_px + 'px (' + d.serp.title_chars + ' chars) · Description: ' + d.serp.description_px + 'px (' + d.serp.description_chars + ' chars)'));
        }
        if (!d.issues || d.issues.length === 0) { out.appendChild(el('div', 'pa-ok', T.ok)); }
        (d.issues || []).forEach(function (code) {
          out.appendChild(el('div', 'pa-issue', T.m[code] || code));
        });
        out.hidden = false;
      })
      .catch(function () { err.textContent = T.failed; err.hidden = false; })
      .finally(function () { $('pa-run').disabled = false; $('pa-run').textContent = T.run; });
  });
})();
</script>
@endpush
