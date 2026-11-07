@php
    $lang = request()->segment(1) === 'en' ? 'en' : 'ar';
    $isAr = $lang === 'ar';
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isAr ? 'AdsRanger — نظام التشغيل والذكاء الموحد لوكالات التسويق في الشرق الأوسط' : 'AdsRanger — Unified Intelligence, Creative Studio & Operations OS for MENA Agencies' }}</title>
    <meta name="description" content="{{ $isAr ? 'منصة متكاملة لإدارة 18 شبكة إعلانية، توليد الإعلانات باللهجات المحلية، تحليل العائد المدمج MER، وتتبع عمليات الوكالات.' : 'Unified ad management, AI creative studio in MENA dialects, blended ROAS analytics, and agency operations OS for MENA marketing teams.' }}">
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
        .badge-pill { display:inline-flex; align-items:center; gap:8px; padding:6px 16px; border-radius:999px; background:rgba(20,184,166,0.14); color:#2DD4BF; font-size:0.86rem; font-weight:700; margin-bottom:20px; border:1px solid rgba(20,184,166,0.3); }
        h1 { font-size:clamp(2.4rem, 5vw, 3.8rem); font-weight:700; margin:0 0 20px; letter-spacing:-0.025em; line-height:1.2; }
        h1 span { background:linear-gradient(135deg, #2DD4BF 0%, #38BDF8 100%); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .subtitle { font-size:clamp(1.05rem, 2vw, 1.25rem); color:var(--text-muted); max-width:780px; margin:0 auto 36px; }
        .cta-group { display:flex; flex-wrap:wrap; justify-content:center; gap:14px; margin-bottom:50px; }
        .btn-primary { background:var(--accent-dark); color:#fff; padding:14px 28px; border-radius:12px; font-weight:700; text-decoration:none; font-size:1rem; box-shadow:0 10px 25px -5px rgba(15,118,110,0.4); transition:all 0.2s; }
        .btn-primary:hover { background:var(--accent); color:#0B1120; transform:translateY(-2px); }
        .btn-secondary { background:rgba(30,41,59,0.7); color:var(--text-main); border:1px solid var(--border-color); padding:14px 28px; border-radius:12px; font-weight:600; text-decoration:none; font-size:1rem; transition:all 0.2s; }
        .btn-secondary:hover { background:rgba(30,41,59,1); border-color:#475569; }

        .metrics-bar { max-width:1040px; margin:0 auto 70px; padding:0 24px; display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:16px; }
        .metric-card { background:rgba(17,24,39,0.7); border:1px solid var(--border-color); border-radius:14px; padding:20px; text-align:center; backdrop-filter:blur(8px); }
        .metric-val { font-size:1.75rem; font-weight:700; color:#2DD4BF; margin-bottom:4px; }
        .metric-lbl { font-size:0.88rem; color:var(--text-muted); }

        .section { max-width:1200px; margin:0 auto 80px; padding:0 24px; }
        .section-header { text-align:center; max-width:700px; margin:0 auto 50px; }
        .section-header h2 { font-size:2.2rem; font-weight:700; margin:0 0 12px; }
        .section-header p { color:var(--text-muted); font-size:1.05rem; }

        .grid-3 { display:grid; grid-template-columns:repeat(auto-fit, minmax(340px, 1fr)); gap:24px; }
        .card { background:var(--bg-card); border:1px solid var(--border-color); border-radius:18px; padding:32px 28px; display:flex; flex-direction:column; justify-content:space-between; transition:transform 0.2s, border-color 0.2s; }
        .card:hover { transform:translateY(-4px); border-color:rgba(20,184,166,0.4); }
        .card h3 { font-size:1.3rem; margin:0 0 12px; }
        .card p { color:var(--text-muted); font-size:0.95rem; margin:0 0 20px; }
        .card-link { color:#2DD4BF; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:6px; font-size:0.92rem; }
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
        <span>{{ $isAr ? 'مصمم خصيصاً لوكالات التسويق في الشرق الأوسط وإفريقيا' : 'Built for MENA Growth & Performance Agencies' }}</span>
    </div>
    <h1>
        {{ $isAr ? 'نظام التشغيل، الذكاء الإعلاني والاستوديو الإبداعي في' : 'The Unified Intelligence, Creative Studio & Operations OS for' }}
        <br><span>{{ $isAr ? 'منصة واحدة متكاملة' : 'MENA Agencies' }}</span>
    </h1>
    <p class="subtitle">
        {{ $isAr
            ? 'أدر 18 شبكة إعلانية، أنشئ إعلانات باللهجات الخليجية والشامية والمصرية بالذكاء الاصطناعي، تتبع العائد المدمج (MER)، وأرسل تقارير WhatsApp الآلية لعملائك.'
            : 'Manage 18 ad networks, generate localized AI ad copy in Gulf, Levantine, Egyptian & MSA dialects, track true blended ROAS, and automate client WhatsApp reports.' }}
    </p>
    <div class="cta-group">
        <a href="{{ $isAr ? '/waitlist' : '/en/waitlist' }}" class="btn-primary">
            {{ $isAr ? 'طلب دعوة لوكالتك (Early Access)' : 'Request Agency Access' }}
        </a>
        <a href="{{ $isAr ? '/demo-report' : '/en/demo-report' }}" class="btn-secondary">
            {{ $isAr ? 'استعراض تقرير العميل الحي (Demo)' : 'View Live Demo Report' }}
        </a>
    </div>
</section>

<div class="networks-ticker">
    <div class="networks-track">
        @foreach (['Meta Ads (FB/IG)', 'Google Ads & PMax', 'TikTok Ads', 'Snapchat Ads', 'Google Analytics 4', 'LinkedIn Ads', 'X (Twitter) Ads', 'Apple Search Ads', 'Amazon Ads', 'Noon Ads', 'Talabat Ads', 'Anghami Ads', 'Shahid VIP', 'Taboola & Outbrain', 'Google DV360', 'AppsFlyer & Adjust'] as $net)
            <span class="net-pill">⚡ {{ $net }}</span>
        @endforeach
        @foreach (['Meta Ads (FB/IG)', 'Google Ads & PMax', 'TikTok Ads', 'Snapchat Ads', 'Google Analytics 4', 'LinkedIn Ads', 'X (Twitter) Ads', 'Apple Search Ads', 'Amazon Ads', 'Noon Ads', 'Talabat Ads', 'Anghami Ads', 'Shahid VIP', 'Taboola & Outbrain', 'Google DV360', 'AppsFlyer & Adjust'] as $net)
            <span class="net-pill">⚡ {{ $net }}</span>
        @endforeach
    </div>
</div>

<div class="metrics-bar" style="margin-top:70px;">
    <div class="metric-card">
        <div class="metric-val">18+</div>
        <div class="metric-lbl">{{ $isAr ? 'شبكة إعلانية ومنصة تتبع' : 'Ad Networks & Trackers' }}</div>
    </div>
    <div class="metric-card">
        <div class="metric-val">5</div>
        <div class="metric-lbl">{{ $isAr ? 'لهجات عربية وإقليمية للإعلانات' : 'MENA Dialects Supported' }}</div>
    </div>
    <div class="metric-card">
        <div class="metric-val">240/240</div>
        <div class="metric-lbl">{{ $isAr ? 'اختبار آلي معتمد للجاهزية' : 'Verified Passing Tests' }}</div>
    </div>
    <div class="metric-card">
        <div class="metric-val">100%</div>
        <div class="metric-lbl">{{ $isAr ? 'عزل تام للبيانات وصلاحيات العملاء' : 'Tenant & Client Isolation' }}</div>
    </div>
</div>

<section class="section">
    <div class="section-header">
        <h2>{{ $isAr ? 'كل ما تحتاجه وكالتك لزيادة الأرباح وتقليل الهدر' : 'Everything Your Agency Needs to Scale Profitably' }}</h2>
        <p>{{ $isAr ? 'مزايا مصممة خصيصاً لتحديات السوق الإقليمي وسرعة اتخاذ القرار' : 'Engineered specifically for regional market dynamics and fast-paced agency operations.' }}</p>
    </div>

    <div class="grid-3">
        <div class="card">
            <div>
                <h3>{{ $isAr ? 'استوديو الإباشر والذكاء الاصطناعي (#131)' : 'AdsRanger Studio & AI Copy (#131)' }}</h3>
                <p>{{ $isAr ? 'ولد نصوصاً إعلانية جذابة باللهجة الخليجية، الشامية، والمصرية خلال ثوانٍ، مع فحص سياسات Meta وGoogle وإدارة مجموعات الأصول.' : 'Generate high-converting ad copy in Gulf, Levantine, Egyptian & MSA dialects with built-in policy pre-checks.' }}</p>
            </div>
            <a href="{{ $isAr ? '/pricing' : '/en/pricing' }}" class="card-link">{{ $isAr ? 'استكشف الباقات ←' : 'Explore Tiers →' }}</a>
        </div>

        <div class="card">
            <div>
                <h3>{{ $isAr ? 'اسأل المنصة والمراجعة الأسبوعية (#69 / #99)' : 'Ask the Platform & AI Review (#69 / #99)' }}</h3>
                <p>{{ $isAr ? 'محتوى تحليلي فوري باللغتين العربية والإنجليزية للإجابة عن تساؤلات الميزانية والعائد، مع أهم 3 توصيات أسبوعية لكل عميل.' : 'Instant Arabic & English natural-language Q&A on blended ROAS, CPA spikes, and top weekly client actions.' }}</p>
            </div>
            <a href="{{ $isAr ? '/demo-report' : '/en/demo-report' }}" class="card-link">{{ $isAr ? 'شاهد التقرير الحي ←' : 'View Live Demo →' }}</a>
        </div>

        <div class="card">
            <div>
                <h3>{{ $isAr ? 'تهيئة العملاء وتغذية البيانات (#105 / #77)' : 'Client Onboarding & Sheets Feed (#105 / #77)' }}</h3>
                <p>{{ $isAr ? 'أرسل رابط تهيئة آمن للعملاء لربط حساباتهم الإعلانية، وصدر البيانات تلقائياً إلى Looker Studio أو Google Sheets.' : 'Secure client onboarding links for read-only OAuth and automated JSON data feeds for Looker Studio & Sheets.' }}</p>
            </div>
            <a href="{{ $isAr ? '/integrations' : '/en/integrations' }}" class="card-link">{{ $isAr ? 'استعراض التكاملات ←' : 'Browse Integrations →' }}</a>
        </div>
    </div>
</section>

@include('public.footer', ['lang' => $lang])
</body>
</html>
