@php
    $ar = app()->getLocale() === 'ar';
@endphp
<!DOCTYPE html>
<html lang="{{ $ar ? 'ar' : 'en' }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $ar ? 'إعداد مساحة العمل' : 'Workspace Setup' }} · AdsRanger</title>
    <style>
        body{margin:0;font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#f8fafc;display:grid;place-items:center;min-height:100vh;padding:20px}
        .box{width:100%;max-width:460px;background:#1e293b;border:1px solid rgba(94,234,212,.25);border-radius:16px;padding:28px}
        h1{font-size:22px;margin:0 0 8px}
        p.sub{color:#cbd5e1;font-size:14px;line-height:1.6;margin:0 0 20px}
        label{display:block;font-size:13px;font-weight:600;margin:14px 0 6px;color:#e2e8f0}
        input{width:100%;box-sizing:border-box;min-height:44px;padding:10px 12px;border-radius:10px;border:1px solid rgba(148,163,184,.35);background:#0f172a;color:#f8fafc;font-size:15px}
        .btn{margin-top:20px;width:100%;min-height:46px;border:0;border-radius:10px;background:#0f766e;color:#ffffff;font-weight:700;font-size:15px;cursor:pointer}
        .btn:hover{background:#14b8a6;color:#0f172a}
        .err{color:#fca5a5;font-size:13px;margin-top:6px}
    </style>
</head>
<body>
<main class="box">
    @if ($state !== 'ready')
        <h1>{{ $ar ? 'انتهت صلاحية رابط الدخول' : 'Setup link expired or already used' }}</h1>
        <p class="sub">{!! $ar ? 'اطلب من مسؤول <bdi dir="ltr">AdsRanger</bdi> إصدار رابط جديد صالح لمدة 7 أيام.' : 'Ask the AdsRanger administrator to regenerate a fresh 7-day setup link.' !!}</p>
    @else
        <h1>{!! $ar ? 'مرحبًا بك في <bdi dir="ltr">AdsRanger</bdi>' : 'Welcome to AdsRanger' !!}</h1>
        <p class="sub">
            {{ $ar ? 'مساحة العمل المجهّزة لك:' : 'Your provisioned workspace:' }}
            <strong>{{ $agency->name }}</strong> (<bdi dir="ltr">{{ $user->email }}</bdi>)
        </p>
        <form method="POST" action="{{ url('/magic-login/'.$token) }}">
            @csrf
            <label for="ms-name">{{ $ar ? 'اسمك الكامل' : 'Your full name' }}</label>
            <input id="ms-name" name="name" type="text" required maxlength="120" value="{{ old('name', $user->name) }}">
            @error('name')<div class="err">{{ $message }}</div>@enderror

            <label for="ms-pass">{{ $ar ? 'تعيين كلمة مرور (اختياري، 10 أحرف على الأقل)' : 'Set a password (optional, min 10 chars)' }}</label>
            <input id="ms-pass" name="password" type="password" dir="ltr" autocomplete="new-password">
            @error('password')<div class="err">{{ $message }}</div>@enderror

            <label for="ms-pass2">{{ $ar ? 'تأكيد كلمة المرور' : 'Confirm password' }}</label>
            <input id="ms-pass2" name="password_confirmation" type="password" dir="ltr" autocomplete="new-password">

            <button type="submit" class="btn">{{ $ar ? 'الدخول إلى مساحة العمل' : 'Enter workspace' }}</button>
        </form>
    @endif
</main>
</body>
</html>
