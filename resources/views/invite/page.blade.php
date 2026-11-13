@php
    $ar = ($invite?->agency?->language ?? 'ar') === 'ar';
    $t = fn ($a, $e) => $ar ? $a : $e;
@endphp
<!doctype html>
<html lang="{{ $ar ? 'ar' : 'en' }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $t('دعوة', 'Invitation') }} · AdsRanger</title>
<meta name="robots" content="noindex">
<link rel="icon" href="/brand/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#0b1020;--card:#121933;--ink:#eef1fb;--muted:#9aa3c0;--acc:#2DD4BF;--line:#232c4d}
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;font-family:{!! $ar ? '"IBM Plex Sans Arabic",' : '' !!}system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;background:var(--bg);color:var(--ink);line-height:1.7}
.card{width:100%;max-width:440px;background:var(--card);border:1px solid var(--line);border-radius:16px;padding:28px}
.logo{display:flex;align-items:center;gap:10px;font-weight:700;direction:ltr;margin-bottom:18px}.logo img{width:30px}.logo b{color:var(--acc)}
h1{font-size:21px;margin:0 0 8px}p{color:#d6dbeb;margin:0 0 14px}
label{display:block;font-size:14px;margin:12px 0 4px;color:var(--muted)}
input{width:100%;padding:10px 12px;border-radius:10px;border:1px solid var(--line);background:#0b1020;color:var(--ink);font-size:15px}
button,.btn{display:inline-block;width:100%;margin-top:18px;padding:11px;border:0;border-radius:10px;background:var(--acc);color:#0B1020;font-weight:700;font-size:15px;cursor:pointer;text-align:center;text-decoration:none}
.err{color:#fca5a5;font-size:13px;margin-top:4px}.muted{color:var(--muted);font-size:13px}
</style>
</head>
<body>
<div class="card">
    <div class="logo" role="img" aria-label="AdsRanger" translate="no"><img src="/brand/adsranger-mark-teal.svg" alt=""><span aria-hidden="true">Ads<b>Ranger</b></span></div>

    @if ($state === 'invalid')
        <h1>{{ $t('الدعوة غير صالحة', 'This invitation is not valid') }}</h1>
        <p>{{ $t('ربما انتهت صلاحيتها أو تم استخدامها. اطلب من فريقك إرسال دعوة جديدة.', 'It may have expired or already been used. Ask your team to send a new one.') }}</p>
    @else
        <h1>{{ $t('انضم إلى', 'Join') }} {{ $invite->agency->name }}</h1>
        <p>{{ $t('دُعيت بدور', 'You were invited as') }} <strong>{{ \App\Support\TeamRoles::label($invite->role) }}</strong>@if ($invite->email) · <span class="muted" dir="ltr">{{ $invite->email }}</span>@endif</p>
        @if ($invite->via_link && ! $invite->email)
            <p class="muted">{{ $t('هذا الرابط يعمل مرة واحدة خلال 24 ساعة.', 'This link works once, within 24 hours.') }}</p>
        @endif

        @if ($state === 'member')
            <p>{{ $t('أنت عضو في مساحة العمل هذه بالفعل.', 'You are already a member of this workspace.') }}</p>
            <a class="btn" href="/app/{{ $invite->agency->slug }}">{{ $t('افتح مساحة العمل', 'Open the workspace') }}</a>
        @elseif ($state === 'join')
            <form method="post" action="/invite/{{ $token }}">@csrf
                <button type="submit">{{ $t('قبول الدعوة', 'Accept invitation') }}</button>
            </form>
        @elseif ($state === 'mismatch')
            <p>{{ $t('أنت مسجّل الدخول بحساب مختلف:', 'You are signed in with a different account:') }} <strong dir="ltr">{{ $user->email }}</strong>.
               {{ $t('سجّل الخروج ثم افتح رابط الدعوة مرة أخرى.', 'Sign out, then open the invitation link again.') }}</p>
        @else
            @if ($invite->via_link && ! $invite->email)
                <p class="muted">{{ $t('لديك حساب؟', 'Have an account?') }} <a style="color:var(--acc)" href="/app/login">{{ $t('سجّل الدخول', 'Sign in') }}</a> {{ $t('ثم افتح الرابط مرة أخرى.', 'then open the link again.') }}</p>
            @endif
            <form method="post" action="/invite/{{ $token }}">@csrf
                @if ($invite->via_link && ! $invite->email)
                    <label for="email">{{ $t('بريدك الإلكتروني للعمل', 'Your work email') }}</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required maxlength="190" autocomplete="email" dir="ltr">
                    @error('email')<div class="err">{{ $message }}</div>@enderror
                @endif
                <label for="name">{{ $t('الاسم الكامل', 'Full name') }}</label>
                <input id="name" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name">
                @error('name')<div class="err">{{ $message }}</div>@enderror
                <label for="password">{{ $t('كلمة المرور (10 أحرف على الأقل)', 'Password (at least 10 characters)') }}</label>
                <input id="password" type="password" name="password" required minlength="10" autocomplete="new-password">
                @error('password')<div class="err">{{ $message }}</div>@enderror
                <label for="password_confirmation">{{ $t('تأكيد كلمة المرور', 'Confirm password') }}</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required minlength="10" autocomplete="new-password">
                <button type="submit">{{ $t('إنشاء الحساب والانضمام', 'Create account and join') }}</button>
            </form>
            <p class="muted" style="margin-top:14px">{{ $t('بالمتابعة فإنك توافق على', 'By continuing you agree to the') }} <a style="color:var(--acc)" href="{{ $ar ? '' : '/en' }}/terms">{{ $t('شروط الاستخدام', 'Terms') }}</a> {{ $t('و', 'and') }} <a style="color:var(--acc)" href="{{ $ar ? '' : '/en' }}/privacy">{{ $t('سياسة الخصوصية', 'Privacy Policy') }}</a>.</p>
        @endif
    @endif
</div>
</body>
</html>
