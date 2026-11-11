@php
extract(\App\Support\ToolPage::head($lang ?? 'ar', '/tools/capi-pixel-checker',
    ['فاحص البكسل ووسوم التتبع الإعلانية | AdsRanger', 'Ad Pixel & Tag Checker (Meta, GTM, GA4, Salla/Zid) | AdsRanger'],
    ['افحص أي صفحة عامة لرصد وسوم Meta Pixel وGTM وGA4 وTikTok وSnap وروابط سلة وزد مع تنبيهات dataLayer وnoscript. مجانًا ودون تسجيل.',
        'Inspect any public URL to detect Meta Pixel, GTM, GA4, Google Ads, TikTok, Snap, LinkedIn, and Salla/Zid hooks. Free, no sign-up.']));
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
$t = $ar ? [
    'url' => 'رابط الصفحة أو المتجر', 'run' => 'افحص الوسوم', 'running' => 'جارٍ الفحص…',
    'bad_url' => 'أدخل رابطًا يبدأ بـ https://', 'limit' => 'بلغت الحد اليومي لهذه الأداة (5 فحوصات يوميًا)؛ جرّب غدًا.',
    'failed' => 'تعذّر فحص الرابط الآن.', 'blocked' => 'لا يمكن فحص هذا العنوان.',
    'found' => 'الوسوم والمنصات المرصودة في HTML', 'none' => 'لم تُرصد وسوم تتبع إعلانية في كود الصفحة العام.',
    'checks' => 'فحوصات البنية الأساسية', 'dl_ok' => 'كائن dataLayer مهيّأ', 'dl_no' => 'لم يُرصد تعريف dataLayer صريح',
    'ns_ok' => 'وسم noscript الاحتياطي موجود', 'ns_no' => 'لا يوجد وسم noscript احتياطي',
    'scope' => 'ملاحظة شفافية: يفحص هذا الاختبار كود HTML والسكربتات العامة من جهة المتصفح فقط؛ لا يمكن التحقق من أحداث الخادم (Server-Side CAPI) إلا من داخل مدير الأحداث في حسابك الإعلاني.',
] : [
    'url' => 'Landing page or store URL', 'run' => 'Check tags', 'running' => 'Checking…',
    'bad_url' => 'Enter a public URL starting with https://', 'limit' => 'You have reached today\'s limit (5 checks/day); try again tomorrow.',
    'failed' => 'Could not inspect this URL right now.', 'blocked' => 'This host cannot be checked.',
    'found' => 'Detected client-side tags & hooks', 'none' => 'No ad pixels or tracking tags detected in the public HTML.',
    'checks' => 'Baseline checks', 'dl_ok' => 'dataLayer detected', 'dl_no' => 'No explicit dataLayer found',
    'ns_ok' => 'noscript fallback present', 'ns_no' => 'No noscript fallback tag found',
    'scope' => 'Scope note: This check inspects client-side HTML and script tags only. Server-to-server CAPI events do not appear in public HTML and must be verified inside your ad platform\'s Events Manager.',
];
@endphp
@extends('layouts.public')
@push('styles')
@include('public.tools.partials.styles')
@endpush
@section('hero')
<h1>{{ $ar ? 'فاحص البكسل ووسوم التتبع الإعلانية' : 'Ad pixel & tag checker' }}</h1>
<p class="muted" style="font-size:17px">{!! $ar ? 'تحقق في ثوانٍ من وجود <bdi dir="ltr">Meta Pixel</bdi> و<bdi dir="ltr">GTM</bdi> و<bdi dir="ltr">GA4</bdi> وبكسلات المنصات وتكاملات سلة وزد على صفحة الهبوط.' : 'Verify Meta Pixel, Google Tag Manager, GA4, Google Ads, TikTok, Snap, LinkedIn, and Salla/Zid hooks on any public page.' !!}</p>
@endsection
@section('content')
<section class="tl">
    <form class="card" id="cp-form" novalidate>
        <label for="cp-url">{{ $t['url'] }}</label>
        <input id="cp-url" type="url" dir="ltr" inputmode="url" maxlength="500" placeholder="https://example.com" value="https://adsranger.com/">
        <p class="tl-err" id="cp-err" hidden></p>
        <div class="tl-actions"><button type="submit" class="btn" id="cp-btn">{{ $t['run'] }}</button></div>
        <p class="tl-hint" style="margin-top:12px">{{ $t['scope'] }}</p>
    </form>
    <div class="tl-out" id="cp-out" aria-live="polite">
        <p class="muted" style="margin:0">{{ $privacy }}</p>
    </div>
</section>
<p style="margin-top:20px"><a class="btn" href="{{ $u('/waitlist') }}?type=agency">{{ $ar ? 'انضم إلى قائمة الانتظار' : 'Join the waitlist' }}</a></p>
@include('public.tools.partials.tool-guide-faq', ['toolSlug' => 'capi-pixel-checker', 'ar' => $ar])
@endsection
@push('scripts')
<script>
(function(){
  var T = @json($t), $ = function(id){ return document.getElementById(id); };
  function el(tag, cls, txt){ var n = document.createElement(tag); if(cls) n.className = cls; if(txt !== undefined) n.textContent = txt; return n; }
  $('cp-form').addEventListener('submit', function(e){
    e.preventDefault();
    var u = $('cp-url').value.trim(), err = $('cp-err'), out = $('cp-out');
    if(!/^https?:\/\/\S+\.\S+/i.test(u)){ err.textContent = T.bad_url; err.hidden = false; return; }
    err.hidden = true; $('cp-btn').disabled = true; $('cp-btn').textContent = T.running;
    fetch(@json(url('/tools/capi-pixel-checker/run')), {
      method: 'POST',
      headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':@json(csrf_token())},
      body: JSON.stringify({url: u})
    }).then(function(r){ return r.json().then(function(b){ return {s:r.status, b:b}; }); })
      .then(function(res){
        out.innerHTML = '';
        if(res.s === 429){ err.textContent = T.limit; err.hidden = false; return; }
        if(!res.b || !res.b.ok){ err.textContent = (res.b && res.b.error === 'blocked') ? T.blocked : T.failed; err.hidden = false; return; }
        out.appendChild(el('h3', '', T.found));
        if(!res.b.detected.length){
          out.appendChild(el('p', 'muted', T.none));
        } else {
          var ul = el('ul', 'tl-kv');
          res.b.detected.forEach(function(d){
            var li = el('li', '');
            var b1 = el('bdi', '', d.label); b1.dir = 'ltr';
            var b2 = el('strong', '', d.ids && d.ids.length ? d.ids.join(', ') : 'Detected'); b2.dir = 'ltr';
            li.appendChild(b1); li.appendChild(b2); ul.appendChild(li);
          });
          out.appendChild(ul);
        }
        out.appendChild(el('h3', '', T.checks));
        var cUl = el('ul', 'tl-list');
        cUl.appendChild(el('li', '', res.b.has_datalayer ? T.dl_ok : T.dl_no));
        cUl.appendChild(el('li', '', res.b.has_noscript ? T.ns_ok : T.ns_no));
        out.appendChild(cUl);
        out.appendChild(el('p', 'tl-hint', T.scope));
      }).catch(function(){ err.textContent = T.failed; err.hidden = false; })
      .finally(function(){ $('cp-btn').disabled = false; $('cp-btn').textContent = T.run; });
  });
})();
</script>
@endpush
