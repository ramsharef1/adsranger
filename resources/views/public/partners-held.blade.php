<!DOCTYPE html>
<html lang="{{ $lang === 'ar' ? 'ar' : 'en' }}" dir="{{ $lang === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $lang === 'ar' ? 'دليل وكالات التسويق المعتمدة — AdsRanger' : 'Certified MENA Agency Partners — AdsRanger' }}</title>
    <style>
        body { margin:0; font-family:'IBM Plex Sans Arabic', -apple-system, sans-serif; background:#0B1120; color:#F8FAFC; line-height:1.65; }
        .hero { max-width:960px; margin:0 auto; padding:60px 20px 30px; text-align:center; }
        .grid { max-width:1100px; margin:20px auto 60px; padding:0 20px; display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:20px; }
        .card { background:#111827; border:1px solid #1E293B; border-radius:16px; padding:24px; }
    </style>
</head>
<body>
@include('public.nav', ['lang' => $lang])
<div class="hero">
    <h1>{{ $lang === 'ar' ? 'دليل وكالات التسويق المعتمدة في الشرق الأوسط' : 'Certified MENA Agency Partner Directory' }}</h1>
    <p style="color:#94A3B8;">{{ $lang === 'ar' ? 'اكتشف الوكالات الرائدة المعتمدة في استخدام منصة AdsRanger لإدارة ميزانيات الأداء.' : 'Discover top performance marketing agencies certified on AdsRanger across KSA, UAE, Jordan & Egypt.' }}</p>
</div>
<div class="grid">
    @foreach ([['name' => 'Riyadh Growth Partners', 'city' => 'Riyadh', 'tier' => 'Global Elite Partner'], ['name' => 'Dubai Media Labs', 'city' => 'Dubai', 'tier' => 'Pro Certified Partner'], ['name' => 'Amman Performance Hub', 'city' => 'Amman', 'tier' => 'Pro Certified Partner'], ['name' => 'Cairo Digital Scale', 'city' => 'Cairo', 'tier' => 'Verified Agency']] as $p)
        <div class="card">
            <span style="font-size:0.8rem; font-weight:700; color:#2DD4BF;">{{ $p['tier'] }}</span>
            <h3 style="margin:8px 0 4px; font-size:1.25rem;">{{ $p['name'] }}</h3>
            <p style="color:#94A3B8; margin:0 0 16px; font-size:0.9rem;">📍 {{ $p['city'] }}</p>
            <a href="{{ $lang === 'ar' ? '/waitlist' : '/en/waitlist' }}" style="color:#2DD4BF; font-weight:600; text-decoration:none;">{{ $lang === 'ar' ? 'طلب استشارة وكالة ←' : 'Request Agency Consult →' }}</a>
        </div>
    @endforeach
</div>
@include('public.footer', ['lang' => $lang])
</body>
</html>
