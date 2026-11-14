@php
    use App\Support\ResultResolver;
    $snap = (array) ($deck->audit_snapshot ?? []);
    $tiers = (array) ($deck->tier_quotes ?? []);
    $isRtl = $lang !== 'en';
    $showAr = in_array($lang, ['ar', 'both'], true);
    $showEn = in_array($lang, ['en', 'both'], true);
    $agencyName = (string) ($agency->name ?: 'Agency');
@endphp
<!doctype html>
<html lang="{{ $isRtl ? 'ar' : 'en' }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $deck->prospect_name }} — {{ $agencyName }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Tahoma, sans-serif;
            background: #0f172a;
            color: #f8fafc;
            line-height: 1.65;
        }
        .wrap { max-width: 960px; margin: 0 auto; padding: 24px 16px 56px; display: grid; gap: 20px; }
        .card { background: #1e293b; border: 1px solid rgba(148,163,184,.24); border-radius: 14px; padding: 18px; }
        .hdr { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; }
        .badge { display: inline-flex; align-items: center; min-height: 30px; padding: 2px 10px; border-radius: 999px; background: rgba(20,184,166,.16); color: #14b8a6; font-size: 12.5px; font-weight: 600; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 16px; border-radius: 10px; background: #0d9488; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 13.5px; }
        .grid-checks { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 12px; }
        .kv { background: #0f172a; border: 1px solid rgba(148,163,184,.2); border-radius: 10px; padding: 10px 12px; font-size: 13px; display: flex; justify-content: space-between; gap: 8px; }
        .grid-tiers { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 14px; margin-top: 12px; }
        .tier { background: #0f172a; border: 1px solid rgba(20,184,166,.35); border-radius: 12px; padding: 16px; display: grid; gap: 10px; align-content: start; }
        .price { font-size: 20px; font-weight: 700; color: #14b8a6; }
        ul { margin: 0; padding-inline-start: 18px; font-size: 13px; display: grid; gap: 6px; }
    </style>
</head>
<body>
    <main class="wrap">
        <header class="card hdr">
            <div>
                <span class="badge">{{ $agencyName }}</span>
                <h1 style="margin:8px 0 4px;font-size:22px"><bdi>{{ $deck->prospect_name }}</bdi></h1>
                <div style="font-size:13px;opacity:.8"><bdi dir="ltr">{{ $deck->prospect_url }}</bdi></div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a class="btn" href="{{ url('/pitch/'.$deck->share_token.'/pdf?lang='.$lang) }}">
                    @if ($showAr && $showEn)
                        تحميل العرض PDF · <bdi dir="ltr">Download PDF</bdi>
                    @elseif ($showAr)
                        تحميل العرض <bdi dir="ltr">PDF</bdi>
                    @else
                        Download Proposal PDF
                    @endif
                </a>
            </div>
        </header>

        <section class="card">
            <h2 style="margin:0;font-size:17px">
                @if ($showAr) الفحص الفني السريع وجاهزية التتبع @endif
                @if ($showAr && $showEn) · @endif
                @if ($showEn) <bdi dir="ltr">Technical, Speed, Pixel & Answer Readiness Snapshot</bdi> @endif
            </h2>
            <div class="grid-checks">
                <div class="kv">
                    <span>@if ($showAr) حالة الاستجابة @else HTTP Status @endif</span>
                    <strong dir="ltr">{{ $snap['http_status'] ?? '—' }} ({{ (int) ($snap['redirect_hops'] ?? 0) }} hops)</strong>
                </div>
                <div class="kv">
                    <span>@if ($showAr) وسوم التتبع المرصودة @else Detected Pixels @endif</span>
                    <strong dir="ltr">{{ ! empty($snap['detected_pixels']) ? implode(', ', $snap['detected_pixels']) : 'none' }}</strong>
                </div>
                <div class="kv">
                    <span>@if ($showAr) وسوم التتبع الناقصة @else Missing Pixels @endif</span>
                    <strong dir="ltr">{{ ! empty($snap['missing_pixels']) ? implode(', ', $snap['missing_pixels']) : 'none' }}</strong>
                </div>
                <div class="kv">
                    <span>@if ($showAr) ملفات معطّلة للعرض @else Render-Blocking Scripts @endif</span>
                    <strong dir="ltr">{{ (int) ($snap['render_blocking_scripts'] ?? 0) }}</strong>
                </div>
                <div class="kv">
                    <span>@if ($showAr) البيانات المنظمة <bdi dir="ltr">JSON-LD</bdi> @else Structured Data (JSON-LD) @endif</span>
                    <strong dir="ltr">{{ ! empty($snap['has_json_ld']) ? 'Yes' : 'No' }}</strong>
                </div>
                <div class="kv">
                    <span>@if ($showAr) ملف <bdi dir="ltr">/llms.txt</bdi> @else /llms.txt Readiness @endif</span>
                    <strong dir="ltr">{{ ! empty($snap['has_llms_txt']) ? 'Present' : 'Missing' }}</strong>
                </div>
            </div>
        </section>

        <section class="card">
            <h2 style="margin:0;font-size:17px">
                @if ($showAr) خيارات الشراكة الشهرية المقترحة @endif
                @if ($showAr && $showEn) · @endif
                @if ($showEn) <bdi dir="ltr">Proposed Monthly Retainer Options</bdi> @endif
            </h2>
            <div class="grid-tiers">
                @foreach ($tiers as $tier)
                    <article class="tier">
                        <div>
                            @if ($showAr)
                                <div style="font-weight:700;font-size:15px">{{ $tier['label_ar'] ?? '' }}</div>
                            @endif
                            @if ($showEn)
                                <div style="font-weight:600;font-size:13.5px;opacity:.85"><bdi dir="ltr">{{ $tier['label_en'] ?? '' }}</bdi></div>
                            @endif
                        </div>
                        <div class="price" dir="ltr">
                            {{ ResultResolver::money((float) ($tier['monthly_fee'] ?? 0), $deck->currency) }} / mo
                        </div>
                        @if ($showAr && ! empty($tier['deliverables_ar']))
                            <ul>
                                @foreach ($tier['deliverables_ar'] as $lineAr)
                                    <li>{{ $lineAr }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @if ($showEn && ! empty($tier['deliverables_en']))
                            <ul dir="ltr">
                                @foreach ($tier['deliverables_en'] as $lineEn)
                                    <li>{{ $lineEn }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    </main>
</body>
</html>
