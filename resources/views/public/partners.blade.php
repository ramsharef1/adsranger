@php
// Website P-25 /partners (D-120 fields; D-219 built now, no founding-price line; consent partners-v1).
$ar = ($lang ?? 'ar') === 'ar';
$lang = $ar ? 'ar' : 'en';
$arPath = '/partners';
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
$title = $ar ? 'كن وكالة شريكة في تصميم AdsRanger' : 'Become an AdsRanger Design Partner Agency';
$meta = $ar
    ? 'تختار AdsRanger عددًا قليلًا من وكالات المنطقة للعمل معنا قبل الإطلاق في 2027، بدءًا من الأردن. قدّم طلبك في دقيقتين.'
    : 'AdsRanger is choosing a small group of MENA agencies to work with us before the 2027 launch, starting in Jordan. Apply in two minutes.';
$schema = [['@type' => 'WebPage', 'name' => $title, 'url' => \App\Support\PublicUrl::absolute($arPath, $lang), 'inLanguage' => $lang]];
$countries = $ar
    ? ['JO' => 'الأردن', 'SA' => 'السعودية', 'AE' => 'الإمارات', 'KW' => 'الكويت', 'QA' => 'قطر', 'BH' => 'البحرين', 'OM' => 'عُمان', 'EG' => 'مصر', 'IQ' => 'العراق', 'LB' => 'لبنان', 'PS' => 'فلسطين', 'SY' => 'سوريا', 'MA' => 'المغرب', 'DZ' => 'الجزائر', 'TN' => 'تونس', 'LY' => 'ليبيا', 'SD' => 'السودان', 'XX' => 'دولة أخرى']
    : ['JO' => 'Jordan', 'SA' => 'Saudi Arabia', 'AE' => 'United Arab Emirates', 'KW' => 'Kuwait', 'QA' => 'Qatar', 'BH' => 'Bahrain', 'OM' => 'Oman', 'EG' => 'Egypt', 'IQ' => 'Iraq', 'LB' => 'Lebanon', 'PS' => 'Palestine', 'SY' => 'Syria', 'MA' => 'Morocco', 'DZ' => 'Algeria', 'TN' => 'Tunisia', 'LY' => 'Libya', 'SD' => 'Sudan', 'XX' => 'Other'];
$ranges = ['1-5' => '1–5', '6-15' => '6–15', '16-40' => '16–40', '40+' => $ar ? 'أكثر من 40' : 'More than 40'];
$spend = $ar
    ? ['under_10k' => 'أقل من <bdi dir="ltr">10,000 USD</bdi>', '10k_50k' => '<bdi dir="ltr">10,000–50,000 USD</bdi>', '50k_200k' => '<bdi dir="ltr">50,000–200,000 USD</bdi>', 'over_200k' => 'أكثر من <bdi dir="ltr">200,000 USD</bdi>']
    : ['under_10k' => 'Under 10,000 USD', '10k_50k' => '10,000–50,000 USD', '50k_200k' => '50,000–200,000 USD', 'over_200k' => 'Over 200,000 USD'];
$networks = ['meta' => 'Meta', 'google' => 'Google', 'tiktok' => 'TikTok', 'snapchat' => 'Snapchat', 'microsoft' => 'Microsoft Advertising', 'linkedin' => 'LinkedIn', 'x' => 'X', 'other' => null];
$oldNetworks = (array) old('networks', []);
$fieldIds = ['email' => 'pa-email', 'agency_name' => 'pa-agency', 'country' => 'pa-country', 'clients_range' => 'pa-clients', 'consent' => 'pa-consent', 'form' => 'pa-email'];
@endphp
@extends('layouts.public')

@push('styles')
<style>
.pa{max-width:860px;padding-top:24px}
.pa ul.dots{padding-inline-start:22px}
.pa .nets{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:0 12px}
.pa .consent{border:1px solid var(--line);border-radius:12px;padding:6px 14px;margin-top:20px}
.pa .ok{background:var(--info-bg);border:1px solid var(--acc);padding:14px 16px;border-radius:12px;margin-bottom:18px}
.pa .sum{border:1px solid var(--bad);border-radius:12px;padding:12px 16px;margin-bottom:18px}
.pa .sum a{color:var(--bad)}
.pa .err{color:var(--bad);font-size:13px;margin-top:4px}
.pa input[aria-invalid=true],.pa select[aria-invalid=true]{border-color:var(--bad)}
.hp{position:absolute;width:1px;height:1px;margin:-1px;padding:0;border:0;overflow:hidden;clip-path:inset(50%);white-space:nowrap}
</style>
@endpush

@section('content')
<section class="pa">
    <h1>{!! $ar ? 'ساعدنا في بناء <bdi dir="ltr">AdsRanger</bdi> من واقع عمل وكالتك' : 'Help us build AdsRanger with your agency\'s real work' !!}</h1>
    <p class="muted" style="font-size:17px">{{ $ar ? 'نختار مجموعة صغيرة من الوكالات للعمل معنا قبل الإطلاق، نبدأ بوكالات الأردن ثم الخليج.' : 'We\'re choosing a small group of agencies to work with us before launch. We start with agencies in Jordan, then the Gulf.' }}</p>

    <h2>{{ $ar ? 'ما يقدّمه الشركاء' : 'What partners do' }}</h2>
    <ul class="dots">
        <li>{{ $ar ? 'مكالمات قصيرة كل أسبوعين' : 'Short calls every two weeks' }}</li>
        <li>{{ $ar ? 'ملاحظات على الشاشات والتقارير' : 'Feedback on screens and reports' }}</li>
        <li>{{ $ar ? 'تجربة الميزات على حساباتهم بعد موافقة المنصات على تطبيقاتنا (قراءة فقط، وبموافقة مكتوبة من كل عميل)' : 'Trying features on their own accounts once the platforms approve our apps (read-only, with each client\'s written OK)' }}</li>
    </ul>
    <h2>{{ $ar ? 'ما يحصل عليه الشركاء' : 'What partners get' }}</h2>
    <ul class="dots">
        <li>{{ $ar ? 'رأي في ما نبنيه أولًا' : 'A say in what we build first' }}</li>
        <li>{{ $ar ? 'تواصل مباشر مع الفريق' : 'Direct contact with the team' }}</li>
    </ul>

    <h2 id="apply">{{ $ar ? 'قدّم طلبك' : 'Apply' }}</h2>
    <div class="card">
        @if (session('partners_saved'))
            <div class="ok" id="pa-done" tabindex="-1" role="status"><p style="margin:0">{{ $ar ? 'شكرًا. نقرأ كل طلب ونرد بالبريد إذا كان هناك توافق.' : 'Thanks. We read every application and reply by email if there\'s a fit.' }}</p></div>
        @endif
        @if ($errors->any())
            <div class="sum" id="pa-errors" tabindex="-1" role="alert">
                <strong>{{ $ar ? 'راجع الحقول التالية:' : 'Please check these fields:' }}</strong>
                <ul style="margin:6px 0 0;padding-inline-start:20px">
                    @foreach ($errors->keys() as $key)<li><a href="#{{ $fieldIds[$key] ?? 'pa-email' }}">{{ $errors->first($key) }}</a></li>@endforeach
                </ul>
            </div>
        @endif
        <form method="POST" action="{{ $u('/partners') }}" novalidate>
            @csrf
            <div class="hp" aria-hidden="true"><label for="pa-website">Website</label><input id="pa-website" name="website" type="text" tabindex="-1" autocomplete="off" value=""></div>
            <p class="muted" style="margin:0">{{ $ar ? 'الحقول المعلَّمة بـ * مطلوبة.' : 'Fields marked * are required.' }}</p>

            <label for="pa-email">{{ $ar ? 'البريد الإلكتروني للعمل' : 'Work email' }} *</label>
            <input id="pa-email" name="email" type="email" inputmode="email" autocomplete="email" dir="ltr" required maxlength="190" placeholder="name@agency.com" value="{{ old('email') }}" @error('email') aria-invalid="true" aria-describedby="pa-email-err" @enderror>
            @error('email')<div class="err" id="pa-email-err">{{ $message }}</div>@enderror

            <label for="pa-agency">{{ $ar ? 'اسم الوكالة' : 'Agency name' }} *</label>
            <input id="pa-agency" name="agency_name" type="text" autocomplete="organization" required minlength="2" maxlength="140" value="{{ old('agency_name') }}" @error('agency_name') aria-invalid="true" aria-describedby="pa-agency-err" @enderror>
            @error('agency_name')<div class="err" id="pa-agency-err">{{ $message }}</div>@enderror

            <label for="pa-country">{{ $ar ? 'الدولة' : 'Country' }} *</label>
            <select id="pa-country" name="country" required @error('country') aria-invalid="true" aria-describedby="pa-country-err" @enderror>
                <option value="">{{ $ar ? 'اختر دولتك' : 'Choose your country' }}</option>
                @foreach ($countries as $code => $label)<option value="{{ $code }}" @selected(old('country') === $code)>{{ $label }}</option>@endforeach
            </select>
            @error('country')<div class="err" id="pa-country-err">{{ $message }}</div>@enderror

            <fieldset style="border:0;padding:0;margin:16px 0 0" id="pa-clients">
                <legend class="lbl">{{ $ar ? 'عدد العملاء الذين تديرهم' : 'Number of clients you manage' }} *</legend>
                <div class="nets">
                    @foreach ($ranges as $key => $label)<label class="check"><input type="radio" name="clients_range" value="{{ $key }}" @checked(old('clients_range') === $key)> <bdi dir="ltr">{{ $label }}</bdi></label>@endforeach
                </div>
                @error('clients_range')<div class="err">{{ $message }}</div>@enderror
            </fieldset>

            <label for="pa-name">{{ $ar ? 'اسمك (اختياري)' : 'Your name (optional)' }}</label>
            <input id="pa-name" name="name" type="text" autocomplete="name" maxlength="120" value="{{ old('name') }}">

            <label for="pa-spend">{{ $ar ? 'الإنفاق الإعلاني الشهري الذي تديره (اختياري)' : 'Managed monthly ad spend (optional)' }}</label>
            <select id="pa-spend" name="monthly_spend">
                <option value="">{{ $ar ? 'اختر النطاق' : 'Choose a range' }}</option>
                @foreach ($spend as $k => $lbl)<option value="{{ $k }}" @selected(old('monthly_spend') === $k)>{{ strip_tags($lbl) }}</option>@endforeach
            </select>

            <fieldset style="border:0;padding:0;margin:0">
                <legend class="lbl">{{ $ar ? 'المنصات الإعلانية التي تديرها (اختياري)' : 'Ad platforms you run (optional)' }}</legend>
                <div class="nets">
                    @foreach ($networks as $key => $label)
                        <label class="check"><input type="checkbox" name="networks[]" value="{{ $key }}" @checked(in_array($key, $oldNetworks, true))> @if ($label === null){{ $ar ? 'أخرى' : 'Other' }}@else<bdi dir="ltr">{{ $label }}</bdi>@endif</label>
                    @endforeach
                </div>
            </fieldset>

            <div class="consent">
                <label class="check" for="pa-consent">
                    <input id="pa-consent" name="consent" type="checkbox" value="1" required @error('consent') aria-invalid="true" aria-describedby="pa-consent-err" @enderror>
                    <span>{!! $ar ? 'أوافق على أن يتواصل معي فريق <bdi dir="ltr">AdsRanger</bdi> بخصوص برنامج الشركاء.' : 'I agree to be contacted by the AdsRanger team about the partner program.' !!}</span>
                </label>
                @error('consent')<div class="err" id="pa-consent-err">{{ $message }}</div>@enderror
            </div>
            <div style="margin-top:20px"><button type="submit" class="btn">{{ $ar ? 'قدّم طلبك' : 'Apply' }}</button></div>
            <p class="muted" style="font-size:14px;margin:16px 0 0">{{ $ar ? 'نستخدم هذه البيانات فقط لمراجعة طلبك.' : 'We use these details only to review your application.' }} <a href="{{ $u('/privacy') }}">{{ $ar ? 'سياسة الخصوصية' : 'Privacy policy' }}</a></p>
        </form>
    </div>
</section>
@endsection

@push('scripts')
<script>(function(){var el=document.getElementById('pa-errors')||document.getElementById('pa-done');if(el){el.focus();}})();</script>
@endpush
