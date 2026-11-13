@php
    $ar = ($brief->agency?->language ?? 'ar') === 'ar';
    $accent = $kit?->primary_color ?: '#2DD4BF';
@endphp
<!doctype html>
<html lang="{{ $ar ? 'ar' : 'en' }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title>{{ $brief->title }} · {{ $brief->client?->name }} · AdsRanger Studio</title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#0b1020;--card:#121933;--ink:#eef1fb;--muted:#9aa3c0;--acc:{{ $accent }};--line:#232c4d}
*{box-sizing:border-box}body{margin:0;font-family:"IBM Plex Sans Arabic",system-ui,sans-serif;background:var(--bg);color:var(--ink);line-height:1.7}
.w{max-width:780px;margin:0 auto;padding:28px 20px}
.card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:24px;margin-bottom:20px}
.badge{display:inline-block;padding:4px 10px;border-radius:99px;font-size:12px;border:1px solid var(--line);color:var(--acc)}
.btn{padding:10px 18px;border-radius:10px;border:0;font-weight:600;cursor:pointer;font-family:inherit;font-size:14px}
.btn-ok{background:#2DD4BF;color:#0b1020}
.btn-chg{background:transparent;border:1px solid #f59e0b;color:#fbbf24}
textarea{width:100%;padding:10px 12px;border-radius:10px;border:1px solid var(--line);background:#0b1020;color:var(--ink);font-family:inherit;margin:10px 0}
</style>
</head>
<body>
<div class="w">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px">
        <div>
            <div style="font-size:13px;color:var(--muted)">{{ $brief->client?->name }} · v{{ $brief->version }}</div>
            <h1 style="margin:2px 0 0;font-size:24px">{{ $brief->title }}</h1>
        </div>
        <span class="badge">{{ strtoupper($brief->status) }}</span>
    </div>

    @if (session('decision_saved'))
        <div class="card" style="border-color:#2DD4BF">
            {{ $ar ? 'تم حفظ ردّك بنجاح وإبلاغ فريق الوكالة.' : 'Your response has been saved and shared with the agency team.' }}
        </div>
    @endif

    <div class="card">
        <div style="font-size:12px;color:var(--muted);margin-bottom:8px">
            {{ $ar ? 'معاينة الإعلان' : 'Ad Preview' }} ({{ $brief->placement }} · {{ $brief->dialect }})
        </div>
        @if ($brief->hook)
            <div style="font-weight:700;color:var(--acc);margin-bottom:8px">{{ $brief->hook }}</div>
        @endif
        <p style="margin:0 0 14px;white-space:pre-line">{{ $brief->primary_text }}</p>
        <div style="display:flex;justify-content:space-between;align-items:center;padding-top:12px;border-top:1px solid var(--line);flex-wrap:wrap;gap:10px">
            <strong>{{ $brief->headline }}</strong>
            <span style="background:var(--acc);color:#0b1020;padding:6px 14px;border-radius:8px;font-weight:600;font-size:13px">{{ $brief->cta }}</span>
        </div>
        {{-- XS-24 / NB-36 (D-305): licence lines the ad must carry. --}}
        @php
            $lics = $brief->client ? \App\Models\ClientLicence::where('client_id', $brief->client_id)->get() : collect();
        @endphp
        @if ($lics->isNotEmpty())
            <div style="margin-top:12px;font-size:13px;color:var(--muted)" data-brief-licences>
                @foreach ($lics as $l)<div>{{ $ar ? 'رقم الترخيص' : 'Licence no.' }}: <bdi dir="ltr">{{ $l->number }}</bdi></div>@endforeach
            </div>
        @endif
    </div>

    <div class="card">
        <h2 style="margin:0 0 8px;font-size:17px">{{ $ar ? 'اعتماد العميل' : 'Client Approval' }}</h2>
        <form method="POST" action="/studio/brief/{{ $brief->share_token }}">
            @csrf
            <label for="cf-notes" style="font-size:13px;color:var(--muted)">{{ $ar ? 'ملاحظات أو تعديلات مطلوبة (اختياري)' : 'Feedback or requested edits (optional)' }}</label>
            <textarea id="cf-notes" name="client_feedback" rows="3" maxlength="1000">{{ old('client_feedback', $brief->client_feedback) }}</textarea>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px">
                <button type="submit" name="decision" value="approved" class="btn btn-ok">{{ $ar ? 'اعتماد الإعلان ✓' : 'Approve creative ✓' }}</button>
                <button type="submit" name="decision" value="changes_requested" class="btn btn-chg">{{ $ar ? 'طلب تعديلات' : 'Request changes' }}</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
