@php
// SEO slice 0 step 0.4 · /tools/hreflang-checker (PR-015 Part 2 §2.4). The only tool with a server part: the page posts
// the URL to /tools/hreflang-checker/run (SafeFetcher + Seo\Hreflang). Privacy line = Website's draft, Business to confirm.
extract(\App\Support\ToolPage::head($lang ?? 'ar', '/tools/hreflang-checker',
    ['فاحص hreflang للمواقع العربية والإنجليزية | AdsRanger', 'Hreflang Checker for Arabic and English Sites | AdsRanger'],
    ['أدخل رابط صفحة، وسنفحص روابط اللغات فيها وفي نسخها الأخرى: الرموز، والروابط المتبادلة، والنسخة الافتراضية، واتجاه الصفحة العربية. مجانًا ودون تسجيل.',
        'Enter a page URL to check its language links and its other versions: codes, return links, x-default and the Arabic page\'s direction. Free, no sign-up.']));
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
$t = $ar ? [
    'url' => 'رابط الصفحة', 'run' => 'افحص', 'running' => 'جارٍ الفحص…', 'pages' => 'الصفحات التي فُحصت', 'ok' => 'لم نجد مشكلات في روابط اللغات.',
    'bad_url' => 'أدخل رابطًا يبدأ بـ https://', 'limit' => 'بلغت الحد اليومي لهذه الأداة؛ جرّبها غدًا.', 'failed' => 'تعذّر الفحص الآن؛ جرّب بعد قليل.',
    'privacy' => 'نفتح الصفحات التي تطلبها فقط لهذا الفحص، ولا نحفظ الروابط أو النتائج.',
    'ip' => 'لاحتساب حد الفحص اليومي نستخدم عنوان <bdi dir=\"ltr\">IP</bdi> بصيغة مشفّرة فقط، ونحذفه بعد 24 ساعة.',
    'm' => [
        'blocked' => 'لا يمكن فحص هذا الرابط.', 'fetch_failed' => 'تعذّر فتح الصفحة.', 'not_200' => 'الصفحة لا تفتح بنجاح (رمز :v).',
        'no_hreflang' => 'لا توجد روابط hreflang في الصفحة.', 'bad_code' => 'الرمز :v غير صالح؛ استخدم رمز اللغة ثم رمز الدولة، مثل ar-AE وen-GB.',
        'duplicate_code' => 'الرمز :v مكرر في الصفحة نفسها.', 'no_self' => 'لا تشير الصفحة إلى نفسها في روابط اللغات.',
        'no_x_default' => 'لا توجد نسخة x-default.', 'canonical_elsewhere' => 'يشير الرابط المعتمد (canonical) إلى صفحة أخرى: :v',
        'ar_no_lang' => 'الصفحة العربية بلا lang="ar".', 'ar_no_rtl' => 'الصفحة العربية بلا dir="rtl".',
        'no_return' => 'لا تشير هذه الصفحة إلى الصفحة الأولى. أضف رابطًا متبادلًا.',
    ],
] : [
    'url' => 'Page URL', 'run' => 'Check', 'running' => 'Checking…', 'pages' => 'Pages checked', 'ok' => 'No problems found in the language links.',
    'bad_url' => 'Enter a URL starting with https://', 'limit' => 'You\'ve reached today\'s limit for this tool; try again tomorrow.', 'failed' => 'The check couldn\'t run now; try again shortly.',
    'privacy' => 'We open the pages you ask for only for this check, and we don\'t store the URLs or results.',
    'ip' => 'To count the daily limit, we use your IP address only in hashed form and delete it after 24 hours.',
    'm' => [
        'blocked' => 'This URL can\'t be checked.', 'fetch_failed' => 'The page couldn\'t be opened.', 'not_200' => 'The page doesn\'t open successfully (code :v).',
        'no_hreflang' => 'The page has no hreflang links.', 'bad_code' => 'The code :v isn\'t valid; use a language code then a country code, like ar-AE or en-GB.',
        'duplicate_code' => 'The code :v appears twice on the same page.', 'no_self' => 'The page doesn\'t list itself in its language links.',
        'no_x_default' => 'No x-default version.', 'canonical_elsewhere' => 'The canonical link points to another page: :v',
        'ar_no_lang' => 'The Arabic page has no lang="ar".', 'ar_no_rtl' => 'The Arabic page has no dir="rtl".',
        'no_return' => 'This page doesn\'t link back to the first page. Add a return link.',
    ],
];
@endphp
@extends('layouts.public')
@push('styles')
@include('public.tools.partials.styles')
<style>.hl-issue{border-inline-start:4px solid var(--bad);padding:6px 10px;margin:8px 0;background:var(--bg)}.hl-issue.warning{border-color:#d97706}.hl-url{font-size:13px;color:var(--muted);overflow-wrap:anywhere}</style>
@endpush
@section('hero')
<h1>{{ $ar ? 'فاحص روابط اللغات (hreflang)' : 'Hreflang checker' }}</h1>
<p class="muted" style="font-size:17px">{{ $ar ? 'تأكّد أن نسخ صفحتك العربية والإنجليزية تشير إحداها إلى الأخرى بشكل صحيح، حتى يعرض محرك البحث النسخة المناسبة لكل زائر.' : 'Make sure the Arabic and English versions of your page point to each other correctly, so search engines show each visitor the right one.' }}</p>
@endsection
@section('content')
<section class="tl">
    <form class="card" id="hl-form" novalidate>
        <label for="hl-url">{{ $t['url'] }}</label>
        <input id="hl-url" type="text" dir="ltr" inputmode="url" maxlength="500" value="https://adsranger.com/" aria-describedby="hl-err">
        <p class="tl-err" id="hl-err" hidden></p>
        <div class="tl-actions"><button type="submit" class="btn" id="hl-run">{{ $t['run'] }}</button></div>
        <p class="muted" style="margin-top:12px">{{ $t['privacy'] }}</p>
        <p class="muted" style="margin-top:4px">{!! $t['ip'] !!}</p>
    </form>
    <div class="tl-out" aria-live="polite" id="hl-out" hidden></div>
</section>
<p><a class="btn" href="{{ $u('/waitlist') }}?type=agency">{{ $ar ? 'انضم إلى قائمة الانتظار' : 'Join the waitlist' }}</a></p>
@include('public.tools.partials.tool-guide-faq', ['toolSlug' => 'hreflang-checker', 'ar' => $ar])
@endsection
@push('scripts')
<script>
(function(){
  var T = @json($t), $ = function (id) { return document.getElementById(id); };
  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) { e.className = cls; } if (text !== undefined) { e.textContent = text; } return e; }
  $('hl-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var url = $('hl-url').value.trim(), err = $('hl-err'), out = $('hl-out');
    if (!/^https?:\/\/\S+\.\S+/i.test(url)) { err.textContent = T.bad_url; err.hidden = false; return; }
    err.hidden = true; $('hl-run').disabled = true; $('hl-run').textContent = T.running;
    fetch(@json(url('/tools/hreflang-checker/run')), { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) }, body: JSON.stringify({ url: url }) })
      .then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
      .then(function (res) {
        out.innerHTML = '';
        if (res.status === 429) { err.textContent = T.limit; err.hidden = false; return; }
        if (res.status !== 200) { err.textContent = T.failed; err.hidden = false; return; }
        var d = res.body;
        out.appendChild(el('h3', '', T.pages));
        var ul = el('ul', 'tl-list'); ul.dir = 'ltr';
        d.pages.forEach(function (p) { ul.appendChild(el('li', '', (p.status || '—') + ' · ' + p.url.replace(/^https?:\/\//, ''))); });
        out.appendChild(ul);
        if (d.issues.length === 0) { out.appendChild(el('p', 'tl-big', T.ok)); }
        d.issues.forEach(function (i) {
          var box = el('div', 'hl-issue ' + i.level);
          box.appendChild(el('div', '', (T.m[i.code] || i.code).replace(':v', i.value || (d.pages.find(function (p) { return p.url === i.url; }) || {}).status || '')));
          box.appendChild(el('div', 'hl-url', i.url)).dir = 'ltr';
          out.appendChild(box);
        });
        out.hidden = false;
      })
      .catch(function () { err.textContent = T.failed; err.hidden = false; })
      .finally(function () { $('hl-run').disabled = false; $('hl-run').textContent = T.run; });
  });
})();
</script>
@endpush
