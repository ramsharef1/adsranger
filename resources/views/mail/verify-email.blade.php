<!doctype html>
<html lang="{{ $ar ? 'ar' : 'en' }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<body style="margin:0;background:#F5F7FB;font-family:'IBM Plex Sans Arabic',Tahoma,Arial,sans-serif;color:#0B1020">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;padding:28px">
<tr><td style="font-size:20px;font-weight:bold;padding-bottom:6px;direction:ltr;text-align:{{ $ar ? 'right' : 'left' }}">Ads<span style="color:#0F766E">Ranger</span></td></tr>
@if ($ar)
<tr><td dir="rtl" lang="ar" style="font-size:16px;line-height:1.8;text-align:right">
<p>مرحبًا {{ $name }}،</p>
<p>أنشأت حسابًا على أدز رينجر. أكّد بريدك الإلكتروني لبدء الفترة التجريبية وإعداد مساحة عملك.</p>
<p style="padding:12px 0"><a href="{{ $url }}" style="background:#0F766E;color:#ffffff;text-decoration:none;font-weight:bold;padding:14px 24px;border-radius:8px;display:inline-block;min-height:16px">أكّد بريدي الإلكتروني</a></p>
<p style="font-size:13px;color:#4A5473">الرابط صالح لمدة 60 دقيقة.</p>
<p style="font-size:13px;color:#4A5473">إن لم تطلب ذلك، تجاهل هذه الرسالة؛ لن يتغير شيء.</p>
</td></tr>
@else
<tr><td style="font-size:16px;line-height:1.6">
<p>Hello {{ $name }},</p>
<p>You created an AdsRanger account. Confirm your email to start your trial and set up your workspace.</p>
<p style="padding:12px 0"><a href="{{ $url }}" style="background:#0F766E;color:#ffffff;text-decoration:none;font-weight:bold;padding:14px 24px;border-radius:8px;display:inline-block;min-height:16px">Confirm my email</a></p>
<p style="font-size:13px;color:#4A5473">The link is valid for 60 minutes.</p>
<p style="font-size:13px;color:#4A5473">If you didn't ask for this, ignore this email; nothing will change.</p>
</td></tr>
@endif
<tr><td dir="ltr" style="font-size:12px;color:#4A5473;padding-top:14px;border-top:1px solid #E5E8F0;direction:ltr;word-break:break-all">{{ $url }}</td></tr>
<tr><td style="font-size:12px;color:#4A5473;padding-top:10px">{{ $ar ? 'المساعدة:' : 'Help:' }} <bdi dir="ltr">{{ config('adsranger.signup.support_email') }}</bdi></td></tr>
</table>
</td></tr>
</table>
</body>
</html>
