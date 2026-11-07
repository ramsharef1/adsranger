@php
$ar = ($lang ?? 'ar') === 'ar';
$base = $ar ? '' : '/en';
$t = $ar ? [
    'title' => 'الأمان وحماية البيانات والامتثال · AdsRanger',
    'switch_url' => '/en/security',
    'tag' => 'عزل بيانات كل وكالة · صلاحية القراءة فقط',
    'h1' => 'كيف يحمي AdsRanger حسابات عملائك الإعلانية وبيانات وكالتك.',
    'lead' => 'صُمم AdsRanger من اليوم الأول لوكالات الإعلانات التي تدير عشرات الحسابات الإعلانية الحساسة، مع ضمانات تقنية صارمة لعدم تعديل الحملات دون إذن وعزل كل وكالة بالكامل.',
    'pillars' => [
        ['ضمان القراءة فقط للتقارير (ReadOnlyGuard)', 'تستخدم موصلات التقارير صلاحيات القراءة فقط (Read-Only OAuth Scopes)، مع حارس برمجي يمنع أي طلب تعديل أو حذف على حملات عملائك أثناء المزامنة.'],
        ['عزل متعدد المستأجرين (Strict Tenant Isolation)', 'كل جدول بيانات في قاعدة البيانات مرتبط بمعرّف الوكالة (agency_id) مع فحص صلاحيات ظهور كل عميل (Client::visibleTo) لكل مستخدم داخل الوكالة.'],
        ['تشفير رموز OAuth والمصادقة الثنائية (2FA)', 'تُحفظ رموز الربط الإعلاني مشفرة في قاعدة البيانات، مع دعم المصادقة الثنائية (TOTP 2FA) ورموز الاسترداد لحماية الحسابات الإدارية.'],
        ['فحص الروابط الآمن وحماية SSRF', 'عند فحص حالة صفحات الهبوط الخاصة بعملائك، يمنع النظام الوصول إلى أي عناوين داخلية أو خاصة لحماية البنية التحتية.'],
        ['الخصوصية وحذف البيانات', 'يمكن للوكالة إلغاء ربط أي حساب إعلاني وتصدير بياناتها في أي وقت، وطلب حذفها عبر صفحة حذف البيانات.'],
        ['سجل تدقيق (Audit Log)', 'تُسجَّل العمليات الحساسة وتغييرات الصلاحيات وإعدادات الوكالة في سجل تدقيق يراجعه مالك مساحة العمل.'],
    ],
    'cta_deletion' => 'سياسة وإجراءات حذف البيانات',
    'cta_privacy' => 'سياسة الخصوصية',
] : [
    'title' => 'Security, Data Protection & OAuth Compliance · AdsRanger',
    'switch_url' => '/security',
    'tag' => 'Every agency kept apart · read-only access',
    'h1' => 'How AdsRanger protects your agency and client ad accounts.',
    'lead' => 'Built for agencies managing many client ad accounts, with architectural safeguards enforcing read-only reporting, tenant isolation, and encrypted credentials.',
    'pillars' => [
        ['Read-Only Reporting Guard (ReadOnlyGuard)', 'Reporting connectors enforce strict read-only execution so daily sync jobs can never mutate client budgets, bids, or campaign states.'],
        ['Strict Multi-Tenant Isolation (agency_id)', 'Every tenant record is scoped by agency_id at the query level, paired with role-based client visibility (Client::visibleTo) for team members.'],
        ['Encrypted OAuth Tokens & TOTP 2FA', 'Ad network OAuth refresh and access tokens are encrypted at rest, and accounts are protected with Time-based One-Time Password (2FA) and recovery codes.'],
        ['SSRF-Safe Landing Page Diagnostics', 'Automated broken-link and landing-page checks resolve and block private/internal IP ranges to prevent Server-Side Request Forgery.'],
        ['Revocation & data deletion', 'Agencies can disconnect any ad account and export their data at any time, and request deletion through our Data Deletion page.'],
        ['Audit log', 'Role changes, workspace settings, and sensitive administrative actions are recorded in structured audit logs.'],
    ],
    'cta_deletion' => 'View Data Deletion Policy',
    'cta_privacy' => 'View Privacy Policy',
];
@endphp
<!doctype html>
<html lang="{{ $ar ? 'ar' : 'en' }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<link rel="icon" href="/brand/favicon.svg" type="image/svg+xml">
<title>{{ $t['title'] }}</title>
<link rel="alternate" hreflang="ar" href="https://adsranger.com/security">
<link rel="alternate" hreflang="en" href="https://adsranger.com/en/security">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#0b1020;--card:#121933;--ink:#eef1fb;--muted:#9aa3c0;--acc:#2DD4BF;--line:#232c4d}
*{box-sizing:border-box}body{margin:0;font-family:{!! $ar ? '"IBM Plex Sans Arabic",' : '' !!}system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;background:var(--bg);color:var(--ink);line-height:{{ $ar ? '1.8' : '1.55' }}}
.w{max-width:1080px;margin:0 auto;padding:0 20px}
.btn{display:inline-block;padding:10px 18px;border-radius:10px;background:var(--acc);color:#0B1020;text-decoration:none;font-weight:600}
.btn.o{background:transparent;border:1px solid var(--line);color:var(--ink)}
.tag{display:inline-block;font-size:13px;color:var(--acc);border:1px solid var(--line);padding:4px 10px;border-radius:99px;margin-bottom:14px}
.g{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px;padding:24px 0 40px}
.c{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:20px}
.c h3{margin:0 0 8px;font-size:16px;color:var(--acc)}.c p{margin:0;color:var(--muted);font-size:14px}
</style>
</head>
<body>
<div class="w">
    @include('public.nav', ['lang' => $lang, 'switch_url' => $t['switch_url']])
    <section style="padding:36px 0 12px">
        <span class="tag">{{ $t['tag'] }}</span>
        <h1 style="font-size:clamp(28px,4.5vw,44px);margin:0 0 12px">{{ $t['h1'] }}</h1>
        <p style="color:var(--muted);font-size:17px;max-width:760px;margin:0">{{ $t['lead'] }}</p>
    </section>
    <section class="g">
        @foreach ($t['pillars'] as $p)
            <div class="c">
                <h3>{{ $p[0] }}</h3>
                <p>{{ $p[1] }}</p>
            </div>
        @endforeach
    </section>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;padding-bottom:48px">
        <a class="btn o" href="{{ $base }}/privacy">{{ $t['cta_privacy'] }}</a>
        <a class="btn o" href="{{ $base }}/data-deletion">{{ $t['cta_deletion'] }}</a>
    </div>
    @include('public.footer', ['lang' => $lang])
</div>
@include('public.cookie-banner', ['lang' => $lang])
</body>
</html>
