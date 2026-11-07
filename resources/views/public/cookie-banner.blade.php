@php
    $isAr = ($lang ?? 'ar') === 'ar';
    $privUrl = ($isAr ? '' : '/en').'/privacy';
@endphp
<div id="ar-cookie-banner" role="region" aria-label="{{ $isAr ? 'إشعار ملفات تعريف الارتباط' : 'Cookie notice' }}" style="display:none;position:fixed;bottom:16px;inset-inline:16px;max-width:720px;margin:0 auto;background:#121933;border:1px solid #232c4d;border-radius:14px;padding:14px 18px;box-shadow:0 14px 34px rgba(0,0,0,.45);z-index:9999;font-size:13px;color:#eef1fb;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
    <div style="flex:1;min-width:220px;color:#d6dbeb">
        @if ($isAr)
            نستخدم ملفات تعريف الارتباط الأساسية فقط لتشغيل الجلسات وحفظ اللغة والأمان (بدون أي أدوات تتبّع إعلانية). راجع <a href="{{ $privUrl }}" style="color:#5EEAD4">سياسة الخصوصية</a>.
        @else
            We only use essential cookies for sign-in sessions, language preference and security (no third-party ad trackers). See our <a href="{{ $privUrl }}" style="color:#5EEAD4">Privacy Policy</a>.
        @endif
    </div>
    <div style="display:flex;gap:8px;align-items:center">
        <button type="button" id="ar-cookie-accept" style="min-height:44px;padding:7px 14px;border-radius:8px;border:0;background:#5EEAD4;color:#0b1020;font-weight:600;cursor:pointer;font-family:inherit">
            {{ $isAr ? 'موافق' : 'Got it' }}
        </button>
    </div>
</div>
<script>
(function(){
    try {
        var k = 'adsranger_cookie_consent';
        var b = document.getElementById('ar-cookie-banner');
        var btn = document.getElementById('ar-cookie-accept');
        if (b && !localStorage.getItem(k)) {
            b.style.display = 'flex';
        }
        if (btn) {
            btn.addEventListener('click', function(){
                try { localStorage.setItem(k, 'accepted'); } catch(e){}
                b.style.display = 'none';
            });
        }
    } catch (e) {}
})();
</script>
