@php
extract(\App\Support\ToolPage::head($lang ?? 'ar', '/tools/schema-generator',
    ['مولد البيانات المنظمة JSON-LD | AdsRanger', 'Bilingual JSON-LD Schema Generator & Validator | AdsRanger'],
    ['أنشئ وتحقّق من كود البيانات المنظمة JSON-LD لأنواع Organization وLocalBusiness وFAQPage وProduct وSoftwareApplication.',
        'Generate and validate Arabic & English JSON-LD structured data for Organization, LocalBusiness, FAQPage, Product, and SoftwareApplication.']));
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
@endphp
@extends('layouts.public')
@push('styles')
@include('public.tools.partials.styles')
@endpush
@section('hero')
<h1>{!! $ar ? 'مولد ومدقق البيانات المنظمة <bdi dir="ltr">JSON-LD</bdi>' : 'Bilingual JSON-LD Schema Generator & Validator' !!}</h1>
<p class="muted" style="font-size:17px">{{ $ar ? 'أنشئ كود البيانات المنظمة بترميز عربي سليم وتحقّق من صحته البرمجية فورًا.' : 'Build clean Arabic or English JSON-LD snippets and validate syntax right in your browser.' }}</p>
@endsection
@section('content')
<section class="tl">
    <form class="card" onsubmit="return false" novalidate>
        <label for="sc-type">{{ $ar ? 'نوع الكيان (@@type)' : 'Schema @@type' }}</label>
        <select id="sc-type" dir="ltr">
            <option value="Organization">Organization</option>
            <option value="LocalBusiness">LocalBusiness</option>
            <option value="FAQPage">FAQPage</option>
            <option value="Product">Product</option>
            <option value="SoftwareApplication">SoftwareApplication</option>
        </select>

        <label for="sc-name">{{ $ar ? 'الاسم / العنوان' : 'Name / Title' }}</label>
        <input id="sc-name" type="text" value="{{ $ar ? 'وكالة أفق الرقمية' : 'Horizon Digital Agency' }}">

        <label for="sc-url">{{ $ar ? 'الرابط الرسمي' : 'URL' }}</label>
        <input id="sc-url" type="url" dir="ltr" value="https://example.com">

        <label for="sc-desc">{{ $ar ? 'الوصف أو السؤال الأول' : 'Description (or FAQ Question 1)' }}</label>
        <input id="sc-desc" type="text" value="{{ $ar ? 'خدمات إدارة الحملات الإعلانية والسيو العربي.' : 'Performance marketing and Arabic SEO agency.' }}">

        <label for="sc-extra">{{ $ar ? 'قيمة إضافية (السعر / المدينة / الإجابة)' : 'Extra value (Price / City / FAQ Answer)' }}</label>
        <input id="sc-extra" type="text" value="{{ $ar ? 'عمّان' : 'Amman' }}">
    </form>

    <div class="tl-out" aria-live="polite">
        <h2><bdi dir="ltr">JSON-LD</bdi></h2>
        <textarea id="sc-json" rows="11" dir="ltr" style="font-family:monospace;font-size:13px"></textarea>
        <p id="sc-status" style="font-size:13.5px;font-weight:600;margin:8px 0 0;color:var(--acc)"></p>
        <div class="tl-actions">
            <button type="button" class="btn" id="sc-val">{{ $ar ? 'تحقق من الصياغة' : 'Validate JSON-LD' }}</button>
            <button type="button" class="btn" id="sc-copy">{{ $ar ? 'نسخ مع وسم script' : 'Copy <script> tag' }}</button>
        </div>
        <p class="muted" style="margin-top:12px">{{ $privacy }}</p>
    </div>
</section>
<p style="margin-top:20px"><a class="btn" href="{{ $u('/waitlist') }}?type=agency">{{ $ar ? 'انضم إلى قائمة الإطلاق' : 'Join the launch list' }}</a></p>
@include('public.tools.partials.tool-guide-faq', ['toolSlug' => 'schema-generator', 'ar' => $ar])
@endsection
@push('scripts')
<script>
(function(){
  var $ = function(id){ return document.getElementById(id); }, isAr = @json($ar);
  function build(){
    var t = $('sc-type').value, name = $('sc-name').value.trim(), url = $('sc-url').value.trim(), desc = $('sc-desc').value.trim(), ex = $('sc-extra').value.trim();
    var obj = {'@@context': 'https://schema.org', '@@type': t};
    if(t === 'FAQPage'){
      obj.mainEntity = [{'@@type':'Question','name':desc || name,'acceptedAnswer':{'@@type':'Answer','text':ex}}];
    } else if(t === 'Product'){
      obj.name = name; obj.description = desc;
      obj.offers = {'@@type':'Offer','price': parseFloat(ex) || '99.00','priceCurrency':'USD','url':url};
    } else if(t === 'LocalBusiness'){
      obj.name = name; obj.url = url; obj.description = desc;
      obj.address = {'@@type':'PostalAddress','addressLocality':ex};
    } else if(t === 'SoftwareApplication'){
      obj.name = name; obj.url = url; obj.applicationCategory = 'BusinessApplication'; obj.operatingSystem = 'Web'; obj.description = desc;
    } else {
      obj.name = name; obj.url = url; obj.description = desc;
    }
    $('sc-json').value = JSON.stringify(obj, null, 2);
    validate();
  }
  function validate(){
    try {
      var parsed = JSON.parse($('sc-json').value);
      if(!parsed['@@context'] || !parsed['@@type']) throw new Error('missing');
      $('sc-status').style.color = 'var(--acc)';
      $('sc-status').textContent = isAr ? 'صياغة JSON-LD سليمة وجاهزة للنشر.' : 'Valid JSON-LD syntax (@@context and @@type verified).';
    } catch(e){
      $('sc-status').style.color = 'var(--bad)';
      $('sc-status').textContent = isAr ? 'يوجد خطأ في صياغة JSON أو نقص في @@context/@@type.' : 'Invalid JSON syntax or missing @@context / @@type.';
    }
  }
  ['sc-type','sc-name','sc-url','sc-desc','sc-extra'].forEach(function(id){ $(id).addEventListener('input', build); $(id).addEventListener('change', build); });
  $('sc-val').addEventListener('click', validate);
  $('sc-json').addEventListener('input', validate);
  $('sc-copy').addEventListener('click', function(){
    var s = '<script type="application/ld+json">\n' + $('sc-json').value + '\n<\/script>';
    navigator.clipboard && navigator.clipboard.writeText(s);
  });
  build();
})();
</script>
@endpush
