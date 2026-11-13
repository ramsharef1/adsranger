<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $lang === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('How did you hear about us?') }} — {{ $client->name }}</title>
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, sans-serif; background: #0f172a; color: #f8fafc; display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 20px; }
        .card { width: 100%; max-width: 480px; background: #1e293b; border: 1px solid rgba(94, 234, 212, 0.25); border-radius: 16px; padding: 24px; box-sizing: border-box; }
        h1 { margin: 0 0 8px; font-size: 20px; color: #5eead4; }
        p.sub { margin: 0 0 18px; font-size: 14px; color: #cbd5e1; line-height: 1.6; }
        .option-label { display: flex; align-items: center; gap: 10px; min-height: 44px; padding: 10px 14px; margin-bottom: 10px; border-radius: 10px; border: 1px solid rgba(148, 163, 184, 0.3); background: rgba(15, 23, 42, 0.65); cursor: pointer; font-size: 14px; }
        .option-label:hover { border-color: #14b8a6; }
        .btn { width: 100%; min-height: 44px; border: 0; border-radius: 10px; background: #0f766e; color: #ffffff; font-weight: 600; font-size: 15px; cursor: pointer; margin-top: 8px; }
        .btn:hover { background: #14b8a6; color: #0f172a; }
        .done { padding: 14px; border-radius: 10px; background: rgba(15, 118, 110, 0.28); border: 1px solid #14b8a6; color: #5eead4; font-size: 14px; }
    </style>
</head>
<body>
    <main class="card">
        <h1>{{ __('How did you hear about us?') }}</h1>
        <p class="sub">{{ __('Your answer helps us know where to advertise.') }}</p>

        @if (session('survey_submitted'))
            <div class="done">{{ __('Thank you! Your response has been recorded.') }}</div>
        @else
            <form method="POST" action="{{ route('public.survey.submit', ['token' => $survey->survey_token]) }}">
                @csrf
                @php
                    $labels = [
                        'tiktok' => $lang === 'ar' ? 'منصة <bdi>TikTok</bdi>' : 'TikTok',
                        'meta_instagram' => $lang === 'ar' ? 'إعلانات <bdi>Instagram / Meta</bdi>' : 'Instagram / Meta',
                        'snapchat' => $lang === 'ar' ? 'منصة <bdi>Snapchat</bdi>' : 'Snapchat',
                        'google_search' => $lang === 'ar' ? 'بحث <bdi>Google</bdi>' : 'Google Search',
                        'whatsapp' => $lang === 'ar' ? 'محادثة <bdi>WhatsApp</bdi>' : 'WhatsApp',
                        'friend_recommendation' => __('Friend or family recommendation'),
                        'other' => __('Something else'),
                    ];
                @endphp
                @foreach ($options as $opt)
                    <label class="option-label">
                        <input type="radio" name="channel_choice" value="{{ $opt }}" required style="width:20px;height:20px;accent-color:#14b8a6">
                        <span>{!! $labels[$opt] ?? e($opt) !!}</span>
                    </label>
                @endforeach
                <button type="submit" class="btn">{{ __('Submit answer') }}</button>
                {{-- UX-257 (Product's proposal; Business may replace the wording). No AdsRanger branding: the client collects it. --}}
                <p class="sub" style="margin:14px 0 0;font-size:12.5px">{!! __(':client collects this answer to learn how you found them. We don\'t ask for your name or number.', ['client' => '<bdi>'.e($client->name).'</bdi>']) !!}</p>
            </form>
        @endif
    </main>
</body>
</html>
