@php
    $t = fn ($pair) => $ar ? $pair['ar'] : $pair['en'];
    $ch = \App\Support\LeadDesk::channels();
    $oc = \App\Support\LeadDesk::outcomes();
@endphp
<!doctype html>
<html lang="{{ $ar ? 'ar' : 'en' }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $client->name }} · {{ $ar ? 'سجل العملاء المحتملين' : 'Lead log' }}</title>
<style>
:root{--bg:#F5F8F7;--card:#fff;--line:#DDE5E4;--text:#0F1F1E;--muted:#556664;--acc:#0F766E}
@media (prefers-color-scheme:dark){:root{--bg:#0B1020;--card:#111827;--line:#1F2937;--text:#E5E7EB;--muted:#9CA3AF;--acc:#2DD4BF}}
body{margin:0;background:var(--bg);color:var(--text);font-family:'IBM Plex Sans Arabic','Segoe UI',Tahoma,sans-serif;font-size:15px;line-height:1.6}
main{max-width:640px;margin:0 auto;padding:16px;display:grid;gap:14px}
.card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:14px}
button,select,input{font:inherit;min-height:44px;border-radius:10px;border:1px solid var(--line);background:var(--card);color:var(--text);padding:0 12px}
.pri{background:var(--acc);color:#fff;border:0;font-weight:600}
.row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.muted{color:var(--muted);font-size:13px}
.lead{border-top:1px solid var(--line);padding:10px 0;display:grid;gap:6px}
</style>
</head>
<body>
<main>
    <header><strong style="font-size:18px">{{ $client->name }}</strong><div class="muted">{{ $ar ? 'سجّل كل عميل محتمل أو محادثة WhatsApp فور وصولها، ثم اضغط «تم التواصل».' : 'Log each lead or WhatsApp chat as it comes in, then tap "Contacted".' }}</div></header>

    <form method="POST" action="/l/{{ $token }}" class="card row" data-new-lead>
        @csrf
        <label class="muted" for="ch">{{ $ar ? 'المصدر' : 'Source' }}</label>
        <select id="ch" name="channel">@foreach ($ch as $k => $v)<option value="{{ $k }}">{{ $t($v) }}</option>@endforeach</select>
        <input name="label" maxlength="60" placeholder="{{ $ar ? 'اسم أو ملاحظة قصيرة (بلا رقم هاتف)' : 'Name or short note (no phone number)' }}" style="flex:1;min-width:160px" dir="auto">
        <button class="pri" type="submit">{{ $ar ? 'أضف' : 'Add' }}</button>
    </form>

    <section class="card">
        <strong>{{ $ar ? 'آخر يومين' : 'Last 2 days' }}</strong>
        @forelse ($leads as $l)
            <div class="lead">
                <div class="row" style="justify-content:space-between">
                    <span>{{ $t($ch[$l->channel] ?? $ch['other']) }}@if ($l->label) · <bdi>{{ $l->label }}</bdi>@endif</span>
                    <span class="muted"><bdi dir="ltr">{{ $l->received_at->setTimezone($tz)->format('d/m H:i') }}</bdi>
                        @if ($l->contacted_at) · {{ $ar ? 'تم التواصل بعد' : 'answered after' }} {{ (int) $l->received_at->diffInMinutes($l->contacted_at) }} {{ $ar ? 'دقيقة' : 'min' }}@endif
                        @if ($l->outcome) · <strong>{{ $t($oc[$l->outcome]) }}</strong>@endif</span>
                </div>
                @if (! $l->outcome)
                    <div class="row">
                        @unless ($l->contacted_at)
                            <form method="POST" action="/l/{{ $token }}/{{ $l->id }}">@csrf<input type="hidden" name="mark" value="contacted"><button class="pri" type="submit">{{ $ar ? 'تم التواصل' : 'Contacted' }}</button></form>
                        @endunless
                        <form method="POST" action="/l/{{ $token }}/{{ $l->id }}">@csrf<input type="hidden" name="mark" value="qualified"><button type="submit">{{ $t($oc['qualified']) }}</button></form>
                        <form method="POST" action="/l/{{ $token }}/{{ $l->id }}" class="row">@csrf<input type="hidden" name="mark" value="sale"><input name="value" type="number" min="0" step="0.01" placeholder="{{ $ar ? 'القيمة (اختياري)' : 'Value (optional)' }}" style="width:130px" dir="ltr"><button type="submit">{{ $t($oc['sale']) }}</button></form>
                        <form method="POST" action="/l/{{ $token }}/{{ $l->id }}">@csrf<input type="hidden" name="mark" value="not_relevant"><button type="submit">{{ $t($oc['not_relevant']) }}</button></form>
                    </div>
                @endif
            </div>
        @empty
            <p class="muted">{{ $ar ? 'لا شيء بعد.' : 'Nothing yet.' }}</p>
        @endforelse
    </section>
    <p class="muted">{{ $ar ? 'هذا الرابط خاص بفريقكم. لا تشاركه.' : 'This link is for your team only. Do not share it.' }}</p>
</main>
</body>
</html>
