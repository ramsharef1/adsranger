@php
    $ar = $agency->language === 'ar';
    $sev = ['high' => '#dc2626', 'medium' => '#d97706', 'low' => '#64748b'];
    $base = url('/app/'.$agency->slug);
@endphp
<!doctype html>
<html lang="{{ $ar ? 'ar' : 'en' }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<body style="margin:0;background:#f1f5f9;font-family:Segoe UI,Tahoma,Arial,sans-serif;color:#0f172a">
<div style="max-width:640px;margin:0 auto;padding:24px">
    <div style="font-weight:700;font-size:18px;color:#0d9488;margin-bottom:12px">AdsRanger · {{ $agency->name }}</div>
    @if ($digest)
        <div style="background:#fff;border-radius:12px;padding:18px;margin-bottom:16px">
            <div style="font-weight:600;margin-bottom:10px">{{ $ar ? 'نتائج أمس' : 'Yesterday' }} · {{ $digest['day'] }}</div>
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <tr style="color:#64748b;text-align:{{ $ar ? 'right' : 'left' }}">
                    <th style="padding:6px 4px">{{ $ar ? 'العميل' : 'Client' }}</th><th>{{ $ar ? 'الإنفاق' : 'Spend' }}</th><th>{{ $ar ? 'النتائج' : 'Results' }}</th><th>{{ $ar ? 'تكلفة النتيجة' : 'Cost / result' }}</th>
                </tr>
                @forelse ($digest['rows'] as $r)
                    <tr style="border-top:1px solid #e2e8f0">
                        <td style="padding:6px 4px">{{ $r['client']->name }}</td>
                        <td>{{ $r['client']->hide_spend ? '–' : \App\Support\ResultResolver::money($r['t']['spend'], $r['client']->currency) }}</td>
                        <td>{{ number_format($r['t']['main_results']) }} {{ \App\Support\ResultResolver::label($r['t']['main_type'], $ar ? 'ar' : 'en') }}</td>
                        <td>{{ \App\Support\ResultResolver::money($r['t']['cpr']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="padding:6px 4px;color:#64748b">{{ $ar ? 'لا يوجد عملاء بعد.' : 'No clients yet.' }}</td></tr>
                @endforelse
            </table>
        </div>
    @endif
    <div style="background:#fff;border-radius:12px;padding:18px">
        <div style="font-weight:600;margin-bottom:10px">{{ $digest ? ($ar ? 'التنبيهات المفتوحة' : 'Open alerts') : ($ar ? 'تنبيهات جديدة عاجلة' : 'New high alerts') }}</div>
        @forelse ($alerts as $a)
            <div style="border-top:1px solid #e2e8f0;padding:8px 0">
                <span style="display:inline-block;width:8px;height:8px;border-radius:9px;background:{{ $sev[$a->severity] ?? '#64748b' }}"></span>
                <strong style="font-size:14px">{{ $a->title }}</strong>
                <div style="font-size:13px;color:#475569;margin-top:2px">{{ $a->detail }}</div>
            </div>
        @empty
            <div style="font-size:13px;color:#64748b">{{ $ar ? 'لا توجد تنبيهات مفتوحة.' : 'No open alerts.' }}</div>
        @endforelse
        <a href="{{ $base }}/alerts" style="display:inline-block;margin-top:12px;background:#0d9488;color:#fff;text-decoration:none;padding:9px 16px;border-radius:8px;font-size:14px">{{ $ar ? 'فتح صندوق التنبيهات' : 'Open the alerts inbox' }}</a>
    </div>
    <div style="font-size:12px;color:#94a3b8;margin-top:14px">{{ $ar ? 'يمكنك إيقاف هذه الرسائل من صفحة "إشعاراتي".' : 'Turn these emails off on the "My notifications" page.' }} {{ $base }}/notifications</div>
</div>
</body>
</html>
