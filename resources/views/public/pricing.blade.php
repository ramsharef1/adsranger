<!DOCTYPE html>
<html lang="{{ $lang === 'ar' ? 'ar' : 'en' }}" dir="{{ $lang === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $lang === 'ar' ? 'AdsRanger — الباقات ومزايا الوكالات' : 'AdsRanger — Agency Plans & Capabilities' }}</title>
    <link rel="canonical" href="{{ url($lang === 'ar' ? '/pricing' : '/en/pricing') }}">
    <style>
        body { margin:0; font-family:'IBM Plex Sans Arabic', -apple-system, BlinkMacSystemFont, sans-serif; background:#0B1120; color:#F8FAFC; line-height:1.65; }
        .hero { max-width:1080px; margin:0 auto; padding:56px 24px 28px; text-align:center; }
        .badge { display:inline-block; padding:5px 14px; border-radius:999px; background:rgba(20,184,166,0.14); color:#5EEAD4; font-size:0.84rem; font-weight:700; margin-bottom:14px; }
        h1 { font-size:2.35rem; margin:0 0 12px; letter-spacing:-0.02em; }
        .sub { color:#94A3B8; max-width:700px; margin:0 auto; font-size:1.05rem; }
        .grid { max-width:1180px; margin:36px auto 64px; padding:0 24px; display:grid; grid-template-columns:repeat(auto-fit, minmax(310px, 1fr)); gap:22px; }
        .tier { background:#111827; border:1px solid #1E293B; border-radius:18px; padding:30px 26px; display:flex; flex-direction:column; justify-content:space-between; }
        .tier.featured { border-color:#14B8A6; box-shadow:0 16px 40px -15px rgba(20,184,166,0.28); position:relative; }
        .tier h2 { margin:0 0 6px; font-size:1.4rem; }
        .tier-tag { font-size:1.05rem; font-weight:700; color:#5EEAD4; margin:12px 0 4px; }
        .tier ul { list-style:none; padding:0; margin:20px 0 28px; color:#CBD5E1; font-size:0.93rem; }
        .tier li { padding:7px 0; border-bottom:1px solid rgba(148,163,184,0.12); }
        .cta { display:block; text-align:center; padding:12px 18px; border-radius:10px; font-weight:700; text-decoration:none; background:#0F766E; color:#fff; }
        .tier.featured .cta { background:#14B8A6; color:#0B1120; }
        .faq { max-width:920px; margin:0 auto 72px; padding:0 24px; }
        .faq-item { background:#111827; border:1px solid #1E293B; border-radius:14px; padding:20px 24px; margin-bottom:14px; }
    </style>
</head>
<body>
@include('public.nav', ['lang' => $lang])

<section class="hero">
    <span class="badge">{{ $lang === 'ar' ? 'ثلاث باقات مصممة لوكالات التسويق في المنطقة' : '3 Tiers Built for MENA Marketing Agencies' }}</span>
    <h1>{{ $lang === 'ar' ? 'الباقات ومزايا المنصة حسب حجم وكالتك' : 'Plans Structured Around Your Agency Growth' }}</h1>
    <p class="sub">
        {{ $lang === 'ar'
            ? 'تشمل جميع الباقات فترة تجربة مجانية لمدة 14 يوماً على مزايا الباقة الاحترافية، مع عرض تفاصيل الأسعار والفوترة داخل إعدادات مساحة العمل بعد التفعيل.'
            : 'Every workspace starts with a 14-day Pro trial. Exact billing and seat options are managed directly inside Settings › Plan & usage after onboarding.' }}
    </p>
</section>

<p style="text-align:center;color:#94A3B8;font-size:0.9rem">{{ $lang === 'ar' ? '✓ قيد التطوير · ○ مخطط له. المنصة في مرحلة تجريبية خاصة والإطلاق في 2027.' : '✓ in development · ○ planned. AdsRanger is in private beta, launching 2027.' }}</p>
<section class="grid">
    <div class="tier">
        <div>
            <h2>{{ $lang === 'ar' ? 'الباقة الأساسية' : 'Starter' }}</h2>
            <p style="color:#94A3B8; margin:0; font-size:0.9rem;">{{ $lang === 'ar' ? 'للوكالات الناشئة وفرق إدارة الأداء المستقلة' : 'For boutique agencies & performance teams' }}</p>
            <div class="tier-tag">{{ $lang === 'ar' ? 'أساسيات الربط والتقارير اليومية' : 'Core Connectors & Daily Reporting' }}</div>
            <ul>
                <li>✓ {{ $lang === 'ar' ? 'لوحة المتابعة اليومية + نقاط الصحة والأهداف' : 'Daily Desk + Health Score & Targets' }}</li>
                <li>✓ {{ $lang === 'ar' ? 'ربط Meta وGoogle Ads وسجل عملات 13 شهراً' : 'Meta & Google Ads + 13-Month Backfill & FX' }}</li>
                <li>✓ {{ $lang === 'ar' ? '7 قواعد تنبيه أساسية + ملخص الصباح البريدي' : '7 Core Alert Rules + Morning Email Digest' }}</li>
                <li>✓ {{ $lang === 'ar' ? 'تقارير شهرية PDF (عربي/إنجليزي) وروابط مشاركة موقعة' : 'Bilingual Monthly PDF Reports & Signed Links' }}</li>
                <li>✓ {{ $lang === 'ar' ? 'أداة بناء UTM وفحص الروابط + حاسبة الأهداف' : 'UTM Builder, Link Checker & Goal Calculator' }}</li>
            </ul>
        </div>
        <a href="{{ $lang === 'ar' ? '/waitlist' : '/en/waitlist' }}" class="cta">{{ $lang === 'ar' ? 'اطلب دعوة للباقة الأساسية' : 'Request Starter Access' }}</a>
    </div>

    <div class="tier featured">
        <div>
            <span class="badge" style="margin-bottom:8px;">{{ $lang === 'ar' ? 'تشملها التجربة المجانية' : 'Included in the 14-day trial' }}</span>
            <h2>{{ $lang === 'ar' ? 'الباقة الاحترافية' : 'Pro' }}</h2>
            <p style="color:#94A3B8; margin:0; font-size:0.9rem;">{{ $lang === 'ar' ? 'للوكالات المتنامية التي تدير قنوات وميزانيات متعددة' : 'For growing agencies managing multi-channel budgets' }}</p>
            <div class="tier-tag">{{ $lang === 'ar' ? 'ذكاء القنوات والتنبيهات المتقدمة' : 'Full-Funnel Intelligence & Pro Alerts' }}</div>
            <ul>
                <li>✓ {{ $lang === 'ar' ? 'جميع مزايا الباقة الأساسية + 18 قاعدة تنبيه احترافية' : 'Everything in Starter + All 18 Pro Alert Rules' }}</li>
                <li>✓ {{ $lang === 'ar' ? 'تنبيهات الهاتف الحرجة (PWA Push) مع ساعات الهدوء' : 'Critical Phone Push Alerts with Quiet Hours' }}</li>
                <li>✓ {{ $lang === 'ar' ? 'توقع نهاية الشهر + مخطط سيناريوهات رمضان والعيد' : 'Month-End Forecast + Ramadan/Eid Scenario Planner' }}</li>
                <li>✓ {{ $lang === 'ar' ? 'تفاصيل Google Ads + فحص صفحات الهبوط ومراقبة المنافسين' : 'Google Ads Depth, Landing Page Health & Competitor Watch' }}</li>
                <li>○ {{ $lang === 'ar' ? 'مخطط له: جدولة المنشورات العضوية وصندوق الوارد الموحد' : 'Planned: organic post scheduler and unified inbox' }}</li>
            </ul>
        </div>
        <a href="{{ $lang === 'ar' ? '/waitlist' : '/en/waitlist' }}" class="cta">{{ $lang === 'ar' ? 'اطلب دعوة للباقة الاحترافية' : 'Request Pro Trial Invite' }}</a>
    </div>

    <div class="tier">
        <div>
            <h2>{{ $lang === 'ar' ? 'باقة الوكالات' : 'Agency' }}</h2>
            <p style="color:#94A3B8; margin:0; font-size:0.9rem;">{{ $lang === 'ar' ? 'للوكالات الكبرى متعددة الفرق والمنصات' : 'For large agencies with many teams and networks' }}</p>
            <div class="tier-tag">{{ $lang === 'ar' ? 'كل المنصات والتخطيط السنوي' : 'All networks and annual planning' }}</div>
            <ul>
                <li>✓ {{ $lang === 'ar' ? 'جميع مزايا الباقة الاحترافية + منصات المرحلة الثانية (قيد الربط)' : 'Everything in Pro + stage 2 ad networks (in development)' }}</li>
                <li>○ {{ $lang === 'ar' ? 'مخطط له: نطاق مخصص وألوان وكالتك على تقارير العملاء' : 'Planned: custom domain and your colours on client reports' }}</li>
                <li>○ {{ $lang === 'ar' ? 'مخطط له: عمليات الوكالة والمالية وحملات المؤثرين' : 'Planned: agency operations, finance and influencer campaigns' }}</li>
                <li>✓ {{ $lang === 'ar' ? 'خطة الميزانية السنوية مقابل الفعلي' : 'Annual budget plan vs actual' }}</li>
                <li>○ {{ $lang === 'ar' ? 'مخطط له: Webhooks وتغذية Looker Studio وSheets' : 'Planned: webhooks and a Looker Studio / Sheets feed' }}</li>
            </ul>
        </div>
        <a href="{{ $lang === 'ar' ? '/waitlist' : '/en/waitlist' }}" class="cta">{{ $lang === 'ar' ? 'اطلب دعوة لباقة الوكالات' : 'Request Agency Tier Access' }}</a>
    </div>
</section>

@include('public.footer', ['lang' => $lang])
</body>
</html>
