@php
extract(\App\Support\ToolPage::head($lang ?? 'ar', '/tools/safe-zone-checker',
    ['فاحص المناطق الآمنة لفيديو 9:16 | AdsRanger', '9:16 Reels, TikTok & Snapchat Safe-Zone Checker | AdsRanger'],
    ['اختبر تصميم إعلانك العمودي 9:16 داخل المتصفح لتتأكد أن أزرار Reels وTikTok وSnapchat لا تغطي النصوص والترجمة العربية.',
        'Drag and drop a 9:16 frame in your browser to overlay Instagram Reels, TikTok, and Snapchat UI danger zones with zero server uploads.']));
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
@endphp
@extends('layouts.public')
@push('styles')
@include('public.tools.partials.styles')
<style>
.sz-stage{position:relative;width:270px;aspect-ratio:9/16;margin:0 auto;border-radius:18px;overflow:hidden;background:linear-gradient(160deg,#0f172a,#134e4a);border:2px solid var(--line)}
.sz-img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.sz-zone{position:absolute;background:rgba(220,38,38,.34);border:1px dashed rgba(254,202,202,.8);color:#fff;font-size:11px;font-weight:700;display:grid;place-items:center;text-align:center;padding:4px;pointer-events:none}
.sz-safe{position:absolute;border:2px solid #5eead4;border-radius:8px;pointer-events:none;display:grid;place-items:center;color:#5eead4;font-size:12px;font-weight:700;text-shadow:0 1px 3px #000}
</style>
@endpush
@section('hero')
<h1>{{ $ar ? 'فاحص المناطق الآمنة لإعلانات الفيديو العمودية (9:16)' : '9:16 Reels, TikTok & Snapchat Safe-Zone Checker' }}</h1>
<p class="muted" style="font-size:17px">{{ $ar ? 'تأكّد داخل متصفحك أن الترجمة العربية والشعار وزر العرض لا تقع خلف أيقونات التفاعل أو شريط الوصف (دون رفع أي ملف لخوادمنا).' : 'Test 9:16 creative frames locally in your browser so captions and subtitles never hide behind platform UI.' }}</p>
@endsection
@section('content')
<section class="tl">
    <div class="card">
        <label for="sz-plat">{{ $ar ? 'واجهة المنصة' : 'Platform overlay' }}</label>
        <select id="sz-plat">
            <option value="reels"><bdi dir="ltr">Instagram Reels (9:16)</bdi></option>
            <option value="tiktok"><bdi dir="ltr">TikTok In-Feed (9:16)</bdi></option>
            <option value="snap"><bdi dir="ltr">Snapchat Story Ad (9:16)</bdi></option>
        </select>

        <label for="sz-file">{{ $ar ? 'اختر صورة تصميم أو لقطة شاشة (تُعالج محليًا في متصفحك فقط)' : 'Choose a 9:16 image/frame (processed 100% locally in browser)' }}</label>
        <input id="sz-file" type="file" accept="image/*">
        <p class="tl-hint">{{ $ar ? 'المناطق الحمراء تمثل مواضع اسم الحساب وأزرار الإعجاب والمشاركة والوصف السفلي؛ أبقِ النصوص المهمة داخل الإطار الفيروزي.' : 'Red shaded bands mark top headers, side engagement icons, and bottom caption/CTA drawers. Keep key Arabic/English text inside the teal safe frame.' }}</p>
        <p class="muted" style="margin-top:12px">{{ $privacy }}</p>
    </div>

    <div class="tl-out">
        <div class="sz-stage" id="sz-stage">
            <img id="sz-preview" class="sz-img" alt="" hidden>
            <div id="sz-top" class="sz-zone"></div>
            <div id="sz-side" class="sz-zone"></div>
            <div id="sz-bot" class="sz-zone"></div>
            <div id="sz-ok" class="sz-safe">{{ $ar ? 'المنطقة الآمنة للنصوص' : 'Safe Text Zone' }}</div>
        </div>
    </div>
</section>
<p style="margin-top:20px"><a class="btn" href="{{ $u('/waitlist') }}?type=agency">{{ $ar ? 'انضم إلى قائمة الانتظار' : 'Join the waitlist' }}</a></p>
@include('public.tools.partials.tool-guide-faq', ['toolSlug' => 'safe-zone-checker', 'ar' => $ar])
@endsection
@push('scripts')
<script>
(function(){
  var $ = function(id){ return document.getElementById(id); }, isAr = @json($ar);
  var presets = {
    reels: {top:13, bot:22, sideW:16, sideTop:42, sideBot:22},
    tiktok: {top:13, bot:25, sideW:18, sideTop:38, sideBot:25},
    snap: {top:15, bot:20, sideW:0, sideTop:0, sideBot:0}
  };
  function sync(){
    var p = presets[$('sz-plat').value] || presets.reels;
    var top = $('sz-top'), bot = $('sz-bot'), side = $('sz-side'), ok = $('sz-ok');
    top.style.cssText = 'top:0;left:0;right:0;height:'+p.top+'%';
    top.textContent = isAr ? 'شريط علوي' : 'Top UI bar';
    bot.style.cssText = 'bottom:0;left:0;right:0;height:'+p.bot+'%';
    bot.textContent = isAr ? 'الوصف وزر الإجراء' : 'Caption & CTA zone';
    if(p.sideW > 0){
      side.hidden = false;
      side.style.cssText = 'top:'+p.sideTop+'%;bottom:'+p.sideBot+'%;right:0;width:'+p.sideW+'%';
      side.textContent = 'Icons';
    } else {
      side.hidden = true;
    }
    ok.style.cssText = 'top:'+(p.top+2)+'%;bottom:'+(p.bot+2)+'%;left:6%;right:'+(p.sideW > 0 ? (p.sideW+3) : 6)+'%';
  }
  $('sz-plat').addEventListener('change', sync);
  $('sz-file').addEventListener('change', function(e){
    var f = e.target.files && e.target.files[0];
    if(!f) return;
    var img = $('sz-preview');
    img.src = URL.createObjectURL(f);
    img.hidden = false;
  });
  sync();
})();
</script>
@endpush
