@php
// Website P-24 /contact (D-120; D-217 email only until launch, no form, phone or WhatsApp).
$ar = ($lang ?? 'ar') === 'ar';
$lang = $ar ? 'ar' : 'en';
$arPath = '/contact';
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
$title = $ar ? 'تواصل مع AdsRanger' : 'Contact AdsRanger';
$meta = $ar
    ? 'راسل فريق AdsRanger لأي سؤال عن المنتج أو الشراكات أو بياناتك، ونرد بالعربية أو الإنجليزية.'
    : 'Email AdsRanger for questions about the product, partnerships or your data. We reply in Arabic or English.';
$schema = [['@type' => 'ContactPage', 'name' => $title, 'url' => \App\Support\PublicUrl::absolute($arPath, $lang), 'inLanguage' => $lang,
    'mainEntity' => ['@type' => 'Organization', 'name' => 'AdsRanger', 'url' => 'https://adsranger.com/', 'contactPoint' => [
        ['@type' => 'ContactPoint', 'contactType' => 'customer support', 'email' => 'support@adsranger.com', 'availableLanguage' => ['ar', 'en']],
        ['@type' => 'ContactPoint', 'contactType' => 'privacy', 'email' => 'privacy@adsranger.com', 'availableLanguage' => ['ar', 'en']],
    ]]]];
// CL-1 rev 2 E-02 (Growth 08e §2): routes with one line each. hello@ waits for its forward (Brevo on hold, D-246); no response time until Business sets one.
$cards = [
    [$ar ? 'أسئلة عن المنتج والدعم' : 'Product questions and support', 'support@adsranger.com',
        $ar ? 'راسلنا واذكر اسم وكالتك ودولتك.' : 'Email us with your agency name and country.', null],
    [$ar ? 'الخصوصية وبياناتك' : 'Privacy and your data', 'privacy@adsranger.com',
        $ar ? 'لطلب نسخة من بياناتك أو حذفها، أو للإبلاغ عن مشكلة أمنية.' : 'To get a copy of your data, delete it or report a security issue.',
        [$u('/data-deletion'), $ar ? 'كيف تطلب حذف بياناتك' : 'How to ask us to delete your data']],
    [$ar ? 'الشراكات' : 'Partnerships', null,
        $ar ? 'إن كنت تدير وكالة إعلانات في المنطقة وتريد العمل معنا قبل الإطلاق، قدّم طلب شراكة.' : 'If you run an ad agency in the region and want to work with us before launch, send a partner application.',
        [$u('/partners'), $ar ? 'طلب شراكة' : 'Partner application']],
];
@endphp
@extends('layouts.public')

@push('styles')
<style>
.ct{max-width:820px;padding-top:24px}
.ct-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(260px,100%),1fr));gap:14px;list-style:none;padding:0;margin:20px 0}
.ct a.ml,.ct a.pl{display:inline-flex;align-items:center;min-height:44px;font-size:17px}
</style>
@endpush

@section('hero')
<h1>{{ $ar ? 'تواصل معنا' : 'Contact us' }}</h1>
<p class="muted" style="font-size:17px">{{ $ar ? 'البريد الإلكتروني أفضل طريقة للتواصل معنا، ونرد بالعربية أو الإنجليزية.' : 'Email is the best way to reach us. We reply in Arabic or English.' }}</p>
@endsection

@section('content')
<section class="ct">
    <h2>{{ $ar ? 'بماذا نساعدك' : 'How can we help' }}</h2>
    <ul class="ct-cards">
        @foreach ($cards as [$label, $email, $line, $extra])
            <li class="card">
                <h3 style="font-size:18px;margin:0 0 6px">{{ $label }}</h3>
                <p style="margin:0 0 4px">{{ $line }}</p>
                @if ($email)<a class="ml" href="mailto:{{ $email }}"><bdi dir="ltr">{{ $email }}</bdi></a>@endif
                @if ($extra)@if ($email)<br>@endif<a class="pl" href="{{ $extra[0] }}">{{ $extra[1] }}</a>@endif
            </li>
        @endforeach
    </ul>
    <p>{{ $ar ? 'ولمعرفة كيف نحمي البيانات، اقرأ صفحة' : 'To see how we protect data, read our' }} <a class="pl" href="{{ $u('/security') }}">{{ $ar ? 'الأمان' : 'security page' }}</a>.</p>
    <p>{!! $ar ? 'فريق <bdi dir="ltr">AdsRanger</bdi> في عمّان، الأردن. نرد بالعربية أو الإنجليزية.' : 'The AdsRanger team, Amman, Jordan. We reply in Arabic or English.' !!}</p>
    <p>{{ $ar ? 'نرد عادةً خلال يومَي عمل، من الأحد إلى الخميس.' : 'We usually reply within 2 working days, Sunday to Thursday.' }}</p>
    <p>{{ $ar ? 'تريد أن تنضم إلى قائمة الإطلاق؟' : 'Want to join the launch list?' }} <a class="pl" href="{{ $u('/waitlist') }}">{{ $ar ? 'انضم إلى قائمة الإطلاق.' : 'Join the launch list.' }}</a></p>
</section>
@endsection
