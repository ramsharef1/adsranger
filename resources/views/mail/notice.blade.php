{{-- Shared notice mail (EX-53 layout, as verify-email): one column ≤ 600 px, RTL + lang="ar" for Arabic, IBM Plex
     Sans Arabic → Tahoma → Arial, one #0F766E button ≥ 44 px, the plain URL as a fallback, no tracking pixel. --}}
<!doctype html>
<html lang="{{ $ar ? 'ar' : 'en' }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<body style="margin:0;background:#F5F7FB;font-family:'IBM Plex Sans Arabic',Tahoma,Arial,sans-serif;color:#0B1020">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;padding:28px">
<tr><td style="font-size:20px;font-weight:bold;padding-bottom:6px;direction:ltr;text-align:{{ $ar ? 'right' : 'left' }}">Ads<span style="color:#0F766E">Ranger</span></td></tr>
<tr><td @if ($ar) dir="rtl" lang="ar" @endif style="font-size:16px;line-height:{{ $ar ? '1.8' : '1.6' }};text-align:{{ $ar ? 'right' : 'left' }}">
@foreach ($lines as $line)
<p>{{ $line }}</p>
@endforeach
@if (! empty($button) && ! empty($url))
<p style="padding:12px 0"><a href="{{ $url }}" style="background:#0F766E;color:#ffffff;text-decoration:none;font-weight:bold;padding:14px 24px;border-radius:8px;display:inline-block;min-height:16px">{{ $button }}</a></p>
@endif
@foreach ($small ?? [] as $line)
<p style="font-size:13px;color:#4A5473">{{ $line }}</p>
@endforeach
</td></tr>
@if (! empty($url))
<tr><td dir="ltr" style="font-size:12px;color:#4A5473;padding-top:14px;border-top:1px solid #E5E8F0;direction:ltr;word-break:break-all">{{ $url }}</td></tr>
@endif
<tr><td @if ($ar) dir="rtl" lang="ar" @endif style="font-size:12px;color:#4A5473;padding-top:10px;text-align:{{ $ar ? 'right' : 'left' }}">{{ $help }}</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
