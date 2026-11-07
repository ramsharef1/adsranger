<!DOCTYPE html>
<html lang="{{ $lang === 'ar' ? 'ar' : 'en' }}" dir="{{ $lang === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $lang === 'ar' ? 'حاسبة العائد والإنفاق الإعلاني في الشرق الأوسط — AdsRanger' : 'MENA Ad Spend & ROAS Growth Calculator — AdsRanger' }}</title>
    <style>
        body { margin:0; font-family:'IBM Plex Sans Arabic', -apple-system, sans-serif; background:#0B1120; color:#F8FAFC; line-height:1.65; }
        .wrap { max-width:860px; margin:50px auto; padding:0 20px; }
        .card { background:#111827; border:1px solid #1E293B; border-radius:18px; padding:36px; }
        .badge { display:inline-block; padding:5px 14px; border-radius:999px; background:rgba(20,184,166,0.14); color:#2DD4BF; font-size:0.84rem; font-weight:700; margin-bottom:14px; }
        h1 { font-size:2.2rem; margin:0 0 10px; }
        label { display:block; font-weight:600; margin-top:16px; margin-bottom:6px; font-size:0.95rem; }
        select, input { width:100%; box-sizing:border-box; padding:12px; border-radius:10px; border:1px solid #334155; background:#0F172A; color:#F8FAFC; font:inherit; }
        .result-box { margin-top:28px; background:rgba(20,184,166,0.1); border:1px solid rgba(20,184,166,0.3); border-radius:12px; padding:24px; text-align:center; }
        .res-num { font-size:2.4rem; font-weight:700; color:#2DD4BF; margin:6px 0; }
        .currency-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    </style>
</head>
<body>
@include('public.nav', ['lang' => $lang])
<div class="wrap">
    <div class="card" x-data="{ spend: 50000, currency: 'SAR', vertical: 'ecommerce' }">
        <span class="badge">{{ $lang === 'ar' ? 'حاسبة الأداء الإقليمي المتقدمة' : 'Advanced MENA Performance Estimator' }}</span>
        <h1>{{ $lang === 'ar' ? 'احسب العائد الإضافي وتوفير الهدر الإعلاني' : 'Calculate Your Agency Client Revenue Uplift & Waste Reduction' }}</h1>
        <p style="color:#94A3B8; margin-bottom:24px;">{{ $lang === 'ar' ? 'أدخل ميزانية الإنفاق الشهرية والعملة لتقدير العائد باستخدام محرك العائد المدمج (MER).' : 'Input your monthly ad spend and currency to estimate performance gains using cross-channel attribution.' }}</p>

        <div class="currency-row">
            <div>
                <label>{{ $lang === 'ar' ? 'العملة الإقليمية' : 'Currency' }}</label>
                <select x-model="currency">
                    <option value="SAR">SAR (ريال سعودي)</option>
                    <option value="AED">AED (درهم إماراتي)</option>
                    <option value="USD">USD (دولار أمريكي)</option>
                    <option value="KWD">KWD (دينار كويتي)</option>
                </select>
            </div>
            <div>
                <label>{{ $lang === 'ar' ? 'مجال النشاط' : 'Vertical' }}</label>
                <select x-model="vertical">
                    <option value="ecommerce">{{ $lang === 'ar' ? 'تجارة إلكترونية' : 'E-commerce' }}</option>
                    <option value="realestate">{{ $lang === 'ar' ? 'عقارات' : 'Real Estate' }}</option>
                    <option value="leads">{{ $lang === 'ar' ? 'توليد العملاء' : 'Lead Gen' }}</option>
                </select>
            </div>
        </div>

        <label>{{ $lang === 'ar' ? 'إجمالي الإنفاق الشهري' : 'Monthly Ad Spend' }}</label>
        <input type="number" x-model.number="spend" step="5000" min="5000" max="5000000">

        <div class="result-box">
            <div style="font-size:0.9rem; color:#94A3B8;">{{ $lang === 'ar' ? 'العائد الإضافي المتوقع شهرياً' : 'Estimated Monthly Revenue Uplift' }}</div>
            <div class="res-num" x-text="'+ ' + currency + ' ' + Math.round(spend * 0.22).toLocaleString()">+ SAR 11,000</div>
            <div style="font-size:0.88rem; color:#A7F3D0; margin-top:6px;">{{ $lang === 'ar' ? 'متوسط تقليل الهدر الإعلاني: 18% — تحسين العائد المدمج (MER)' : 'Average ad waste reduction: 18% — Blended MER optimization' }}</div>
            <a href="{{ $lang === 'ar' ? '/waitlist' : '/en/waitlist' }}" style="display:inline-block; margin-top:18px; background:#0F766E; color:#fff; padding:10px 22px; border-radius:10px; font-weight:700; text-decoration:none;">{{ $lang === 'ar' ? 'احصل على وصول مبكر لوكالتك' : 'Claim Agency Early Access' }}</a>
        </div>
    </div>
</div>
@include('public.footer', ['lang' => $lang])
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
