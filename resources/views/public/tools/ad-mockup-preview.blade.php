@php
extract(\App\Support\ToolPage::head($lang ?? 'ar', '/tools/ad-mockup-preview',
    ['معاينة الإعلانات الحية بالعربية والإنجليزية | AdsRanger', 'Live RTL & LTR Ad Mockup Previewer | AdsRanger'],
    ['عاين نصوص إعلاناتك بالعربية والإنجليزية عبر خلاصة ميتا وريلز وتيك توك وسناب شات وإعلانات بحث جوجل مع تنبيهات القطع.',
        'Preview Arabic RTL and English LTR ad copy across Meta Feed, 9:16 Reels/TikTok, Snapchat Story, and Google Search RSA with truncation warnings.']));
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
@endphp
@extends('layouts.public')
@push('styles')
@include('public.tools.partials.styles')
<style>
.mp-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
.mp-tab{min-height:44px;padding:0 14px;border-radius:8px;border:1px solid var(--line);background:var(--bg);font-weight:600;cursor:pointer}
.mp-tab[aria-selected="true"]{background:#0f766e;color:#fff;border-color:#0f766e}
.mp-frame{border:1px solid var(--line);border-radius:14px;padding:16px;background:var(--bg)}
.mp-warn{color:#b45309;font-size:13px;margin-top:8px}
</style>
@endpush
@section('hero')
<h1>{{ $ar ? 'معاينة الإعلانات الحية قبل النشر' : 'Live RTL & LTR Ad Mockup Previewer' }}</h1>
<p class="muted" style="font-size:17px">{!! $ar ? 'اختبر كيف يظهر إعلانك في خلاصة <bdi dir="ltr">Meta</bdi> و<bdi dir="ltr">Reels / TikTok</bdi> و<bdi dir="ltr">Snapchat</bdi> وإعلانات بحث <bdi dir="ltr">Google</bdi>.' : 'Test your Arabic RTL or English LTR copy across Meta Feed, 9:16 Reels/TikTok, Snapchat Story, and Google Search RSA.' !!}</p>
@endsection
@section('content')
<section class="tl">
    <form class="card" onsubmit="return false" novalidate>
        <label for="mp-dir">{{ $ar ? 'اتجاه نص الإعلان' : 'Ad copy direction' }}</label>
        <select id="mp-dir">
            <option value="rtl">{{ $ar ? 'عربي (RTL)' : 'Arabic (RTL)' }}</option>
            <option value="ltr" @selected(! $ar)>{{ $ar ? 'إنجليزي (LTR)' : 'English (LTR)' }}</option>
        </select>

        <label for="mp-brand">{{ $ar ? 'اسم العلامة التجارية' : 'Brand name' }}</label>
        <input id="mp-brand" type="text" maxlength="60" value="{{ $ar ? 'متجر الواحة' : 'Oasis Living' }}">

        <label for="mp-head">{{ $ar ? 'العنوان الرئيسي (Headline)' : 'Headline' }}</label>
        <input id="mp-head" type="text" maxlength="90" value="{{ $ar ? 'توصيل مجاني خلال 24 ساعة' : 'Free 24-Hour Express Delivery' }}">

        <label for="mp-body">{{ $ar ? 'النص الأساسي / الوصف' : 'Primary text / description' }}</label>
        <textarea id="mp-body" rows="3" maxlength="300">{{ $ar ? 'اكتشف تشكيلة الأثاث الجديدة لعام 2027 مع ضمان لمدة سنتين وخيارات تقسيط مريحة.' : 'Explore our 2027 home collection with a 2-year warranty and flexible installment plans.' }}</textarea>

        <label for="mp-cta">{{ $ar ? 'زر الدعوة لاتخاذ إجراء (CTA)' : 'Call-to-action button' }}</label>
        <input id="mp-cta" type="text" maxlength="30" value="{{ $ar ? 'تسوق الآن' : 'Shop Now' }}">
    </form>

    <div class="tl-out" aria-live="polite">
        <div class="mp-tabs" role="tablist">
            <button type="button" class="mp-tab" role="tab" data-p="meta" aria-selected="true"><bdi dir="ltr">Meta Feed</bdi></button>
            <button type="button" class="mp-tab" role="tab" data-p="reels"><bdi dir="ltr">Reels / TikTok 9:16</bdi></button>
            <button type="button" class="mp-tab" role="tab" data-p="snap"><bdi dir="ltr">Snapchat Story</bdi></button>
            <button type="button" class="mp-tab" role="tab" data-p="rsa"><bdi dir="ltr">Google Search RSA</bdi></button>
        </div>
        <div class="mp-frame" id="mp-canvas"></div>
        <div id="mp-warnings"></div>
        <p class="muted" style="margin-top:12px">{{ $privacy }}</p>
    </div>
</section>
<p style="margin-top:20px"><a class="btn" href="{{ $u('/waitlist') }}?type=agency">{{ $ar ? 'انضم إلى قائمة الانتظار' : 'Join the waitlist' }}</a></p>
@include('public.tools.partials.tool-guide-faq', ['toolSlug' => 'ad-mockup-preview', 'ar' => $ar])
@endsection
@push('scripts')
<script>
(function(){
  var $ = function(id){ return document.getElementById(id); }, place = 'meta', isArUi = @json($ar);
  function esc(s){ var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  function render(){
    var dir = $('mp-dir').value, brand = $('mp-brand').value, head = $('mp-head').value, body = $('mp-body').value, cta = $('mp-cta').value;
    var canvas = $('mp-canvas'), warns = [];
    canvas.dir = dir;
    if(place === 'meta'){
      if(body.length > 125) warns.push(isArUi ? 'تنبيه: النص الأساسي يتجاوز 125 حرفًا وقد يُطوى خلف «عرض المزيد».' : 'Warning: Primary text exceeds 125 chars and may truncate on mobile feeds.');
      if(head.length > 40) warns.push(isArUi ? 'تنبيه: العنوان أطول من 40 حرفًا وقد يُقطع في شريط البطاقة.' : 'Warning: Headline exceeds 40 chars and may clip on mobile.');
      canvas.innerHTML = '<div style="font-weight:700;margin-bottom:6px">'+esc(brand)+' <span style="font-size:12px;color:var(--muted)">· Sponsored</span></div>'
        + '<p style="margin:0 0 12px;font-size:14px;line-height:1.5">'+esc(body)+'</p>'
        + '<div style="height:150px;border-radius:10px;background:linear-gradient(135deg,#0f172a,#0f766e);display:grid;place-items:center;color:#5eead4;font-weight:700">1080 × 1080</div>'
        + '<div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-top:10px"><strong>'+esc(head)+'</strong><span style="padding:8px 14px;border-radius:8px;background:#0f766e;color:#fff;font-size:13px;font-weight:600">'+esc(cta)+'</span></div>';
    } else if(place === 'reels' || place === 'snap'){
      if(body.length > 90) warns.push(isArUi ? 'تنبيه: وصف الفيديو العمودي يتجاوز 90 حرفًا وقد يغطي عناصر الفيديو.' : 'Warning: Vertical caption exceeds 90 chars and will expand over video.');
      canvas.innerHTML = '<div style="max-width:260px;margin:0 auto;aspect-ratio:9/16;border-radius:16px;background:linear-gradient(180deg,#0f172a 0%,#134e4a 100%);color:#f8fafc;padding:16px;display:flex;flex-direction:column;justify-content:space-between">'
        + '<div style="font-size:13px;font-weight:700">'+esc(brand)+' · '+(place==='snap'?'Snap Ad':'Reels / TikTok')+'</div>'
        + '<div><div style="font-weight:700;font-size:15px;margin-bottom:4px">'+esc(head)+'</div><p style="font-size:12.5px;opacity:.9;margin:0 0 10px">'+esc(body.slice(0,110))+'</p><div style="text-align:center;padding:9px;border-radius:99px;background:#14b8a6;color:#0f172a;font-weight:700;font-size:13px">'+esc(cta)+'</div></div></div>';
    } else {
      if(head.length > 30) warns.push(isArUi ? 'تنبيه RSA: العنوان يتجاوز حد 30 حرفًا في إعلانات بحث جوجل ('+head.length+'/30).' : 'RSA Warning: Headline exceeds Google\'s 30-character limit ('+head.length+'/30).');
      if(body.length > 90) warns.push(isArUi ? 'تنبيه RSA: الوصف يتجاوز حد 90 حرفًا في إعلانات بحث جوجل ('+body.length+'/90).' : 'RSA Warning: Description exceeds Google\'s 90-character limit ('+body.length+'/90).');
      canvas.innerHTML = '<div style="font-size:12px;color:var(--muted);margin-bottom:4px">Sponsored · example.com</div>'
        + '<div style="font-size:18px;font-weight:700;color:#0f766e;margin-bottom:4px">'+esc(brand)+' — '+esc(head)+'</div>'
        + '<p style="margin:0;font-size:14px;line-height:1.5">'+esc(body)+'</p>';
    }
    $('mp-warnings').innerHTML = warns.map(function(w){ return '<div class="mp-warn">'+esc(w)+'</div>'; }).join('');
  }
  document.querySelectorAll('.mp-tab').forEach(function(btn){
    btn.addEventListener('click', function(){
      document.querySelectorAll('.mp-tab').forEach(function(b){ b.setAttribute('aria-selected','false'); });
      btn.setAttribute('aria-selected','true');
      place = btn.getAttribute('data-p');
      render();
    });
  });
  ['mp-dir','mp-brand','mp-head','mp-body','mp-cta'].forEach(function(id){ $(id).addEventListener('input', render); $(id).addEventListener('change', render); });
  render();
})();
</script>
@endpush
