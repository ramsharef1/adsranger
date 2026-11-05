@php
    use App\Support\ResultResolver;
    $lang = $ar ? 'ar' : 'en';
    $align = $ar ? 'right' : 'left';
    $end = $ar ? 'left' : 'right';
    $bdi = fn (?string $s) => '<bdi dir="ltr">'.e((string) $s).'</bdi>';
    $name = $bdi($client->name);
    $agencyName = $bdi($agency->name);
    $pct = function ($now, $before) {
        if (! $before) {
            return null;
        }
        $p = ($now - $before) / $before * 100;

        return ($p >= 0 ? '+' : '−').number_format(abs($p), 0).'%';
    };
    $rows = [[__('report.results'), number_format($t['main_results']).' '.ResultResolver::label($t['main_type'], $lang), $pct($t['main_results'], $prev['main_results'])]];
    if (! $hideSpend) {
        $rows[] = [__('report.cpr'), ResultResolver::money($t['cpr'], $client->currency), $pct($t['cpr'], $prev['cpr'])];
        $rows[] = [__('report.spend'), ResultResolver::money($t['spend'], $client->currency), $pct($t['spend'], $prev['spend'])];
    }
    $rows[] = [__('report.impressions'), number_format($t['impressions']), $pct($t['impressions'], $prev['impressions'])];
    $rows[] = [__('report.clicks'), number_format($t['link_clicks']), $pct($t['link_clicks'], $prev['link_clicks'])];
@endphp
<!doctype html>
<html lang="{{ $lang }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<body style="margin:0;background:#F5F7FB;font-family:Tahoma,Arial,sans-serif;color:#0B1020">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" dir="{{ $ar ? 'rtl' : 'ltr' }}" style="max-width:560px;background:#ffffff;border-radius:12px;padding:24px">
<tr><td style="font-size:20px;font-weight:bold;padding-bottom:4px;text-align:{{ $align }}"><bdi dir="ltr">Ads<span style="color:#0F766E">Ranger</span></bdi> · {!! $agencyName !!}</td></tr>
<tr><td style="font-size:16px;line-height:1.8;text-align:{{ $align }}">
    <p style="margin:12px 0 4px">{{ __('report.mail_hello') }}</p>
    <p style="margin:0 0 12px">
        @if ($kind === 'monthly')
            {!! __('report.mail_intro_monthly', ['client' => $name, 'month' => e($label), 'prev' => e($prevLabel)]) !!}
        @else
            {!! __('report.mail_intro_weekly', ['client' => $name, 'range' => $bdi($label)]) !!}
        @endif
    </p>
</td></tr>
<tr><td>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px">
        @foreach ($rows as [$k, $v, $d])
            <tr style="border-top:1px solid #E5E8F0">
                <td style="padding:8px 4px;color:#4A5473;text-align:{{ $align }}">{{ $k }}</td>
                <td style="padding:8px 4px;font-weight:bold;text-align:{{ $end }}"><bdi dir="ltr">{{ $v }}</bdi></td>
                <td style="padding:8px 4px;color:#4A5473;font-size:12px;text-align:{{ $end }};white-space:nowrap"><bdi dir="ltr">{{ $d ?? '–' }}</bdi></td>
            </tr>
        @endforeach
    </table>
    <p style="font-size:12px;color:#4A5473;margin:6px 0 0;text-align:{{ $align }}">{{ __('report.vs_prev') }}</p>
</td></tr>
@if ($hasPdf)
<tr><td style="font-size:14px;padding-top:14px;text-align:{{ $align }}">{{ __('report.mail_pdf_attached') }}</td></tr>
@endif
@if ($linkUrl)
<tr><td style="padding-top:16px;text-align:{{ $align }}">
    <a href="{{ $linkUrl }}{{ str_contains($linkUrl, '?') ? '&' : '?' }}lang={{ $lang }}" style="background:#0F766E;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 22px;border-radius:8px;display:inline-block">{{ __('report.mail_open_link') }}</a>
    <p style="font-size:13px;color:#4A5473;margin:8px 0 0">{{ __('report.mail_link_note') }}</p>
</td></tr>
@endif
<tr><td style="font-size:12px;color:#4A5473;padding-top:18px;margin-top:18px;border-top:1px solid #E5E8F0;line-height:1.7;text-align:{{ $align }}">
    {!! __('report.mail_why', ['agency' => $agencyName, 'client' => $name]) !!}
    {{ __('report.pdf_note') }}<br>
    <a href="{{ $unsubscribeUrl }}" style="color:#0F766E">{{ __('report.mail_unsubscribe') }}</a>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
