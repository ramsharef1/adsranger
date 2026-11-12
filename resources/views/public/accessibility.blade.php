@php
// /accessibility (R2-46, D-263). Copy: Growth, claude/site/Site_Adds_R2-46-48_2026-11-12.md §2. Every line is true on the live
// site per the EX checks O-338 / O-341 / O-346 (cl77–cl79). Never add "compliant" or "certified".
$ar = ($lang ?? 'ar') === 'ar';
$arPath = '/accessibility';
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $ar ? 'ar' : 'en');
$b = fn (string $t) => '<bdi dir="ltr">'.$t.'</bdi>';
$reviewed = $ar ? '12 نوفمبر 2026' : '12 November 2026';
$title = $ar ? 'إمكانية الوصول في موقع AdsRanger | AdsRanger' : 'Accessibility at AdsRanger | AdsRanger';
$meta = $ar
    ? 'كيف نجعل موقع AdsRanger سهل الاستخدام للجميع، بالعربية والإنجليزية، وما الذي ما زلنا نعمل عليه، وكيف تبلّغنا عن أي مشكلة.'
    : 'How we make the AdsRanger site easy to use for everyone, in Arabic and English, what we are still working on, and how to report a problem.';
$schema = [['@type' => 'WebPage', 'name' => $title, 'url' => \App\Support\PublicUrl::absolute($arPath, $ar ? 'ar' : 'en'), 'inLanguage' => $ar ? 'ar' : 'en']];
$sections = $ar ? [
    ['ما الذي نفعله اليوم', [
        'تُكتب كل صفحة عربية من اليمين إلى اليسار، وتُعلَن لغتها واتجاهها لقارئات الشاشة، وكذلك الصفحات الإنجليزية من اليسار إلى اليمين.',
        'نعزل الكلمات اللاتينية داخل النص العربي حتى لا تختلط الأقواس والأرقام عند القراءة.',
        'تعمل الصفحات بالوضعين الفاتح والداكن، ونحرص على أن يكون تباين النص والأزرار كافيًا للقراءة في الحالتين.',
        'تبلغ مساحة الروابط والأزرار والحقول '.$b('44').' بكسل على الأقل، لتسهل إصابتها باللمس.',
        'يمكن التنقل في القوائم والأدوات المجانية بلوحة المفاتيح، وتُغلَق القائمة بزر '.$b('Esc').'.',
        'لا تتحرك الصفحات أفقيًا على شاشة الهاتف، ولكل صفحة عنوان رئيسي واحد وعناوين فرعية مرتبة.',
        'في نموذج قائمة الانتظار تظهر رسالة الخطأ بجانب الحقل نفسه، وتُجمع الأخطاء في أعلى النموذج مع روابط إليها.',
    ]],
    ['كيف نفحص', [
        'نفحص الصفحات بأدوات آلية وبالمراجعة اليدوية، على شاشة هاتف وشاشة حاسوب، بالعربية والإنجليزية، وبالوضعين الفاتح والداكن، قبل نشر أي صفحة جديدة وبعده.',
    ]],
    ['ما الذي ما زلنا نعمل عليه', [
        'مساحة العمل نفسها ما زالت قيد التطوير، وسنراجع إمكانية الوصول فيها قبل الإطلاق في 2027.',
        'لم نختبر الموقع بعد مع مستخدمين يعتمدون على قارئات الشاشة، ونخطط لذلك قبل الإطلاق.',
        'بعض نتائج الأدوات المجانية تظهر في جداول طويلة، وقد تحتاج إلى تمرير جانبي داخل الجدول على الهاتف.',
    ]],
    ['أخبرنا عن أي مشكلة', [
        'إذا واجهت صعوبة في قراءة صفحة أو استخدام أداة، راسلنا على <a href="mailto:support@adsranger.com">'.$b('support@adsranger.com').'</a>، واذكر رابط الصفحة وما حدث ونوع جهازك إن أمكن.',
        'نرد عادةً خلال يومَي عمل، من الأحد إلى الخميس.',
    ]],
] : [
    ['What we do today', [
        'Arabic pages read right to left and English pages left to right, and each page tells screen readers its language and direction.',
        'Latin words inside Arabic text are isolated, so brackets and numbers don\'t get mixed up when read.',
        'Pages work in light and dark mode, and we keep text and button contrast readable in both.',
        'Links, buttons and form fields are at least 44 pixels, so they are easy to tap.',
        'Menus and the free tools can be used with a keyboard, and the menu closes with Esc.',
        'Pages don\'t scroll sideways on a phone, and each page has one main heading with headings in order.',
        'On the waitlist form, errors show next to the field and in a list at the top of the form, with links to each field.',
    ]],
    ['How we check', [
        'We check pages with automated tools and by hand, on a phone and a desktop screen, in Arabic and English, in light and dark mode, before and after each new page goes live.',
    ]],
    ['What we are still working on', [
        'The workspace itself is in development; we will review its accessibility before launch in 2027.',
        'We haven\'t yet tested the site with people who use screen readers every day; we plan to before launch.',
        'Some free-tool results show in long tables that may scroll sideways inside the table on a phone.',
    ]],
    ['Tell us about a problem', [
        'If a page is hard to read or a tool is hard to use, email <a href="mailto:support@adsranger.com">support@adsranger.com</a> with the page link, what happened and, if you can, your device.',
        'We usually reply within 2 working days, Sunday to Thursday.',
    ]],
];
@endphp
@extends('layouts.public')

@push('styles')
<style>
.a11y{padding-top:24px;max-width:860px}
.a11y h2{margin-top:28px}
</style>
@endpush

@section('hero')
<h1>{{ $ar ? 'إمكانية الوصول' : 'Accessibility' }}</h1>
<p class="muted" style="font-size:17px">{!! $ar
? 'نريد أن يكون موقع '.$b('AdsRanger').' سهل القراءة والاستخدام لكل شخص، أيًّا كانت الشاشة أو الطريقة التي يتصفح بها. هدفنا مستوى '.$b('AA').' من إرشادات '.$b('WCAG 2.2').'، ونفحص صفحاتنا لنقترب منه في كل تحديث.'
: 'We want the AdsRanger site to be easy to read and use for everyone, whatever screen or way they browse with. Our target is WCAG 2.2 level AA, and we check our pages against it with every update.' !!}</p>
@endsection

@section('content')
<section class="a11y">
    @foreach ($sections as [$heading, $items])
        <h2>{{ $heading }}</h2>
        <ul>
            @foreach ($items as $line)<li>{!! $line !!}</li>@endforeach
        </ul>
    @endforeach
    <p class="muted" style="margin-top:28px">{{ $ar ? 'آخر مراجعة لهذه الصفحة: '.$reviewed : 'This page was last reviewed on '.$reviewed.'.' }}</p>
</section>
@endsection
