@php
    $lang = request()->segment(1) === 'en' ? 'en' : 'ar';
    $isAr = $lang === 'ar';
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $isAr ? 'AdsRanger — تقارير الإعلانات للوكالات' : 'AdsRanger — ad reporting for agencies' }}</title>
    <meta name="description" content="{{ $isAr ? 'منصة تقارير للوكالات الإعلانية: النتائج حسب الهدف الحقيقي لكل حملة، تنبيهات يومية، وتقارير بالعربية والإنجليزية. قيد التطوير، الإطلاق في 2027.' : 'Reporting platform for ad agencies: results by the real goal of each campaign, daily alerts, and Arabic and English client reports. In development, launching 2027.' }}">
    <link rel="canonical" href="{{ url($isAr ? '/' : '/en') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap">
    <style>
        :root {
            --bg-primary: #0B1120;
            --bg-card: #111827;
            --border-color: #1E293B;
            --text-main: #F8FAFC;
            --text-muted: #94A3B8;
            --accent: #14B8A6;
            --accent-dark: #0F766E;
        }
        body { margin:0; font-family:'IBM Plex Sans Arabic', -apple-system, BlinkMacSystemFont, sans-serif; background:var(--bg-primary); color:var(--text-main); line-height:1.7; }
        .hero { max-width:1160px; margin:0 auto; padding:80px 24px 50px; text-align:center; }
        .badge-pill { display:inline-flex; align-items:center; gap:8px; padding:6px 16px; border-radius:999px; background:rgba(20,184,166,0.14); color:#5EEAD4; font-size:0.86rem; font-weight:700; margin-bottom:20px; border:1px solid rgba(20,184,166,0.3); }
        h1 { font-size:clamp(2.4rem, 5vw, 3.8rem); font-weight:700; margin:0 0 20px; letter-spacing:-0.025em; line-height:1.2; }
        h1 span { background:linear-gradient(135deg, #5EEAD4 0%, #38BDF8 100%); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .subtitle { font-size:clamp(1.05rem, 2vw, 1.25rem); color:var(--text-muted); max-width:780px; margin:0 auto 36px; }
        .cta-group { display:flex; flex-wrap:wrap; justify-content:center; gap:14px; margin-bottom:50px; }
        .btn-primary { background:var(--accent-dark); color:#fff; padding:14px 28px; border-radius:12px; font-weight:700; text-decoration:none; font-size:1rem; box-shadow:0 10px 25px -5px rgba(15,118,110,0.4); transition:all 0.2s; }
        .btn-primary:hover { background:var(--accent); color:#0B1120; transform:translateY(-2px); }
        .btn-secondary { background:rgba(30,41,59,0.7); color:var(--text-main); border:1px solid var(--border-color); padding:14px 28px; border-radius:12px; font-weight:600; text-decoration:none; font-size:1rem; transition:all 0.2s; }
        .btn-secondary:hover { background:rgba(30,41,59,1); border-color:#475569; }

        .metrics-bar { max-width:1040px; margin:0 auto 70px; padding:0 24px; display:grid; grid-template-columns:repeat(auto-fit, minmax(min(210px, 100%), 1fr)); gap:16px; }
        .metric-card { background:rgba(17,24,39,0.7); border:1px solid var(--border-color); border-radius:14px; padding:20px; text-align:center; backdrop-filter:blur(8px); }
        .metric-val { font-size:1.75rem; font-weight:700; color:#5EEAD4; margin-bottom:4px; }
        .metric-lbl { font-size:0.88rem; color:var(--text-muted); }

        .section { max-width:1200px; margin:0 auto 80px; padding:0 24px; }
        .section-header { text-align:center; max-width:700px; margin:0 auto 50px; }
        .section-header h2 { font-size:2.2rem; font-weight:700; margin:0 0 12px; }
        .section-header p { color:var(--text-muted); font-size:1.05rem; }

        .grid-3 { display:grid; grid-template-columns:repeat(auto-fit, minmax(min(340px, 100%), 1fr)); gap:24px; }
        .card { background:var(--bg-card); border:1px solid var(--border-color); border-radius:18px; padding:32px 28px; display:flex; flex-direction:column; justify-content:space-between; transition:transform 0.2s, border-color 0.2s; }
        .card:hover { transform:translateY(-4px); border-color:rgba(20,184,166,0.4); }
        .card h3 { font-size:1.3rem; margin:0 0 12px; }
        .card p { color:var(--text-muted); font-size:0.95rem; margin:0 0 20px; }
        .card-link { color:#5EEAD4; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:6px; font-size:0.92rem; min-height:44px; }
        .card-link:hover { text-decoration:underline; }

        .networks-ticker { background:rgba(15,23,42,0.8); border-top:1px solid var(--border-color); border-bottom:1px solid var(--border-color); padding:28px 0; overflow:hidden; white-space:nowrap; }
        .networks-track { display:flex; gap:36px; animation:scroll 35s linear infinite; width:max-content; }
        @keyframes scroll { from { transform: translateX(0); } to { transform: translateX(-50%); } }
        .net-pill { background:rgba(30,41,59,0.5); border:1px solid var(--border-color); padding:8px 18px; border-radius:99px; font-size:0.9rem; font-weight:600; color:#CBD5E1; }
    </style>
</head>
<body>
@include('public.nav', ['lang' => $lang])

<section class="hero">
    <div class="badge-pill">
        <span>✨</span>
        <span>{{ $isAr ? 'عربية أولًا مع مخطط لرمضان' : 'Arabic-first, with a Ramadan planner' }}</span>
    </div>
    <h1>
        {{ $isAr ? 'تقارير إعلانات كل عملائك' : 'Every client\'s ad reporting' }}
        <br><span>{{ $isAr ? 'في مساحة عمل واحدة' : 'in one workspace' }}</span>
    </h1>
    {{-- D-139: Rami's approved lead line (Latin brand names in bdi). --}}
    <p class="subtitle">
        @if ($isAr)
            منصة تقارير <bdi dir="ltr">Meta</bdi>، <bdi dir="ltr">Google</bdi> للوكالات الإعلانية: الرسائل والمكالمات والعملاء المحتملون والحجوزات والمبيعات تُحسب حسب الهدف الحقيقي لكل حملة، مع تنبيهات يومية تكشف هدر الميزانية، وتقارير بالعربية والإنجليزية يفهمها عملاؤك.
        @else
            A Meta and Google reporting platform for ad agencies: messages, calls, leads, bookings and sales counted by each campaign's real goal, with daily alerts that catch wasted budget and Arabic and English reports your clients understand.
        @endif
    </p>
    <p style="color:var(--text-muted);font-size:0.9rem;margin:-20px auto 30px">{{ $isAr ? 'قيد التطوير · الإطلاق في 2027' : 'In development · launching 2027' }}</p>
    <div class="cta-group">
        <a href="{{ $isAr ? '/waitlist' : '/en/waitlist' }}" class="btn-primary">
            {{ $isAr ? 'طلب دعوة لوكالتك (Early Access)' : 'Request Agency Access' }}
        </a>
        <a href="{{ $isAr ? '/demo-report' : '/en/demo-report' }}" class="btn-secondary">
            {{ $isAr ? 'تقرير تجريبي ببيانات نموذجية' : 'Sample report (demo data)' }}
        </a>
    </div>
</section>

<p style="text-align:center;color:var(--text-muted);font-size:0.88rem;margin:0 0 10px">{{ $isAr ? 'المنصات الإعلانية قيد الربط: تُفعَّل كل منصة بعد اعتماد تطبيقها لدى الشبكة.' : 'Ad networks in development: each one opens once the network approves our app.' }}</p>
<div class="networks-ticker">
    <div class="networks-track">
        @foreach (['Meta', 'Google Ads', 'TikTok', 'Snapchat', 'Google Analytics 4', 'Microsoft Ads', 'LinkedIn', 'X', 'Pinterest', 'Reddit', 'Apple Ads', 'Amazon Ads', 'Taboola', 'Outbrain'] as $net)
            <span class="net-pill">{{ $net }}</span>
        @endforeach
        @foreach (['Meta', 'Google Ads', 'TikTok', 'Snapchat', 'Google Analytics 4', 'Microsoft Ads', 'LinkedIn', 'X', 'Pinterest', 'Reddit', 'Apple Ads', 'Amazon Ads', 'Taboola', 'Outbrain'] as $net)
            <span class="net-pill" aria-hidden="true">{{ $net }}</span>
        @endforeach
    </div>
</div>

<div class="metrics-bar" style="margin-top:70px;">
    <div class="metric-card">
        <div class="metric-val">{{ $isAr ? 'عربي أولًا' : 'Arabic-first' }}</div>
        <div class="metric-lbl">{{ $isAr ? 'لوحات وتقارير بالعربية والإنجليزية' : 'Arabic and English panels and reports' }}</div>
    </div>
    <div class="metric-card">
        <div class="metric-val">{{ $isAr ? 'حسب الهدف' : 'By goal' }}</div>
        <div class="metric-lbl">{{ $isAr ? 'النتيجة كما يعرّفها هدف الحملة' : 'Results as each campaign goal defines them' }}</div>
    </div>
    <div class="metric-card">
        <div class="metric-val">{{ $isAr ? 'رمضان' : 'Ramadan' }}</div>
        <div class="metric-lbl">{{ $isAr ? 'تقويم هجري ومواسم المنطقة' : 'Hijri calendar and regional seasons' }}</div>
    </div>
</div>

<section class="section">
    <div class="section-header">
        <h2>{{ $isAr ? 'كل ما تحتاجه وكالتك لزيادة الأرباح وتقليل الهدر' : 'Everything Your Agency Needs to Scale Profitably' }}</h2>
        <p>{{ $isAr ? 'مزايا مصممة خصيصًا لتحديات السوق الإقليمي وسرعة اتخاذ القرار' : 'Engineered specifically for regional market dynamics and fast-paced agency operations.' }}</p>
    </div>

    <div class="grid-3">
        <div class="card">
            <div>
                <h3>{{ $isAr ? 'المكتب اليومي والتنبيهات' : 'Daily desk and alerts' }}</h3>
                <p>{{ $isAr ? 'كل عملائك في شاشة واحدة كل صباح، مع تنبيهات حرجة وتحذيرات تكشف هدر الميزانية مبكرًا. قيد التطوير.' : 'Every client on one screen each morning, with critical alerts and warnings that catch wasted budget early. In development.' }}</p>
            </div>
            <a href="{{ $isAr ? '/pricing' : '/en/pricing' }}" class="card-link">{{ $isAr ? 'الباقات ←' : 'Plans →' }}</a>
        </div>

        <div class="card">
            <div>
                <h3>{{ $isAr ? 'تقارير العملاء بالعربية والإنجليزية' : 'Client reports in Arabic and English' }}</h3>
                <p>{{ $isAr ? 'روابط تقارير آمنة وتقرير شهري PDF وتقارير بالبريد بلغة كل عميل. قيد التطوير.' : 'Secure report links, a monthly PDF and email reports in each client\'s language. In development.' }}</p>
            </div>
            <a href="{{ $isAr ? '/demo-report' : '/en/demo-report' }}" class="card-link">{{ $isAr ? 'تقرير تجريبي ←' : 'Sample report →' }}</a>
        </div>

        <div class="card">
            <div>
                <h3>{{ $isAr ? 'استوديو الإعلانات (مخطط له)' : 'Ads Studio (planned)' }}</h3>
                <p>{{ $isAr ? 'إنشاء الحملات ونصوص إعلانية عربية مع فحص السياسات قبل النشر، وكل نشر بموافقتك. مخطط له.' : 'Campaign creation and Arabic ad copy with policy checks before publishing, every publish approved by you. Planned.' }}</p>
            </div>
            <a href="{{ $isAr ? '/waitlist' : '/en/waitlist' }}" class="card-link">{{ $isAr ? 'قائمة الانتظار ←' : 'Join the waitlist →' }}</a>
        </div>
    </div>
</section>

@include('public.footer', ['lang' => $lang])
@include('public.cookie-banner', ['lang' => $lang])
</body>
</html>
