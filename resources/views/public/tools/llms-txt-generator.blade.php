@php
extract(\App\Support\ToolPage::head($lang ?? 'ar', '/tools/llms-txt-generator',
    ['منشئ ملف llms.txt وفاحص زواحف الذكاء الاصطناعي | AdsRanger', 'llms.txt Generator & AI Crawler robots.txt Checker | AdsRanger'],
    ['أنشئ ملف llms.txt القياسي لموقعك العربي أو الإنجليزي وافحص صلاحيات GPTBot وClaudeBot وPerplexityBot في ملف robots.txt.',
        'Generate standard llms.txt markdown files and audit your domain\'s robots.txt rules for GPTBot, OAI-SearchBot, ClaudeBot, PerplexityBot, and Google-Extended.']));
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
@endphp
@extends('layouts.public')
@push('styles')
@include('public.tools.partials.styles')
@endpush
@section('hero')
<h1>{!! $ar ? 'منشئ ملف <bdi dir="ltr">llms.txt</bdi> وفاحص زواحف الذكاء' : 'llms.txt Generator & AI Crawler Checker' !!}</h1>
<p class="muted" style="font-size:17px">{!! $ar ? 'جهّز دليل <bdi dir="ltr">llms.txt</bdi> القياسي لموقعك وتحقّق من حالة زواحف البحث الذكية في <bdi dir="ltr">robots.txt</bdi>.' : 'Build a clean llms.txt markdown file and check how your robots.txt treats AI search crawlers.' !!}</p>
@endsection
@section('content')
<section class="tl">
    <form class="card" onsubmit="return false" novalidate>
        <label for="lm-name">{{ $ar ? 'اسم الموقع أو العلامة' : 'Site or brand name' }}</label>
        <input id="lm-name" type="text" value="AdsRanger">

        <label for="lm-sum">{{ $ar ? 'وصف موجز للموقع' : 'One-line summary' }}</label>
        <input id="lm-sum" type="text" value="{{ $ar ? 'منصة تقارير الإعلانات والسيو العربي لوكالات التسويق.' : 'Arabic-first ad reporting and SEO workspace for MENA agencies.' }}">

        <label for="lm-links">{{ $ar ? 'أهم الصفحات (كل سطر بصيغة: العنوان | الرابط | ملاحظة)' : 'Key pages (one per line: Title | URL | Note)' }}</label>
        <textarea id="lm-links" rows="4" dir="ltr">Features | https://adsranger.com/features | Core platform modules
Pricing | https://adsranger.com/pricing | Plan tiers and limits
Security | https://adsranger.com/security | Data handling and OAuth scopes</textarea>

        <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">
        <label for="lm-url">{!! $ar ? 'افحص ملف <bdi dir="ltr">robots.txt</bdi> لموقعك' : 'Audit a domain\'s robots.txt for AI crawlers' !!}</label>
        <input id="lm-url" type="url" dir="ltr" placeholder="https://adsranger.com" value="https://adsranger.com">
        <p class="tl-err" id="lm-err" hidden></p>
        <div class="tl-actions"><button type="button" class="btn" id="lm-check">{{ $ar ? 'افحص صلاحيات الزواحف' : 'Check AI crawler rules' }}</button></div>
    </form>

    <div class="tl-out" aria-live="polite">
        <h3><bdi dir="ltr">llms.txt</bdi></h3>
        <pre class="tl-code" id="lm-out" dir="ltr" style="white-space:pre-wrap"></pre>
        <div class="tl-actions">
            <button type="button" class="btn" id="lm-copy">{{ $ar ? 'نسخ الملف' : 'Copy markdown' }}</button>
        </div>
        <div id="lm-audit" style="margin-top:16px"></div>
        <p class="muted" style="margin-top:12px">{{ $privacy }}</p>
    </div>
</section>
<p style="margin-top:20px"><a class="btn" href="{{ $u('/waitlist') }}?type=agency">{{ $ar ? 'انضم إلى قائمة الانتظار' : 'Join the waitlist' }}</a></p>
@include('public.tools.partials.tool-guide-faq', ['toolSlug' => 'llms-txt-generator', 'ar' => $ar])
@endsection
@push('scripts')
<script>
(function(){
  var $ = function(id){ return document.getElementById(id); }, isAr = @json($ar);
  function build(){
    var md = '# ' + $('lm-name').value.trim() + '\n\n> ' + $('lm-sum').value.trim() + '\n\n## Key Pages\n\n';
    $('lm-links').value.split(/\r?\n/).forEach(function(line){
      var p = line.split('|').map(function(x){ return x.trim(); });
      if(p[0] && p[1]) md += '- [' + p[0] + '](' + p[1] + ')' + (p[2] ? ': ' + p[2] : '') + '\n';
    });
    $('lm-out').textContent = md;
  }
  ['lm-name','lm-sum','lm-links'].forEach(function(id){ $(id).addEventListener('input', build); });
  $('lm-copy').addEventListener('click', function(){ navigator.clipboard && navigator.clipboard.writeText($('lm-out').textContent); });
  $('lm-check').addEventListener('click', function(){
    var u = $('lm-url').value.trim(), err = $('lm-err'), box = $('lm-audit');
    if(!/^https?:\/\/\S+\.\S+/i.test(u)){ err.textContent = isAr ? 'أدخل رابطًا يبدأ بـ https://' : 'Enter a valid https:// URL.'; err.hidden = false; return; }
    err.hidden = true;
    fetch(@json(url('/tools/llms-txt-generator/check')), {
      method:'POST',
      headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':@json(csrf_token())},
      body:JSON.stringify({url:u})
    }).then(function(r){ return r.json().then(function(b){ return {s:r.status,b:b}; }); })
      .then(function(res){
        box.innerHTML = '';
        if(res.s === 429){ err.textContent = isAr ? 'بلغت الحد اليومي لهذه الأداة؛ جرّب غدًا.' : 'Daily check limit reached; try again tomorrow.'; err.hidden = false; return; }
        if(!res.b || !res.b.ok){ err.textContent = isAr ? 'تعذّر فحص هذا النطاق.' : 'Could not inspect this host.'; err.hidden = false; return; }
        var ul = document.createElement('ul'); ul.className = 'tl-kv'; ul.dir = 'ltr';
        Object.keys(res.b.bots || {}).forEach(function(bot){
          var li = document.createElement('li');
          var s = document.createElement('span'); s.textContent = bot;
          var st = document.createElement('strong'); st.textContent = res.b.bots[bot];
          li.appendChild(s); li.appendChild(st); ul.appendChild(li);
        });
        box.appendChild(ul);
      });
  });
  build();
})();
</script>
@endpush
