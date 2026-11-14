@php
    use App\Support\ResultResolver;
    $snap = (array) ($deck->audit_snapshot ?? []);
    $tiers = (array) ($deck->tier_quotes ?? []);
    $showAr = in_array($lang, ['ar', 'both'], true);
    $showEn = in_array($lang, ['en', 'both'], true);
    $agencyName = (string) ($agency->name ?: 'Agency');
@endphp
<!doctype html>
<html lang="{{ $lang === 'en' ? 'en' : 'ar' }}" dir="{{ $lang === 'en' ? 'ltr' : 'rtl' }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: dejavusans, sans-serif; color: #0f172a; font-size: 11pt; line-height: 1.55; }
        .hdr { background: #0f172a; color: #f8fafc; padding: 14px 16px; border-radius: 6px; margin-bottom: 14px; }
        .sub { color: #14b8a6; font-size: 9.5pt; font-weight: bold; }
        h2 { color: #0f172a; font-size: 13pt; border-bottom: 2px solid #0d9488; padding-bottom: 4px; margin-top: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #cbd5e1; padding: 7px 9px; font-size: 10pt; vertical-align: top; }
        th { background: #f1f5f9; font-weight: bold; }
        .tier-box { border: 1px solid #0d9488; padding: 10px 12px; margin-bottom: 10px; border-radius: 6px; }
        .fee { color: #0d9488; font-weight: bold; font-size: 12pt; }
    </style>
</head>
<body>
    <div class="hdr">
        <div class="sub">{{ $agencyName }}</div>
        <div style="font-size:15pt;font-weight:bold;margin-top:4px">{{ $deck->prospect_name }}</div>
        <div style="font-size:9.5pt;margin-top:2px" dir="ltr">{{ $deck->prospect_url }}</div>
    </div>

    <h2>
        @if ($showAr) الفحص الفني السريع وجاهزية التتبع @endif
        @if ($showAr && $showEn) / @endif
        @if ($showEn) Technical, Speed, Pixel & Answer Readiness @endif
    </h2>
    <table>
        <tbody>
            <tr>
                <th>@if ($showAr) حالة الاستجابة @endif @if ($showAr && $showEn) / @endif @if ($showEn) HTTP Status @endif</th>
                <td dir="ltr">{{ $snap['http_status'] ?? '—' }} ({{ (int) ($snap['redirect_hops'] ?? 0) }} hops)</td>
            </tr>
            <tr>
                <th>@if ($showAr) وسوم التتبع المرصودة @endif @if ($showAr && $showEn) / @endif @if ($showEn) Detected Pixels @endif</th>
                <td dir="ltr">{{ ! empty($snap['detected_pixels']) ? implode(', ', $snap['detected_pixels']) : 'none' }}</td>
            </tr>
            <tr>
                <th>@if ($showAr) وسوم التتبع الناقصة @endif @if ($showAr && $showEn) / @endif @if ($showEn) Missing Pixels @endif</th>
                <td dir="ltr">{{ ! empty($snap['missing_pixels']) ? implode(', ', $snap['missing_pixels']) : 'none' }}</td>
            </tr>
            <tr>
                <th>@if ($showAr) ملفات معطّلة للعرض @endif @if ($showAr && $showEn) / @endif @if ($showEn) Render-Blocking Scripts @endif</th>
                <td dir="ltr">{{ (int) ($snap['render_blocking_scripts'] ?? 0) }}</td>
            </tr>
            <tr>
                <th>@if ($showAr) البيانات المنظمة وملف llms.txt @endif @if ($showAr && $showEn) / @endif @if ($showEn) JSON-LD & /llms.txt @endif</th>
                <td dir="ltr">JSON-LD: {{ ! empty($snap['has_json_ld']) ? 'Yes' : 'No' }} | llms.txt: {{ ! empty($snap['has_llms_txt']) ? 'Present' : 'Missing' }}</td>
            </tr>
        </tbody>
    </table>

    <h2>
        @if ($showAr) خيارات الشراكة الشهرية (3 باقات) @endif
        @if ($showAr && $showEn) / @endif
        @if ($showEn) 3-Tier Monthly Retainer Proposal @endif
    </h2>
    @foreach ($tiers as $tier)
        <div class="tier-box">
            <div style="font-weight:bold;font-size:11.5pt">
                @if ($showAr) {{ $tier['label_ar'] ?? '' }} @endif
                @if ($showAr && $showEn) — @endif
                @if ($showEn) {{ $tier['label_en'] ?? '' }} @endif
            </div>
            <div class="fee" dir="ltr">{{ ResultResolver::money((float) ($tier['monthly_fee'] ?? 0), $deck->currency) }} / mo</div>
            @if ($showAr && ! empty($tier['deliverables_ar']))
                <ul>
                    @foreach ($tier['deliverables_ar'] as $itemAr)
                        <li>{{ $itemAr }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($showEn && ! empty($tier['deliverables_en']))
                <ul dir="ltr">
                    @foreach ($tier['deliverables_en'] as $itemEn)
                        <li>{{ $itemEn }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endforeach
</body>
</html>
