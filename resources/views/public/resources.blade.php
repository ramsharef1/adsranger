<!DOCTYPE html>
<html lang="{{ $lang === 'ar' ? 'ar' : 'en' }}" dir="{{ $lang === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $lang === 'ar' ? 'مركز الموارد ودراسات الحالة — AdsRanger' : 'MENA Case Studies & Resources Hub — AdsRanger' }}</title>
    <style>
        body { margin:0; font-family:'IBM Plex Sans Arabic', -apple-system, sans-serif; background:#0B1120; color:#F8FAFC; line-height:1.65; }
        .hero { max-width:960px; margin:0 auto; padding:60px 20px 30px; text-align:center; }
        .grid { max-width:1100px; margin:20px auto 60px; padding:0 20px; display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:22px; }
        .card { background:#111827; border:1px solid #1E293B; border-radius:16px; padding:26px; }
    </style>
</head>
<body>
@include('public.nav', ['lang' => $lang])
<div class="hero">
    <h1>{{ $lang === 'ar' ? 'دراسات الحالة وأدلة الأداء التسويقي الإقليمي' : 'MENA Performance Playbooks & Case Studies' }}</h1>
    <p style="color:#94A3B8;">{{ $lang === 'ar' ? 'أبحاث متعمقة حول تحسين العائد المدمج (MER) وأساليب الإعلان الفعالة في أسواق الخليج ومصر.' : 'Data-backed research on blended ROAS attribution, creative fatigue, and regional media buying.' }}</p>
</div>
<div class="grid">
    @foreach ([
        ['title' => 'The 2027 MENA Ramadan Media Playbook', 'read' => '12 min read'],
        ['title' => 'Scaling E-Commerce MER Beyond 3.5x in KSA & UAE', 'read' => '9 min read'],
        ['title' => 'CAPI Event Match Quality (EMQ) Blueprint for iOS18', 'read' => '7 min read']
    ] as $res)
        <div class="card">
            <span style="font-size:0.8rem; color:#94A3B8;">📖 {{ $res['read'] }}</span>
            <h3 style="margin:8px 0 10px; font-size:1.2rem;">{{ $res['title'] }}</h3>
            <p style="color:#94A3B8; font-size:0.9rem; margin:0 0 16px;">{{ $lang === 'ar' ? 'استراتيجيات مجربة من محافظ الوكالات المدارة عبر منصة AdsRanger.' : 'Proven tactical frameworks derived from agency portfolios managed on AdsRanger.' }}</p>
            <a href="{{ $lang === 'ar' ? '/waitlist' : '/en/waitlist' }}" style="color:#2DD4BF; font-weight:600; text-decoration:none;">{{ $lang === 'ar' ? 'قراءة المقال الكامل ←' : 'Read Full Playbook →' }}</a>
        </div>
    @endforeach
</div>
@include('public.footer', ['lang' => $lang])
</body>
</html>
