<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $link->prospect_name }} — {{ $agency->name ?? 'AdsRanger' }} Onboarding</title>
    <style>
        body { margin:0; font-family:'IBM Plex Sans Arabic', -apple-system, BlinkMacSystemFont, sans-serif; background:#0B1120; color:#F8FAFC; line-height:1.65; }
        .wrap { max-width:760px; margin:40px auto; padding:0 20px; }
        .card { background:#111827; border:1px solid #1E293B; border-radius:16px; padding:28px; margin-bottom:20px; }
        .badge { display:inline-block; padding:4px 12px; border-radius:999px; background:rgba(20,184,166,0.14); color:#2DD4BF; font-size:0.82rem; font-weight:700; }
        label { display:block; font-weight:600; margin-top:14px; margin-bottom:6px; font-size:0.92rem; }
        input[type="url"], textarea { width:100%; box-sizing:border-box; padding:11px 12px; border-radius:10px; border:1px solid #334155; background:#0F172A; color:#F8FAFC; font:inherit; }
        .net-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:10px; margin-top:8px; }
        .net-item { display:flex; align-items:center; gap:8px; padding:10px 12px; border-radius:10px; border:1px solid #1E293B; background:#0F172A; font-weight:500; }
        .btn { margin-top:18px; background:#0F766E; color:#fff; border:0; border-radius:10px; padding:12px 22px; font-weight:700; cursor:pointer; font-size:0.95rem; }
        .alert-ok { padding:14px 16px; border-radius:12px; background:rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.45); color:#A7F3D0; margin-bottom:16px; font-weight:600; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <span class="badge">{{ $agency->name ?? 'AdsRanger' }} · {{ __('Client Onboarding & Access Checklist') }}</span>
        <h1 style="margin:12px 0 6px; font-size:1.65rem;">{{ $link->prospect_name }}</h1>
        <p style="color:#94A3B8; margin:0 0 18px;">
            {{ __('Tell your agency which ad accounts it can connect, and share your brand notes, in one step.') }}
        </p>

        @if (session('onboarding_completed') || $link->status === 'completed')
            <div class="alert-ok" data-onboarding-completed>
                {{ __('Thank you! Your onboarding checklist and ad network permissions have been recorded.') }}
            </div>
        @endif

        <form method="POST" action="{{ route('client.onboard.submit', ['token' => $link->token]) }}">
            @csrf
            <label for="website_url">{{ __('Primary Website or Store URL') }}</label>
            <input id="website_url" type="url" name="website_url" value="{{ old('website_url', $link->website_url) }}" placeholder="https://example.com">

            <label>{{ __('Ad accounts you are ready to connect') }}</label>
            @php
                $requested = $link->requested_networks ?: ['meta', 'google_ads', 'ga4', 'tiktok', 'snapchat'];
                $granted = $link->granted_networks ?: [];
                $labels = [
                    'meta' => 'Meta Ads (FB / IG)',
                    'google_ads' => 'Google Ads (Search / PMax)',
                    'ga4' => 'Google Analytics 4 (GA4)',
                    'tiktok' => 'TikTok Business',
                    'snapchat' => 'Snapchat Ads',
                    'linkedin' => 'LinkedIn Campaign Manager',
                ];
            @endphp
            <div class="net-grid">
                @foreach ($requested as $netKey)
                    <label class="net-item">
                        <input type="checkbox" name="granted_networks[]" value="{{ $netKey }}" @checked(in_array($netKey, $granted, true))>
                        <span><bdi dir="ltr">{{ $labels[$netKey] ?? strtoupper($netKey) }}</bdi></span>
                    </label>
                @endforeach
            </div>

            <label for="brand_notes">{{ __('Target KPIs, Monthly Budget & Brand Guidelines') }}</label>
            <textarea id="brand_notes" name="brand_notes" rows="4" placeholder="{{ __('Share your target CPA/ROAS, key markets (KSA, UAE, Jordan), or brand tone notes...') }}">{{ old('brand_notes', $link->brand_notes) }}</textarea>

            @if (\App\Http\Controllers\ClientOnboardingController::intakeLive())
                {{-- DB-05 client intake: the answers reach the agency as drafts it confirms. --}}
                @foreach (\App\Http\Controllers\ClientOnboardingController::intakeLabels() as $key => $label)
                    <label for="intake_{{ $key }}">{{ $label }}</label>
                    <textarea id="intake_{{ $key }}" name="intake[{{ $key }}]" rows="2" maxlength="1500">{{ old('intake.'.$key, $link->intake[$key] ?? '') }}</textarea>
                @endforeach
                <p style="color:#94A3B8;font-size:13px">{{ config('services.client_intake.legal_line') }}</p>
            @endif

            <button type="submit" class="btn">{{ __('Confirm Access & Complete Onboarding') }}</button>
        </form>
    </div>
</div>
</body>
</html>
