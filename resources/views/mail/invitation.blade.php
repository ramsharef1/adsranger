<!doctype html>
<html lang="{{ $ar ? 'ar' : 'en' }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<body style="margin:0;background:#F5F7FB;font-family:Tahoma,Arial,sans-serif;color:#0B1020">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;padding:28px">
<tr><td style="font-size:20px;font-weight:bold;padding-bottom:6px;direction:ltr;text-align:{{ $ar ? 'right' : 'left' }}">Ads<span style="color:#0F766E">Ranger</span></td></tr>
@if ($ar)
<tr><td style="font-size:16px;line-height:1.8;text-align:right">
<p>مرحبًا،</p>
<p>دعاك <strong>{{ $inviter?->name ?? 'أحد أعضاء الفريق' }}</strong> للانضمام إلى مساحة عمل <strong>{{ $agency->name }}</strong> على <bdi dir="ltr">AdsRanger</bdi> بدور <strong>{{ $roleLabel }}</strong>.</p>
<p style="padding:12px 0"><a href="{{ $url }}" style="background:#14B8A6;color:#0B1020;text-decoration:none;font-weight:bold;padding:12px 22px;border-radius:8px;display:inline-block">قبول الدعوة</a></p>
<p style="font-size:13px;color:#4A5473">تنتهي صلاحية الدعوة في {{ $expires->format('Y-m-d') }}. إذا لم تكن تتوقع هذه الرسالة فيمكنك تجاهلها.</p>
</td></tr>
@else
<tr><td style="font-size:16px;line-height:1.6">
<p>Hello,</p>
<p><strong>{{ $inviter?->name ?? 'A team member' }}</strong> invited you to join the <strong>{{ $agency->name }}</strong> workspace on AdsRanger as <strong>{{ $roleLabel }}</strong>.</p>
<p style="padding:12px 0"><a href="{{ $url }}" style="background:#14B8A6;color:#0B1020;text-decoration:none;font-weight:bold;padding:12px 22px;border-radius:8px;display:inline-block">Accept invitation</a></p>
<p style="font-size:13px;color:#4A5473">This invitation expires on {{ $expires->copy()->locale('en')->translatedFormat('j M Y') }}. If you were not expecting it, you can ignore this email.</p>
</td></tr>
@endif
<tr><td style="font-size:12px;color:#4A5473;padding-top:14px;border-top:1px solid #E5E8F0;direction:ltr">{{ $url }}</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
