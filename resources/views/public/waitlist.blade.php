@php
$ar = ($lang ?? 'ar') === 'ar';
$base = $ar ? '' : '/en';
$t = $ar ? [
    'title' => 'قائمة الانتظار · AdsRanger',
    'switch' => 'English', 'switch_url' => '/en/waitlist',
    'home' => 'الرئيسية', 'ramadan' => 'تخطيط رمضان 2027', 'signin' => 'تسجيل الدخول',
    'tag' => 'وصول مبكر للوكالات · إطلاق سبتمبر 2027',
    'h1' => 'انضم إلى قائمة الانتظار لوكالات الإعلانات في الشرق الأوسط.',
    'lead' => 'نفتح الحسابات التجريبية للوكالات على دفعات لضمان جودة الربط والدعم الفني. سجّل بيانات وكالتك وسنتواصل معك فور فتح الدفعة القادمة.',
    'saved' => 'تم تسجيل طلبك في قائمة الانتظار بنجاح. سنتواصل معك عبر البريد الإلكتروني قريباً.',
    'name' => 'الاسم الكامل',
    'agency' => 'اسم الوكالة',
    'email' => 'البريد الإلكتروني للعمل',
    'spend' => 'الإنفاق الإعلاني الشهري المُدار (تقريبي)',
    'spend_opts' => [
        '' => 'اختر النطاق...',
        'under_10k' => 'أقل من 10,000 دولار / شهرياً',
        '10k_50k' => '10,000 – 50,000 دولار / شهرياً',
        '50k_200k' => '50,000 – 200,000 دولار / شهرياً',
        'over_200k' => 'أكثر من 200,000 دولار / شهرياً',
    ],
    'notes' => 'الشبكات الإعلانية الأهم لوكالتك (اختياري)',
    'submit' => 'انضم إلى قائمة الانتظار',
    'foot' => 'عمّان',
] : [
    'title' => 'Join the Agency Waitlist · AdsRanger',
    'switch' => 'العربية', 'switch_url' => '/waitlist',
    'home' => 'Home', 'ramadan' => 'Ramadan 2027 Planner', 'signin' => 'Sign in',
    'tag' => 'Early agency access · Public launch Sep 2027',
    'h1' => 'Join the waitlist for MENA marketing agencies.',
    'lead' => 'We onboard agencies in cohorts so every workspace gets hands-on support across Meta, Google, TikTok, Snapchat and 14+ networks. Reserve your spot below.',
    'saved' => 'You are on the waitlist! We will reach out to your work email as soon as the next agency cohort opens.',
    'name' => 'Full name',
    'agency' => 'Agency name',
    'email' => 'Work email',
    'spend' => 'Managed monthly ad spend (approx.)',
    'spend_opts' => [
        '' => 'Select range...',
        'under_10k' => 'Under $10,000 / month',
        '10k_50k' => '$10,000 – $50,000 / month',
        '50k_200k' => '$50,000 – $200,000 / month',
        'over_200k' => '$200,000+ / month',
    ],
    'notes' => 'Top ad networks your agency runs (optional)',
    'submit' => 'Reserve agency spot',
    'foot' => 'Amman',
];
@endphp
<!doctype html>
<html lang="{{ $ar ? 'ar' : 'en' }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<link rel="icon" href="/brand/favicon.svg" type="image/svg+xml">
<title>{{ $t['title'] }}</title>
<link rel="alternate" hreflang="ar" href="https://adsranger.com/waitlist">
<link rel="alternate" hreflang="en" href="https://adsranger.com/en/waitlist">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#0b1020;--card:#121933;--ink:#eef1fb;--muted:#9aa3c0;--acc:#2DD4BF;--line:#232c4d}
*{box-sizing:border-box}body{margin:0;font-family:{{ $ar ? '"IBM Plex Sans Arabic",' : '' }}system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;background:var(--bg);color:var(--ink);line-height:{{ $ar ? '1.8' : '1.55' }}}
.w{max-width:860px;margin:0 auto;padding:0 20px}
nav{display:flex;justify-content:space-between;align-items:center;padding:20px 0;gap:12px;flex-wrap:wrap}
.logo{font-weight:700;letter-spacing:.2px;direction:ltr;color:var(--ink);text-decoration:none;display:flex;align-items:center;gap:10px}.logo b{color:var(--acc)}
.nr{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.btn{display:inline-block;padding:10px 18px;border-radius:10px;background:var(--acc);color:#0B1020;text-decoration:none;font-weight:600;border:0;cursor:pointer;font-family:inherit;font-size:15px}
.btn.o{background:transparent;border:1px solid var(--line);color:var(--ink)}
.lang{color:var(--muted);text-decoration:none;font-size:14px;padding:8px 10px}
.tag{display:inline-block;font-size:13px;color:var(--acc);border:1px solid var(--line);padding:4px 10px;border-radius:99px;margin-bottom:14px}
.card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:26px;margin:24px 0 48px}
label{display:block;font-size:14px;font-weight:600;margin:14px 0 6px}
input,select,textarea{width:100%;padding:11px 13px;border-radius:10px;border:1px solid var(--line);background:#0b1020;color:var(--ink);font-family:inherit;font-size:15px}
.ok{background:rgba(45,212,191,.12);border:1px solid var(--acc);color:var(--ink);padding:14px 16px;border-radius:12px;margin-bottom:18px}
.err{color:#f87171;font-size:13px;margin-top:4px}
footer{border-top:1px solid var(--line);padding:22px 0;color:var(--muted);font-size:13px;display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px}
</style>
</head>
<body>
<div class="w">
<nav>
    <a class="logo" href="{{ $base ?: '/' }}"><img src="/brand/adsranger-mark-teal.svg" alt="" width="34" height="34"><span>Ads<b>Ranger</b></span></a>
    <div class="nr">
        <a class="lang" href="{{ $base ?: '/' }}">{{ $t['home'] }}</a>
        <a class="lang" href="{{ $base }}/ramadan-2027">{{ $t['ramadan'] }}</a>
        <a class="lang" href="{{ $t['switch_url'] }}" lang="{{ $ar ? 'en' : 'ar' }}">{{ $t['switch'] }}</a>
        <a class="btn o" href="/app/login">{{ $t['signin'] }}</a>
    </div>
</nav>
<section style="padding:32px 0 8px">
    <span class="tag">{{ $t['tag'] }}</span>
    <h1 style="font-size:clamp(26px,4.5vw,40px);margin:0 0 12px">{{ $t['h1'] }}</h1>
    <p style="color:var(--muted);font-size:17px;margin:0">{{ $t['lead'] }}</p>
</section>
<div class="card">
    @if (session('waitlist_saved'))
        <div class="ok">{{ $t['saved'] }}</div>
    @endif
    <form method="POST" action="{{ $base }}/waitlist">
        @csrf
        <label for="wl-name">{{ $t['name'] }} *</label>
        <input id="wl-name" name="name" type="text" required maxlength="120" value="{{ old('name') }}">
        @error('name')<div class="err">{{ $message }}</div>@enderror

        <label for="wl-agency">{{ $t['agency'] }}</label>
        <input id="wl-agency" name="agency_name" type="text" maxlength="140" value="{{ old('agency_name') }}">

        <label for="wl-email">{{ $t['email'] }} *</label>
        <input id="wl-email" name="email" type="email" dir="ltr" required maxlength="190" value="{{ old('email') }}">
        @error('email')<div class="err">{{ $message }}</div>@enderror

        <label for="wl-spend">{{ $t['spend'] }}</label>
        <select id="wl-spend" name="monthly_spend">
            @foreach ($t['spend_opts'] as $k => $lbl)
                <option value="{{ $k }}" @selected(old('monthly_spend') === $k)>{{ $lbl }}</option>
            @endforeach
        </select>

        <label for="wl-notes">{{ $t['notes'] }}</label>
        <textarea id="wl-notes" name="notes" rows="3" maxlength="500">{{ old('notes') }}</textarea>

        <div style="margin-top:20px">
            <button type="submit" class="btn">{{ $t['submit'] }}</button>
        </div>
    </form>
</div>
<footer>
    <span>© {{ date('Y') }} AdsRanger · {{ $t['foot'] }}</span>
    <span>
        <a href="{{ $base }}/privacy" style="color:inherit;margin-inline-end:12px">{{ $ar ? 'الخصوصية' : 'Privacy' }}</a>
        <a href="{{ $base }}/terms" style="color:inherit;margin-inline-end:12px">{{ $ar ? 'الشروط' : 'Terms' }}</a>
        <a href="{{ $base }}/data-deletion" style="color:inherit">{{ $ar ? 'حذف البيانات' : 'Data deletion' }}</a>
    </span>
</footer>
</div>
@include('public.cookie-banner', ['lang' => $lang])
</body>
</html>
