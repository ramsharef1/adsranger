@php
extract(\App\Support\ToolPage::head($lang ?? 'ar', '/tools/ai-crawler-revenue-checker',
    ['مدقق زيارات بوتات الذكاء الاصطناعي والعائد غير المستثمر | AdsRanger', 'AI Bot Traffic & Unmonetized Revenue Checker | AdsRanger'],
    ['اعرف أي بوتات ذكاء اصطناعي تستطيع قراءة صفحتك حسب ملف robots.txt، وهل تجد فيها عروضًا تُنسب إليك.',
        'See which AI crawlers your robots.txt lets read a page, and whether they find any offer that credits you.']));
$schema[1]['itemListElement'][1]['name'] = $ar ? 'مدقق زيارات بوتات الذكاء الاصطناعي' : 'AI Bot Traffic Checker';
$h = fn (string $s) => (string) \App\Support\Seo\ToolCopy::html($s, $ar);
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
$t = $ar ? [
    'run' => 'افحص الآن', 'running' => 'جارٍ الفحص…', 'bad_url' => $h('أدخل رابطًا صالحًا يبدأ بـ https://'),
    'limit' => 'بلغت الحد اليومي لهذه الأداة (5 فحوصات يوميًا)؛ جرّب غدًا.', 'blocked' => 'لا يمكن فحص هذا العنوان.', 'failed' => 'تعذّر إتمام الفحص الآن.',
    'allowed' => 'مسموح', 'blocked_s' => 'محظور', 'own' => 'قاعدة خاصة به', 'wildcard' => $h('قاعدة * العامة'), 'none' => 'لا قواعد',
    'purpose' => ['training' => 'تدريب النماذج', 'search' => 'البحث والإجابات', 'user' => 'طلب من مستخدم'],
    'head' => ['البوت', 'الجهة', 'الغرض', 'الحالة', 'حسب'],
    'sum' => 'مسموح: :a · محظور: :b', 'yes' => 'موجود', 'no' => 'غير موجود',
    'llms' => $h('ملف llms.txt'), 'offer' => $h('ترميز schema.org Offer في الصفحة'),
    'hits' => 'من :n زيارة بوت شهريًا (من سجلاتك)، لا تحمل أي زيارة عرضًا يُنسب إليك.',
    'm' => [
        'no_robots' => $h('لم نجد ملف robots.txt يعمل (الرمز ليس 200)، فكل بوتات الذكاء الاصطناعي تستطيع القراءة.'),
        'read_not_monetised' => $h('بوتات الذكاء الاصطناعي تقرأ صفحتك، لكن لا يوجد فيها ترميز Offer ولا ملف llms.txt، فلا تحمل قراءتها أي عرض يُنسب إليك.'),
        'no_offer_schema' => $h('لا يوجد ترميز schema.org Offer في الصفحة.'),
        'no_llms_txt' => $h('لا يوجد ملف llms.txt في جذر الموقع.'),
        'all_blocked' => 'كل بوتات الذكاء الاصطناعي المعروفة محظورة من هذه الصفحة: لا قراءات منها ولا عائد.',
    ],
] : [
    'run' => 'Run check', 'running' => 'Checking…', 'bad_url' => 'Enter a valid URL starting with https://',
    'limit' => 'Daily limit reached (5 checks a day); try again tomorrow.', 'blocked' => 'This address cannot be checked.', 'failed' => 'The check could not finish right now.',
    'allowed' => 'Allowed', 'blocked_s' => 'Blocked', 'own' => 'its own rules', 'wildcard' => 'the * rules', 'none' => 'no rules',
    'purpose' => ['training' => 'Model training', 'search' => 'Search and answers', 'user' => 'A user request'],
    'head' => ['Crawler', 'Run by', 'Purpose', 'Status', 'Decided by'],
    'sum' => 'Allowed: :a · Blocked: :b', 'yes' => 'found', 'no' => 'not found',
    'llms' => 'llms.txt file', 'offer' => 'schema.org Offer markup on the page',
    'hits' => 'Of :n AI-bot hits a month (from your logs), none carries an offer that credits you.',
    'm' => [
        'no_robots' => 'No working robots.txt (status not 200), so every AI crawler may read.',
        'read_not_monetised' => 'AI crawlers can read this page, but it has no Offer markup and the site has no llms.txt, so their reads carry no offer that credits you.',
        'no_offer_schema' => 'No schema.org Offer markup on the page.',
        'no_llms_txt' => 'No llms.txt file at the site root.',
        'all_blocked' => 'Every known AI crawler is blocked from this page: no reads, and no revenue from them.',
    ],
];
@endphp
@extends('layouts.public')
@push('styles')
@include('public.tools.partials.styles')
@endpush
@section('hero')
<h1>{{ $ar ? 'مدقق زيارات بوتات الذكاء الاصطناعي والعائد غير المستثمر' : 'AI Bot Traffic & Unmonetized Revenue Checker' }}</h1>
<p class="muted" style="font-size:17px">{!! $h($ar ? 'اعرف أي بوتات ذكاء اصطناعي (GPTBot، ClaudeBot، PerplexityBot، Google-Extended، وغيرها) يسمح لها ملف robots.txt بقراءة صفحتك، وهل تجد فيها عروضًا تُنسب إليك.' : 'See which AI crawlers (GPTBot, ClaudeBot, PerplexityBot, Google-Extended and more) your robots.txt lets read a page, and whether they find any offer that credits you.') !!}</p>
@endsection
@section('content')
<section class="tl">
    <form class="card" id="aic-form" novalidate>
        <label for="aic-url">{{ $ar ? 'رابط الصفحة' : 'Page URL' }}</label>
        <input id="aic-url" type="url" dir="ltr" maxlength="500" value="https://adsranger.com/">
        <label for="aic-hits" style="margin-top:10px">{{ $ar ? 'زيارات بوتات الذكاء الاصطناعي شهريًا من سجلات خادمك (اختياري)' : 'AI-bot hits a month from your server logs (optional)' }}</label>
        <input id="aic-hits" type="text" inputmode="numeric" dir="ltr" maxlength="12">
        <p class="tl-err" id="aic-err" hidden></p>
        <div class="tl-actions"><button type="submit" class="btn" id="aic-btn">{{ $t['run'] }}</button></div>
        <p class="muted" style="margin-top:12px">{{ $privacy }} {{ $ar ? 'لا نقدّر زياراتك ولا أرباحك: لا نرى سجلات خادمك.' : 'We do not guess your traffic or earnings: we cannot see your server logs.' }}</p>
    </form>
    <div class="tl-out" id="aic-out" aria-live="polite" hidden></div>
</section>
<section class="card" style="margin-top:20px">
    <h2 style="margin-top:0">{!! $h($ar ? 'حوّل قراءات الذكاء الاصطناعي إلى عروض تُنسب إليك مع AdsRanger Reach' : 'Turn AI reads into offers that credit you with AdsRanger Reach') !!}</h2>
    <p class="muted">{!! $h($ar ? 'موجز عروض بترميز Offer، ومقطع llms.txt يحمل أكوادك المتتبَّعة، وعدّاد يفصل زيارات البشر عن قراءات البوتات. قيد التطوير، ولا يُدفع شيء قبل الإطلاق.' : 'An offers feed with Offer markup, an llms.txt block carrying your tracked codes, and a counter that keeps human views apart from AI reads. In development; nothing is paid before launch.') !!}</p>
    <p><a class="btn" href="{{ $u('/reach') }}">{{ $ar ? 'تعرّف على Reach' : 'See Reach' }}</a></p>
</section>
@endsection
@push('scripts')
<script>
(function(){
  var T = @json($t), $ = function(id){ return document.getElementById(id); };
  function el(tag, txt, html){ var n = document.createElement(tag); if(html !== undefined) n.innerHTML = html; else if(txt !== undefined) n.textContent = txt; return n; }
  function western(s){ return String(s || '').replace(/[٠-٩]/g, function(d){ return d.charCodeAt(0) - 1632; }).replace(/[^0-9]/g, ''); }
  $('aic-form').addEventListener('submit', function(e){
    e.preventDefault();
    var u = $('aic-url').value.trim(), err = $('aic-err'), out = $('aic-out'), hits = parseInt(western($('aic-hits').value), 10);
    if(!/^https?:\/\/\S+\.\S+/i.test(u)){ err.innerHTML = T.bad_url; err.hidden = false; return; }
    err.hidden = true; $('aic-btn').disabled = true; $('aic-btn').textContent = T.running;
    fetch(@json(url('/tools/ai-crawler-revenue-checker/run')), {
      method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':@json(csrf_token())}, body:JSON.stringify({url:u})
    }).then(function(r){ return r.json().then(function(b){ return {s:r.status,b:b}; }); })
      .then(function(res){
        out.innerHTML = '';
        if(res.s === 429){ err.textContent = T.limit; err.hidden = false; return; }
        if(!res.b || !res.b.ok){ err.textContent = (res.b && res.b.error === 'blocked') ? T.blocked : T.failed; err.hidden = false; return; }
        out.appendChild(el('p', T.sum.replace(':a', res.b.allowed).replace(':b', res.b.blocked))).className = 'tl-big';
        var wrap = el('div'); wrap.style.overflowX = 'auto';
        var tb = el('table'); tb.style.width = '100%'; tb.style.borderCollapse = 'collapse';
        var hr = el('tr'); T.head.forEach(function(x){ var th = el('th', x); th.style.textAlign = 'start'; th.style.padding = '6px'; hr.appendChild(th); }); tb.appendChild(hr);
        res.b.bots.forEach(function(b){
          var tr = el('tr'); tr.style.borderTop = '1px solid rgba(127,127,127,.25)';
          var bot = el('td'); bot.appendChild(el('bdi', b.bot)).dir = 'ltr';
          var own = el('td'); own.appendChild(el('bdi', b.owner)).dir = 'ltr';
          [bot, own, el('td', T.purpose[b.purpose]), el('td', b.status === 'allowed' ? T.allowed : T.blocked_s), el('td', '', T[b.rule])].forEach(function(td){ td.style.padding = '6px'; tr.appendChild(td); });
          tb.appendChild(tr);
        });
        wrap.appendChild(tb); out.appendChild(wrap);
        out.appendChild(el('p', '', T.llms + ': ' + (res.b.has_llms_txt ? T.yes : T.no) + ' · ' + T.offer + ': ' + (res.b.has_offer_schema ? T.yes : T.no)));
        (res.b.findings || []).forEach(function(code){ var p = el('p', '', T.m[code] || code); p.className = 'tl-err'; out.appendChild(p); });
        if(hits > 0 && (res.b.findings || []).indexOf('read_not_monetised') > -1){ out.appendChild(el('p', T.hits.replace(':n', hits.toLocaleString('en-US')))); }
        out.hidden = false;
      }).catch(function(){ err.textContent = T.failed; err.hidden = false; })
      .finally(function(){ $('aic-btn').disabled = false; $('aic-btn').textContent = T.run; });
  });
})();
</script>
@endpush
