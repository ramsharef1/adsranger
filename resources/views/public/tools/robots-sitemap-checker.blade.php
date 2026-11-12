@php
extract(\App\Support\ToolPage::head($lang ?? 'ar', '/tools/robots-sitemap-checker',
    ['فاحص robots.txt وخريطة الموقع | AdsRanger', 'Robots.txt, XML Sitemap & Canonical Checker | AdsRanger'],
    ['افحص ملف robots.txt وخرائط الموقع المعلنة ووسم الرابط المعتمد (canonical) لاكتشاف تعارضات الحظر وأخطاء الأرشفة.',
        'Inspect robots.txt directives, declared XML sitemaps, and page canonical tags to catch Disallow conflicts and HTTP errors.']));
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
$t = $ar ? [
    'url' => 'رابط الصفحة المراد فحصها', 'run' => 'افحص الآن', 'running' => 'جارٍ الفحص…',
    'bad_url' => 'أدخل رابطًا صالحًا يبدأ بـ https://', 'limit' => 'بلغت الحد اليومي لهذه الأداة (5 فحوصات يوميًا)؛ جرّب غدًا.',
    'blocked' => 'لا يمكن فحص هذا العنوان.', 'failed' => 'تعذّر إتمام الفحص الآن.',
    'ok' => 'لا توجد تعارضات بين robots.txt وخريطة الموقع والرابط المعتمد.',
    'm' => [
        'missing_robots' => 'لم يُعثر على ملف robots.txt صالح (غير 200).',
        'url_disallowed' => 'مسار الصفحة محظور بواسطة قاعدة Disallow في ملف robots.txt.',
        'no_sitemap_in_robots' => 'لا يوجد سطر Sitemap معلن داخل ملف robots.txt.',
        'sitemap_error' => 'إحدى خرائط الموقع المعلنة لا تفتح برمز 200 أو ليست بصيغة XML سليمة.',
        'missing_canonical' => 'الصفحة لا تحتوي على وسم رابط معتمد (rel="canonical").',
        'canonical_points_elsewhere' => 'يشير وسم canonical إلى رابط مختلف عن الصفحة المفحوصة.',
        'page_not_200' => 'الصفحة نفسها لم ترجع رمز النجاح 200.',
    ],
] : [
    'url' => 'Page URL to inspect', 'run' => 'Run check', 'running' => 'Checking…',
    'bad_url' => 'Enter a valid URL starting with https://', 'limit' => 'Daily limit reached (5 checks/day); try again tomorrow.',
    'blocked' => 'This host cannot be checked.', 'failed' => 'Check could not complete right now.',
    'ok' => 'No conflicts detected across robots.txt, XML sitemap, and canonical tag.',
    'm' => [
        'missing_robots' => 'No HTTP 200 /robots.txt file found.',
        'url_disallowed' => 'Target URL path is blocked by a wildcard Disallow rule in robots.txt.',
        'no_sitemap_in_robots' => 'No Sitemap: directive declared in robots.txt.',
        'sitemap_error' => 'A declared XML sitemap failed HTTP 200 or XML root validation.',
        'missing_canonical' => 'Page is missing a <link rel="canonical"> tag.',
        'canonical_points_elsewhere' => 'Page canonical tag points to a different URL.',
        'page_not_200' => 'Target page did not return HTTP 200.',
    ],
];
@endphp
@extends('layouts.public')
@push('styles')
@include('public.tools.partials.styles')
@endpush
@section('hero')
<h1>{!! $ar ? 'فاحص <bdi dir="ltr">robots.txt</bdi> وخريطة الموقع والرابط المعتمد' : 'Robots.txt, XML Sitemap & Canonical Checker' !!}</h1>
<p class="muted" style="font-size:17px">{{ $ar ? 'تأكّد من عدم وجود تعارض بين قواعد الزحف وخرائط XML ووسم الرابط المعتمد للصفحة.' : 'Catch Disallow blocks, missing XML sitemap declarations, and canonical mismatches in one check.' }}</p>
@endsection
@section('content')
<section class="tl">
    <form class="card" id="rs-chk-form" novalidate>
        <label for="rsc-url">{{ $t['url'] }}</label>
        <input id="rsc-url" type="url" dir="ltr" maxlength="500" value="https://adsranger.com/">
        <p class="tl-err" id="rsc-err" hidden></p>
        <div class="tl-actions"><button type="submit" class="btn" id="rsc-btn">{{ $t['run'] }}</button></div>
        <p class="muted" style="margin-top:12px">{{ $privacy }}</p>
    </form>

    <div class="tl-out" id="rsc-out" aria-live="polite" hidden></div>
</section>
<p style="margin-top:20px"><a class="btn" href="{{ $u('/waitlist') }}?type=agency">{{ $ar ? 'انضم إلى قائمة الانتظار' : 'Join the waitlist' }}</a></p>
@include('public.tools.partials.tool-guide-faq', ['toolSlug' => 'robots-sitemap-checker', 'ar' => $ar])
@endsection
@push('scripts')
<script>
(function(){
  var T = @json($t), $ = function(id){ return document.getElementById(id); };
  function el(tag, cls, txt){ var n = document.createElement(tag); if(cls) n.className = cls; if(txt !== undefined) n.textContent = txt; return n; }
  $('rs-chk-form').addEventListener('submit', function(e){
    e.preventDefault();
    var u = $('rsc-url').value.trim(), err = $('rsc-err'), out = $('rsc-out');
    if(!/^https?:\/\/\S+\.\S+/i.test(u)){ err.textContent = T.bad_url; err.hidden = false; return; }
    err.hidden = true; $('rsc-btn').disabled = true; $('rsc-btn').textContent = T.running;
    fetch(@json(url('/tools/robots-sitemap-checker/run')), {
      method:'POST',
      headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':@json(csrf_token())},
      body:JSON.stringify({url:u})
    }).then(function(r){ return r.json().then(function(b){ return {s:r.status,b:b}; }); })
      .then(function(res){
        out.innerHTML = '';
        if(res.s === 429){ err.textContent = T.limit; err.hidden = false; return; }
        if(!res.b || !res.b.ok){ err.textContent = (res.b && res.b.error === 'blocked') ? T.blocked : T.failed; err.hidden = false; return; }
        var kv = el('ul', 'tl-kv'); kv.dir = 'ltr';
        [['Page HTTP', res.b.page_status || '—'], ['robots.txt HTTP', res.b.robots_status || '—'], ['Canonical', res.b.canonical || '—'], ['Sitemaps declared', (res.b.sitemaps || []).length]].forEach(function(pair){
          var li = el('li', ''); li.appendChild(el('span', '', pair[0])); li.appendChild(el('strong', '', String(pair[1]))); kv.appendChild(li);
        });
        out.appendChild(kv);
        if(!(res.b.issues || []).length){
          var okP = el('p', 'tl-big', T.ok); okP.style.marginTop = '14px'; out.appendChild(okP);
        } else {
          (res.b.issues || []).forEach(function(code){
            var p = el('p', 'tl-err', T.m[code] || code); out.appendChild(p);
          });
        }
        out.hidden = false;
      }).catch(function(){ err.textContent = T.failed; err.hidden = false; })
      .finally(function(){ $('rsc-btn').disabled = false; $('rsc-btn').textContent = T.run; });
  });
})();
</script>
@endpush
