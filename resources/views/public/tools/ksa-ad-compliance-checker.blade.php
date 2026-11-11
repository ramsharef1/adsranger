@php
extract(\App\Support\ToolPage::head($lang ?? 'ar', '/tools/ksa-ad-compliance-checker',
    ['قائمة إفصاحات الإعلانات في السعودية والإمارات | AdsRanger', 'KSA & UAE Ad Disclosure Checklist | AdsRanger'],
    ['قائمة فحص إرشادية ومسح لنص الإعلان لمراجعة متطلبات ترخيص فال العقاري والغذاء والدواء ووزارة الصحة ووسم معلن وإفصاحات التقسيط.',
        'Reminder checklist and word search for Saudi REGA/Fal, SFDA, MOH disclaimers, UAE Media Council permit tags, and BNPL disclosures.']));
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
@endphp
@extends('layouts.public')
@push('styles')
@include('public.tools.partials.styles')
@endpush
@section('hero')
<h1>{{ $ar ? 'قائمة إفصاحات الإعلانات في السعودية والإمارات' : 'KSA & UAE Ad Disclosure Checklist' }}</h1>
<p class="muted" style="font-size:17px">{{ $ar ? 'قائمة تذكير قبل نشر الإعلان: هل ذكرت رقم الترخيص، وتنبيهات الصحة والغذاء والدواء، ووسم #معلن، وشروط التقسيط؟' : 'A reminder list before you publish: licence numbers, health and SFDA notes, the #معلن tag, and instalment terms.' }}</p>
@endsection
@section('content')
<section class="tl">
    <form class="card" onsubmit="return false" novalidate>
        <label for="kc-sec">{{ $ar ? 'القطاع الإعلاني' : 'Campaign sector' }}</label>
        <select id="kc-sec">
            <option value="realestate">{{ $ar ? 'عقارات (السعودية - الهيئة العامة للعقار / فال)' : 'Real Estate (KSA REGA / Fal License)' }}</option>
            <option value="health">{{ $ar ? 'صحة وعيادات وتجميل (وزارة الصحة السعودية / الإمارات)' : 'Healthcare & Clinics (KSA / UAE MOH)' }}</option>
            <option value="sfda">{{ $ar ? 'أغذية ومكملات ومستحضرات (هيئة الغذاء والدواء SFDA)' : 'Food, Supplements & Cosmetics (SFDA)' }}</option>
            <option value="influencer">{{ $ar ? 'إعلانات المؤثرين وصناع المحتوى (موثوق / #معلن)' : 'Creator & Influencer Ads (Mawthooq / #معلن)' }}</option>
            <option value="bnpl">{{ $ar ? 'تمويل وتقسيط (تابي / تمارا / بنوك)' : 'BNPL & Consumer Finance Disclosures' }}</option>
        </select>

        <label for="kc-copy">{{ $ar ? 'نص الإعلان المراد فحصه' : 'Ad copy to scan' }}</label>
        <textarea id="kc-copy" rows="5">{{ $ar ? 'شقق تمليك فاخرة في شمال الرياض بأسعار تبدأ من 750,000 ريال.' : 'Luxury apartments in North Riyadh starting from 750,000 SAR.' }}</textarea>

        <p class="tl-hint" style="margin-top:12px">
            {{ $ar ? 'هذه قائمة تذكير لفريق التسويق، لا فحص قانوني: نبحث فقط عن كلمات في النص، ولا نحكم إن كان الإعلان مطابقًا للأنظمة. راجع اللوائح الحالية لدى الجهة المختصة أو مستشارًا قانونيًا قبل النشر.' : 'This is a reminder checklist for marketing teams, not a legal check: it only looks for words in your text and does not decide whether an ad complies. Check the current rules with the regulator or a lawyer before publishing.' }}
        </p>
    </form>

    <div class="tl-out" aria-live="polite">
        <h3>{{ $ar ? 'نتيجة فحص النص وقائمة التحقق' : 'Copy scan & required disclosures' }}</h3>
        <ul class="tl-kv" id="kc-list"></ul>
        <p class="muted" style="margin-top:14px">{{ $privacy }}</p>
    </div>
</section>
<p style="margin-top:20px"><a class="btn" href="{{ $u('/waitlist') }}?type=agency">{{ $ar ? 'انضم إلى قائمة الانتظار' : 'Join the waitlist' }}</a></p>
@include('public.tools.partials.tool-guide-faq', ['toolSlug' => 'ksa-ad-compliance-checker', 'ar' => $ar])
@endsection
@push('scripts')
<script>
(function(){
  var $ = function(id){ return document.getElementById(id); }, isAr = @json($ar);
  var rules = {
    realestate: [
      {re:/(فال|ترخيص|رخصة|fal|license)\s*[:#-]?\s*\d{6,}/i, ar:'رقم رخصة فال / ترخيص الإعلان العقاري', en:'REGA / Fal license number in copy'},
      {re:/(مضمون\s*الربح|عائد\s*مضمون|guaranteed\s*return)/i, invert:true, ar:'عدم استخدام وعود عوائد استثمارية مضمونة مضللة', en:'No misleading "guaranteed return" claims'}
    ],
    health: [
      {re:/(ترخيص|صحة|moh|license)\s*[:#-]?\s*[A-Za-z0-9-]{4,}/i, ar:'رقم ترخيص الإعلان الصحي (وزارة الصحة)', en:'MOH healthcare advertisement permit number'},
      {re:/(شفاء\s*نهائي|مضمون\s*100|100%\s*cure|instant\s*cure)/i, invert:true, ar:'خلو النص من ادعاءات الشفاء النهائي أو المضمون 100%', en:'No prohibited "100% cure" medical claims'}
    ],
    sfda: [
      {re:/(فسح|غذاء|sfda|مدرج)/i, ar:'الإشارة لرقم الإدراج أو الفسح لدى هيئة الغذاء والدواء عند اللزوم', en:'SFDA registration/clearance reference where required'},
      {re:/(يعالج|يشفي|cures|treats\s+disease)/i, invert:true, ar:'عدم نسبة ادعاءات علاجية طبية لمكمل غذائي أو منتج تجميلي', en:'No disease-treatment claims on supplements/cosmetics'}
    ],
    influencer: [
      {re:/(#?معلن|#?اعلان|#?إعلان|\bad\b|#sponsored|ترخيص\s*إعلامي|موثوق)/i, ar:'وسم الإفصاح الإعلاني الصريح (#معلن / #إعلان / ترخيص إعلامي)', en:'Explicit disclosure tag (#معلن / #Ad / UAE Media Council / Mawthooq permit)'}
    ],
    bnpl: [
      {re:/(تطبق\s*الشروط|الشروط\s*والأحكام|رسوم|معدل\s*النسبة\s*السنوي|terms\s*apply|apr)/i, ar:'إفصاح الشروط والأحكام أو الرسوم المرتبطة بالتقسيط', en:'Terms & conditions / fee / APR disclosure'}
    ]
  };
  function scan(){
    var sec = $('kc-sec').value, txt = $('kc-copy').value, ul = $('kc-list');
    ul.innerHTML = '';
    (rules[sec] || []).forEach(function(r){
      var hit = r.re.test(txt);
      var ok = r.invert ? !hit : hit;
      var li = document.createElement('li');
      var lbl = document.createElement('span'); lbl.textContent = isAr ? r.ar : r.en;
      var st = document.createElement('strong');
      // A word match is not a legal check (D-249): we only say what the text contains, never pass / fail.
      st.textContent = r.invert
        ? (hit ? (isAr ? 'وُجدت عبارة تحتاج مراجعة' : 'Phrase found: review it') : (isAr ? 'لم نجد هذه العبارة' : 'Phrase not found'))
        : (hit ? (isAr ? 'وُجد في النص' : 'Found in the text') : (isAr ? 'لم نجده في النص' : 'Not found in the text'));
      st.style.color = ok ? 'var(--acc)' : '#b45309';
      li.appendChild(lbl); li.appendChild(st); ul.appendChild(li);
    });
  }
  $('kc-sec').addEventListener('change', scan);
  $('kc-copy').addEventListener('input', scan);
  scan();
})();
</script>
@endpush
