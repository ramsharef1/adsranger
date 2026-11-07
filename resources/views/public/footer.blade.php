@php
$ar = ($lang ?? 'ar') === 'ar';
$base = $ar ? '' : '/en';
@endphp
<footer style="border-top:1px solid var(--line);padding:24px 0;color:var(--muted);font-size:13px;display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px">
    <span>© {{ date('Y') }} AdsRanger · {{ $ar ? 'عمّان' : 'Amman' }} · {{ $ar ? 'قيد التطوير · الإطلاق في 2027' : 'In development · launching 2027' }}</span>
    <span style="display:flex;gap:12px;flex-wrap:wrap">
        <a href="{{ $base }}/pricing" style="color:inherit;text-decoration:none">{{ $ar ? 'الأسعار' : 'Pricing' }}</a>
        <a href="{{ $base }}/integrations" style="color:inherit;text-decoration:none">{{ $ar ? 'المنصات الإعلانية' : 'Ad networks' }}</a>
        <a href="{{ $base }}/demo-report" style="color:inherit;text-decoration:none">{{ $ar ? 'تقرير تجريبي' : 'Demo Report' }}</a>
        <a href="{{ $base }}/mena-calendar" style="color:inherit;text-decoration:none">{{ $ar ? 'تقويم المواسم' : 'MENA Calendar' }}</a>
        <a href="{{ $base }}/security" style="color:inherit;text-decoration:none">{{ $ar ? 'الأمان والامتثال' : 'Security' }}</a>
        <a href="{{ $base }}/privacy" style="color:inherit;text-decoration:none">{{ $ar ? 'الخصوصية' : 'Privacy' }}</a>
        <a href="{{ $base }}/terms" style="color:inherit;text-decoration:none">{{ $ar ? 'الشروط' : 'Terms' }}</a>
        <a href="{{ $base }}/data-deletion" style="color:inherit;text-decoration:none">{{ $ar ? 'حذف البيانات' : 'Data deletion' }}</a>
    </span>
</footer>
