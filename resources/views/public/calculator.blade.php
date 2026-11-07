<!DOCTYPE html>
<html lang="{{ $lang === 'ar' ? 'ar' : 'en' }}" dir="{{ $lang === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $lang === 'ar' ? 'حاسبة العائد والإنفاق الإعلاني في الشرق الأوسط — AdsRanger' : 'MENA Ad Spend & ROAS Growth Calculator — AdsRanger' }}</title>
    <style>
        body { margin:0; font-family:'IBM Plex Sans Arabic', -apple-system, sans-serif; background:#0B1120; color:#F8FAFC; line-height:1.65; }
        .wrap { max-width:820px; margin:50px auto; padding:0 20px; }
        .card { background:#111827; border:1px solid #1E293B; border-radius:18px; padding:36px; }
        .badge { display:inline-block; padding:5px 14px; border-radius:999px; background:rgba(20,184,166,0.14); color:#2DD4BF; font-size:0.84rem; font-weight:700; margin-bottom:14px; }
        h1 { font-size:2.2rem; margin:0 0 10px; }
        label { display:block; font-weight:600; margin-top:16px; margin-bottom:6px; font-size:0.95rem; }
        select, input { width:100%; box-sizing:border-box; padding:12px; border-radius:10px; border:1px solid #334155; background:#0F172A; color:#F8FAFC; font:inherit; }
        .result-box { margin-top:28px; background:rgba(20,184,166,0.1); border:1px solid rgba(20,184,166,0.3); border-radius:12px; padding:22px; text-align:center; }
        .res-num { font-size:2.2rem; font-weight:700; color:#2DD4BF; margin:6px 0; }
    </style>
</head>
<body>
@include('public.nav', ['lang' => $lang])
<div class="wrap">
    <div class="card">
        <span class="badge">{{ $lang === 'ar' ? 'حاسبة الأداء الإقليمي' : 'MENA Performance Estimator' }}</span>
        <h1>{{ $lang === 'ar' ? 'احسب العائد المتوقع وحجم توفير الهدر الإعلاني' : 'Calculate Your Agency Client Revenue Uplift & Waste Reduction' }}</h1>
        <p style="color:#94A3B8; margin-bottom:24px;">{{ $lang === 'ar' ? 'أدخل ميزانية الإنفاق الشهرية الحالية لتتعرف على العائد المتوقع عند استخدام خوارزميات AdsRanger المدمجة.' : 'Input your monthly ad spend to estimate performance gains using cross-channel attribution and MER.' }}</p>

        <form x-data="{ spend: 50000, vertical: 'ecommerce' }">
            <label>{{ $lang === 'ar' ? 'مجال النشاط التجاري' : 'Business Vertical' }}</label>
            <select x-model="vertical">
                <option value="ecommerce">{{ $lang === 'ar' ? 'تجارة إلكترونية (E-commerce)' : 'E-commerce & Retail' }}</option>
                <option value="realestate">{{ $lang === 'ar' ? 'عقارات (Real Estate)' : 'Real Estate & Property' }}</option>
                <option value="leads">{{ $lang === 'ar' ? 'توليد العملاء المهتمين (Lead Gen)' : 'B2B & Lead Generation' }}</option>
            </select>

            <label>{{ $lang === 'ar' ? 'إجمالي الإنفاق الشهري (دولار أمريكي / ريال / درهم)' : 'Monthly Ad Spend (USD equivalent)' }}</label>
            <input type="number" x-model.number="spend" step="5000" min="5000" max="1000000">

            <div class="result-box">
                <div style="font-size:0.9rem; color:#94A3B8;">{{ $lang === 'ar' ? 'العائد الإضافي المتوقع شهرياً (Estimated Monthly Uplift)' : 'Estimated Monthly Revenue Uplift' }}</div>
                <div class="res-num" x-text="'+$' + Math.round(spend * 0.22).toLocaleString()">+$11,000</div>
                <div style="font-size:0.88rem; color:#A7F3D0;">{{ $lang === 'ar' ? 'متوسط تقليل الهدر الإعلاني: 18% — تحسين العائد المدمج (MER)' : 'Average ad waste reduction: 18% — Blended MER optimization' }}</div>
            </div>
        </form>
    </div>
</div>
@include('public.footer', ['lang' => $lang])
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
