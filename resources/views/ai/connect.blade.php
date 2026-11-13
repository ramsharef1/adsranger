@php
    use App\Models\AiConnection;
    use App\Models\Client;
    use App\Support\Modules;
    $ar = app()->getLocale() === 'ar';
    $aiName = AiConnection::guessProvider($client->name) ? \App\Models\AiBrainItem::PROVIDERS[AiConnection::guessProvider($client->name)] : ($client->name ?: 'AI');
    $agencies = $user->agencies()->orderBy('name')->get()->filter(fn ($a) => Modules::on('ai_workspace', $user, $a))->values();
    $ltr = fn ($v) => '<bdi dir="ltr">'.e((string) $v).'</bdi>';
@endphp
<!doctype html>
<html lang="{{ $ar ? 'ar' : 'en' }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('Connect :ai to AdsRanger', ['ai' => $aiName]) }}</title>
    <style>
        :root { --bg:#f8fafc; --fg:#0f172a; --muted:#475569; --card:#fff; --line:#e2e8f0; --accent:#0F766E; --err:#b91c1c; }
        @media (prefers-color-scheme: dark) { :root { --bg:#0b1020; --fg:#f1f5f9; --muted:#a3aec2; --card:#121933; --line:#24304f; --accent:#5EEAD4; --err:#f87171; } }
        * { box-sizing: border-box; }
        body { margin:0; background:var(--bg); color:var(--fg); font-family:'IBM Plex Sans Arabic', system-ui, sans-serif; line-height:1.7; }
        main { max-width:560px; margin:0 auto; padding:24px 16px 48px; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:18px; margin-top:14px; }
        h1 { font-size:20px; margin:0 0 6px; } h2 { font-size:15px; margin:0 0 8px; }
        .muted { color:var(--muted); font-size:14px; }
        label.row { display:flex; gap:10px; align-items:flex-start; min-height:44px; padding:8px 0; cursor:pointer; }
        input[type=checkbox], input[type=radio] { width:20px; height:20px; margin-top:4px; flex:none; accent-color:var(--accent); }
        .btns { display:flex; gap:10px; flex-wrap:wrap; margin-top:18px; }
        button { min-height:44px; padding:0 18px; border-radius:10px; font:inherit; font-weight:600; cursor:pointer; border:1px solid var(--line); background:transparent; color:var(--fg); }
        button.primary { background:var(--accent); border-color:var(--accent); color:#fff; }
        .err { color:var(--err); font-size:14px; margin:6px 0 0; }
        bdi { unicode-bidi:isolate; }
    </style>
</head>
<body>
<main>
    <h1>{!! __('Connect :ai to AdsRanger', ['ai' => $ltr($aiName)]) !!}</h1>
    <p class="muted">{!! __(':ai will read only the clients you tick, and can save notes and drafts to them. It can\'t change any ad account. You can disconnect it at any time.', ['ai' => $ltr($aiName)]) !!}</p>

    @if ($agencies->isEmpty())
        <div class="card"><p>{{ __('AI Workspace is not available for your workspaces yet.') }}</p></div>
        <form method="post" action="{{ route('passport.authorizations.deny') }}">
            @csrf @method('DELETE')
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <div class="btns"><button type="submit">{{ __('Back to my AI') }}</button></div>
        </form>
    @else
    <form method="post" action="{{ route('passport.authorizations.approve') }}">
        @csrf
        <input type="hidden" name="auth_token" value="{{ $authToken }}">

        <div class="card">
            <h2>{{ __('1. Workspace and clients') }}</h2>
            <p class="muted">{{ __('Nothing is ticked: choose the clients this AI may read.') }}</p>
            @foreach ($agencies as $i => $agency)
                <label class="row"><input type="radio" name="agency_id" value="{{ $agency->id }}" @checked(old('agency_id', $agencies->count() === 1 ? $agency->id : null) == $agency->id)> <strong><bdi>{{ $agency->name }}</bdi></strong></label>
                <div style="padding-inline-start:30px">
                    @forelse (Client::visibleTo($user, $agency)->orderBy('name')->get() as $c)
                        <label class="row"><input type="checkbox" name="clients[{{ $agency->id }}][]" value="{{ $c->id }}" @checked(in_array($c->id, (array) old("clients.{$agency->id}", [])))> <bdi>{{ $c->name }}</bdi></label>
                    @empty
                        <p class="muted">{{ __('No clients in this workspace yet.') }}</p>
                    @endforelse
                </div>
            @endforeach
            @error('agency_id')<p class="err">{{ $message }}</p>@enderror
        </div>

        <div class="card">
            <h2>{{ __('2. Your AI, your output') }}</h2>
            <label class="row"><input type="checkbox" name="ownership" value="1" @checked(old('ownership'))>
                <span>{!! __('This AI is your own account. What it writes is yours, and you are responsible for how you use it. :brand checks it but doesn\'t write it.', ['brand' => $ltr('AdsRanger')]) !!}</span></label>
            @error('ownership')<p class="err">{{ $message }}</p>@enderror
            <p class="muted">{!! __('Data your AI reads through :brand is processed by your AI provider under its own terms.', ['brand' => $ltr('AdsRanger')]) !!}</p>
        </div>
        @error('auth_token')<p class="err">{{ $message }}</p>@enderror

        <div class="btns"><button type="submit" class="primary">{{ __('Connect') }}</button></div>
    </form>
    <form method="post" action="{{ route('passport.authorizations.deny') }}">
        @csrf @method('DELETE')
        <input type="hidden" name="auth_token" value="{{ $authToken }}">
        <div class="btns"><button type="submit">{{ __('Cancel') }}</button></div>
    </form>
    @endif
</main>
</body>
</html>
