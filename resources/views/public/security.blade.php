@php
// Website P-10 /security (U-20, UX-157, UX-158; O-222: access keys use encrypted casts; contact privacy@).
$ar = ($lang ?? 'ar') === 'ar';
$arPath = '/security';
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $ar ? 'ar' : 'en');
$title = $ar ? 'كيف تحمي AdsRanger بيانات الوكالات وعملائها | AdsRanger' : 'How AdsRanger Protects Agency and Client Data | AdsRanger';
$meta = $ar
    ? 'كيف تحمي AdsRanger، وهي قيد التطوير، بيانات الوكالات وعملائها: صلاحية قراءة فقط، وفصل بيانات كل وكالة، وتحقق بخطوتين.'
    : 'How AdsRanger, now in development, protects agency and client data: read-only reporting, separate agency data and two-step sign-in.';
$schema = [['@type' => 'WebPage', 'name' => $title, 'url' => \App\Support\PublicUrl::absolute($arPath, $ar ? 'ar' : 'en'), 'inLanguage' => $ar ? 'ar' : 'en']];
$sections = $ar ? [
    ['تقارير بصلاحية قراءة فقط', 'تطلب اتصالات التقارير من كل منصة إعلانية صلاحية قراءة بياناتك الإعلانية فقط.'],
    ['فصل بيانات كل وكالة', 'كل سجل يخص وكالة واحدة، ولا يرى أعضاء الفريق إلا العملاء المسندين إليهم.'],
    ['تسجيل دخول محمي', 'تحقق بخطوتين عبر تطبيق المصادقة ورموز الاسترداد، ومفاتيح الوصول إلى المنصات محفوظة مشفّرة.'],
    ['فحص آمن للروابط', 'فحوص صفحات الهبوط لا تتصل أبدًا بعناوين خاصة أو داخلية.'],
    ['بياناتك تحت تصرفك', 'افصل أي حساب، أو صدّر تقاريرك، أو اطلب منا حذف بياناتك.'],
    ['سجل النشاط', 'تُسجَّل تغييرات الأدوار والإعدادات الحساسة.'],
] : [
    ['Read-only reporting', 'Reporting connections ask each ad platform for read access to your ad data only.'],
    ['Each agency kept apart', 'Every record belongs to one agency, and team members see only the clients they\'re given.'],
    ['Protected sign-in', 'Two-step verification with an authenticator app and recovery codes; network access keys are stored encrypted.'],
    ['Safe link checks', 'Our landing-page checks never call private or internal addresses.'],
    ['Your data, your control', 'Disconnect any account, export your reports, or ask us to delete your data.'],
    ['Activity log', 'Role changes and sensitive settings are recorded.'],
];
$sections[2][1] = $ar
    ? 'تحقق بخطوتين عبر تطبيق المصادقة ورموز الاسترداد، ومفاتيح الوصول إلى المنصات محفوظة مشفّرة.'
    : $sections[2][1];
@endphp
@extends('layouts.public')

@push('styles')
<style>
.sec{padding-top:24px;max-width:960px}
.sec a{display:inline-flex;align-items:center;min-height:44px}
.sec-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(280px,100%),1fr));gap:16px;margin-top:24px}
.sec-grid .card h2{font-size:19px;margin:0 0 8px}
.sec-grid .card p{margin:0;color:var(--muted)}
</style>
@endpush

@section('hero')
<h1>{{ $ar ? 'كيف نحمي بيانات وكالتك وعملائك' : 'How we protect your agency and client data' }}</h1>
<p class="muted" style="font-size:17px">{!! $ar
? 'ما زالت <bdi dir="ltr">AdsRanger</bdi> قيد التطوير، وهذه وسائل الحماية المبنية فيها اليوم.'
: 'AdsRanger is in development. These are the safeguards built into it today.' !!}</p>
@endsection

@section('content')
<section class="sec">
    <div class="sec-grid">
        @foreach ($sections as $i => [$heading, $body])
            <div class="card">
                <h2>{{ $heading }}</h2>
                <p>{{ $body }}
                    @if ($i === 4)
                        <br><a href="{{ $u('/privacy') }}">{{ $ar ? 'سياسة الخصوصية' : 'Privacy policy' }}</a> ·
                        <a href="{{ $u('/data-deletion') }}">{{ $ar ? 'حذف البيانات' : 'Data deletion' }}</a>
                    @endif
                </p>
            </div>
        @endforeach
    </div>
    <div class="card" style="margin-top:24px;background:var(--surface)">
        <p style="margin:0">{!! $ar
            ? 'وجدت مشكلة أمنية؟ راسلنا على <a href="mailto:privacy@adsranger.com"><bdi dir="ltr">privacy@adsranger.com</bdi></a>.'
            : 'Found a security issue? Email <a href="mailto:privacy@adsranger.com">privacy@adsranger.com</a>.' !!}</p>
    </div>
</section>
@endsection
