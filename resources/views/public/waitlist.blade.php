@php
$ar = ($lang ?? 'ar') === 'ar';
$base = $ar ? '' : '/en';
$self = 'https://adsranger.com'.($ar ? '/waitlist' : '/en/waitlist');
$t = $ar ? [
    'title' => 'قائمة انتظار AdsRanger: نخبرك عند الإطلاق',
    'meta' => 'اترك بريدك واسم وكالتك ودولتك، وسنراسلك عند إطلاق AdsRanger، مساحة العمل العربية أولاً لوكالات المنطقة، في 2027.',
    'switch_url' => '/en/waitlist',
    'h1' => 'انضم إلى قائمة انتظار <bdi dir="ltr">AdsRanger</bdi>',
    'lead' => '<bdi dir="ltr">AdsRanger</bdi> قيد التطوير، والإطلاق في 2027. اترك بياناتك وسنراسلك عند الإطلاق. لا رسائل مزعجة، ويمكنك مغادرة القائمة في أي وقت.',
    'saved_h' => 'شكرًا، سجّلنا بياناتك',
    'saved_p' => 'حفظنا بياناتك. قبل أن نرسل إليك أي أخبار، سنراسلك مرة واحدة لتأكيد بريدك.',
    'errors_h' => 'راجع الحقول التالية:',
    'email' => 'البريد الإلكتروني للعمل',
    'agency' => 'اسم الوكالة',
    'country' => 'الدولة',
    'country_pick' => 'اختر دولتك',
    'countries' => ['JO' => 'الأردن', 'SA' => 'السعودية', 'AE' => 'الإمارات', 'KW' => 'الكويت', 'QA' => 'قطر', 'BH' => 'البحرين', 'OM' => 'عُمان', 'EG' => 'مصر', 'IQ' => 'العراق', 'LB' => 'لبنان', 'PS' => 'فلسطين', 'SY' => 'سوريا', 'MA' => 'المغرب', 'DZ' => 'الجزائر', 'TN' => 'تونس', 'LY' => 'ليبيا', 'SD' => 'السودان', 'XX' => 'دولة أخرى'],
    'name' => 'اسمك (اختياري)',
    'spend' => 'الإنفاق الإعلاني الشهري الذي تديره (اختياري)',
    'spend_pick' => 'اختر النطاق',
    'spend_opts' => ['under_10k' => 'أقل من 10,000 USD', '10k_50k' => '10,000–50,000 USD', '50k_200k' => '50,000–200,000 USD', 'over_200k' => 'أكثر من 200,000 USD'],
    'networks' => 'المنصات الإعلانية التي تديرها (اختياري)',
    'other' => 'أخرى',
    'consent' => 'أوافق على تلقي رسائل بريد إلكتروني حول إطلاق <bdi dir="ltr">AdsRanger</bdi>.',
    'policy' => 'سياسة الخصوصية',
    'new_tab' => '(يفتح في علامة تبويب جديدة)',
    'submit' => 'انضم إلى قائمة الانتظار',
    'privacy_line' => 'نستخدم بريدك واسم وكالتك ودولتك لنتواصل معك بخصوص إطلاق <bdi dir="ltr">AdsRanger</bdi>. الاسم ونطاق الإنفاق والمنصات التي تديرها اختيارية، وتساعدنا على دعوة الوكالات المناسبة أولًا. لا نشارك هذه البيانات مع أي جهة، ويمكنك إلغاء الاشتراك أو طلب حذفها في أي وقت.',
    'privacy_link' => 'اطّلع على سياسة الخصوصية.',
] : [
    'title' => 'AdsRanger Waitlist: Hear When We Launch',
    'meta' => 'Get an email when AdsRanger, the Arabic-first workspace for MENA agencies, launches in 2027. Leave your email, agency name and country.',
    'switch_url' => '/waitlist',
    'h1' => 'Join the AdsRanger waitlist',
    'lead' => 'AdsRanger is in development and launching in 2027. Leave your details and we\'ll email you when it launches. No spam, and you can leave the list at any time.',
    'saved_h' => 'Thanks, you\'re on the list',
    'saved_p' => 'We\'ve saved your details. Before we send you any news, we\'ll email you once to confirm your address.',
    'errors_h' => 'Please check these fields:',
    'email' => 'Work email',
    'agency' => 'Agency name',
    'country' => 'Country',
    'country_pick' => 'Choose your country',
    'countries' => ['JO' => 'Jordan', 'SA' => 'Saudi Arabia', 'AE' => 'United Arab Emirates', 'KW' => 'Kuwait', 'QA' => 'Qatar', 'BH' => 'Bahrain', 'OM' => 'Oman', 'EG' => 'Egypt', 'IQ' => 'Iraq', 'LB' => 'Lebanon', 'PS' => 'Palestine', 'SY' => 'Syria', 'MA' => 'Morocco', 'DZ' => 'Algeria', 'TN' => 'Tunisia', 'LY' => 'Libya', 'SD' => 'Sudan', 'XX' => 'Other'],
    'name' => 'Your name (optional)',
    'spend' => 'Managed monthly ad spend (optional)',
    'spend_pick' => 'Choose a range',
    'spend_opts' => ['under_10k' => 'Under 10,000 USD', '10k_50k' => '10,000–50,000 USD', '50k_200k' => '50,000–200,000 USD', 'over_200k' => 'Over 200,000 USD'],
    'networks' => 'Ad platforms you run (optional)',
    'other' => 'Other',
    'consent' => 'I agree to receive emails about AdsRanger\'s launch.',
    'policy' => 'Privacy policy',
    'new_tab' => '(opens in a new tab)',
    'submit' => 'Join the waitlist',
    'privacy_line' => 'We use your email, agency and country to contact you about AdsRanger\'s launch. Name, spend range and the networks you run are optional; they help us invite the right agencies first. We don\'t share this with anyone, and you can unsubscribe or ask us to delete it at any time.',
    'privacy_link' => 'See our privacy policy.',
];
$networkLabels = ['meta' => 'Meta', 'google' => 'Google', 'tiktok' => 'TikTok', 'snapchat' => 'Snapchat', 'microsoft' => 'Microsoft Advertising', 'linkedin' => 'LinkedIn', 'x' => 'X', 'other' => null];
$fieldIds = ['email' => 'wl-email', 'agency_name' => 'wl-agency', 'country' => 'wl-country', 'consent' => 'wl-consent', 'form' => 'wl-email'];
$oldNetworks = (array) old('networks', []);
@endphp
<!doctype html>
<html lang="{{ $ar ? 'ar' : 'en' }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<link rel="icon" href="/brand/favicon.svg" type="image/svg+xml">
<title>{{ $t['title'] }}</title>
<meta name="description" content="{{ $t['meta'] }}">
<link rel="canonical" href="{{ $self }}">
<link rel="alternate" hreflang="ar" href="https://adsranger.com/waitlist">
<link rel="alternate" hreflang="en" href="https://adsranger.com/en/waitlist">
<link rel="alternate" hreflang="x-default" href="https://adsranger.com/waitlist">
<script type="application/ld+json">{!! json_encode(['@@context' => 'https://schema.org', '@type' => 'WebPage', 'name' => $t['title'], 'url' => $self, 'inLanguage' => $ar ? 'ar' : 'en'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#0b1020;--card:#121933;--ink:#eef1fb;--muted:#9aa3c0;--acc:#5EEAD4;--line:#232c4d;--bad:#fca5a5}
*{box-sizing:border-box}body{margin:0;font-family:{{ $ar ? '"IBM Plex Sans Arabic",' : '' }}system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;background:var(--bg);color:var(--ink);line-height:{{ $ar ? '1.8' : '1.55' }}}
.w{max-width:860px;margin:0 auto;padding:0 20px}
.btn{display:inline-flex;align-items:center;min-height:44px;padding:10px 18px;border-radius:10px;background:var(--acc);color:#0B1020;text-decoration:none;font-weight:600;border:0;cursor:pointer;font-family:inherit;font-size:15px}
.card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:26px;margin:24px 0 48px}
label,.lbl{display:block;font-size:14px;font-weight:600;margin:16px 0 6px}
input[type=text],input[type=email],select{width:100%;min-height:44px;padding:10px 13px;border-radius:10px;border:1px solid var(--line);background:#0b1020;color:var(--ink);font-family:inherit;font-size:15px}
input[aria-invalid=true],select[aria-invalid=true]{border-color:var(--bad)}
.check{display:flex;align-items:center;gap:10px;min-height:44px;margin:0;font-weight:400;cursor:pointer}
.check input{width:20px;height:20px;flex:none;accent-color:var(--acc)}
.nets{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:0 12px}
.consent{border:1px solid var(--line);border-radius:12px;padding:6px 14px;margin-top:20px}
.ok{background:rgba(94,234,212,.12);border:1px solid var(--acc);padding:14px 16px;border-radius:12px;margin-bottom:18px}
.ok h2{margin:0 0 4px;font-size:18px}.ok p{margin:0}
.sum{border:1px solid var(--bad);border-radius:12px;padding:12px 16px;margin-bottom:18px}
.sum a{color:var(--bad)}
.err{color:var(--bad);font-size:13px;margin-top:4px}
.hp{position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden}
.priv{color:var(--muted);font-size:13px;margin:18px 0 0}.priv a,.consent a{color:var(--acc)}
</style>
</head>
<body>
<div class="w">
@include('public.nav', ['lang' => $lang, 'switch_url' => $t['switch_url']])
<section style="padding:32px 0 8px">
    <h1 style="font-size:clamp(26px,4.5vw,40px);margin:0 0 12px">{!! $t['h1'] !!}</h1>
    <p style="color:var(--muted);font-size:17px;margin:0">{!! $t['lead'] !!}</p>
</section>
<div class="card">
    @if (session('waitlist_saved'))
        <div class="ok" id="wl-done" tabindex="-1" role="status">
            <h2>{{ $t['saved_h'] }}</h2>
            <p>{{ $t['saved_p'] }}</p>
        </div>
    @endif
    @if ($errors->any())
        <div class="sum" id="wl-errors" tabindex="-1" role="alert">
            <strong>{{ $t['errors_h'] }}</strong>
            <ul style="margin:6px 0 0;padding-inline-start:20px">
                @foreach ($errors->keys() as $key)
                    <li><a href="#{{ $fieldIds[$key] ?? 'wl-email' }}">{{ $errors->first($key) }}</a></li>
                @endforeach
            </ul>
        </div>
    @endif
    <form method="POST" action="{{ $base }}/waitlist" novalidate>
        @csrf
        <div class="hp" aria-hidden="true">
            <label for="wl-website">Website</label>
            <input id="wl-website" name="website" type="text" tabindex="-1" autocomplete="off" value="">
        </div>

        <label for="wl-email">{{ $t['email'] }} *</label>
        <input id="wl-email" name="email" type="email" inputmode="email" autocomplete="email" dir="ltr" required maxlength="190" placeholder="name@agency.com" value="{{ old('email') }}" @error('email') aria-invalid="true" aria-describedby="wl-email-err" @enderror>
        @error('email')<div class="err" id="wl-email-err">{{ $message }}</div>@enderror

        <label for="wl-agency">{{ $t['agency'] }} *</label>
        <input id="wl-agency" name="agency_name" type="text" autocomplete="organization" required minlength="2" maxlength="140" value="{{ old('agency_name') }}" @error('agency_name') aria-invalid="true" aria-describedby="wl-agency-err" @enderror>
        @error('agency_name')<div class="err" id="wl-agency-err">{{ $message }}</div>@enderror

        <label for="wl-country">{{ $t['country'] }} *</label>
        <select id="wl-country" name="country" required @error('country') aria-invalid="true" aria-describedby="wl-country-err" @enderror>
            <option value="">{{ $t['country_pick'] }}</option>
            @foreach ($t['countries'] as $code => $label)
                <option value="{{ $code }}" @selected(old('country') === $code)>{{ $label }}</option>
            @endforeach
        </select>
        @error('country')<div class="err" id="wl-country-err">{{ $message }}</div>@enderror

        <label for="wl-name">{{ $t['name'] }}</label>
        <input id="wl-name" name="name" type="text" autocomplete="name" maxlength="120" value="{{ old('name') }}">

        <label for="wl-spend">{{ $t['spend'] }}</label>
        <select id="wl-spend" name="monthly_spend">
            <option value="">{{ $t['spend_pick'] }}</option>
            @foreach ($t['spend_opts'] as $k => $lbl)
                <option value="{{ $k }}" @selected(old('monthly_spend') === $k)>{{ $lbl }}</option>
            @endforeach
        </select>

        <fieldset style="border:0;padding:0;margin:0">
            <legend class="lbl">{{ $t['networks'] }}</legend>
            <div class="nets">
                @foreach ($networkLabels as $key => $label)
                    <label class="check"><input type="checkbox" name="networks[]" value="{{ $key }}" @checked(in_array($key, $oldNetworks, true))>
                        @if ($label === null){{ $t['other'] }}@elseif ($ar)<bdi dir="ltr">{{ $label }}</bdi>@else{{ $label }}@endif
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="consent">
            <label class="check" for="wl-consent">
                <input id="wl-consent" name="consent" type="checkbox" value="1" required @error('consent') aria-invalid="true" aria-describedby="wl-consent-err" @enderror>
                <span>{!! $t['consent'] !!} <a href="{{ $base }}/privacy" target="_blank" rel="noopener" aria-label="{{ $t['policy'] }} {{ $t['new_tab'] }}">{{ $t['policy'] }}</a></span>
            </label>
            @error('consent')<div class="err" id="wl-consent-err">{{ $message }}</div>@enderror
        </div>

        <div style="margin-top:20px">
            <button type="submit" class="btn">{{ $t['submit'] }}</button>
        </div>
        <p class="priv">{!! $t['privacy_line'] !!} <a href="{{ $base }}/privacy">{{ $t['privacy_link'] }}</a></p>
    </form>
</div>
@include('public.footer', ['lang' => $lang])
</div>
<script>
(function(){var el=document.getElementById('wl-errors')||document.getElementById('wl-done');if(el){el.focus();}})();
</script>
@include('public.cookie-banner', ['lang' => $lang])
</body>
</html>
