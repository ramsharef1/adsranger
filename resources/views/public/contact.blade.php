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
$cards = [
    [$ar ? 'الأسئلة والدعم' : 'Questions and support', 'support@adsranger.com', null],
    [$ar ? 'الخصوصية وبياناتك' : 'Privacy and your data', 'privacy@adsranger.com', [$u('/data-deletion'), $ar ? 'حذف البيانات' : 'Data deletion']],
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
    <ul class="ct-cards">
        @foreach ($cards as [$label, $email, $extra])
            <li class="card">
                <h2 style="font-size:18px;margin:0 0 6px">{{ $label }}</h2>
                <a class="ml" href="mailto:{{ $email }}"><bdi dir="ltr">{{ $email }}</bdi></a>
                @if ($extra)<br><a class="pl" href="{{ $extra[0] }}">{{ $extra[1] }}</a>@endif
            </li>
        @endforeach
    </ul>
    <p>{{ $ar ? 'تريد أن نخبرك عند الإطلاق؟' : 'Want to hear when we launch?' }} <a class="pl" href="{{ $u('/waitlist') }}">{{ $ar ? 'انضم إلى قائمة الانتظار.' : 'Join the waitlist.' }}</a></p>
</section>
@endsection
