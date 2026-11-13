@php
$ar = ($lang ?? 'ar') === 'ar';
$base = $ar ? '' : '/en';
$arPath = '/waitlist';
$self = 'https://adsranger.com'.($ar ? '/waitlist' : '/en/waitlist');
$t = $ar ? [
    'title' => 'قائمة إطلاق AdsRanger في 2027',
    'meta' => 'اترك بريدك واسم وكالتك أو شركتك ودولتك لتنضم إلى قائمة إطلاق AdsRanger، مساحة العمل العربية أولًا لوكالات المنطقة، في 2027.',
    'switch_url' => '/en/waitlist',
    'h1' => 'انضم إلى قائمة انتظار <bdi dir="ltr">AdsRanger</bdi>',
    'lead' => 'ما زالت <bdi dir="ltr">AdsRanger</bdi> قيد التطوير، والإطلاق في 2027. اترك بياناتك لتنضم إلى قائمة الإطلاق في 2027. لا رسائل مزعجة، ويمكنك مغادرة القائمة في أي وقت.',
    'saved_h' => 'شكرًا، سجّلنا بياناتك',
    'saved_p' => 'حفظنا بياناتك في قائمة الإطلاق.',
    'errors_h' => 'راجع الحقول التالية:',
    'email' => 'البريد الإلكتروني للعمل',
    'agency' => 'اسم الوكالة أو الشركة',
    'audience' => 'من أنت؟ (اختياري)',
    'audience_opts' => ['agency' => 'وكالة إعلانية', 'freelancer' => 'مستقل', 'business' => 'شركة تدير إعلاناتها بنفسها'],
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
    'privacy_line' => 'نستخدم بريدك واسم شركتك أو وكالتك ودولتك فقط لإبلاغك بإطلاق <bdi dir="ltr">AdsRanger</bdi>. الاسم ونطاق الإنفاق والمنصات التي تديرها وإجابة «من أنت؟» اختيارية، وتساعدنا على دعوة المناسبين أولًا. لا نشارك هذه البيانات مع أي جهة، ويمكنك إلغاء الاشتراك أو طلب حذفها في أي وقت.',
    'privacy_link' => 'اطّلع على سياسة الخصوصية.',
] : [
    'title' => 'AdsRanger Waitlist: the 2027 Launch List',
    'meta' => 'Join the launch list for AdsRanger, the Arabic-first workspace for MENA agencies, launching in 2027. Leave your email, agency or company name and country.',
    'switch_url' => '/waitlist',
    'h1' => 'Join the AdsRanger waitlist',
    'lead' => 'AdsRanger is in development and launching in 2027. Leave your details to join the launch list for 2027. No spam, and you can leave the list at any time.',
    'saved_h' => 'Thanks, you\'re on the list',
    'saved_p' => 'We\'ve saved your details on the launch list.',
    'errors_h' => 'Please check these fields:',
    'email' => 'Work email',
    'agency' => 'Agency or company name',
    'audience' => 'You are… (optional)',
    'audience_opts' => ['agency' => 'Ad agency', 'freelancer' => 'Freelancer', 'business' => 'A business running its own ads'],
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
    'privacy_line' => 'We use your email, company name and country only to tell you about AdsRanger\'s launch. Name, spend range, the ad platforms you run and "You are…" are optional; they help us invite the right people first. We don\'t share this with anyone, and you can unsubscribe or ask us to delete it at any time.',
    'privacy_link' => 'See our privacy policy.',
];
$networkLabels = ['meta' => 'Meta', 'google' => 'Google Ads', 'tiktok' => 'TikTok', 'snapchat' => 'Snapchat', 'microsoft' => 'Microsoft Advertising', 'linkedin' => 'LinkedIn', 'x' => 'X', 'other' => null];
$fieldIds = ['email' => 'wl-email', 'agency_name' => 'wl-agency', 'country' => 'wl-country', 'consent' => 'wl-consent', 'form' => 'wl-email'];
$oldNetworks = (array) old('networks', []);
$title = $t['title'];
$meta = $t['meta'];
$schema = [['@type' => 'WebPage', 'name' => $t['title'], 'url' => $self, 'inLanguage' => $ar ? 'ar' : 'en']];
@endphp
@extends('layouts.public')

@push('styles')
<style>
.wl .check{min-height:44px;padding-block:6px}
.wl .nets{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:0 12px}
.wl .consent{border:1px solid var(--line);border-radius:12px;padding:6px 14px;margin-top:20px}
.wl .ok{background:var(--info-bg);border:1px solid var(--acc);padding:14px 16px;border-radius:12px;margin-bottom:18px}
.wl .ok h2{margin:0 0 4px;font-size:18px}.wl .ok p{margin:0}
.wl .sum{border:1px solid var(--bad);border-radius:12px;padding:12px 16px;margin-bottom:18px}
.wl .sum a{color:var(--bad)}
.wl .err{color:var(--bad);font-size:13px;margin-top:4px}
.wl input[aria-invalid=true],.wl select[aria-invalid=true]{border-color:var(--bad)}
.hp{position:absolute;width:1px;height:1px;margin:-1px;padding:0;border:0;overflow:hidden;clip-path:inset(50%);white-space:nowrap}
.wl .priv{color:var(--muted);font-size:14px;margin:18px 0 0}
</style>
@endpush

@section('hero')
<h1 style="font-size:clamp(26px,4.5vw,40px);margin:0 0 12px">{!! $t['h1'] !!}</h1>
<p style="color:var(--muted);font-size:17px;margin:0">{!! $t['lead'] !!}</p>
@endsection

@section('content')
<div class="card wl" style="max-width:860px;margin:24px 0">
    @if (session('waitlist_saved'))
        <div class="ok" id="wl-done" tabindex="-1" role="status">
            <h2>{{ $t['saved_h'] }}</h2>
            <p>{{ $t['saved_p'] }}</p>
            @if (session('waitlist_ref'))
                {{-- GR-07 (D-289): a share link for a colleague; we only count sign-ups per code. Growth reviews the line. --}}
                @php $shareUrl = \App\Support\PublicUrl::absolute('/waitlist', $ar ? 'ar' : 'en').'?ref='.session('waitlist_ref'); @endphp
                <p>{{ $ar ? 'شارك قائمة الإطلاق مع زميل:' : 'Share the launch list with a colleague:' }} <bdi dir="ltr"><a href="{{ $shareUrl }}">{{ $shareUrl }}</a></bdi></p>
            @endif
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

        <fieldset style="border:0;padding:0;margin:16px 0 0">
            <legend class="lbl">{{ $t['audience'] }}</legend>
            <div class="nets">
                @foreach ($t['audience_opts'] as $key => $label)
                    <label class="check"><input type="radio" name="audience" value="{{ $key }}" @checked(old('audience', request('type')) === $key)> {{ $label }}</label>
                @endforeach
            </div>
        </fieldset>

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
            <p class="muted" style="font-size:14px;margin:8px 0 0">{{ $ar ? 'تحتفظ الوكالات المؤسِّسة بسعر الإطلاق.' : 'Founding agencies keep their launch price.' }}</p>
        </div>
        <p class="priv">{!! $t['privacy_line'] !!} <a href="{{ $base }}/privacy">{{ $t['privacy_link'] }}</a></p>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function(){var el=document.getElementById('wl-errors')||document.getElementById('wl-done');if(el){el.focus();}})();
</script>
@endpush
